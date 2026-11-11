<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoHeatmap extends Model
{
    protected $connection = 'mysql';
    public $timestamps = false;

    protected $casts = [
        'kill_data' => 'array',
        'death_data' => 'array',
        'team1' => 'array',
        'team2' => 'array',
    ];
}
