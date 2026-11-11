<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThrowPoint extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'nade_id',
        'title',
        'description',
        'throw_x',
        'throw_y',
        'video_url',
        'technique',
        'additional_info',
        'order',
        'active',
    ];

    protected $casts = [
        'throw_x' => 'decimal:2',
        'throw_y' => 'decimal:2',
        'order' => 'integer',
        'active' => 'boolean',
    ];

    public function nade(): BelongsTo
    {
        return $this->belongsTo(Nade::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}