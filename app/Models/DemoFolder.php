<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo,
    BelongsToMany,
    HasMany
};

class DemoFolder extends Model
{
    protected $connection = 'mysql';
    protected $table = 'demo_folders';

    protected $fillable = [
        'name',
        'user_id',
        'parent_id',
        'is_root',
        'color',
    ];

    protected $appends = ['children_number'];

    public function demos(): BelongsToMany
    {
        return $this->belongsToMany(Demo::class, 'demo_folder_has_demo', 'demo_folder_id', 'demo_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DemoFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DemoFolder::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getChildrenNumberAttribute()
    {
        return $this->countChildrenAndDemos();
    }

    protected function countChildrenAndDemos(): int
    {
        $count = $this->demos()->count();
        $children = $this->children()->with('children', 'demos')->get();

        foreach ($children as $child) {
            $count += 1;

            $count += $child->countChildrenAndDemos();
        }

        return $count;
    }

    public function sharedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_demos', 'demo_id', 'team_id');
    }
}
