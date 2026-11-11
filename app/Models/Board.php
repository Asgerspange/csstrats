<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo,
    HasOne
};

class Board extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'boards';

    protected $fillable = [
        'id',
        'tactic_id',
        'team_id',
        'name',
        'map_id',
        'data'
    ];

    protected $casts = [
        'data' => 'array'
    ];

    /**
     * NOTE: for stratbook boards, the `tactic_id` column actually references
     * `stratbook_tactics.id` (see StratbookTactic::board()), not the library
     * `tactics` table. Prefer stratbookTactic() below. This relation is kept
     * for backward-compatibility but is effectively unused.
     */
    public function tactic(): BelongsTo
    {
        return $this->belongsTo(Tactic::class);
    }

    /**
     * The stratbook tactic this board belongs to. The FK is the (legacy-named)
     * `tactic_id` column, matching StratbookTactic::board().
     */
    public function stratbookTactic(): BelongsTo
    {
        return $this->belongsTo(StratbookTactic::class, 'tactic_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(BoardSnapshot::class);
    }
}
