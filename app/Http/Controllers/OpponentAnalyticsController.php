<?php

namespace App\Http\Controllers;

use App\Models\DemoHeatmap;
use App\Models\DemoRound;
use App\Models\DemoStat;
use App\Models\OpponentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpponentAnalyticsController extends Controller
{
    /**
     * Return fully aggregated analytics for an opponent team.
     * Pulls from: demo_stats, demo_heatmaps, demo_rounds (meta only — no frame_data)
     *
     * GET /api/opponent-teams/{id}/analytics
     */
    public function analytics(Request $request, int $id): JsonResponse
    {
        $opponent = OpponentTeam::visibleTo($request->user())->findOrFail($id);
        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();

        if (empty($demoIds)) {
            return new JsonResponse(['error' => 'No demos linked'], 422);
        }

        $stats = DemoStat::whereIn('demo_id', $demoIds)->get();
        $heatmaps = DemoHeatmap::whereIn('demo_id', $demoIds)->get();
        $rounds = DemoRound::whereIn('demo_id', $demoIds)
            ->where('round_index', '>', 0)
            ->select('demo_id', 'round_index', 'meta')
            ->get();

        $players = $opponent->players()->get()->keyBy('steamid');

        return new JsonResponse([
            'player_stats' => $this->aggregatePlayerStats($stats, $players, $demoIds),
            'kill_heatmap' => $this->aggregateHeatmaps($heatmaps, $players, 'kill_data'),
            'death_heatmap' => $this->aggregateHeatmaps($heatmaps, $players, 'death_data'),
            'round_outcomes' => $this->aggregateRoundOutcomes($rounds, $players),
            'weapon_usage' => $this->aggregateWeaponUsage($rounds, $players),
            'grenade_patterns' => $this->aggregateGrenadePatterns($rounds, $players),
            'first_kill_timing' => $this->aggregateFirstKillTiming($rounds, $players),
            'map' => $opponent->demos()->pluck('map')->filter()->unique()->values(),
            'total_demos' => count($demoIds),
            'total_rounds' => $rounds->count(),
        ]);
    }

    // ─── Aggregators ──────────────────────────────────────────────────────────

    private function aggregatePlayerStats($statsRows, $players, array $demoIds): array
    {
        $byPlayer = [];

        foreach ($statsRows as $stat) {
            $allStats = $stat->stats ?? [];
            $allKda = $stat->KDA ?? [];
            $allKast = $stat->kast ?? [];
            $allRating = $stat->rating ?? [];
            $allHits = $stat->hitgroups ?? [];

            foreach ($allStats as $row) {
                if (($row['side'] ?? '') !== 'all') {
                    continue;
                }
                $sid = (string) ($row['steamid'] ?? '');
                if (! $sid || ! isset($players[$sid])) {
                    continue;
                }

                if (! isset($byPlayer[$sid])) {
                    $byPlayer[$sid] = [
                        'steamid' => $sid,
                        'name' => $players[$sid]->alias ?? $players[$sid]->name,
                        'demos' => 0,
                        'totals' => [],
                    ];
                }

                $byPlayer[$sid]['demos']++;

                $numericKeys = [
                    'dmg', 'adr', 'utility_damage', 'enemies_flashed', 'mvps',
                    'headshot_kills', '3k_rounds', '4k_rounds', '5k_rounds',
                    '1v1', '1v2', '1v3', '1v4', '1v5', 'n_rounds',
                ];
                foreach ($numericKeys as $k) {
                    if (isset($row[$k])) {
                        $byPlayer[$sid]['totals'][$k] = ($byPlayer[$sid]['totals'][$k] ?? 0) + $row[$k];
                    }
                }

                // KDA
                $kda = collect($allKda)->first(fn ($p) => (string) ($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($kda) {
                    foreach (['kills', 'deaths', 'assists'] as $k) {
                        $byPlayer[$sid]['totals'][$k] = ($byPlayer[$sid]['totals'][$k] ?? 0) + ($kda[$k] ?? 0);
                    }
                }

                // KAST
                $kastRow = collect($allKast)->first(fn ($p) => (string) ($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($kastRow) {
                    $byPlayer[$sid]['totals']['kast_sum'] = ($byPlayer[$sid]['totals']['kast_sum'] ?? 0) + ($kastRow['kast'] ?? 0);
                }

                // Rating
                $ratingRow = collect($allRating)->first(fn ($p) => (string) ($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($ratingRow) {
                    $byPlayer[$sid]['totals']['rating_sum'] = ($byPlayer[$sid]['totals']['rating_sum'] ?? 0) + ($ratingRow['rating'] ?? 0);
                }

                // Hitgroups
                $hitRow = collect($allHits)->first(fn ($p) => (string) ($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($hitRow) {
                    foreach (['headshots', 'chest', 'stomach', 'left_arm', 'right_arm', 'left_leg', 'right_leg'] as $k) {
                        $byPlayer[$sid]['totals']['hg_'.$k] = ($byPlayer[$sid]['totals']['hg_'.$k] ?? 0) + ($hitRow[$k] ?? 0);
                    }
                }
            }
        }

        $result = [];
        foreach ($byPlayer as $sid => $data) {
            $n = max(1, $data['demos']);
            $t = $data['totals'];
            $kills = $t['kills'] ?? 0;
            $deaths = max(1, $t['deaths'] ?? 1);
            $hsKills = $t['headshot_kills'] ?? 0;

            $result[] = [
                'steamid' => $sid,
                'name' => $data['name'],
                'demos' => $data['demos'],
                'avg_rating' => round(($t['rating_sum'] ?? 0) / $n, 2),
                'avg_kast' => round(($t['kast_sum'] ?? 0) / $n, 1),
                'avg_adr' => round(($t['adr'] ?? 0) / $n, 1),
                'avg_kills' => round($kills / $n, 1),
                'avg_deaths' => round($deaths / $n, 1),
                'kd_ratio' => round($kills / $deaths, 2),
                'hs_percent' => $kills > 0 ? round($hsKills / $kills * 100, 1) : 0,
                'avg_utility_dmg' => round(($t['utility_damage'] ?? 0) / $n, 1),
                'avg_flashed' => round(($t['enemies_flashed'] ?? 0) / $n, 1),
                'avg_mvps' => round(($t['mvps'] ?? 0) / $n, 1),
                'multi_kills' => [
                    '3k' => round(($t['3k_rounds'] ?? 0) / $n, 2),
                    '4k' => round(($t['4k_rounds'] ?? 0) / $n, 2),
                    '5k' => round(($t['5k_rounds'] ?? 0) / $n, 2),
                ],
                'clutches' => [
                    '1v1' => $t['1v1'] ?? 0,
                    '1v2' => $t['1v2'] ?? 0,
                    '1v3' => $t['1v3'] ?? 0,
                    '1v4' => $t['1v4'] ?? 0,
                    '1v5' => $t['1v5'] ?? 0,
                ],
                'hitgroups' => [
                    'head' => $t['hg_headshots'] ?? 0,
                    'chest' => $t['hg_chest'] ?? 0,
                    'stomach' => $t['hg_stomach'] ?? 0,
                    'arms' => ($t['hg_left_arm'] ?? 0) + ($t['hg_right_arm'] ?? 0),
                    'legs' => ($t['hg_left_leg'] ?? 0) + ($t['hg_right_leg'] ?? 0),
                ],
            ];
        }

        usort($result, fn ($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);

        return $result;
    }

    /**
     * Aggregate position_dominance across all linked demos for this opponent.
     * Returns per-player, per-side zone time data.
     *
     * GET /api/opponent-teams/{id}/position-dominance
     */
    public function positionDominance(Request $request, int $id): JsonResponse
    {
        $opponent = OpponentTeam::visibleTo($request->user())->findOrFail($id);
        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();
        $players = $opponent->players()->get()->keyBy('steamid');

        if (empty($demoIds)) {
            return new JsonResponse(['dominance' => [], 'map' => null]);
        }

        $stats = DemoStat::whereIn('demo_id', $demoIds)
            ->whereNotNull('position_dominance')
            ->with('demo:id,map')
            ->get();

        if ($stats->isEmpty()) {
            return new JsonResponse([
                'dominance' => [],
                'map' => null,
                'maps' => [],
                'message' => 'No position data yet — demos are still being processed',
            ]);
        }

        // Aggregate ticks per player per side per zone across all demos
        // steamid → side → zone_id → { zone_name, zone_type, color, ticks }
        $byPlayer = [];
        $mapName = null;

        foreach ($stats as $stat) {
            $mapName = $mapName ?? $stat->demo?->map;
            $dominance = $stat->position_dominance ?? [];

            foreach ($dominance as $steamid => $sides) {
                $steamid = (string) $steamid;
                if (! isset($players[$steamid])) {
                    continue;
                }

                if (! isset($byPlayer[$steamid])) {
                    $byPlayer[$steamid] = [
                        'steamid' => $steamid,
                        'name' => $players[$steamid]->alias ?? $players[$steamid]->name,
                        'T' => [],
                        'CT' => [],
                    ];
                }

                foreach (['T', 'CT'] as $side) {
                    foreach ($sides[$side] ?? [] as $zoneData) {
                        $zid = $zoneData['zone_id'];

                        if (! isset($byPlayer[$steamid][$side][$zid])) {
                            $byPlayer[$steamid][$side][$zid] = [
                                'zone_id' => $zid,
                                'zone_name' => $zoneData['zone_name'],
                                'zone_type' => $zoneData['zone_type'],
                                'color' => $zoneData['color'],
                                'ticks' => 0,
                            ];
                        }

                        $byPlayer[$steamid][$side][$zid]['ticks'] += $zoneData['ticks'];
                    }
                }
            }
        }

        // Convert zone maps to sorted arrays with percentages
        $result = [];
        foreach ($byPlayer as $steamid => $data) {
            $row = ['steamid' => $steamid, 'name' => $data['name']];

            foreach (['T', 'CT'] as $side) {
                $zones = array_values($data[$side]);
                $totalTicks = max(1, array_sum(array_column($zones, 'ticks')));

                usort($zones, fn ($a, $b) => $b['ticks'] <=> $a['ticks']);

                $row[$side] = array_map(fn ($z) => [
                    ...$z,
                    'pct' => round($z['ticks'] / $totalTicks * 100, 1),
                ], $zones);
            }

            $result[] = $row;
        }

        // Sort by total ticks descending (most active players first)
        usort($result, function ($a, $b) {
            $ticksA = array_sum(array_column($a['T'] ?? [], 'ticks'))
                    + array_sum(array_column($a['CT'] ?? [], 'ticks'));
            $ticksB = array_sum(array_column($b['T'] ?? [], 'ticks'))
                    + array_sum(array_column($b['CT'] ?? [], 'ticks'));

            return $ticksB <=> $ticksA;
        });

        return new JsonResponse([
            'dominance' => $result,
            'map' => $mapName,
            'maps' => array_values(array_unique(
                $stats->map(fn ($s) => $s->demo?->map)->filter()->toArray()
            )),
            'total_demos' => $stats->count(),
        ]);
    }

    private function aggregateHeatmaps($heatmaps, $players, string $field): array
    {
        $merged = [];
        foreach ($heatmaps as $heatmap) {
            $data = $heatmap->{$field} ?? [];
            foreach ($data as $steamid => $roundData) {
                if (! isset($players[(string) $steamid])) {
                    continue;
                }

                if (! isset($merged[$steamid])) {
                    $merged[$steamid] = [
                        'steamid' => (string) $steamid,
                        'name' => $players[$steamid]->alias ?? $players[$steamid]->name,
                        'points' => [],
                    ];
                }

                foreach ($roundData as $round => $points) {
                    foreach ($points as $point) {
                        $merged[$steamid]['points'][] = [
                            'x' => $point['x'] ?? 0,
                            'y' => $point['y'] ?? 0,
                            'value' => $point['value'] ?? 1,
                        ];
                    }
                }
            }
        }

        return array_values($merged);
    }

    private function aggregateRoundOutcomes($rounds, $players): array
    {
        $tWins = 0;
        $ctWins = 0;
        $total = 0;
        $playerRoundKills = [];

        foreach ($rounds as $round) {
            $meta = $round->meta;
            if (! isset($meta['winner'])) {
                continue;
            }

            $total++;
            $winner = strtolower($meta['winner']);
            if ($winner === 't') {
                $tWins++;
            }
            if ($winner === 'ct') {
                $ctWins++;
            }

            // Track kills per player per round from alive_players
            foreach ($meta['alive_players'] ?? [] as $sid => $info) {
                if (! isset($players[(string) $sid])) {
                    continue;
                }
                $sid = (string) $sid;
                $playerRoundKills[$sid][] = $info['kills'] ?? 0;
            }
            foreach ($meta['dead_players'] ?? [] as $sid => $info) {
                if (! isset($players[(string) $sid])) {
                    continue;
                }
                $sid = (string) $sid;
                $playerRoundKills[$sid][] = $info['kills'] ?? 0;
            }
        }

        // Impact fraggers — avg kills per round
        $impact = [];
        foreach ($playerRoundKills as $sid => $killsArr) {
            $impact[] = [
                'steamid' => $sid,
                'name' => $players[$sid]->alias ?? $players[$sid]->name,
                'avg_round_kills' => round(array_sum($killsArr) / max(1, count($killsArr)), 2),
                'rounds' => count($killsArr),
            ];
        }
        usort($impact, fn ($a, $b) => $b['avg_round_kills'] <=> $a['avg_round_kills']);

        return [
            't_wins' => $tWins,
            'ct_wins' => $ctWins,
            'total_rounds' => $total,
            't_win_rate' => $total > 0 ? round($tWins / $total * 100, 1) : 0,
            'ct_win_rate' => $total > 0 ? round($ctWins / $total * 100, 1) : 0,
            'impact_players' => array_slice($impact, 0, 5),
        ];
    }

    private function aggregateWeaponUsage($rounds, $players): array
    {
        $weaponKills = [];

        foreach ($rounds as $round) {
            $kills = $round->meta['kills'] ?? [];
            foreach ($kills as $tick => $killList) {
                foreach ($killList as $kill) {
                    $sid = (string) ($kill['attacker_steamid'] ?? '');
                    if (! $sid || ! isset($players[$sid])) {
                        continue;
                    }

                    $weapon = $kill['weapon'] ?? 'unknown';
                    // Normalize
                    $weapon = str_replace(['weapon_', 'knife_'], ['', 'knife'], $weapon);

                    if (! isset($weaponKills[$sid])) {
                        $weaponKills[$sid] = [
                            'steamid' => $sid,
                            'name' => $players[$sid]->alias ?? $players[$sid]->name,
                            'weapons' => [],
                        ];
                    }
                    $weaponKills[$sid]['weapons'][$weapon] = ($weaponKills[$sid]['weapons'][$weapon] ?? 0) + 1;
                }
            }
        }

        // Sort each player's weapons and keep top 5
        foreach ($weaponKills as &$player) {
            arsort($player['weapons']);
            $player['weapons'] = array_slice($player['weapons'], 0, 5, true);
            $player['top_weapon'] = array_key_first($player['weapons']) ?? 'unknown';
        }

        return array_values($weaponKills);
    }

    private function aggregateGrenadePatterns($rounds, $players): array
    {
        $counts = [];

        foreach ($rounds as $round) {
            $kills = $round->meta['kills'] ?? [];
            foreach ($kills as $tick => $killList) {
                foreach ($killList as $kill) {
                    $sid = (string) ($kill['attacker_steamid'] ?? '');
                    if (! $sid || ! isset($players[$sid])) {
                        continue;
                    }

                    $hs = (bool) ($kill['headshot'] ?? false);
                    $smoke = (bool) ($kill['thrusmoke'] ?? false);
                    $blind = (bool) ($kill['attackerblind'] ?? false);
                    $noscope = (bool) ($kill['noscope'] ?? false);

                    if (! isset($counts[$sid])) {
                        $counts[$sid] = [
                            'steamid' => $sid,
                            'name' => $players[$sid]->alias ?? $players[$sid]->name,
                            'total_kills' => 0,
                            'headshots' => 0,
                            'thru_smoke' => 0,
                            'blind_kills' => 0,
                            'noscopes' => 0,
                        ];
                    }

                    $counts[$sid]['total_kills']++;
                    if ($hs) {
                        $counts[$sid]['headshots']++;
                    }
                    if ($smoke) {
                        $counts[$sid]['thru_smoke']++;
                    }
                    if ($blind) {
                        $counts[$sid]['blind_kills']++;
                    }
                    if ($noscope) {
                        $counts[$sid]['noscopes']++;
                    }
                }
            }
        }

        return array_values($counts);
    }

    private function aggregateFirstKillTiming($rounds, $players): array
    {
        $timings = [];

        foreach ($rounds as $round) {
            $meta = $round->meta;
            $freezeEnd = (int) ($meta['freeze_end'] ?? 0);
            if (! $freezeEnd) {
                continue;
            }

            $kills = $meta['kills'] ?? [];
            // kills keyed by tick, find smallest tick
            if (empty($kills)) {
                continue;
            }

            $firstTick = min(array_map('intval', array_keys($kills)));
            $secondsAfterFreeze = ($firstTick - $freezeEnd) / 64; // 64 tick

            foreach ($kills[$firstTick] ?? [] as $kill) {
                $sid = (string) ($kill['attacker_steamid'] ?? '');
                if (! $sid || ! isset($players[$sid])) {
                    continue;
                }

                $timings[$sid][] = max(0, $secondsAfterFreeze);
            }
        }

        $result = [];
        foreach ($timings as $sid => $times) {
            $result[] = [
                'steamid' => $sid,
                'name' => $players[$sid]->alias ?? $players[$sid]->name,
                'avg_first_kill_seconds' => round(array_sum($times) / count($times), 1),
                'samples' => count($times),
            ];
        }

        usort($result, fn ($a, $b) => $a['avg_first_kill_seconds'] <=> $b['avg_first_kill_seconds']);

        return $result;
    }
}
