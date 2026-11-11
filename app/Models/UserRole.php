<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'users_roles';

    protected $fillable = [
        'id',
        'team_id',
        'user_id',
        'role'
    ];

    public $timestamps = false;
}
