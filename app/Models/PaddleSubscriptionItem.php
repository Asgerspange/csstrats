<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaddleSubscriptionItem extends Model
{
    protected $table = 'paddle_subscription_items';

    protected $fillable = [
        'subscription_id',
        'product_id',
        'price_id',
        'status',
        'quantity',
        'trial_ends_at',
        'paused_at',
        'ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'paused_at'     => 'datetime',
        'ends_at'       => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PaddleSubscription::class, 'subscription_id');
    }
}