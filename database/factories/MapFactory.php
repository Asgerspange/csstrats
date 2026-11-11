<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MapFactory extends Factory
{
    public function definition(): array
    {
        $name = 'de_' . fake()->word();

        return [
            'name' => $name,
            'display_name' => ucfirst(str_replace('de_', '', $name)),
            'slug' => null,
            'image_path' => '/images/demo/' . $name . '.svg',
            'width' => 1024,
            'height' => 1024,
            'active' => true,
        ];
    }
}
