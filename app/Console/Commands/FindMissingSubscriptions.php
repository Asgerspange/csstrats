<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Subscription;
use Stripe\StripeClient;

class FindMissingSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:find-missing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find users with active Stripe subscriptions but missing entries in the subscriptions table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to check for missing subscription records...');

        // Initialize Stripe client with your secret key
        $stripe = new StripeClient(config('cashier.secret'));

        // Get all users with a stripe_id
        $users = User::whereNotNull('stripe_id')->get();
        $this->info('Found ' . $users->count() . ' users with Stripe IDs');

        // Array to store users with missing subscription records
        $missingSubscriptions = [];

        // Progress bar
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            // Get all subscriptions for this customer from Stripe
            try {
                $stripeSubscriptions = $stripe->subscriptions->all([
                    'customer' => $user->stripe_id,
                    'status' => 'active',
                ]);

                // Check if user has active subscriptions in Stripe
                if (count($stripeSubscriptions->data) > 0) {
                    // Check if user has entries in the subscriptions table
                    $hasSubscriptionRecord = Subscription::where('user_id', $user->id)->exists();

                    if (!$hasSubscriptionRecord) {
                        // Add to the list of users with missing subscription records
                        $missingSubscriptions[] = [
                            'id' => $user->id,
                            'email' => $user->email,
                            'stripe_id' => $user->stripe_id,
                            'stripe_subscription_count' => count($stripeSubscriptions->data),
                            'stripe_subscription_ids' => collect($stripeSubscriptions->data)->pluck('id')->toArray(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error processing user {$user->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $count = count($missingSubscriptions);
        $this->info("Found {$count} users with active Stripe subscriptions but missing entries in the subscriptions table:");

        if ($count > 0) {
            $this->table(
                ['User ID', 'Email', 'Stripe ID', 'Active Subscriptions', 'Subscription IDs'],
                array_map(function ($item) {
                    return [
                        $item['id'],
                        $item['email'],
                        $item['stripe_id'],
                        $item['stripe_subscription_count'],
                        implode(', ', $item['stripe_subscription_ids']),
                    ];
                }, $missingSubscriptions)
            );

            // Optional: Export to CSV
            if ($this->confirm('Do you want to export this list to a CSV file?')) {
                $filename = 'missing_subscriptions_' . date('Y-m-d_H-i') . '.csv';
                $handle = fopen($filename, 'w');
                
                // Add CSV headers
                fputcsv($handle, ['User ID', 'Email', 'Stripe ID', 'Active Subscriptions', 'Subscription IDs']);
                
                // Add data rows
                foreach ($missingSubscriptions as $item) {
                    fputcsv($handle, [
                        $item['id'],
                        $item['email'],
                        $item['stripe_id'],
                        $item['stripe_subscription_count'],
                        implode(', ', $item['stripe_subscription_ids']),
                    ]);
                }
                
                fclose($handle);
                $this->info("CSV file exported to {$filename}");
            }
        }

        return Command::SUCCESS;
    }
}