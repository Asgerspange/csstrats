<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;

class RefreshCacheCommand extends Command
{
    protected $signature = 'cache:refresh';
    protected $description = 'Refresh application cache data';

    public function handle(CacheService $cacheService)
    {
        $this->info('Refreshing cache...');
        $cacheService->refreshCache();
        $this->info('Cache refreshed successfully!');
    }
}