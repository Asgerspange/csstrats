<?php

namespace App\Observers;

use App\Models\Demo;
use App\Models\DemoRound;
use App\Models\DemoStat;

class DemoObserver
{
    /**
     * Handle the Demo "created" event.
     */
    public function created(Demo $demo): void
    {
        //
    }

    /**
     * Handle the Demo "updated" event.
     */
    public function updated(Demo $demo): void
    {
        //
    }

    /**
     * Handle the Demo "deleted" event.
     */
    public function deleted(Demo $demo): void
    {
        DemoRound::where('demo_id', $demo->id)->delete();
        DemoStat::where('demo_id', $demo->id)->delete();
    }

    /**
     * Handle the Demo "restored" event.
     */
    public function restored(Demo $demo): void
    {
        //
    }

    /**
     * Handle the Demo "force deleted" event.
     */
    public function forceDeleted(Demo $demo): void
    {
        //
    }
}
