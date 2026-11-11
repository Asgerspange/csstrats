<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoardSnapshot extends Model
{
    public $timestamps = false;

    protected $connection = 'mysql';

    protected $table = 'board_snapshots';

    protected $fillable = [
        'id',
        'board_id',
        'tactic_id',
        'team_id',
        'url',
    ];

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function tactic()
    {
        return $this->belongsTo(Tactic::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
