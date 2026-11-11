<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $referral_source_id
 * @property string|null $visitor_id
 */
class ReferralVisit extends Model
{
    // Only created_at is tracked (no updated_at) for these append-only rows.
    const UPDATED_AT = null;

    protected $fillable = [
        'referral_source_id',
        'visitor_id',
        'ip_hash',
        'country_code',
        'referrer_url',
        'landing_path',
        'utm_medium',
        'utm_campaign',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'referral_source_id');
    }
}
