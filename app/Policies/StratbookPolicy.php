<?php

namespace App\Policies;

use App\Models\Stratbook;
use App\Models\User;
use App\Models\Team;

class StratbookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Stratbook $stratbook): bool
    {
        if ($stratbook->team_id && $user->belongsToTeam(Team::findOrFail($stratbook->team_id))) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Stratbook $stratbook): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Stratbook $stratbook): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Stratbook $stratbook): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Stratbook $stratbook): bool
    {
        return false;
    }
}
