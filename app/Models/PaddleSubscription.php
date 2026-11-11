<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaddleSubscription extends Model
{
    protected $table = 'paddle_subscriptions';

    protected $fillable = [
        'billable_id',
        'billable_type',
        'type',
        'paddle_id',
        'paddle_status',
        'paddle_plan',
        'quantity',
        'trial_ends_at',
        'paused_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'paused_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

        public function billable()
        {
            return $this->morphTo();
        }

        public function customer()
        {
            return $this->belongsTo(PaddleCustomer::class, 'billable_id', 'billable_id')
                        ->where('billable_type', $this->billable_type);
        }
}