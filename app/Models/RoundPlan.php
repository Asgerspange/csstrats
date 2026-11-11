<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundPlan extends Model
{
    protected $fillable = [
        'stratbook_tactic_id',
        'team_id',
        'created_by',
        'name',
        'map',
        'side',
        'data',
        'snapshot_url',
        'share_token',
        'share_enabled',
    ];

    protected $casts = [
        'data' => 'array',
        'share_enabled' => 'boolean',
    ];

    public function tactic(): BelongsTo
    {
        return $this->belongsTo(StratbookTactic::class, 'stratbook_tactic_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
