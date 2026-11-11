<?php

namespace App\Console\Commands;

use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeResync extends Command
{
    protected $signature = 'stripe:resync
                            {--dry-run : Show what would happen without making changes}
                            {--execute : Apply the changes}';

    protected $description = 'Soft-delete content for cancelled Stripe subscriptions';

    private bool $dryRun;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if (! $this->dryRun && ! $this->option('execute')) {
            $this->error('Use --dry-run to preview or --execute to apply.');

            return 1;
        }

        $stripe = new StripeClient(config('cashier.secret'));
        $users = User::whereNotNull('stripe_id')->get();
        $this->info('Found '.$users->count().' users with Stripe IDs');

        $report = [
            'cancelled_content_deleted' => 0,
            'restored' => 0,
            'errors' => 0,
        ];

        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            try {
                $stripeSubs = $stripe->subscriptions->all([
                    'customer' => $user->stripe_id,
                    'status' => 'all',
                    'limit' => 100,
                ]);

                foreach ($stripeSubs->data as $stripeSub) {
                    $localSub = Subscription::where('stripe_id', $stripeSub->id)->first();

                    if ($stripeSub->status === 'active') {
                        // Active sub — restore any previously soft-deleted content
                        $this->restoreIfDeleted($user, $report);
                    } elseif (in_array($stripeSub->status, ['canceled', 'cancelled', 'unpaid', 'incomplete_expired'])) {
                        $this->handleCancelledSub($user, $stripeSub, $localSub, $report);
                    }
                }
            } catch (\Exception $e) {
                $report['errors']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Resync Report ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Cancelled subs, content soft-deleted', $report['cancelled_content_deleted']],
                ['Re-activated subs, content restored', $report['restored']],
                ['Errors', $report['errors']],
            ]
        );

        if ($this->dryRun) {
            $this->warn('Dry run — no changes were made. Run with --execute to apply.');
        }

        return 0;
    }

    private function restoreIfDeleted(User $user, array &$report): void
    {
        $teams = Team::where('owned_by', $user->id)->get();
        $restored = false;

        foreach ($teams as $team) {
            $stratbookIds = $team->stratbooks()->withTrashed()->pluck('id');
            $deleted = StratbookTactic::withTrashed()
                ->whereIn('stratbook_id', $stratbookIds)
                ->whereNotNull('deleted_at')
                ->exists();

            if ($deleted) {
                if ($this->dryRun) {
                    $this->line("  Would restore content for team {$team->id} (user {$user->id})");
                } else {
                    StratbookTactic::withTrashed()->whereIn('stratbook_id', $stratbookIds)
                        ->whereNotNull('deleted_at')->update(['deleted_at' => null]);
                    Stratbook::withTrashed()->whereIn('id', $stratbookIds)
                        ->whereNotNull('deleted_at')->update(['deleted_at' => null]);
                }
                $restored = true;
            }
        }

        if ($restored) {
            $report['restored']++;
        }
    }

    private function handleCancelledSub(User $user, $stripeSub, ?Subscription $localSub, array &$report): void
    {
        $teams = Team::where('owned_by', $user->id)->whereNull('deleted_at')->get();

        foreach ($teams as $team) {
            $sbIds = $team->stratbooks()->withTrashed()->pluck('id');
            $hasSoftDeleted = Stratbook::withTrashed()
                ->whereIn('id', $sbIds)
                ->whereNotNull('deleted_at')
                ->exists();

            if ($hasSoftDeleted) {
                continue;
            }

            if ($this->dryRun) {
                $this->line("  Would soft-delete content for team {$team->id} (user {$user->id}, sub {$stripeSub->id})");
                $report['cancelled_content_deleted']++;
                continue;
            }

            $stratbookIds = $team->stratbooks()->pluck('id');
            StratbookTactic::whereIn('stratbook_id', $stratbookIds)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
            Stratbook::whereIn('id', $stratbookIds)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            $report['cancelled_content_deleted']++;
        }
    }
}
