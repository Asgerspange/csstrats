<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    const CREATED_AT = null;

    protected $fillable = [
        'uuid',
        'name',
        'prod_ids',
        'coupon',
        'ends_at',
    ];
    public $casts = [
        'prod_ids' => 'array',
    ];

    public function product()
    {
        return Product::whereIn('id', $this->prod_ids)->get();
    }
}
