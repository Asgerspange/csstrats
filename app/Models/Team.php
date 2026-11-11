<?php

namespace App\Models;

use App\Events;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'teams';

    protected $fillable = [
        'name',
        'user_ids',
        'owned_by',
        'edit_users',
        'invite_token',
        'admin_users',
        'settings',
        'entitlement_mode',
        'map_lock_expires_at',
    ];

    protected $casts = [
        'user_ids' => 'array',
        'edit_users' => 'array',
        'admin_users' => 'array',
        'settings' => 'array',
        'map_lock_expires_at' => 'datetime',
    ];

    public function logo(): HasOne
    {
        return $this->hasOne(Image::class, 'name', 'id')->where('image_group', 'team');
    }

    public function stratbooks(): HasMany
    {
        return $this->hasMany(Stratbook::class, 'team_id', 'id');
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            TeamMember::class,
            'team_id',     // Foreign key on team_members table
            'id',          // Foreign key on users table
            'id',          // Local key on teams table
            'user_id'      // Local key on team_members table
        );
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'owned_by');
    }

    protected $dispatchesEvents = [
        'deleting' => Events\TeamDeleting::class,
        'deleted' => Events\TeamDeleted::class,
    ];

    public function getSetting($key)
    {
        return $this->settings[$key] ?? null;
    }

    public function setSetting($key, $value)
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        $this->save();
    }

    public function demoFolders(): BelongsToMany
    {
        return $this->belongsToMany(DemoFolder::class, 'team_demo_folders', 'team_id', 'demo_folder_id');
    }

    public function demos(): BelongsToMany
    {
        return $this->belongsToMany(Demo::class, 'team_demos', 'team_id', 'demo_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'team_id', 'id');
    }

    public function selectedMaps(): BelongsToMany
    {
        return $this->belongsToMany(Map::class, 'team_map_selection', 'team_id', 'map_id');
    }

    public function mapSelectionAudits(): HasMany
    {
        return $this->hasMany(MapSelectionAudit::class, 'team_id', 'id');
    }
}
