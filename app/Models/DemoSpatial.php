<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoSpatial extends Model
{
    protected $table    = 'demo_spatial';
    protected $fillable = ['demo_id', 'type', 'data'];

    // data is stored as base64-encoded gzip — decode automatically
    protected $casts = [];

    public function demo(): BelongsTo
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }

    /**
     * Decode the compressed data column → PHP array.
     */
    public function getDecodedData(): array
    {
        if (!$this->data) return [];
        try {
            $compressed = base64_decode($this->data);
            $json       = gzdecode($compressed);
            return json_decode($json, true) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}