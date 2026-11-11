<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One earned commission row, created when a Paddle transaction completes for
 * a referred user. Unique per Paddle transaction.
 *
 * @property int $id
 * @property int $referrer_id
 * @property int|null $referral_source_id
 * @property int $user_id
 * @property string $paddle_transaction_id
 * @property float $order_total
 * @property string $currency
 * @property float $commission_rate
 * @property float $commission_amount
 */
class ReferralCommission extends Model
{
    protected $fillable = [
        'referrer_id',
        'referral_source_id',
        'user_id',
        'paddle_transaction_id',
        'order_total',
        'currency',
        'commission_rate',
        'commission_amount',
        'billed_at',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'billed_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'referral_source_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
