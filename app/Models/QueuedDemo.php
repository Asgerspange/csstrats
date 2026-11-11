<?php

namespace App\Models;

use App\Enums\QueuedDemoStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueuedDemo extends Model
{
    use HasFactory;
    protected $connection = 'mysql';

    public const UPDATED_AT = null;
    public const DELETED_AT = null;
    public $timestamps = true;

    protected $table = 'queued_demos';

    protected $fillable = [
        'faceit_match_id',
        'status',
        'owner',
        'name',
        'metadata',
    ];

    protected $casts = [
        'status' => QueuedDemoStatus::class,
        'metadata' => 'array',
    ];

    public function owner() {
        return $this->belongsTo(User::class, 'owner');
    }

    public function chunks() {
        return $this->hasMany(QueuedDemoChunk::class, 'queued_demo_id');
    }
}