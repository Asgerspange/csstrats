<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Map extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'image_path',
        'width',
        'height',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function nades(): HasMany
    {
        return $this->hasMany(Nade::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function mapMetadata()
    {
        return $this->hasOne(MapMetadata::class);
    }
}