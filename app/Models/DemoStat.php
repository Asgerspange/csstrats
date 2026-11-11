<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoStat extends Model
{
    use HasFactory;
 /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';
    protected $table = 'demo_stats';

    protected $fillable = [
        'demo_id',
        'stats',
        'kast',
        'rating',
        'KDA',
        'hitgroups',
        'position_dominance',
        'team1',
        'team2',
    ];

    protected $casts = [
        'stats' => 'array',
        'team1' => 'array',
        'team2' => 'array',
        'rating' => 'array',
        'kast' => 'array',
        'KDA' => 'array',
        'hitgroups' => 'array',
        'position_dominance' => 'array',
    ];

    public function demo()
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }
}
