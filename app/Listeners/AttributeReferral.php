<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;

/**
 * Attribute a referral to a user who registers through the standard email
 * registration flow (which fires Registered). OAuth signups call the service
 * directly in their callbacks.
 */
class AttributeReferral
{
    public function __construct(private ReferralService $referrals)
    {
    }

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->referrals->attribute($event->user, request());
        }
    }
}
