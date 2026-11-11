<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle subscription updated.
     *
     * @param array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $subscription = $payload['data']['object'];
        $user = User::where('stripe_id', $subscription['customer'])->first();

        if ($user) {
            $localSubscription = $user->subscriptions()->where('stripe_id', $subscription['id'])->first();

            if ($localSubscription) {
                if ($subscription['plan']['id'] !== $localSubscription->stripe_price) {
                    $localSubscription->stripe_price = $subscription['plan']['id'];
                }

                $localSubscription->stripe_status = $subscription['status'];

                if (isset($subscription['cancel_at'])) {
                    $localSubscription->ends_at = Carbon::createFromTimestamp($subscription['cancel_at']);
                } else {
                    $localSubscription->ends_at = null;
                }

                $localSubscription->updated_at = now();

                $localSubscription->save();

                // Restore content on re-activation (status went back to active)
                if ($subscription['status'] === 'active') {
                    $this->restoreContent($user);
                }

                Log::info('Subscription updated via webhook', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription['id'],
                    'status' => $subscription['status']
                ]);
            }
        }

        return $this->successMethod();
    }
    
    /**
     * Handle subscription deleted.
     *
     * @param array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $subscription = $payload['data']['object'];
        $user = User::where('stripe_id', $subscription['customer'])->first();

        if ($user) {
            $localSubscription = $user->subscriptions()->where('stripe_id', $subscription['id'])->first();

            if ($localSubscription && is_null($localSubscription->ends_at)) {
                $localSubscription->ends_at = now();
                $localSubscription->updated_at = now();
                $localSubscription->save();

                Log::info('Subscription cancelled via webhook', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription['id']
                ]);
            }

            // Soft-delete the owner's team content
            $this->softDeleteContent($user);
        }

        return $this->successMethod();
    }

    /**
     * Soft-delete all stratbooks and tactics for teams owned by the user.
     */
    private function softDeleteContent(User $user): void
    {
        $teams = Team::where('owned_by', $user->id)->whereNull('deleted_at')->get();

        foreach ($teams as $team) {
            $stratbookIds = $team->stratbooks()->pluck('id');
            StratbookTactic::whereIn('stratbook_id', $stratbookIds)->whereNull('deleted_at')->update(['deleted_at' => now()]);
            Stratbook::whereIn('id', $stratbookIds)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        }

        Log::info('Soft-deleted content for cancelled subscription', [
            'user_id' => $user->id,
            'teams_affected' => $teams->count(),
        ]);
    }

    /**
     * Restore soft-deleted stratbooks and tactics for teams owned by the user.
     */
    private function restoreContent(User $user): void
    {
        $teams = Team::where('owned_by', $user->id)->get();

        foreach ($teams as $team) {
            $stratbookIds = $team->stratbooks()->withTrashed()->pluck('id');
            StratbookTactic::withTrashed()->whereIn('stratbook_id', $stratbookIds)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
            Stratbook::withTrashed()->whereIn('id', $stratbookIds)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
        }

        Log::info('Restored content for re-activated subscription', [
            'user_id' => $user->id,
            'teams_affected' => $teams->count(),
        ]);
    }

    /**
     * Handle payment action required.
     *
     * @param array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleInvoicePaymentActionRequired(array $payload)
    {
        $invoice = $payload['data']['object'];
        $user = User::where('stripe_id', $invoice['customer'])->first();
        
        if ($user) {
            // You could send a notification to the user here
            Log::info('Payment action required', [
                'user_id' => $user->id,
                'invoice_id' => $invoice['id']
            ]);
        }
        
        return $this->successMethod();
    }
}