<?php

// routes/application/antistrat.php
// Include this file from routes/api.php with:
//   require_once __DIR__ . '/application/antistrat.php';

use App\Http\Controllers\{
    OpponentHeatmapController,
    OpponentTeamController,
};
use Illuminate\Support\Facades\Route;

Route::prefix('opponent-teams')->group(function () {
    // CRUD for opponent profiles
    Route::get   ('/',                                  [OpponentTeamController::class, 'index']);
    Route::post  ('/',                                  [OpponentTeamController::class, 'store']);
    Route::get   ('/{id}',                              [OpponentTeamController::class, 'show']);
    Route::patch ('/{id}',                              [OpponentTeamController::class, 'update']);
    Route::delete('/{id}',                              [OpponentTeamController::class, 'destroy']);

    // Sharing — owner syncs which of their teams can see this profile
    Route::put   ('/{id}/shares',                       [OpponentTeamController::class, 'updateShares']);

    // Demo linking
    Route::post  ('/{id}/demos',                        [OpponentTeamController::class, 'attachDemo']);
    Route::delete('/{id}/demos',                        [OpponentTeamController::class, 'detachDemo']);

    // Auto-match a demo to an opponent
    Route::post  ('/auto-match',                        [OpponentTeamController::class, 'autoMatch']);

    // Player management
    Route::patch ('/{opponentId}/players/{playerId}',   [OpponentTeamController::class, 'updatePlayer']);

    // Counter strats
    Route::post  ('/{id}/counter-strats',               [OpponentTeamController::class, 'storeCounterStrat']);
    Route::delete('/{opponentId}/counter-strats/{stratId}', [OpponentTeamController::class, 'destroyCounterStrat']);

    // AI
    Route::post  ('/{id}/insights/refresh',             [OpponentTeamController::class, 'refreshInsights']);

    Route::get('/{id}/analytics', [\App\Http\Controllers\OpponentAnalyticsController::class, 'analytics']);

    Route::get('/{id}/insights/status',   [OpponentTeamController::class, 'insightsStatus']);
 
    // Movement paths from frame_data
    Route::get('/{id}/movement',          [\App\Http\Controllers\OpponentMovementController::class, 'movement']);
    Route::get('/{id}/heatmap',  [OpponentHeatmapController::class, 'show']);
    Route::get('{id}/spatial',  [OpponentHeatmapController::class, 'spatial']);
    // Grenade positions
    Route::get('/{id}/grenades',          [\App\Http\Controllers\OpponentMovementController::class, 'grenades']);

    Route::get('/{id}/position-dominance', [\App\Http\Controllers\OpponentAnalyticsController::class, 'positionDominance']);

    Route::post('/{id}/chat', [\App\Http\Controllers\AntiStratChatController::class, 'chat']);
 
    Route::post('/{id}/counter-strats/generate', [OpponentTeamController::class, 'generateCounterStrats']);

});

Route::prefix('opponent-suggestions')->group(function () {
    Route::get('/', [\App\Http\Controllers\OpponentSuggestionsController::class, 'index']);
    Route::post('/{id}/dismiss', [\App\Http\Controllers\OpponentSuggestionsController::class, 'dismiss']);
    Route::post('/{id}/create-profile', [\App\Http\Controllers\OpponentSuggestionsController::class, 'createProfile']);
});