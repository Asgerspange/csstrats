<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{
    Model,
    SoftDeletes
};

class AdminDemoTactic extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mysql';
    protected $table = 'admin_demo_tactics';

    protected $fillable = [
        'id',
        'tactic_id',
        'stratbook_tactic_id',
        'demo_id',
        'map',
        'round',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'id' => 'integer',
        'tactic_id' => 'integer',
        'stratbook_tactic_id' => 'integer',
        'demo_id' => 'integer',
        'map' => 'string',
        'round' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function tactic()
    {
        return $this->belongsTo(Tactic::class, 'tactic_id', 'id');
    }

    public function stratbookTactic()
    {
        return $this->belongsTo(StratbookTactic::class, 'stratbook_tactic_id', 'id');
    }

    // demo_id now references the unified demos table (admin demos are
    // demos.is_admin = 1); round data lives in demo_rounds.
    public function demo()
    {
        return $this->belongsTo(Demo::class, 'demo_id', 'id');
    }

    public function round()
    {
        return $this->belongsTo(DemoRound::class, 'demo_id', 'id')->where('round_index', $this->round);
    }
}
