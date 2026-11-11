<?php

use Illuminate\Support\Facades\Route;

Route::get('stratbooks/tactics/all', [App\Http\Controllers\StratbookTacticController::class, 'all']);
Route::prefix('stratbooks/{stratbook}')->middleware('map.access')->group(function () {
    Route::post('export-batch', [App\Http\Controllers\StratbookController::class, 'export']);
    Route::get('tactics', [App\Http\Controllers\StratbookTacticController::class, 'index']);
    Route::get('tactic-ids', [App\Http\Controllers\StratbookTacticController::class, 'tacticIds']);
    Route::patch('tactics/{tacticId}', [App\Http\Controllers\StratbookTacticController::class, 'update']);
    Route::get('tactics/{tacticId}', [App\Http\Controllers\StratbookTacticController::class, 'find']);
    Route::get('tactics/{tacticId}/export', [App\Http\Controllers\StratbookTacticController::class, 'export']);
    Route::post('tactics', [App\Http\Controllers\StratbookTacticController::class, 'store']);
    Route::post('custom-tactics', [App\Http\Controllers\StratbookTacticController::class, 'storeCustom']);
    Route::put('tactics/reorder', [App\Http\Controllers\StratbookTacticController::class, 'reorder']);
    Route::delete('tactics/{tacticId}', [App\Http\Controllers\StratbookTacticController::class, 'deleteCustom']);
});
