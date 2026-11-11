<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoEnrichmentJob extends Model
{
    protected $connection = 'mysql';
    protected $table = 'demo_enrichment_jobs';

    protected $fillable = [
        'demo_id',
        'round_indices',
        'status',
        'error_message',
    ];

    protected $casts = [
        'round_indices' => 'array',
    ];

    public function demo()
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }
}
