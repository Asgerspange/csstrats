<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaddleTransaction extends Model
{
    protected $table = 'paddle_transactions';

    protected $fillable = [
        'billable_id',
        'billable_type',
        'paddle_id',
        'paddle_subscription_id',
        'invoice_number',
        'status',
        'total',
        'tax',
        'currency',
        'billed_at',
    ];

    protected $casts = [
        'billed_at' => 'datetime',
    ];
}