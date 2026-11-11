<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapSelectionAudit extends Model
{
    public $timestamps = false;

    protected $table = 'map_selection_audits';

    protected $fillable = [
        'team_id',
        'map_ids',
        'selected_by',
        'selected_at',
        'reason',
    ];

    protected $casts = [
        'map_ids' => 'array',
        'selected_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
