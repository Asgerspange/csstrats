<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dust 2 Callout Seeder — 51 zones across 3 layers
 * de_dust2: pos_x=-2476, pos_y=3239, scale=4.4
 *
 * php artisan db:seed --class=Dust2CalloutSeeder
 */
class Dust2CalloutSeeder extends Seeder
{
    public function run(): void
    {
        $mapId = 2; // de_dust2

        // Wipe existing data for this map
        $zoneIds = DB::table('map_callout_zones')->where('map_id', $mapId)->pluck('id');
        DB::table('team_callout_names')->whereIn('zone_id', $zoneIds)->delete();
        DB::table('map_callout_zones')->where('map_id', $mapId)->delete();
        DB::table('callout_layers')->where('map_id', $mapId)->delete();

        // ── Create layers ─────────────────────────────────────────────────
        $layers = [
            'area'  => DB::table('callout_layers')->insertGetId([
                'map_id' => $mapId, 'name' => 'Base Areas',     'order' => 0,
                'color' => null, 'created_at' => now(), 'updated_at' => now(),
            ]),
            'zone'  => DB::table('callout_layers')->insertGetId([
                'map_id' => $mapId, 'name' => 'Sub-Areas',      'order' => 1,
                'color' => null, 'created_at' => now(), 'updated_at' => now(),
            ]),
            'point' => DB::table('callout_layers')->insertGetId([
                'map_id' => $mapId, 'name' => 'Point Callouts', 'order' => 2,
                'color' => null, 'created_at' => now(), 'updated_at' => now(),
            ]),
        ];

        // ── Zone data ─────────────────────────────────────────────────────
        $zones = [
            // T Spawn
            ['T Spawn', 'TSPAWN', 'area', '#ef4444', 0, json_encode([[-2344, -369], [-1464, -369], [-1464, -1205], [-2344, -1205]])],
            // CT Spawn
            ['CT Spawn', 'CTSPAWN', 'area', '#3b82f6', 1, json_encode([[428, 2975], [1704, 2975], [1704, 1875], [428, 1875]])],
            // Mid
            ['Mid', 'MID', 'area', '#8b5cf6', 2, json_encode([[-1244, 1479], [-12, 1479], [-12, 247], [-1244, 247]])],
            // A Site
            ['A Site', 'ASITE', 'area', '#f59e0b', 3, json_encode([[428, 2183], [1704, 2183], [1704, 951], [428, 951]])],
            // B Site
            ['B Site', 'BSITE', 'area', '#10b981', 4, json_encode([[-2212, 1435], [-980, 1435], [-980, 247], [-2212, 247]])],
            // Plat
            ['Plat', 'PLAT', 'zone', '#ef4444', 5, json_encode([[-2256, -105], [-1596, -105], [-1596, -457], [-2256, -457]])],
            // T Ramp
            ['T Ramp', 'TRAMP', 'zone', '#ef4444', 6, json_encode([[-2256, -457], [-1728, -457], [-1728, -941], [-2256, -941]])],
            // Outside Tunnels
            ['Outside Tunnels', 'OUTTUN', 'zone', '#34d399', 7, json_encode([[-2256, -941], [-1596, -941], [-1596, -1267], [-2256, -1267]])],
            // Outside Long
            ['Outside Long', 'OUTLONG', 'zone', '#f59e0b', 8, json_encode([[-1684, -105], [-408, -105], [-408, -721], [-1684, -721]])],
            // Long Doors
            ['Long Doors', 'LONGDOORS', 'zone', '#f97316', 9, json_encode([[-716, 555], [-12, 555], [-12, -105], [-716, -105]])],
            // Long A
            ['Long A', 'LONGA', 'zone', '#f97316', 10, json_encode([[-364, 1127], [516, 1127], [516, 379], [-364, 379]])],
            // A Ramp
            ['A Ramp', 'ARAMP', 'zone', '#fbbf24', 11, json_encode([[76, 1391], [560, 1391], [560, 775], [76, 775]])],
            // A Short
            ['A Short', 'ASHORT', 'zone', '#fbbf24', 12, json_encode([[-232, 2139], [516, 2139], [516, 1127], [-232, 1127]])],
            // Catwalk
            ['Catwalk', 'CAT', 'zone', '#a78bfa', 13, json_encode([[-716, 1655], [76, 1655], [76, 1127], [-716, 1127]])],
            // A Platform
            ['A Platform', 'APLAT', 'zone', '#d97706', 14, json_encode([[428, 2183], [1220, 2183], [1220, 1479], [428, 1479]])],
            // CT Box (A)
            ['CT Box (A)', 'CTBOX', 'zone', '#2563eb', 15, json_encode([[1088, 2183], [1704, 2183], [1704, 1479], [1088, 1479]])],
            // Goose
            ['Goose', 'GOOSE', 'zone', '#f59e0b', 16, json_encode([[692, 1655], [1352, 1655], [1352, 1083], [692, 1083]])],
            // Car (A)
            ['Car (A)', 'CAR_A', 'zone', '#fbbf24', 17, json_encode([[516, 1479], [868, 1479], [868, 1039], [516, 1039]])],
            // Pit
            ['Pit', 'PIT', 'zone', '#b45309', 18, json_encode([[252, 1303], [736, 1303], [736, 731], [252, 731]])],
            // Stairs
            ['Stairs', 'STAIRS', 'zone', '#fbbf24', 19, json_encode([[32, 1875], [384, 1875], [384, 1391], [32, 1391]])],
            // CT Spawn (A)
            ['CT Spawn (A)', 'CTA', 'zone', '#3b82f6', 20, json_encode([[1220, 2183], [1704, 2183], [1704, 1743], [1220, 1743]])],
            // Mid Doors
            ['Mid Doors', 'MIDDOORS', 'zone', '#8b5cf6', 21, json_encode([[-892, 1259], [-276, 1259], [-276, 687], [-892, 687]])],
            // Xbox
            ['Xbox', 'XBOX', 'zone', '#8b5cf6', 22, json_encode([[-804, 1215], [-452, 1215], [-452, 863], [-804, 863]])],
            // Suicide
            ['Suicide', 'SUICIDE', 'zone', '#94a3b8', 23, json_encode([[-1244, 1347], [-760, 1347], [-760, 907], [-1244, 907]])],
            // Right Side Mid
            ['Right Side Mid', 'RSIDEMID', 'zone', '#64748b', 24, json_encode([[-760, 863], [-364, 863], [-364, 511], [-760, 511]])],
            // Top Mid
            ['Top Mid', 'TOPMID', 'zone', '#7c3aed', 25, json_encode([[-1112, 1567], [-408, 1567], [-408, 1171], [-1112, 1171]])],
            // CT Mid
            ['CT Mid', 'CTMID', 'zone', '#6366f1', 26, json_encode([[-276, 1743], [472, 1743], [472, 1083], [-276, 1083]])],
            // Palm
            ['Palm', 'PALM', 'zone', '#a78bfa', 27, json_encode([[-760, 1655], [-452, 1655], [-452, 1391], [-760, 1391]])],
            // T Mid
            ['T Mid', 'TMID', 'zone', '#ec4899', 28, json_encode([[-1244, 1303], [-760, 1303], [-760, 731], [-1244, 731]])],
            // Lower Mid
            ['Lower Mid', 'LOWERMID', 'zone', '#8b5cf6', 29, json_encode([[-1112, 687], [-276, 687], [-276, 159], [-1112, 159]])],
            // Upper Tunnels
            ['Upper Tunnels', 'UPPTUN', 'zone', '#10b981', 30, json_encode([[-2212, 511], [-1068, 511], [-1068, -325], [-2212, -325]])],
            // Lower Tunnels
            ['Lower Tunnels', 'LOWTUN', 'zone', '#34d399', 31, json_encode([[-2300, -193], [-1596, -193], [-1596, -1029], [-2300, -1029]])],
            // B Tunnels
            ['B Tunnels', 'BTUN', 'zone', '#059669', 32, json_encode([[-1948, 951], [-1024, 951], [-1024, 379], [-1948, 379]])],
            // B Doors
            ['B Doors', 'BDOORS', 'zone', '#10b981', 33, json_encode([[-2212, 1435], [-1508, 1435], [-1508, 775], [-2212, 775]])],
            // B Window
            ['B Window', 'BWINDOW', 'zone', '#6ee7b7', 34, json_encode([[-1684, 1523], [-1024, 1523], [-1024, 1039], [-1684, 1039]])],
            // B Platform
            ['B Platform', 'BPLAT', 'zone', '#059669', 35, json_encode([[-2212, 1435], [-1420, 1435], [-1420, 687], [-2212, 687]])],
            // Outside Tunnels B
            ['Outside Tunnels B', 'OUTTUNB', 'zone', '#34d399', 36, json_encode([[-2300, -237], [-1728, -237], [-1728, -985], [-2300, -985]])],
            // Long Corner
            ['Long Corner', 'LONGCORNER', 'point', '#ef4444', 37, json_encode([[-518, 643], [-232, 643], [-232, 357], [-518, 357]])],
            // Sniper's Nest
            ['Sniper\'s Nest', 'SNEST', 'point', '#3b82f6', 38, json_encode([[736, 2051], [1088, 2051], [1088, 1655], [736, 1655]])],
            // Short Boost
            ['Short Boost', 'SBOOST', 'point', '#f97316', 39, json_encode([[-34, 1919], [274, 1919], [274, 1567], [-34, 1567]])],
            // Xbox Box
            ['Xbox Box', 'XBOXBOX', 'point', '#8b5cf6', 40, json_encode([[-782, 1193], [-496, 1193], [-496, 885], [-782, 885]])],
            // Suicide Window
            ['Suicide Window', 'SUIWIN', 'point', '#94a3b8', 41, json_encode([[-1178, 1347], [-870, 1347], [-870, 1083], [-1178, 1083]])],
            // B Car
            ['B Car', 'BCAR', 'point', '#10b981', 42, json_encode([[-2190, 1237], [-1794, 1237], [-1794, 863], [-2190, 863]])],
            // Dog
            ['Dog', 'DOG', 'point', '#6ee7b7', 43, json_encode([[-2212, 643], [-1904, 643], [-1904, 379], [-2212, 379]])],
            // Fence
            ['Fence', 'FENCE', 'point', '#6ee7b7', 44, json_encode([[-1904, 687], [-1596, 687], [-1596, 423], [-1904, 423]])],
            // Big Box (B)
            ['Big Box (B)', 'BIGBOX', 'point', '#10b981', 45, json_encode([[-2212, 1391], [-1772, 1391], [-1772, 1039], [-2212, 1039]])],
            // Barrels
            ['Barrels', 'BARRELS', 'point', '#f59e0b', 46, json_encode([[428, 1215], [736, 1215], [736, 907], [428, 907]])],
            // T Plat Box
            ['T Plat Box', 'TPLATBOX', 'point', '#ef4444', 47, json_encode([[-2256, -83], [-1838, -83], [-1838, -435], [-2256, -435]])],
            // Boost Spot (Long)
            ['Boost Spot (Long)', 'LONGBOOST', 'point', '#f97316', 48, json_encode([[-408, 599], [-144, 599], [-144, 335], [-408, 335]])],
            // Doors (A)
            ['Doors (A)', 'ADOORS', 'point', '#fbbf24', 49, json_encode([[428, 1655], [692, 1655], [692, 1347], [428, 1347]])],
        ];

        // ── Insert zones ──────────────────────────────────────────────────
        foreach ($zones as [$name, $short, $type, $color, $order, $polygon]) {
            DB::table('map_callout_zones')->insert([
                'map_id'         => $mapId,
                'layer_id'       => $layers[$type],
                'canonical_name' => $name,
                'short_code'     => $short,
                'zone_type'      => $type,
                'color'          => $color,
                'sort_order'     => $order,
                'polygon'        => $polygon,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $this->command->info(sprintf(
            'Dust 2: seeded %d zones (%d areas, %d sub-areas, %d points)',
            count($zones),
            count(array_filter($zones, fn($z) => $z[2] === 'area')),
            count(array_filter($zones, fn($z) => $z[2] === 'zone')),
            count(array_filter($zones, fn($z) => $z[2] === 'point'))
        ));
    }
}