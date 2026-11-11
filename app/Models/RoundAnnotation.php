<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoundAnnotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'demo_id',
        'user_id',
        'round_number',
        'tick',
        'type',
        'content',
        'shape_data',
        'color',
        'label',
    ];

    protected $casts = [
        'shape_data'   => 'array',
        'round_number' => 'integer',
        'tick'         => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function demo()
    {
        return $this->belongsTo(Demo::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filter to a specific demo + round combination.
     */
    public function scopeForRound($query, int $demoId, int $roundNumber)
    {
        return $query->where('demo_id', $demoId)
                     ->where('round_number', $roundNumber);
    }

    /**
     * Only text-note type annotations.
     */
    public function scopeNotes($query)
    {
        return $query->where('type', 'note');
    }

    /**
     * Only shape/area annotations (area + freehand + utility).
     */
    public function scopeShapes($query)
    {
        return $query->whereIn('type', ['area', 'freehand', 'utility']);
    }
}