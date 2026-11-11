<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single attribution key owned by a referrer. Matched against
 * ?utm_source=<code> and the short link /r/<code>.
 *
 * @property int $id
 * @property int $referrer_id
 * @property string $code
 * @property string|null $label
 */
class ReferralSource extends Model
{
    protected $fillable = [
        'referrer_id',
        'code',
        'label',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ReferralVisit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }
}
