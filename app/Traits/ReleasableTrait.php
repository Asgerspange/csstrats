<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait ReleasableTrait
{
    /**
     * Boot the trait to add global scope
     */
    protected static function bootReleasableTrait()
    {
        static::addGlobalScope('released', function (Builder $builder) {
            $builder->where('is_released', true);
        });
    }

    /**
     * Scope to include unreleased items
     */
    public function scopeWithUnreleased(Builder $query)
    {
        return $query->withoutGlobalScope('released');
    }

    /**
     * Scope to get only unreleased items
     */
    public function scopeOnlyUnreleased(Builder $query)
    {
        return $query->withoutGlobalScope('released')->where('is_released', false);
    }

    /**
     * Scope to get only released items (explicit)
     */
    public function scopeOnlyReleased(Builder $query)
    {
        return $query->where('is_released', true);
    }

    /**
     * Mark as released
     */
    public function release()
    {
        $this->update(['is_released' => true]);
        return $this;
    }

    /**
     * Mark as unreleased
     */
    public function unrelease()
    {
        $this->update(['is_released' => false]);
        return $this;
    }

    /**
     * Check if item is released
     */
    public function isReleased()
    {
        return $this->is_released;
    }
}