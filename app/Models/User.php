<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{
    BelongsToMany,
    HasMany,
    HasManyThrough,
    HasOne,
};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;
use DateTime;
/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $auth_token
 * @property string|null $teamMember
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $steam_id
 * @property string|null $google_id
 * @property string|null $country_code
 * @property string|null $stripe_id
 * @property string $password
 * @property bool|int $is_admin
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $faceit_id
 * @property string|null $faceit_nickname
 * @property string|null $perm
 * @property array|null $settings
 * @property string|null $remember_token
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * 
 * Subscription properties:
 * @property DateTime $subscription_ends_at
 * 
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'auth_token',
        'teamMember',
        'email_verified_at',
        'steam_id',
        'google_id',
        'country_code',
        'id',
        'stripe_id',
        'password',
        'is_admin',
        'created_at',
        'faceit_id',
        'faceit_nickname',
        'perm',
        'settings',
        'referral_source_id',
        'referral_visitor_id',
        'referred_at',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'subscriptions',
        'password',
        'updated_at',
        'pm_type',
        'pm_last_four',
        'remember_token',
    ];
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'settings' => 'array',
        'referred_at' => 'datetime',
    ];

    public function referralSource(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\ReferralSource::class, 'referral_source_id');
    }

    public function paddleSubscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\PaddleSubscription::class, 'billable_id')
                    ->where('billable_type', self::class);
    }

    public function paddleCustomer(): HasOne
    {
        return $this->hasOne(\App\Models\PaddleCustomer::class, 'billable_id')
                    ->where('billable_type', self::class);
    }
    
    public function userLimit(): HasOne
    {
        return $this->hasOne(\App\Models\UserLimit::class, 'user_id');
    }

    public function hasActivePaddleSubscription(): bool
    {
        return \App\Models\PaddleSubscription::where('billable_id', $this->id)
            ->where('billable_type', self::class)
            ->where('status', 'active')
            ->exists();
    }

    public function profilePicture(): HasOne
    {
        return $this->hasOne(Image::class, 'name', 'id');
    }

    public function teams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            TeamMember::class,
            'user_id',     // Foreign key on team_members table
            'id',          // Foreign key on teams table
            'id',          // Local key on users table
            'team_id'      // Local key on team_members table
        );
    }

    public function userInfo(): HasOne
    {
        return $this->hasOne(UserInfo::class, 'uuid', 'uuid');
    }

    public function userRole(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id', 'id');
    }

    public function mailListing(): HasOne
    {
        return $this->hasOne(MailListing::class, 'user_id', 'id');
    }

    public function hasActiveSubscription(): HasOne
    {
        return $this->hasOne(\Laravel\Cashier\Subscription::class, 'user_id', 'id')
            ->where('stripe_status', 'active');
    }

    public function hasSubscription(): HasOne
    {
        return $this->hasOne(\Laravel\Cashier\Subscription::class, 'user_id', 'id');
    }

    public function hasGrantedAccess(): bool
    {
        return $this->hasOne(GrantedAccess::class, 'user_id', 'id')
            ->where(function ($query) {
            $query->where('expires_at', '>', now())
                  ->orWhereNull('expires_at');
            })->exists();
    }

    public function grantedAccess(): HasOne
    {
        return $this->hasOne(GrantedAccess::class, 'user_id', 'id')
            ->where(function ($query) {
            $query->where('expires_at', '>', now())
                  ->orWhereNull('expires_at');
            });
    }
    
    public function subscriptions(): HasMany
    {
        return $this->hasMany(\Laravel\Cashier\Cashier::$subscriptionModel);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()
            ->active()
            ->first();
    }

    public function product()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return collect();
        }

        $stripeProductId = $subscription->items->first()?->stripe_product;

        if (! $stripeProductId) {
            return collect();
        }

        return \App\Models\Product::where(
            'prod_id',
            $stripeProductId
        )->first();
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        $this->save();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function demoFolders(): HasMany
    {
        return $this->hasMany(DemoFolder::class, 'user_id', 'id');
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    public function ownsTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->where('owner_id', $this->id)->exists();
    }

    public function hasTeamRole(Team $team, string $role): bool
    {
        return $this->teams()
            ->where('teams.id', $team->id)
            ->whereHas('members', function ($query) use ($role) {
                $query->where('user_id', $this->id)->where('role', $role);
            })->exists();
    }
}
