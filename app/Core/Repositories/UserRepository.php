<?php

namespace App\Core\Repositories;

use App\Models\TeamMember;
use App\Models\User;

class UserRepository
{
    public function getSubscribedUsers()
    {
        return User::whereHas('subscriptions')->get();
    }

    public function getTeamMembers()
    {
        return TeamMember::select('user_id')->distinct()->get();
    }

    public function totalUsers()
    {
        $startThisMonth = now()->startOfMonth();
        $endThisMonth = now()->endOfMonth();
        $startLastMonth = now()->subMonth()->startOfMonth();
        $endLastMonth = now()->subMonth()->endOfMonth();
        $users = User::all();

        $active = null;
        $usersThisMonth = $users->whereBetween('created_at', [$startThisMonth, $endThisMonth]);
        $usersLastMonth = $users->whereBetween('created_at', [$startLastMonth, $endLastMonth]);
        return [
            'total' => $users->count(),
            'active' => $active,
            'this_month' => $usersThisMonth->count(),
            'last_month' => $usersLastMonth->count(),
        ];
    }
}
