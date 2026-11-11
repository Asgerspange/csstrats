<?php

namespace App\Http\Controllers;

use App\Models\DemoRound;
use App\Models\OpponentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentMovementController extends Controller
{
    /**
     * Return sampled X/Y movement paths for all opponent players across linked demos.
     *
     * GET /api/opponent-teams/{id}/movement
     *
     * Frame data is gzipped JSON — we decompress, extract player positions for
     * opponent steamids only, sample every Nth tick, and return lightweight paths.
     *
     * Query params:
     *   ?sample=8        — keep every Nth tick (default 8, lower = more detail)
     *   ?rounds=1,3,5    — optional comma-separated round indexes to limit scope
     */
    public function movement(Request $request, int $id): JsonResponse
    {
        $opponent = OpponentTeam::visibleTo($request->user())->findOrFail($id);

        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();
        $opponentIds = $opponent->players()->pluck('steamid')->map(fn ($s) => (string) $s)->toArray();
        $sample = max(1, (int) $request->query('sample', 8));
        $roundFilter = $request->query('rounds')
            ? array_map('intval', explode(',', $request->query('rounds')))
            : [];

        if (empty($demoIds) || empty($opponentIds)) {
            return new JsonResponse(['paths' => [], 'maps' => []]);
        }

        $query = DemoRound::whereIn('demo_id', $demoIds)
            ->where('round_index', '>', 0)
            ->select('id', 'demo_id', 'round_index', 'frame_data');

        if (! empty($roundFilter)) {
            $query->whereIn('round_index', $roundFilter);
        }

        // Limit to avoid memory issues — max 50 rounds across all demos
        $rounds = $query->limit(50)->get();

        // Map name per demo
        $demoMaps = \App\Models\Demo::whereIn('id', $demoIds)->pluck('map', 'id')->toArray();

        $paths = [];   // steamid → [ {x, y, round, demo_id}[] ]
        $mapsFound = [];

        foreach ($rounds as $round) {
            $map = $demoMaps[$round->demo_id] ?? 'unknown';
            $mapsFound[$map] = true;

            // Decompress frame_data
            try {
                $json = gzdecode($round->frame_data);
                if (! $json) {
                    continue;
                }
                $data = json_decode($json, true);
                if (! $data || ! isset($data['ticks'])) {
                    continue;
                }
            } catch (\Exception) {
                continue;
            }

            $ticks = $data['ticks'];
            $tickKeys = array_keys($ticks);
            sort($tickKeys);

            // Sample every Nth tick
            $sampled = array_filter($tickKeys, fn ($_, $i) => $i % $sample === 0, ARRAY_FILTER_USE_BOTH);

            foreach ($sampled as $tick) {
                $tickData = $ticks[$tick] ?? [];
                $players = $tickData['players'] ?? [];

                foreach ($players as $player) {
                    $sid = (string) ($player['steamid'] ?? '');
                    if (! in_array($sid, $opponentIds, true)) {
                        continue;
                    }

                    $x = $player['X'] ?? null;
                    $y = $player['Y'] ?? null;
                    if ($x === null || $y === null) {
                        continue;
                    }
                    if (($player['health'] ?? 0) <= 0) {
                        continue;
                    } // skip dead

                    $paths[$sid][] = [
                        'x' => round((float) $x, 1),
                        'y' => round((float) $y, 1),
                        'round' => $round->round_index,
                        'demo_id' => $round->demo_id,
                        'tick' => (int) $tick,
                    ];
                }
            }
        }

        // Build per-player response
        $result = [];
        foreach ($paths as $sid => $points) {
            $playerModel = $opponent->players()->where('steamid', $sid)->first();
            $result[] = [
                'steamid' => $sid,
                'name' => $playerModel?->alias ?? $playerModel?->name ?? 'Unknown',
                'points' => $points,
                // Also provide heatmap-compatible flat points for simpleheat
                'heatmap_points' => array_map(fn ($p) => [
                    'x' => $p['x'],
                    'y' => $p['y'],
                    'value' => 1,
                ], $points),
            ];
        }

        return new JsonResponse([
            'paths' => $result,
            'maps' => array_keys($mapsFound),
            'total_points' => array_sum(array_map(fn ($p) => count($p['points']), $result)),
        ]);
    }

    /**
     * Return grenade throw origins + landing positions for opponent players.
     *
     * GET /api/opponent-teams/{id}/grenades
     */
    public function grenades(Request $request, int $id): JsonResponse
    {
        $opponent = OpponentTeam::visibleTo($request->user())->findOrFail($id);

        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();
        $opponentIds = $opponent->players()->pluck('steamid')->map(fn ($s) => (string) $s)->toArray();

        if (empty($demoIds) || empty($opponentIds)) {
            return new JsonResponse(['grenades' => []]);
        }

        $rounds = DemoRound::whereIn('demo_id', $demoIds)
            ->where('round_index', '>', 0)
            ->select('id', 'demo_id', 'round_index', 'frame_data')
            ->limit(80)
            ->get();

        $demoMaps = \App\Models\Demo::whereIn('id', $demoIds)->pluck('map', 'id')->toArray();

        // grenade_type → [ {thrower, x, y, round, map} ]
        $byType = [];

        foreach ($rounds as $round) {
            $map = $demoMaps[$round->demo_id] ?? 'unknown';

            try {
                $json = gzdecode($round->frame_data);
                if (! $json) {
                    continue;
                }
                $data = json_decode($json, true);
            } catch (\Exception) {
                continue;
            }

            // Grenades are in ticks as tick_data.grenades[]
            foreach ($data['ticks'] ?? [] as $tick => $tickData) {
                foreach ($tickData['grenades'] ?? [] as $grenade) {
                    $sid = (string) ($grenade['thrower_steamid'] ?? '');
                    if (! in_array($sid, $opponentIds, true)) {
                        continue;
                    }

                    $type = $grenade['grenade_type'] ?? 'unknown';
                    $x = $grenade['X'] ?? null;
                    $y = $grenade['Y'] ?? null;
                    if ($x === null || $y === null) {
                        continue;
                    }

                    $byType[$type][] = [
                        'steamid' => $sid,
                        'x' => round((float) $x, 1),
                        'y' => round((float) $y, 1),
                        'round' => $round->round_index,
                        'map' => $map,
                    ];
                }
            }

            // Also pull smokes/infernos from the smokes[] and infernos[] arrays
            foreach (['smokes', 'infernos'] as $utilType) {
                foreach ($data[$utilType] ?? [] as $util) {
                    $sid = (string) ($util['thrower_steamid'] ?? '');
                    if (! in_array($sid, $opponentIds, true)) {
                        continue;
                    }

                    $x = $util['X'] ?? null;
                    $y = $util['Y'] ?? null;
                    if ($x === null || $y === null) {
                        continue;
                    }

                    $byType[$utilType][] = [
                        'steamid' => $sid,
                        'x' => round((float) $x, 1),
                        'y' => round((float) $y, 1),
                        'round' => $round->round_index,
                        'map' => $map,
                    ];
                }
            }
        }

        return new JsonResponse([
            'grenades' => $byType,
            'types' => array_keys($byType),
            'total_demos' => count($demoIds),
        ]);
    }
}
