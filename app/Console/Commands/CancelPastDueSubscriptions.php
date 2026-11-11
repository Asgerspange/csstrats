<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CancelPastDueSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cancel-past-due-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all users with past due subscriptions
        $users = User::whereHas('subscriptions', function ($query) {
            $query->where('stripe_status', 'past_due');
        })->get();

        foreach ($users as $user) {
            if ($user->subscriptions()->first()->updated_at->diffInDays(now()) > 28) {
                $user->subscription('default')->cancel();
            }
        }
    }
}
