<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property mixed $session_id
 * @property mixed $total_price
 * @property string $stripe_payment_intent_id
 * @property string $status
 * @property DateTime $created_at
 * @property DateTime $updated_at
 */
class Order extends Model
{
    use HasFactory;
}
