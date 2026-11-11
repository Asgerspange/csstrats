<?php

namespace App\Http\Controllers;

use App\Models\PaddleCustomer;
use App\Models\PaddleSubscription;
use App\Models\PaddleSubscriptionItem;
use App\Models\PaddleTransaction;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLimit;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaddleWebhookController extends \Illuminate\Routing\Controller
{
    public function __invoke(Request $request)
    {
        // Verify signature
        if (! $this->verifySignature($request)) {
            return response('Unauthorized', 403);
        }

        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        // Only handle csstrats events
        $customData = $data['custom_data']
            ?? $data['items'][0]['price']['custom_data']
            ?? [];

        if (($customData['site'] ?? null) !== 'csstrats') {
            return response('ok', 200);
        }

        match ($eventType) {
            'subscription.created',
            'subscription.activated' => $this->handleSubscriptionCreated($data, $customData),
            'subscription.updated' => $this->handleSubscriptionUpdated($data, $customData),
            'subscription.canceled' => $this->handleSubscriptionCanceled($data),
            'transaction.completed' => $this->handleTransactionCompleted($data),
            default => Log::info("Paddle webhook unhandled: {$eventType}"),
        };

        return response('ok', 200);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('cashier-paddle.webhook_secret') ?? env('PADDLE_WEBHOOK_SECRET');
        $signature = $request->header('Paddle-Signature');

        if (! $secret || ! $signature) {
            return false;
        }

        // Parse ts and h1 from header
        $parts = [];
        foreach (explode(';', $signature) as $part) {
            [$k, $v] = explode('=', $part, 2);
            $parts[$k] = $v;
        }

        $ts = $parts['ts'] ?? '';
        $h1 = $parts['h1'] ?? '';
        $signed = $ts.':'.$request->getContent();
        $expected = hash_hmac('sha256', $signed, $secret);

        return hash_equals($expected, $h1);
    }

    private function resolveUser(array $data): ?User
    {
        $customerId = $data['customer_id'] ?? null;
        if (! $customerId) {
            return null;
        }

        $customer = PaddleCustomer::where('paddle_id', $customerId)->first();
        if (! $customer) {
            return null;
        }

        return User::find($customer->billable_id);
    }

    private function handleSubscriptionCreated(array $data, array $customData): void
    {
        $user = $this->resolveUser($data);
        if (! $user) {
            return;
        }

        $sub = PaddleSubscription::updateOrCreate(
            ['paddle_id' => $data['id']],
            [
                'billable_type' => User::class,
                'billable_id' => $user->id,
                'type' => $data['custom_data']['subscription_type'] ?? 'default',
                'status' => $data['status'],
                'trial_ends_at' => $data['status'] === 'trialing'
                    ? now()->parse($data['next_billed_at'])
                    : null,
            ]
        );

        foreach ($data['items'] ?? [] as $item) {
            PaddleSubscriptionItem::updateOrCreate(
                ['subscription_id' => $sub->id, 'price_id' => $item['price']['id']],
                [
                    'product_id' => $item['price']['product_id'],
                    'status' => $item['status'],
                    'quantity' => $item['quantity'] ?? 1,
                ]
            );
        }

        $this->upsertLimits($user->id, $customData, $data['id'], $data['items'][0]['price']['id'] ?? null);

        // Expand or truncate the user's team map selections to match the
        // new plan's max_maps. Teams are created with 4 default maps; without
        // this, a Team-plan subscriber (6 maps) would stay stuck at 4.
        app(EntitlementService::class)->syncMapSelectionsForUser($user);

        // Migration: if this user still has an active legacy Stripe
        // subscription, cancel it at the end of the current Stripe period.
        // They have already paid for that period, so we don't revoke access
        // immediately; the Paddle sub now drives billing and the
        // user_limits row above drives feature gating. resque() guards a
        // missing or already-cancelled Stripe sub.
        $this->cancelLegacyStripeSubscription($user);

        // Restore any soft-deleted content from a previous cancellation
        $this->restoreContent($user);

        Log::info('Paddle: subscription created', ['user_id' => $user->id, 'sub_id' => $data['id']]);
    }

    /**
     * Cancel a user's active Stripe "default" subscription at the end of the
     * current period, if one exists. Called on Paddle subscription creation
     * so migrating users don't keep paying on both billing systems. Cashier's
     * cancel() sets ends_at to the current period end (non-punitive).
     */
    private function cancelLegacyStripeSubscription(User $user): void
    {
        rescue(function () use ($user) {
            $stripeSub = $user->subscription('default');
            if (! $stripeSub) {
                return;
            }
            // Only cancel subs that are actually active and not already
            // marked to end. A sub already pending cancellation is left alone.
            if ($stripeSub->stripe_status !== 'active') {
                return;
            }
            if ($stripeSub->ends_at) {
                return;
            }

            $stripeSub->cancel();
            Log::info('Paddle migration: cancelled legacy Stripe subscription', [
                'user_id' => $user->id,
                'stripe_subscription_id' => $stripeSub->id,
            ]);
        }, function () {
            // A Stripe API hiccup during migration should not fail the
            // webhook (which would cause Paddle to retry). The user's
            // Paddle sub and limits are already recorded.
        }, false);
    }

    private function handleSubscriptionUpdated(array $data, array $customData): void
    {
        $user = $this->resolveUser($data);
        if (! $user) {
            return;
        }

        PaddleSubscription::where('paddle_id', $data['id'])->update([
            'status' => $data['status'],
            'paused_at' => $data['paused_at'] ?? null,
            'ends_at' => $data['canceled_at'] ?? null,
        ]);

        $paused = ! empty($data['paused_at']);
        $this->upsertLimits($user->id, $paused ? [
            'max_members' => 0, 'max_teams' => 0, 'max_stratbooks' => 0,
        ] : $customData, $data['id'], $data['items'][0]['price']['id'] ?? null);

        // Sync map selections on plan changes, but not on pause/unpause.
        if (! $paused) {
            app(EntitlementService::class)->syncMapSelectionsForUser($user);
        }

        Log::info('Paddle: subscription updated', ['user_id' => $user->id]);
    }

    private function handleSubscriptionCanceled(array $data): void
    {
        $user = $this->resolveUser($data);

        PaddleSubscription::where('paddle_id', $data['id'])
            ->update(['status' => 'canceled']);

        if ($user) {
            UserLimit::where('user_id', $user->id)->delete();

            // Soft-delete content for legacy teams. For map_based teams,
            // revoke map access (all maps locked).
            $teams = Team::where('owned_by', $user->id)->whereNull('deleted_at')->get();
            foreach ($teams as $team) {
                if ($team->entitlement_mode === 'map_based') {
                    $team->selectedMaps()->detach();
                } else {
                    $stratbookIds = $team->stratbooks()->pluck('id');
                    StratbookTactic::whereIn('stratbook_id', $stratbookIds)->whereNull('deleted_at')->update(['deleted_at' => now()]);
                    Stratbook::whereIn('id', $stratbookIds)->whereNull('deleted_at')->update(['deleted_at' => now()]);
                }
            }

            Log::info('Paddle: subscription canceled', ['user_id' => $user->id]);
        }
    }

    private function handleTransactionCompleted(array $data): void
    {
        $user = $this->resolveUser($data);

        PaddleTransaction::updateOrCreate(
            ['paddle_id' => $data['id']],
            [
                'billable_type' => User::class,
                'billable_id' => $user?->id,
                'paddle_subscription_id' => $data['subscription_id'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'status' => $data['status'],
                'total' => $data['details']['totals']['total'] ?? 0,
                'tax' => $data['details']['totals']['tax'] ?? 0,
                'currency' => $data['currency_code'] ?? 'EUR',
                'billed_at' => $data['billed_at'] ?? now(),
            ]
        );

        // Credit the referrer (if any) for this paid order.
        app(\App\Services\ReferralService::class)->recordCommissionForTransaction($data, $user);

        Log::info('Paddle: transaction completed', ['user_id' => $user?->id]);
    }

    private function upsertLimits(int $userId, array $limits, ?string $subId, ?string $priceId): void
    {
        UserLimit::updateOrCreate(
            ['user_id' => $userId],
            array_merge([
                'max_members' => (int) ($limits['max_members'] ?? 8),
                'max_teams' => (int) ($limits['max_teams'] ?? 1),
                'max_stratbooks' => (int) ($limits['max_stratbooks'] ?? 8),
                'paddle_subscription_id' => $subId,
                'paddle_price_id' => $priceId,
            ])
        );
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
    }
}
