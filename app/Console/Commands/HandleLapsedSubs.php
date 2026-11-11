<?php

namespace App\Console\Commands;

use App\Models\MapSelectionAudit;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\UserLimit;
use App\Services\MapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HandleLapsedSubs extends Command
{
    protected $signature = 'subs:handle-lapsed {--dry-run : Show what would happen without applying}';

    protected $description = 'Soft-delete content for past-due subscriptions older than 7 days';

    public function handle(MapService $mapService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Find Stripe subs that are past_due or unpaid for more than 7 days
        $cutoff = now()->subDays(7);
        $lapsedSubs = Subscription::where('stripe_status', 'past_due')
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($lapsedSubs->isEmpty()) {
            $this->info('No lapsed subscriptions found.');
            return 0;
        }

        $this->info("Found {$lapsedSubs->count()} lapsed subscriptions (past_due > 7 days).");

        $softDeleted = 0;
        $truncated = 0;

        foreach ($lapsedSubs as $sub) {
            $user = $sub->user;
            if (! $user) {
                continue;
            }

            $teams = Team::where('owned_by', $user->id)->whereNull('deleted_at')->get();

            foreach ($teams as $team) {
                if ($team->entitlement_mode === 'map_based') {
                    // Truncate selection to first 4 maps, excess goes view-only
                    $defaultMapIds = $mapService->defaultMapIds(4);
                    $currentSelected = $team->selectedMaps()->pluck('maps.id')->toArray();

                    if (count($currentSelected) > 4) {
                        $truncatedIds = array_slice($currentSelected, 0, 4);
                        $this->line("  Team {$team->id}: truncating selection from " . count($currentSelected) . " to 4 maps");
                        if (! $dryRun) {
                            $team->selectedMaps()->sync($truncatedIds);
                            $team->map_lock_expires_at = now()->addDays(30);
                            $team->save();

                            MapSelectionAudit::create([
                                'team_id' => $team->id,
                                'map_ids' => $truncatedIds,
                                'selected_by' => 0,
                                'reason' => 'downgrade',
                            ]);
                        }
                        $truncated++;
                    }
                } else {
                    // Legacy team: soft-delete content
                    $stratbookIds = $team->stratbooks()->whereNull('deleted_at')->pluck('id');
                    if ($stratbookIds->isNotEmpty()) {
                        $this->line("  Team {$team->id} (legacy): soft-deleting {$stratbookIds->count()} stratbooks");
                        if (! $dryRun) {
                            StratbookTactic::whereIn('stratbook_id', $stratbookIds)
                                ->whereNull('deleted_at')
                                ->update(['deleted_at' => now()]);
                            Stratbook::whereIn('id', $stratbookIds)
                                ->whereNull('deleted_at')
                                ->update(['deleted_at' => now()]);
                        }
                        $softDeleted++;
                    }
                }
            }

            // Mark the sub as handled by setting ends_at
            if (! $dryRun) {
                $sub->ends_at = now();
                $sub->save();
                Log::info('Lapsed sub handled', [
                    'user_id' => $user->id,
                    'sub_id' => $sub->stripe_id,
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary: {$softDeleted} legacy teams soft-deleted, {$truncated} map_based teams truncated.");

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');
        }

        return 0;
    }
}
