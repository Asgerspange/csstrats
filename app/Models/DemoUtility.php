<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoUtility extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'demo_id',
        'round_index',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $table = 'demo_grenades';

    protected $primaryKey = 'id';

    protected $connection = 'mysql';
}
