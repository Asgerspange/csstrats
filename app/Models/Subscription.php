<?php

namespace App\Models;

use DateTime;

/**
 * @property-read \Laravel\Cashier\SubscriptionItem|null $item
 * @property DateTime $ends_at
 * @property DateTime $updated_at
 */
class Subscription extends \Laravel\Cashier\Subscription
{
    public function item()
    {
        return $this->items->first();
    }
}
