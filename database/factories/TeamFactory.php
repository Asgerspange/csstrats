<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owned_by' => \App\Models\User::factory(),
            'entitlement_mode' => 'legacy',
            'map_lock_expires_at' => null,
        ];
    }
}
