<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\MapProvisioner;
use Illuminate\Console\Command;

class MapsProvision extends Command
{
    protected $signature = 'maps:provision {teamId? : Provision for a single team}';

    protected $description = 'Provision missing map stratbooks for map_based teams (idempotent repair)';

    public function handle(MapProvisioner $provisioner): int
    {
        $teamId = $this->argument('teamId');

        if ($teamId) {
            $team = Team::find($teamId);
            if (! $team) {
                $this->error("Team {$teamId} not found.");

                return 1;
            }
            if ($team->entitlement_mode !== 'map_based') {
                $this->warn("Team {$teamId} is not map_based (mode: {$team->entitlement_mode}). Skipping.");

                return 0;
            }

            $before = $team->stratbooks()->whereNotNull('map_id')->count();
            $provisioner->provision($team);
            $after = $team->stratbooks()->whereNotNull('map_id')->count();
            $created = $after - $before;

            $this->info("Team {$teamId}: {$created} stratbook(s) provisioned ({$after} total).");

            return 0;
        }

        $teams = Team::where('entitlement_mode', 'map_based')
            ->whereNull('deleted_at')
            ->get();

        if ($teams->isEmpty()) {
            $this->info('No map_based teams found.');

            return 0;
        }

        $totalCreated = 0;
        $bar = $this->output->createProgressBar($teams->count());

        foreach ($teams as $team) {
            $before = $team->stratbooks()->whereNotNull('map_id')->count();
            $provisioner->provision($team);
            $after = $team->stratbooks()->whereNotNull('map_id')->count();
            $totalCreated += ($after - $before);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Provisioned {$totalCreated} stratbook(s) across {$teams->count()} team(s).");

        return 0;
    }
}
