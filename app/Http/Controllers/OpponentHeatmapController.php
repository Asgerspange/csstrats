<?php

namespace App\Http\Controllers;

use App\Models\DemoHeatmap;
use App\Models\OpponentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentHeatmapController extends Controller
{
    /**
     * GET /api/opponent-teams/{id}/heatmap?map=de_ancient
     *
     * Aggregates kill_data and death_data from all linked demos,
     * filtered to the opponent team's player steamids.
     * Returns the same structure as demo_heatmaps: { kill_data, death_data }
     * keyed by steamid → round → [{x, y, value}]
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $opponent = OpponentTeam::visibleTo($request->user())
            ->with('players', 'demos')
            ->findOrFail($id);

        $mapFilter = $request->query('map');
        $opponentSids = $opponent->players->pluck('steamid')->map(fn ($s) => (string) $s)->toArray();

        if (empty($opponentSids)) {
            return new JsonResponse(['kill_data' => [], 'death_data' => [], 'total_demos' => 0]);
        }

        // Get heatmaps for linked demos, optionally filtered by map
        $demoIds = $opponent->demos()
            ->when($mapFilter, fn ($q) => $q->where('map', $mapFilter))
            ->pluck('demos.id')
            ->toArray();

        if (empty($demoIds)) {
            return new JsonResponse(['kill_data' => [], 'death_data' => [], 'total_demos' => 0]);
        }

        $heatmaps = DemoHeatmap::whereIn('demo_id', $demoIds)->get();

        // Merge all heatmap data, keeping only opponent steamids
        $mergedKills = [];
        $mergedDeaths = [];

        foreach ($heatmaps as $heatmap) {
            $killData = is_string($heatmap->kill_data) ? json_decode($heatmap->kill_data, true) : ($heatmap->kill_data ?? []);
            $deathData = is_string($heatmap->death_data) ? json_decode($heatmap->death_data, true) : ($heatmap->death_data ?? []);

            foreach ($opponentSids as $sid) {
                // Kills
                if (isset($killData[$sid])) {
                    if (! isset($mergedKills[$sid])) {
                        $mergedKills[$sid] = [];
                    }
                    foreach ($killData[$sid] as $round => $points) {
                        // Use unique round key across demos to avoid collision
                        $key = $heatmap->demo_id.'_'.$round;
                        $mergedKills[$sid][$key] = $points;
                    }
                }
                // Deaths
                if (isset($deathData[$sid])) {
                    if (! isset($mergedDeaths[$sid])) {
                        $mergedDeaths[$sid] = [];
                    }
                    foreach ($deathData[$sid] as $round => $points) {
                        $key = $heatmap->demo_id.'_'.$round;
                        $mergedDeaths[$sid][$key] = $points;
                    }
                }
            }
        }

        // Also aggregate position_dominance from demo_stats for the Positions mode
        // Format: [ { steamid, name, CT: [{zone_id, zone_name, ticks}], T: [...] } ]
        $statRows = \App\Models\DemoStat::whereIn('demo_id', $demoIds)->get();
        $players = $opponent->players->keyBy('steamid');
        $byPlayer = [];

        foreach ($statRows as $stat) {
            $dominance = $stat->position_dominance ?? [];
            if (empty($dominance)) {
                continue;
            }

            foreach ($dominance as $steamid => $sides) {
                $steamid = (string) $steamid;
                if (! isset($players[$steamid])) {
                    continue;
                }

                if (! isset($byPlayer[$steamid])) {
                    $byPlayer[$steamid] = [
                        'steamid' => $steamid,
                        'name' => $players[$steamid]->alias ?? $players[$steamid]->name,
                        'CT' => [],
                        'T' => [],
                    ];
                }

                foreach (['T', 'CT'] as $side) {
                    foreach ($sides[$side] ?? [] as $z) {
                        $zid = $z['zone_id'];
                        if (! isset($byPlayer[$steamid][$side][$zid])) {
                            $byPlayer[$steamid][$side][$zid] = $z;
                            $byPlayer[$steamid][$side][$zid]['ticks'] = 0;
                        }
                        $byPlayer[$steamid][$side][$zid]['ticks'] += $z['ticks'];
                    }
                }
            }
        }

        // Convert to arrays
        $positionDominance = array_values(array_map(function ($data) {
            return [
                'steamid' => $data['steamid'],
                'name' => $data['name'],
                'CT' => array_values($data['CT']),
                'T' => array_values($data['T']),
            ];
        }, $byPlayer));

        return new JsonResponse([
            'kill_data' => $mergedKills,
            'death_data' => $mergedDeaths,
            'position_dominance' => $positionDominance,
            'total_demos' => count($demoIds),
        ]);
    }

    /**
     * GET /api/opponent-teams/{id}/spatial?map=de_ancient&type=paths
     *
     * Returns decoded spatial data for one type, aggregated across linked demos.
     * type: paths | utility | shots | plants | entries
     */
    public function spatial(Request $request, int $id): JsonResponse
    {
        $opponent = OpponentTeam::visibleTo($request->user())
            ->with('players', 'demos')
            ->findOrFail($id);

        $mapFilter = $request->query('map');
        $type = $request->query('type', 'paths');
        $opponentSids = $opponent->players->pluck('steamid')->map(fn ($s) => (string) $s)->toArray();

        $demoIds = $opponent->demos()
            ->when($mapFilter, fn ($q) => $q->where('map', $mapFilter))
            ->pluck('demos.id')
            ->toArray();

        if (empty($demoIds)) {
            return new JsonResponse(['data' => [], 'total_demos' => 0]);
        }

        $rows = \App\Models\DemoSpatial::whereIn('demo_id', $demoIds)
            ->where('type', $type)
            ->get();

        // Merge type-specific data across demos, filtered to opponent steamids
        $merged = $this->mergeSpatialByType($type, $rows, $opponentSids);

        return new JsonResponse([
            'data' => $merged,
            'total_demos' => count($demoIds),
        ]);
    }

    private function mergeSpatialByType(string $type, $rows, array $sids): mixed
    {
        $sidSet = array_flip($sids); // O(1) lookup

        if ($type === 'paths') {
            // { steamid: { "demoId_round": [{x,y,s}] } }
            // Keep demo+round as opaque key — frontend just draws all lines, no round filtering
            $merged = [];
            foreach ($rows as $row) {
                $demoId = $row->demo_id;
                $data = $row->getDecodedData();
                foreach ($data as $sid => $rounds) {
                    if (! isset($sidSet[(string) $sid])) {
                        continue;
                    }
                    if (! isset($merged[$sid])) {
                        $merged[$sid] = [];
                    }
                    foreach ($rounds as $rn => $pts) {
                        $merged[$sid]["{$demoId}_{$rn}"] = $pts;
                    }
                }
            }

            return $merged;
        }

        if ($type === 'utility') {
            // { smoke: [...], molotov: [...], flash: [...], he: [...], decoy: [...] }
            $merged = ['smoke' => [], 'molotov' => [], 'flash' => [], 'he' => [], 'decoy' => []];
            foreach ($rows as $row) {
                $data = $row->getDecodedData();
                foreach ($merged as $gtype => $_) {
                    foreach ($data[$gtype] ?? [] as $pt) {
                        if (! isset($sidSet[(string) ($pt['sid'] ?? '')])) {
                            continue;
                        }
                        $merged[$gtype][] = $pt;
                    }
                }
            }

            return $merged;
        }

        if ($type === 'shots') {
            // { steamid: [{x,y,w,r}] }
            $merged = [];
            foreach ($rows as $row) {
                $data = $row->getDecodedData();
                foreach ($data as $sid => $pts) {
                    if (! isset($sidSet[(string) $sid])) {
                        continue;
                    }
                    if (! isset($merged[$sid])) {
                        $merged[$sid] = [];
                    }
                    array_push($merged[$sid], ...$pts);
                }
            }

            return $merged;
        }

        if ($type === 'plants' || $type === 'entries') {
            // flat array of [{x,y,r,sid}]
            $merged = [];
            foreach ($rows as $row) {
                $data = $row->getDecodedData();
                if ($type === 'entries') {
                    // entries: { steamid: [{x,y,s,r}] }
                    foreach ($data as $sid => $pts) {
                        if (! isset($sidSet[(string) $sid])) {
                            continue;
                        }
                        array_push($merged, ...array_map(fn ($p) => array_merge($p, ['sid' => $sid]), $pts));
                    }
                } else {
                    foreach ($data as $pt) {
                        if (! isset($sidSet[(string) ($pt['sid'] ?? '')])) {
                            continue;
                        }
                        $merged[] = $pt;
                    }
                }
            }

            return $merged;
        }

        return [];
    }
}
