<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property float $price
 * @property float $percent
 * @property float $monthly_price
 * @property string $stripe_price_id
 * @property string $prod_id
 * @property string $description
 * @property string $small_description
 * @property array $features
 * @property int $max_members
 * @property int $max_stratbooks
 * @property int $max_teams
 * @property string|null $banner
 * @property bool $hide
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $connection = 'mysql';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'percent',
        'monthly_price',
        'stripe_price_id',
        'prod_id',
        'description',
        'small_description',
        'features',
        'max_members',
        'max_stratbooks',
        'max_teams',
        'banner',
        'hide'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'percent' => 'float',
        'max_members' => 'integer',
        'max_stratbooks' => 'integer',
        'max_teams' => 'integer',
        'hide' => 'boolean',
        'features' => 'json',
    ];

    /**
     * Check if the product is a custom package
     * 
     * @return bool
     */
    public function isCustomPackage()
    {
        return $this->hide === true;
    }

    /**
     * Get all visible (non-custom) packages
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getVisiblePackages()
    {
        return self::where('hide', 0)->get();
    }

    /**
     * Get all custom packages
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getCustomPackages()
    {
        return self::where('hide', 1)->get();
    }
}