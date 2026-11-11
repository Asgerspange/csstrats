<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    protected $table = 'team_invitations';

    protected $fillable = [
        'team_id',
        'email',
        'invited_by',
        'user_id',
        'role',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'account_created',
    ];

    protected $casts = [
        'expires_at'      => 'datetime',
        'accepted_at'     => 'datetime',
        'account_created' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPending(): bool
    {
        if ($this->status !== 'pending') return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
