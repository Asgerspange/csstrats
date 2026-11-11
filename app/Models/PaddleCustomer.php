<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaddleCustomer extends Model
{
    protected $table = 'paddle_customers';

    protected $fillable = [
        'billable_id',
        'billable_type',
        'paddle_id',
        'name',
        'email',
    ];
}