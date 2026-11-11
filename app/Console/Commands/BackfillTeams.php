<?php

namespace App\Console\Commands;

use App\Core\Repositories\TeamRepository;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillTeams extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'teams:backfill';

    /**
     * The console command description.
     */
    protected $description = 'Create a "New team" for existing users who own none';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $repo = new TeamRepository;

        // Match ensureUserHasTeam's logic: users who OWN no team. A user who
        // is a member of someone else's team but owns none still gets one,
        // so EnsureUserHasTeam middleware and this command stay consistent.
        $userIds = Team::whereNotNull('owned_by')->pluck('owned_by')->unique();
        $users = User::whereNotIn('id', $userIds)->get();

        if ($users->isEmpty()) {
            $this->info('No users without a team.');

            return 0;
        }

        $created = 0;
        $errors = 0;
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            try {
                $repo->ensureUserHasTeam($user);
                $created++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->warn("  Failed for user {$user->id} ({$user->email}): " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfilled teams for {$created} user(s).");
        if ($errors > 0) {
            $this->warn("{$errors} user(s) failed (charset or other errors).");
        }

        return 0;
    }
}
