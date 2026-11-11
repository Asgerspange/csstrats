<?php

namespace App\Http\Controllers;

use App\Actions\{
    CreateCheckoutSession,
    HandleCheckoutSuccess
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\BillingPortal\Session as BillingPortal;

class PlanController extends Controller
{
    public $user;
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(config('cashier.secret'));
    }

    public function checkout(Request $request)
    {
        return CreateCheckoutSession::handle($request);
    }

    public function success(Request $request)
    {
        return HandleCheckoutSuccess::handle($request);
    }

    public function cancel(Request $request)
    {
        return redirect('/#pricing');
    }
    /**
     * Redirect the user to the Stripe Customer Portal.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirectToStripePortal()
    {
        $user = Auth::user();

        if (!$user->stripe_id) {
            return response()->json(['error' => 'No Stripe customer ID found.'], 404);
        }

        $session = BillingPortal::create([
            'customer' => $user->stripe_id,
            'return_url' =>url('/billing-portal-return'), // URL to return to after managing the subscription
        ]);

        // Return the Stripe Billing Portal URL as a JSON response
        return response()->json(['url' => $session->url]);
    }

    public function pastDue(Request $request)
    {
        $user = Auth::user();
        $due = $user->subscriptions()->first()->updated_at->addDays(28);
        return view('billing.past_due', compact('user', 'due'));
    }

    public function billingPortalReturn()
    {
        return redirect('/profile');
    }
}
