<?php
use App\Http\Middleware\{
    AuthenticateAdminMiddleware,
    AuthenticateDevMiddleware,
};
use App\Http\Controllers\{
    AdminTacticsController,
    ReleaseTacticController,
    NadeMapController
};
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => [AuthenticateAdminMiddleware::class]], function () {
    Route::group(['middleware' => [AuthenticateDevMiddleware::class]], function () {
        Route::get('/admin/dashboard/statistics', [App\Http\Controllers\Routing\AdminController::class, 'adminDashboardStatistics']);

        Route::prefix('admin/tactics')->name('admin.tactics.')->group(function () {
            Route::post('release/{id}', [ReleaseTacticController::class, 'releaseSingle'])->name('release.single');
            Route::post('release-multiple', [ReleaseTacticController::class, 'releaseMultiple'])->name('release.multiple');
            Route::post('schedule-release', [ReleaseTacticController::class, 'scheduleRelease'])->name('schedule.release');
            Route::post('release-amount', [ReleaseTacticController::class, 'releaseAmount'])->name('release.amount');
            Route::post('unreleased/{id}', [ReleaseTacticController::class, 'unreleased'])->name('unreleased');
            Route::post('feature/{id}', [ReleaseTacticController::class, 'feature'])->name('feature');
            Route::post('unfeature/{id}', [ReleaseTacticController::class, 'unfeature'])->name('unfeature');
            Route::get('scheduled', [ReleaseTacticController::class, 'getScheduled'])->name('scheduled');
            Route::get('cancel-schedule/{id}', [ReleaseTacticController::class, 'cancelScheduledRelease'])->name('cancel.schedule');
        });

    Route::post('system/process-scheduled-releases', [ReleaseTacticController::class, 'processScheduledReleases'])
        ->name('system.process.scheduled.releases');
    });

    Route::get('/admin/dashboard/stalker', [App\Http\Controllers\Routing\AdminController::class, 'adminDashboardStalker'])->name('admin');

    Route::get('/admin', [App\Http\Controllers\Routing\AdminController::class, 'admin']);

    Route::get('/admin/teams', [App\Http\Controllers\Routing\AdminController::class, 'adminTeams'])
        ->name('admin.teams');

    Route::get('/admin/loginas/{id}', [App\Http\Controllers\Routing\AdminController::class, 'loginAs'])
        ->name('admin.loginas');

    Route::post('/nades/{map}/store', [NadeMapController::class, 'storeNade'])->name('nades.store');
    Route::put('/nades/{nade}', [NadeMapController::class, 'updateNade'])->name('nades.update');
    Route::delete('/nades/{nade}', [NadeMapController::class, 'deleteNade'])->name('nades.destroy');
    
    Route::post('/nades/{nade}/throw-points', [NadeMapController::class, 'storeThrowPoint'])->name('throw-points.store');
    Route::put('/throw-points/{throwPoint}', [NadeMapController::class, 'updateThrowPoint'])->name('throw-points.update');
    Route::delete('/throw-points/{throwPoint}', [NadeMapController::class, 'deleteThrowPoint'])->name('throw-points.destroy');
});