<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A referral partner (e.g. HLTV, a tournament organiser) with their own
 * login to the partner dashboard. Authenticated via the "referrer" guard.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property float $commission_rate
 * @property string $commission_type
 * @property int|null $recurring_months
 * @property bool $is_active
 */
class Referrer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'commission_rate',
        'commission_type',
        'recurring_months',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'recurring_months' => 'integer',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(ReferralSource::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function visits(): HasManyThrough
    {
        return $this->hasManyThrough(
            ReferralVisit::class,
            ReferralSource::class,
            'referrer_id',          // FK on referral_sources
            'referral_source_id',   // FK on referral_visits
            'id',                   // local key on referrers
            'id',                   // local key on referral_sources
        );
    }
}
