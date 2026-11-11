<?php

use App\Http\Controllers\Api\RoundAnnotationController;
use App\Http\Controllers\RoundPlanController;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will be
| assigned to the "api" middleware group. Make something great!
|
*/
Route::post(
    'stripe/webhook',
    [App\Http\Controllers\StripeWebhookController::class, 'handleWebhook']
)->middleware(\Laravel\Cashier\Http\Middleware\VerifyWebhookSignature::class)->withoutMiddleware(VerifyCsrfToken::class);

// Public, unauthenticated read of a shared round plan. Token-gated; no auth.
Route::get('/public/round-plans/{token}', [RoundPlanController::class, 'publicShow']);

// ──────────────────────────────────────────────────────────────────────────
// Routes accessible to any authenticated user with a team, even without a
// subscription. The Team page is the product home; a user must be able to
// view and manage their team, browse the tactic overview, and use the
// getting-started guide before they pay. Auth + TeamPolicy::view + inline
// repo guards protect these; check.permissions (the subscription gate) does
// NOT run here. Creating a team (store) still has its own subscription check
// inside the repository.
// ──────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'auth'])->group(function () {
    // User (already had withoutMiddleware in the old layout)
    Route::resource('user', App\Http\Controllers\UserController::class)->only(['index', 'store']);
    Route::post('user/settings/theme', [App\Http\Controllers\UserController::class, 'updateTheme']);
    Route::post('user/settings/dismiss-checklist', [App\Http\Controllers\UserController::class, 'dismissChecklist']);

    // Plan catalogue for the in-app upgrade modal
    Route::get('plans', [App\Http\Controllers\PlanCatalogueController::class, 'catalogue']);

    // Maps (needed by the Team page filter bar)
    Route::resource('maps', App\Http\Controllers\MapController::class)->only(['index', 'show']);

    // Team list (returns only the user's teams) + create (has its own
    // subscription check in the repo).
    Route::resource('teams', App\Http\Controllers\TeamController::class)
        ->only(['index', 'store']);

    // Team show — membership-gated via TeamPolicy::view (belongsToTeam).
    Route::get('teams/{team}', [App\Http\Controllers\TeamController::class, 'show'])
        ->middleware('can:view,team');

    // Cross-stratbook tactic overview — same membership gate.
    Route::get('teams/{team}/tactics', [App\Http\Controllers\TeamController::class, 'tactics'])
        ->middleware('can:view,team');

    // Invite link (shareable team join URL) — membership-gated, then inline
    // role checks in the controller (Owner/Coach/IGL for get+generate,
    // Owner/Coach for revoke).
    Route::get('/teams/{team}/invite', [\App\Http\Controllers\TeamInviteController::class, 'getLink'])
        ->middleware('can:view,team');
    Route::post('/teams/{team}/invite/generate', [\App\Http\Controllers\TeamInviteController::class, 'generate'])
        ->middleware('can:view,team');
    Route::post('/teams/{team}/invite/revoke', [\App\Http\Controllers\TeamInviteController::class, 'revoke'])
        ->middleware('can:view,team');

    // Stratbook index only (list + check hasStratbooks). Create/update/delete
    // stay in the check.permissions group below — managing stratbooks is paid.
    Route::get('stratbooks', [App\Http\Controllers\StratbookController::class, 'index']);

    // Onboarding (GettingStarted guide marks completion)
    Route::post('/onboarding/complete', [\App\Http\Controllers\OnboardingController::class, 'complete']);
    Route::post('/onboarding/reset', [\App\Http\Controllers\OnboardingController::class, 'reset']);

    // Team management routes (rename, roles, tags, types, permissions,
    // add/remove member, delete team, invitations, stats)
    require_once __DIR__.'/application/team.php';
});

Route::middleware(['web', 'auth'])->post(
    '/invite/{token}/accept',
    [\App\Http\Controllers\TeamInviteController::class, 'accept']
);

// Email-invitation flow (per-recipient tokens, distinct from shareable team link)
Route::middleware(['web'])->get(
    '/team-invitations/{token}',
    [\App\Http\Controllers\TeamMemberInvitationController::class, 'show']
);
Route::middleware(['web', 'auth'])->post(
    '/team-invitations/{token}/accept',
    [\App\Http\Controllers\TeamMemberInvitationController::class, 'accept']
);
Route::middleware(['web', 'auth'])->post(
    '/team-invitations/{token}/decline',
    [\App\Http\Controllers\TeamMemberInvitationController::class, 'decline']
);

// ──────────────────────────────────────────────────────────────────────────
// Subscription-gated routes. A user must have an active subscription or be
// on a team whose owner has one. These are the product features you pay for:
// tactics library, stratbook create/edit, demos, board, round plans, etc.
// ──────────────────────────────────────────────────────────────────────────
Route::middleware(['web', 'check.permissions'])->group(function () {
    Route::resource('tactics', App\Http\Controllers\TacticController::class)->only(['index', 'show']);

    // Stratbook create/update/delete (managing stratbooks is a paid feature)
    Route::resource('stratbooks', App\Http\Controllers\StratbookController::class)->only(['store', 'destroy', 'update'])->middleware('map.access');
    Route::resource('boards', App\Http\Controllers\BoardController::class)->only(['index', 'store', 'destroy', 'update']);
    Route::resource('demos/folders', App\Http\Controllers\DemoFolderController::class)->only(['index', 'show', 'store', 'destroy', 'update']);
    Route::resource('demos', App\Http\Controllers\DemoController::class)->only(['index', 'show', 'store', 'destroy'])->withoutMiddleware([VerifyCsrfToken::class]);

    Route::post('/demos/{id}/chat', [\App\Http\Controllers\DemoChatController::class, 'chat']);

    Route::get('/round-plans', [RoundPlanController::class, 'index']);
    Route::post('/round-plans', [RoundPlanController::class, 'store']);
    Route::get('/round-plans/{roundPlan}', [RoundPlanController::class, 'show']);
    Route::patch('/round-plans/{roundPlan}', [RoundPlanController::class, 'update']);
    Route::delete('/round-plans/{roundPlan}', [RoundPlanController::class, 'destroy']);
    // Share management (auth required — only team members can toggle)
    Route::post('/round-plans/{roundPlan}/share', [RoundPlanController::class, 'enableShare']);
    Route::delete('/round-plans/{roundPlan}/share', [RoundPlanController::class, 'disableShare']);

    Route::post('demos/requestUpload', [App\Http\Controllers\DemoController::class, 'requestUpload'])
        ->withoutMiddleware([VerifyCsrfToken::class, \Illuminate\Routing\Middleware\ThrottleRequests::class]);

    Route::post('demos/{id}/upload', [App\Http\Controllers\DemoController::class, 'upload'])
        ->withoutMiddleware([VerifyCsrfToken::class, \Illuminate\Routing\Middleware\ThrottleRequests::class]);

    Route::post('demos/{id}/finalize', [App\Http\Controllers\DemoController::class, 'finalizeUpload'])
        ->withoutMiddleware([VerifyCsrfToken::class, \Illuminate\Routing\Middleware\ThrottleRequests::class]);
    Route::patch('demos/{id}/update', [App\Http\Controllers\DemoController::class, 'update'])->withoutMiddleware(VerifyCsrfToken::class);

    Route::get('/demos/{demoId}/rounds', [App\Http\Controllers\DemoController::class, 'getDemoRounds']);

    Route::get('demo-chunk', [App\Http\Controllers\DemoController::class, 'getChunk']);
    Route::get('demo/{id}/info', 'App\Http\Controllers\DemoController@getDemoInfo');

    Route::get('pro-teams', [App\Http\Controllers\ProTeamController::class, 'index']);

    Route::get('/callouts/all', [\App\Http\Controllers\CalloutController::class, 'allZonesForTeam']);
    Route::get('/callouts/{map}', [\App\Http\Controllers\CalloutController::class, 'zonesForTeam']);
    Route::post('/callouts/{map}/team/{team}', [\App\Http\Controllers\CalloutController::class, 'saveTeamNames']);

    Route::get('/demos/{demo}/rounds/{round}/annotations', [RoundAnnotationController::class, 'index']);
    Route::post('/demos/{demo}/rounds/{round}/annotations', [RoundAnnotationController::class, 'store']);
    Route::patch('/annotations/{annotation}', [RoundAnnotationController::class, 'update']);
    Route::delete('/annotations/{annotation}', [RoundAnnotationController::class, 'destroy']);

    require_once __DIR__.'/application/stratbook.php';
    require_once __DIR__.'/application/antistrat.php';

    Route::get('images', [App\Http\Controllers\ImageController::class, 'index']);

    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('faceit/matches', [App\Http\Controllers\FaceitController::class, 'getRecentMatches']);
        Route::post('faceit/queue', [App\Http\Controllers\FaceitController::class, 'queueMatch']);
    });
});
Route::prefix('metrics')->group(function () {
    Route::get('/retention', [\App\Http\Controllers\RetentionMetricsController::class, 'retention']);
    Route::get('/funnel', [\App\Http\Controllers\RetentionMetricsController::class, 'funnel']);
    Route::get('/churn-risk', [\App\Http\Controllers\RetentionMetricsController::class, 'churnRisk']);
    Route::get('/trial', [\App\Http\Controllers\RetentionMetricsController::class, 'trial']);
    Route::get('/invite-funnel', [\App\Http\Controllers\RetentionMetricsController::class, 'inviteFunnel']);
});

Route::middleware(['web', 'admin'])->group(function () {
    Route::get('/admin/callouts/maps', [\App\Http\Controllers\CalloutController::class, 'adminMaps']);
    Route::get('/admin/callouts/{map}/layers', [\App\Http\Controllers\CalloutController::class, 'adminLayers']);
    Route::post('/admin/callouts/{map}/layers', [\App\Http\Controllers\CalloutController::class, 'storeLayer']);
    Route::put('/admin/callouts/layers/{layer}', [\App\Http\Controllers\CalloutController::class, 'updateLayer']);
    Route::delete('/admin/callouts/layers/{layer}', [\App\Http\Controllers\CalloutController::class, 'deleteLayer']);
    Route::get('/admin/callouts/{map}', [\App\Http\Controllers\CalloutController::class, 'adminZones']);
    Route::post('/admin/callouts/{map}/zones', [\App\Http\Controllers\CalloutController::class, 'storeZone']);
    Route::put('/admin/callouts/zones/{zone}', [\App\Http\Controllers\CalloutController::class, 'updateZone']);
    Route::delete('/admin/callouts/zones/{zone}', [\App\Http\Controllers\CalloutController::class, 'deleteZone']);

    // Referral partner management
    Route::get('/admin/referrers', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'index']);
    Route::post('/admin/referrers', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'store']);
    Route::get('/admin/referrers/{referrer}', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'show']);
    Route::put('/admin/referrers/{referrer}', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'update']);
    Route::delete('/admin/referrers/{referrer}', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'destroy']);
    Route::post('/admin/referrers/{referrer}/sources', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'storeSource']);
    Route::delete('/admin/referral-sources/{source}', [\App\Http\Controllers\Admin\AdminReferrerController::class, 'destroySource']);

    // Tactic management (create / edit / delete) for the vue-router admin pages
    Route::post('/admin/tactics', [\App\Http\Controllers\TacticController::class, 'adminStore']);
    Route::get('/admin/tactics/{id}', [\App\Http\Controllers\TacticController::class, 'adminShow'])->whereNumber('id');
    Route::put('/admin/tactics/{id}', [\App\Http\Controllers\TacticController::class, 'adminUpdate'])->whereNumber('id');
    Route::delete('/admin/tactics/{id}', [\App\Http\Controllers\TacticController::class, 'adminDestroy'])->whereNumber('id');

    // Curate a parsed user demo as an admin demo (replaces the old admin upload pipeline)
    Route::post('/demos/{id}/curate-rounds', [\App\Http\Controllers\DemoController::class, 'curateRounds'])->whereNumber('id');
    Route::post('/demos/{id}/uncurate-rounds', [\App\Http\Controllers\DemoController::class, 'uncurateRounds'])->whereNumber('id');

    // Release management (JSON)
    Route::get('/admin/tactics-release', [\App\Http\Controllers\ReleaseTacticController::class, 'apiIndex']);
    Route::get('/admin/tactics-scheduled', [\App\Http\Controllers\ReleaseTacticController::class, 'getScheduled']);
    Route::post('/admin/tactics/release/{id}', [\App\Http\Controllers\ReleaseTacticController::class, 'releaseSingle'])->whereNumber('id');
    Route::post('/admin/tactics/release-multiple', [\App\Http\Controllers\ReleaseTacticController::class, 'releaseMultiple']);
    Route::post('/admin/tactics/schedule-release', [\App\Http\Controllers\ReleaseTacticController::class, 'scheduleRelease']);
    Route::post('/admin/tactics/release-amount', [\App\Http\Controllers\ReleaseTacticController::class, 'releaseAmount']);
    Route::post('/admin/tactics/unreleased/{id}', [\App\Http\Controllers\ReleaseTacticController::class, 'unreleased'])->whereNumber('id');
    Route::post('/admin/tactics/feature/{id}', [\App\Http\Controllers\ReleaseTacticController::class, 'feature'])->whereNumber('id');
    Route::post('/admin/tactics/unfeature/{id}', [\App\Http\Controllers\ReleaseTacticController::class, 'unfeature'])->whereNumber('id');
    Route::post('/admin/tactics/cancel-schedule/{id}', [\App\Http\Controllers\ReleaseTacticController::class, 'cancelScheduledRelease'])->whereNumber('id');

    // Plan management — lets an admin set any user's effective plan (tier
    // preset or custom limit overrides) so the in-app gating can be tested
    // end to end. See App\Http\Controllers\Admin\AdminPlanController.
    Route::get('/admin/plans', [\App\Http\Controllers\Admin\AdminPlanController::class, 'index']);
    Route::post('/admin/users/{id}/plan', [\App\Http\Controllers\Admin\AdminPlanController::class, 'setPlan'])->whereNumber('id');
    Route::delete('/admin/users/{id}/plan', [\App\Http\Controllers\Admin\AdminPlanController::class, 'clearPlan'])->whereNumber('id');

    // Team & stratbook management
    Route::get('/admin/teams', [\App\Http\Controllers\Admin\AdminTeamController::class, 'index']);
    Route::get('/admin/teams/{id}', [\App\Http\Controllers\Admin\AdminTeamController::class, 'show'])->whereNumber('id');
    Route::post('/admin/teams/{id}/convert', [\App\Http\Controllers\Admin\AdminTeamController::class, 'convertToMapBased'])->whereNumber('id');
    Route::patch('/admin/stratbooks/{id}', [\App\Http\Controllers\Admin\AdminTeamController::class, 'updateStratbook'])->whereNumber('id');
    Route::post('/admin/stratbooks/{id}/assign-map', [\App\Http\Controllers\Admin\AdminTeamController::class, 'assignMap'])->whereNumber('id');
    Route::patch('/admin/tactics/{id}', [\App\Http\Controllers\Admin\AdminTeamController::class, 'updateTactic'])->whereNumber('id');
    Route::post('/admin/tactics/{id}/duplicate', [\App\Http\Controllers\Admin\AdminTeamController::class, 'duplicateTactic'])->whereNumber('id');
    Route::delete('/admin/tactics/{id}', [\App\Http\Controllers\Admin\AdminTeamController::class, 'deleteTactic'])->whereNumber('id');

    // Command runner
    Route::get('/admin/commands', [\App\Http\Controllers\Admin\AdminCommandsController::class, 'index']);
    Route::post('/admin/commands/run', [\App\Http\Controllers\Admin\AdminCommandsController::class, 'run']);

    // Detected tactics (demo scanner)
    Route::get('/admin/scanned-demos', [\App\Http\Controllers\DetectedTacticController::class, 'scannedDemos']);
    Route::get('/admin/detected-tactics', [\App\Http\Controllers\DetectedTacticController::class, 'index']);
    Route::get('/admin/detected-tactics/{id}', [\App\Http\Controllers\DetectedTacticController::class, 'show'])->whereNumber('id');
    Route::post('/admin/detected-tactics/{id}/reject', [\App\Http\Controllers\DetectedTacticController::class, 'reject'])->whereNumber('id');
    Route::post('/admin/detected-tactics/{id}/approve', [\App\Http\Controllers\DetectedTacticController::class, 'approve'])->whereNumber('id');
    Route::post('/admin/detected-tactics/{id}/retry-recording', [\App\Http\Controllers\DetectedTacticController::class, 'retryRecording'])->whereNumber('id');
    Route::post('/admin/detected-tactics/{id}/link', [\App\Http\Controllers\DetectedTacticController::class, 'link'])->whereNumber('id');
    Route::post('/admin/detected-tactics/{id}/release', [\App\Http\Controllers\ReleaseTacticController::class, 'releaseDetectedTactic'])->whereNumber('id');

    // Recording callback from subtick (server-key auth, not admin)
    Route::post('/admin/detected-tactics/recording-callback', [\App\Http\Controllers\DetectedTacticController::class, 'recordingCallback'])
        ->middleware('server.key')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
});
