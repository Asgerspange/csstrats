<?php

namespace Database\Seeders;

use App\Models\{
    ThrowPoint,
    Nade,
};
use Illuminate\Database\Seeder;

class NadeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tunnelMolly = Nade::create([
            'map_id' => 2,
            'type' => 'molotov',
            'side' => 't',
            'name' => 'B Tunnels Molotov',
            'description' => 'Clears close corner in tunnels',
            'landing_x' => 300,
            'landing_y' => 600,
            'active' => true,
        ]);

        ThrowPoint::create([
            'nade_id' => $tunnelMolly->id,
            'title' => 'Lower Tunnels to Corner',
            'description' => 'Easy molly from tunnels entrance',
            'throw_x' => 400,
            'throw_y' => 750,
            'video_url' => 'https://www.youtube.com/watch?v=example3',
            'technique' => 'runthrow',
            'additional_info' => 'Run forward and throw',
            'order' => 1,
            'active' => true,
        ]);

        // Example flashbang
        $aFlash = Nade::create([
            'map_id' => 2,
            'type' => 'flashbang',
            'side' => 'both',
            'name' => 'A Site Flash',
            'description' => 'Flashes players on site',
            'landing_x' => 650,
            'landing_y' => 400,
            'active' => true,
        ]);

        ThrowPoint::create([
            'nade_id' => $aFlash->id,
            'title' => 'A Long Corner Flash',
            'description' => 'Pop flash for teammates pushing',
            'throw_x' => 700,
            'throw_y' => 350,
            'video_url' => 'https://www.youtube.com/watch?v=example4',
            'technique' => 'jumpthrow',
            'additional_info' => 'Communicate with team before throwing',
            'order' => 1,
            'active' => true,
        ]);

        // Example HE grenade
        $bStackNade = Nade::create([
            'map_id' => 2,
            'type' => 'grenade',
            'side' => 't',
            'name' => 'B Site Stack Nade',
            'description' => 'Damages stacked CT players',
            'landing_x' => 250,
            'landing_y' => 500,
            'active' => true,
        ]);

        ThrowPoint::create([
            'nade_id' => $bStackNade->id,
            'title' => 'B Doors to Site',
            'description' => 'Standard stack nade',
            'throw_x' => 350,
            'throw_y' => 650,
            'video_url' => 'https://www.youtube.com/watch?v=example5',
            'technique' => 'normal',
            'additional_info' => 'Best used during execute',
            'order' => 1,
            'active' => true,
        ]);

        // Add a few Mirage examples
        $aStairsSmoke = Nade::create([
            'map_id' => 1,
            'type' => 'smoke',
            'side' => 't',
            'name' => 'A Stairs Smoke',
            'description' => 'Smokes stairs from T spawn',
            'landing_x' => 700,
            'landing_y' => 350,
            'active' => true,
        ]);

        ThrowPoint::create([
            'nade_id' => $aStairsSmoke->id,
            'title' => 'T Spawn to Stairs',
            'description' => 'Standard T-side smoke',
            'throw_x' => 300,
            'throw_y' => 700,
            'video_url' => 'https://www.youtube.com/watch?v=example6',
            'technique' => 'jumpthrow',
            'additional_info' => 'Line up with the tree',
            'order' => 1,
            'active' => true,
        ]);
    }
}
