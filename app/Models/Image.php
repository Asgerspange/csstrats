<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base64',
        'image_group',
        'url',
    ];
    protected $casts = [
        'base64' => 'string',
        'image_group' => 'string',
    ];

    public $timestamps = false;
}
