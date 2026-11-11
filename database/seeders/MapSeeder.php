<?php

// database/seeders/MapSeeder.php

namespace Database\Seeders;

use App\Models\Map;
use Illuminate\Database\Seeder;

class MapSeeder extends Seeder
{
    public function run(): void
    {
        $maps = [
            [
                'name' => 'de_mirage',
                'display_name' => 'Mirage',
                'image_path' => '/images/demo/de_mirage.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_dust2',
                'display_name' => 'Dust II',
                'image_path' => '/images/demo/de_dust2.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_inferno',
                'display_name' => 'Inferno',
                'image_path' => '/images/demo/de_inferno.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_overpass',
                'display_name' => 'Overpass',
                'image_path' => '/images/demo/de_overpass.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_vertigo',
                'display_name' => 'Vertigo',
                'image_path' => '/images/demo/de_vertigo.svg',
                'workshop_id' => null,
                'active' => false,
            ],
            [
                'name' => 'de_ancient',
                'display_name' => 'Ancient',
                'image_path' => '/images/demo/de_ancient.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_anubis',
                'display_name' => 'Anubis',
                'image_path' => '/images/demo/de_anubis.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_nuke',
                'display_name' => 'Nuke',
                'image_path' => '/images/demo/de_nuke.svg',
                'workshop_id' => null,
                'active' => true,
            ],
            [
                'name' => 'de_train',
                'display_name' => 'Train',
                'image_path' => '/images/demo/de_train.svg',
                'workshop_id' => null,
                'active' => false,
            ],
            [
                'name' => 'de_cache',
                'display_name' => 'Cache',
                'image_path' => '/images/demo/de_cache.svg',
                'workshop_id' => null,
                'active' => true,
            ],
        ];

        foreach ($maps as $map) {
            Map::updateOrCreate(
                ['name' => $map['name']],
                $map
            );
        }
    }
}
