<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use App\Services\CacheService;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            app(CacheService::class)->refreshCache();
        })->daily()
          ->at('03:00')
          ->appendOutputTo(storage_path('logs/cache-refresh.log'));
        $schedule->command('tactics:process-scheduled-releases')->hourly()->appendOutputTo(storage_path('logs/scheduled_releases.log'))->timezone('Europe/Amsterdam');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
