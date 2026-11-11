<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrantedAccess extends Model
{
    protected $connection = 'mysql';

    protected $table = 'granted_access';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'granted_by',
        'granted_at',
        'expires_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeGranted($query)
    {
        return $query->where('expires_at', '>', now())
                     ->orWhereNull('expires_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by', 'id');
    }

    public function isGranted()
    {
        return !$this->expires_at || $this->expires_at > now();
    }
}
