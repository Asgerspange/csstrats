<?php

use Illuminate\Support\Facades\Route;

// Team management routes (rename, roles, tags, types, permissions,
// add/remove member, delete team, invitations, stats). These live in the
// ['web', 'auth'] group in api.php — no check.permissions (subscription gate).
// Auth + inline repository guards (isAdministrator) protect these.
Route::prefix('team/{id}')->group(function () {
    Route::post('settings/name', [App\Http\Controllers\TeamController::class, 'updateName']);
    Route::post('settings/roles', [App\Http\Controllers\TeamController::class, 'updateRoles']);
    Route::post('settings/tags', [App\Http\Controllers\TeamController::class, 'updateTags']);
    Route::post('settings/types', [App\Http\Controllers\TeamController::class, 'updateTypes']);
    Route::post('settings/permissions', [App\Http\Controllers\TeamController::class, 'updatePermissions']);

    Route::post('add-member', [App\Http\Controllers\TeamController::class, 'addMember']);
    Route::post('remove-member', [App\Http\Controllers\TeamController::class, 'removeMember']);

    Route::get('maps', [App\Http\Controllers\TeamController::class, 'maps']);
    Route::post('maps/select', [App\Http\Controllers\TeamController::class, 'selectMaps']);

    Route::delete('/', [App\Http\Controllers\TeamController::class, 'destroy']);

    Route::get('stats/overview', [App\Http\Controllers\TeamStatsController::class, 'overview']);
    Route::get('stats/matches', [App\Http\Controllers\TeamStatsController::class, 'matches']);
    Route::get('stats/maps', [App\Http\Controllers\TeamStatsController::class, 'maps']);
    Route::get('stats/roster', [App\Http\Controllers\TeamStatsController::class, 'roster']);

    Route::get('invitations', [App\Http\Controllers\TeamMemberInvitationController::class, 'index']);
    Route::post('invitations', [App\Http\Controllers\TeamMemberInvitationController::class, 'store']);
    Route::post('invitations/{inviteId}/revoke', [App\Http\Controllers\TeamMemberInvitationController::class, 'revoke']);
});
