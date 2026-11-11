<?php

use App\Http\Controllers\FaceitAuthController;
use App\Http\Controllers\GameboxController;
use App\Http\Middleware\GeoRedirectMiddleware;
use App\Http\Middleware\TrackUserActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/auth/faceit/redirect', [FaceitAuthController::class, 'redirect'])->name('faceit.redirect');
    Route::get('/auth/faceit/callback', [FaceitAuthController::class, 'callback'])->name('faceit.callback');
});
use Illuminate\Support\Facades\Log;

Route::post('/test-upload', function () {
    Log::info('Connection reached Laravel routing layer');

    return response()->json(['status' => 'success']);
});
Route::get('stream-banner', function () {
    return view('stream-banner');
});
Auth::routes(['verify' => true, 'register' => false]);
Route::match(['get', 'post'], '/register', function () {
    return redirect('/login');
})->name('register')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if (sha1($user->getEmailForVerification()) === $hash) {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect('/profile')->with('verified', true);
    }

    abort(403, 'Invalid verification link');
})->middleware(['auth'])->name('verification.verify');

// use GuzzleHttp\Client as GuzzleClient;
Route::get('/auth/google/redirect', function (Request $request) {
    $certPath = storage_path('certs/cacert.pem');

    return Socialite::driver('google')
        // ->setHttpClient(new GuzzleClient(['verify' => $certPath]))
        ->redirect();
})->name('google.redirect')->withoutMiddleware(GeoRedirectMiddleware::class);

Route::get('/auth/google/callback', function (Request $request) {
    $certPath = storage_path('certs/cacert.pem');
    $user = Socialite::driver('google')
        // ->setHttpClient(new GuzzleClient(['verify' => $certPath]))
        ->user();

    $existingGoogleUser = \App\Models\User::where('google_id', $user->getId())->first();

    if ($existingGoogleUser) {
        Auth::login($existingGoogleUser, true);

        return redirect()->to('/profile');
    }

    $existingUser = \App\Models\User::where('email', $user->getEmail())->first();
    if ($existingUser) {
        $existingUser->google_id = $user->getId();
        $existingUser->save();
        Auth::login($existingUser, true);

        return redirect()->to('/profile');
    }

    return redirect('/login')->with('status', 'CsStrats is closing. New accounts are no longer created.');
})->name('google.callback')->withoutMiddleware(GeoRedirectMiddleware::class);

Route::get('/tos', function () {
    return view('tos');
})->name('tos');

// In routes/web.php, temporarily replace the webhook route:
Route::post('/paddle/webhook', \App\Http\Controllers\PaddleWebhookController::class)
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/invite/{token}', [\App\Http\Controllers\TeamInviteController::class, 'show'])
    ->name('invite.show')
    ->withoutMiddleware(['verified']);

Route::get('/policy', function () {
    return view('policy');
})->name('policy');

Route::get('/cookie-policy', function () {
    return view('cookie-policy');
})->name('cookie-policy');

// Short referral links: /r/{code} sets attribution then forwards to landing.
Route::get('/r/{code}', \App\Http\Controllers\ReferralLinkController::class)->name('referral.link');

// Public marketing pages capture ?utm_source=<code> attribution.
Route::middleware(\App\Http\Middleware\CaptureReferral::class)->group(function () {
    Route::get('/', [App\Http\Controllers\WelcomeController::class, 'index'])->name('home');
    Route::get('/hltv', [App\Http\Controllers\WelcomeController::class, 'hltv'])->name('hltv');
    Route::get('/demo', [App\Http\Controllers\WelcomeController::class, 'sampleDemo'])->name('demo.sample');
    Route::get('/tactics/weekly', [App\Http\Controllers\WelcomeController::class, 'weeklyTactic'])->name('tactics.weekly');
});

Route::get('/welcome-data', [App\Http\Controllers\WelcomeController::class, 'data'])->name('welcome.data');

// Isolated partner (referral) dashboard. Own guard, no product-app access.
Route::prefix('partners')->group(function () {
    Route::get('login', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'showLogin'])->name('partner.login');
    Route::post('login', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'login'])->name('partner.login.attempt');
    Route::post('logout', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'logout'])->name('partner.logout');

    Route::middleware('auth:referrer')->group(function () {
        Route::get('/', [\App\Http\Controllers\Partner\PartnerDashboardController::class, 'index'])->name('partner.dashboard');
        Route::get('data', [\App\Http\Controllers\Partner\PartnerDashboardController::class, 'data'])->name('partner.data');
    });
});
Route::get('/tactics/weekly-data', [App\Http\Controllers\WelcomeController::class, 'weeklyTacticData'])->name('tactics.weekly.data');

Route::get('custom-package/{id}', [App\Http\Controllers\CustomPackageController::class, 'showOffer'])->name('custom-package.offer');

Route::get('/logout', function () {
    Auth::logout();

    return redirect('/');
})->name('logout');

Route::post('/checkout', [App\Http\Controllers\PlanController::class, 'checkout'])
    ->name('checkout');

Route::get('/success', [App\Http\Controllers\PlanController::class, 'success'])
    ->name('success');

Route::get('/cancel', [App\Http\Controllers\PlanController::class, 'cancel'])
    ->name('cancel');
Route::get('/offer/{uuid}', [App\Http\Controllers\OfferController::class, 'show'])
    ->name('offer.show');
Route::get('/billing-portal-return', 'App\Http\Controllers\PlanController@billingPortalReturn')->name('billing.portal.return');

// Public, read-only round plan viewer. Token-gated; no auth required.
// Renders the app shell so the SPA router mounts PublicRoundPlan at /plan/:token.
Route::get('/plan/{token}', function (string $token) {
    return view('app', ['publicPlanToken' => $token]);
})->where('token', '[0-9a-f-]{36}')->name('public.plan');

Route::get('/gamebox', [GameboxController::class, 'show'])->name('gamebox.show');
Route::post('/gamebox', [GameboxController::class, 'register'])->name('gamebox.register');

Route::middleware([TrackUserActivity::class, 'verified'])->group(function () {
    Route::middleware('auth')->get('/stripe-portal', 'App\Http\Controllers\PlanController@redirectToStripePortal');
    Route::get('/past-due', [App\Http\Controllers\PlanController::class, 'pastDue'])
        ->name('past-due');
    require_once __DIR__.'/application/admin.php';

    Route::get('/billing/customer', function (Request $request) {
        return response()->json([
            'customer_id' => $request->user()->paddleCustomer()?->paddle_id,
        ]);
    })->middleware('auth');

    Route::post('/billing/customer', function (Request $request) {
        $user = $request->user();

        $paddleCustomer = \App\Models\PaddleCustomer::where('billable_id', $user->id)
            ->where('billable_type', \App\Models\User::class)
            ->first();

        if (! $paddleCustomer) {
            $response = \Laravel\Paddle\Cashier::api('POST', 'customers', [
                'name' => $user->name,
                'email' => $user->email,
            ]);

            $paddleCustomer = \App\Models\PaddleCustomer::create([
                'billable_id' => $user->id,
                'billable_type' => \App\Models\User::class,
                'paddle_id' => $response['data']['id'],
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        return response()->json([
            'customer_id' => $paddleCustomer->paddle_id,
        ]);
    })->middleware('auth');

    Route::get('/billing/subscription', function (Request $request) {
        $user = $request->user();
        $subscription = $user->paddleSubscriptions()->latest()->first();

        return response()->json([
            'customer_id' => $user->paddleCustomer?->paddle_id,
            'subscription_id' => $subscription?->paddle_id,
            'status' => $subscription?->status,
            'is_on_trial' => $subscription?->status === 'trialing',
        ]);
    })->middleware('auth');

    Route::get('steam', \App\Http\Controllers\Auth\SteamAuthController::class);

    // Signed .dem download — used by subtick to download demos for recording.
    // HMAC-gated, not behind auth (subtick fetches server-side with no cookie).
    Route::get('/api/demos/dl/{expires}/{token}/{demoId}.dem', function (string $expires, string $token, string $demoId, \Illuminate\Http\Request $request) {
        if (time() > (int) $expires) {
            return response()->json(['error' => 'Link expired.'], 403);
        }
        $expected = hash_hmac('sha256', $demoId.'|'.$expires, config('app.key'));
        if (! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Invalid link.'], 403);
        }

        // Try Bunny CDN first (meta.dem_cdn_url set by demoparser)
        $demo = \App\Models\Demo::find((int) $demoId);
        if ($demo && ($demo->meta['dem_cdn_url'] ?? null)) {
            return redirect()->away($demo->meta['dem_cdn_url']);
        }

        return response()->json(['error' => 'Demo file not available.'], 404);
    })->where(['expires' => '[0-9]+', 'demoId' => '[0-9]+']);

    Route::get('/{vue?}', function () {
        $user = Cache::remember('global-user:'.Auth::id(), now()->addMinute(), function () {
            $user = User::with(['profilePicture', 'userInfo', 'userRole'])
                ->find(Auth::id());

            if (! $user->is_admin) {
                unset($user->is_admin);
            }

            return $user;
        });

        app()->instance('user', $user);

        return view('app');
    })->middleware(\App\Http\Middleware\EnsureUserHasTeam::class)
        ->where('vue', '[\/\w\.-]*');
});
