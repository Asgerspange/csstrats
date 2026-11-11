<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    protected $table = 'users_info';

    protected $fillable = [
        'uuid',
        'steam_id',
        'country_code',
        'role',
        'last_online',
    ];

    public $timestamps = false;
}
