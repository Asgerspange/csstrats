<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRound extends Model
{
    use HasFactory;
 /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';
    protected $table = 'demo_rounds';
    public $timestamps = false;

    protected $fillable = [
        'demo_id',
        'round_index',
        'frame_data',
        'curated',
    ];

    protected $hidden = ['frame_data'];

    protected $casts = [
        'meta' => 'array',
        'curated' => 'boolean',
    ];

    public function demo()
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }
}
