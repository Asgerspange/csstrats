<?php

namespace App\Core\Repositories;

use App\Models\Demo;

class DemoRepository
{
    public function totalDemos()
    {
        $startThisMonth = now()->startOfMonth();
        $endThisMonth = now()->endOfMonth();
        $startLastMonth = now()->subMonth()->startOfMonth();
        $endLastMonth = now()->subMonth()->endOfMonth();
        $demos = Demo::withTrashed()->get();

        $active = $demos->whereNull('deleted_at');
        $demosThisMonth = $demos->whereBetween('created_at', [$startThisMonth, $endThisMonth]);
        $demosLastMonth = $demos->whereBetween('created_at', [$startLastMonth, $endLastMonth]);

        return [
            'total' => $demos->count(),
            'active' => $active->count(),
            'this_month' => $demosThisMonth->count(),
            'last_month' => $demosLastMonth->count(),
        ];
    }
}
