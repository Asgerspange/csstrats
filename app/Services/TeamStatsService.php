<?php

namespace App\Services;

use App\Models\{Team, Demo, DemoStat, DemoRound, MapMetadata};
use Illuminate\Support\Collection;

class TeamStatsService
{
    private function activeDutyMaps(): array
    {
        return MapMetadata::where('is_active_duty', 1)
            ->pluck('name')
            ->map(fn($n) => (string) $n)
            ->toArray();
    }

    public function overview(Team $team, int $matchLimit = 10): array
    {
        $demos = $this->teamDemos($team)->take($matchLimit)->values();
        $allDemos = $this->teamDemos($team);
        $memberSteamIds = $this->memberSteamIds($team);

        return [
            'recent_matches'    => $demos->map(fn($d) => $this->summarizeMatch($d, $memberSteamIds))->values(),
            'form_trend'        => $this->formTrend($allDemos->take(20), $memberSteamIds),
            'headline'          => $this->headlineMetrics($allDemos, $memberSteamIds),
            'top_maps'          => $this->topMaps($allDemos, $memberSteamIds, 4),
            'round_economy'     => $this->roundEconomy($allDemos, $memberSteamIds),
            'pattern_surfacer'  => $this->patternSurfacer($allDemos, $memberSteamIds),
            'total_demos'       => $allDemos->count(),
        ];
    }

    public function matches(Team $team, int $limit = 50): array
    {
        $demos = $this->teamDemos($team)->take($limit)->values();
        $memberSteamIds = $this->memberSteamIds($team);

        return [
            'matches' => $demos->map(fn($d) => $this->fullMatchDetail($d, $memberSteamIds))->values(),
            'total'   => $this->teamDemos($team)->count(),
        ];
    }

    public function maps(Team $team): array
    {
        $demos = $this->teamDemos($team);
        $memberSteamIds = $this->memberSteamIds($team);

        $byMap = [];
        foreach ($demos as $demo) {
            $mapKey = $demo->map ?: 'unknown';
            $summary = $this->summarizeMatch($demo, $memberSteamIds);
            if (!isset($byMap[$mapKey])) {
                $byMap[$mapKey] = [
                    'map'              => $mapKey,
                    'played'           => 0,
                    'rounds_played'    => 0,
                    'rounds_won'       => 0,
                    't_rounds_played'  => 0,
                    't_rounds_won'     => 0,
                    'ct_rounds_played' => 0,
                    'ct_rounds_won'    => 0,
                    'last_played'      => null,
                    'recent_form'      => [],
                ];
            }
            $byMap[$mapKey]['played']++;
            $byMap[$mapKey]['rounds_played']    += $summary['rounds_played'];
            $byMap[$mapKey]['rounds_won']       += $summary['our_rounds_won'];
            $byMap[$mapKey]['t_rounds_played']  += $summary['t_rounds_played'];
            $byMap[$mapKey]['t_rounds_won']     += $summary['t_rounds_won'];
            $byMap[$mapKey]['ct_rounds_played'] += $summary['ct_rounds_played'];
            $byMap[$mapKey]['ct_rounds_won']    += $summary['ct_rounds_won'];
            if (!$byMap[$mapKey]['last_played'] || $demo->created_at > $byMap[$mapKey]['last_played']) {
                $byMap[$mapKey]['last_played'] = $demo->created_at;
            }
            $byMap[$mapKey]['recent_form'][] = $summary['result'];
        }

        foreach ($byMap as &$row) {
            $row['win_rate']    = $row['rounds_played']    > 0 ? round($row['rounds_won']    / $row['rounds_played']    * 100, 1) : null;
            $row['t_win_rate']  = $row['t_rounds_played']  > 0 ? round($row['t_rounds_won']  / $row['t_rounds_played']  * 100, 1) : null;
            $row['ct_win_rate'] = $row['ct_rounds_played'] > 0 ? round($row['ct_rounds_won'] / $row['ct_rounds_played'] * 100, 1) : null;
            $row['recent_form'] = array_slice($row['recent_form'], 0, 8);
            $row['last_played'] = $row['last_played'] ? $row['last_played']->toIso8601String() : null;
        }
        unset($row);

        $activeDutyKeys = array_flip($this->activeDutyMaps());
        $activeDuty = array_intersect_key($byMap, $activeDutyKeys);
        $others = array_diff_key($byMap, $activeDutyKeys);

        $sorter = function ($a, $b) {
            return $b['played'] <=> $a['played'];
        };
        uasort($activeDuty, $sorter);
        uasort($others, $sorter);

        return [
            'active_duty' => array_values($activeDuty),
            'other_maps'  => array_values($others),
        ];
    }

    public function roster(Team $team): array
    {
        $demos = $this->teamDemos($team);

        $memberRows = $team->users()->with('userInfo')->get();
        $members = $memberRows
            ->filter(fn($u) => $u->userInfo?->steam_id)
            ->keyBy(fn($u) => (string) $u->userInfo->steam_id);
        $unlinkedMembers = $memberRows->filter(fn($u) => !$u->userInfo?->steam_id);

        // Batch-load all roles for this team once (avoids N+1 in roleForUser)
        $roleMap = $team->roles()
            ->whereIn('user_id', $memberRows->pluck('id'))
            ->pluck('role', 'user_id')
            ->toArray();

        $statsRows = DemoStat::whereIn('demo_id', $demos->pluck('id'))->get();

        $byPlayer = [];
        foreach ($statsRows as $stat) {
            $allStats   = $stat->stats     ?? [];
            $allKda     = $stat->KDA       ?? [];
            $allKast    = $stat->kast      ?? [];
            $allRating  = $stat->rating    ?? [];
            $allHits    = $stat->hitgroups ?? [];

            foreach ($allStats as $row) {
                if (($row['side'] ?? '') !== 'all') continue;
                $sid = (string) ($row['steamid'] ?? '');
                if (!$sid || !$members->has($sid)) continue;

                if (!isset($byPlayer[$sid])) {
                    $member = $members[$sid];
                    $byPlayer[$sid] = [
                        'steamid'         => $sid,
                        'user_id'         => $member->id,
                        'name'            => $member->name,
                        'profile_picture' => $member->profile_picture ?? null,
                        'role'            => $roleMap[$member->id] ?? 'Member',
                        'demos'           => 0,
                        '_acc'            => [],
                    ];
                }
                $byPlayer[$sid]['demos']++;

                $numeric = ['dmg','adr','utility_damage','enemies_flashed','mvps','headshot_kills',
                            '3k_rounds','4k_rounds','5k_rounds','1v1','1v2','1v3','1v4','1v5','n_rounds'];
                foreach ($numeric as $k) {
                    if (isset($row[$k])) {
                        $byPlayer[$sid]['_acc'][$k] = ($byPlayer[$sid]['_acc'][$k] ?? 0) + $row[$k];
                    }
                }

                $kda = $this->firstMatch($allKda, $sid, 'all');
                if ($kda) {
                    foreach (['kills','deaths','assists'] as $k) {
                        $byPlayer[$sid]['_acc'][$k] = ($byPlayer[$sid]['_acc'][$k] ?? 0) + ($kda[$k] ?? 0);
                    }
                }

                $kastRow = $this->firstMatch($allKast, $sid, 'all');
                if ($kastRow) {
                    $byPlayer[$sid]['_acc']['kast_sum']    = ($byPlayer[$sid]['_acc']['kast_sum']    ?? 0) + ($kastRow['kast'] ?? 0);
                }

                $ratingRow = $this->firstMatch($allRating, $sid, 'all');
                if ($ratingRow) {
                    $byPlayer[$sid]['_acc']['rating_sum']  = ($byPlayer[$sid]['_acc']['rating_sum']  ?? 0) + ($ratingRow['rating'] ?? 0);
                }

                $hitRow = $this->firstMatch($allHits, $sid, 'all');
                if ($hitRow) {
                    foreach (['headshots','chest','stomach','left_arm','right_arm','left_leg','right_leg'] as $k) {
                        $byPlayer[$sid]['_acc']['hg_' . $k] = ($byPlayer[$sid]['_acc']['hg_' . $k] ?? 0) + ($hitRow[$k] ?? 0);
                    }
                }
            }
        }

        $players = [];
        $totalRating = 0; $totalAdr = 0; $totalKast = 0; $playerCount = 0;
        foreach ($byPlayer as $sid => $p) {
            $n = max(1, $p['demos']);
            $a = $p['_acc'];
            $kills  = $a['kills']  ?? 0;
            $deaths = max(1, $a['deaths'] ?? 1);
            $rating = round(($a['rating_sum'] ?? 0) / $n, 2);
            $kast   = round(($a['kast_sum']   ?? 0) / $n, 1);
            $adr    = round(($a['adr']        ?? 0) / $n, 1);
            $hs     = $kills > 0 ? round(($a['headshot_kills'] ?? 0) / $kills * 100, 1) : 0;

            $players[] = [
                'steamid'         => $sid,
                'user_id'         => $p['user_id'],
                'name'            => $p['name'],
                'profile_picture' => $p['profile_picture'],
                'role'            => $p['role'],
                'steam_linked'    => true,
                'demos'           => $p['demos'],
                'rating'          => $rating,
                'kast'            => $kast,
                'adr'             => $adr,
                'kd'              => round($kills / $deaths, 2),
                'avg_kills'       => round($kills  / $n, 1),
                'avg_deaths'      => round(($a['deaths']  ?? 0) / $n, 1),
                'avg_assists'     => round(($a['assists'] ?? 0) / $n, 1),
                'hs_pct'          => $hs,
                'utility_dmg'     => round(($a['utility_damage'] ?? 0) / $n, 1),
                'enemies_flashed' => round(($a['enemies_flashed'] ?? 0) / $n, 1),
                'multi_kills'     => [
                    '3k' => $a['3k_rounds'] ?? 0,
                    '4k' => $a['4k_rounds'] ?? 0,
                    '5k' => $a['5k_rounds'] ?? 0,
                ],
                'clutches'        => [
                    '1v1' => $a['1v1'] ?? 0,
                    '1v2' => $a['1v2'] ?? 0,
                    '1v3' => $a['1v3'] ?? 0,
                ],
            ];
            $totalRating += $rating;
            $totalAdr    += $adr;
            $totalKast   += $kast;
            $playerCount++;
        }

        usort($players, fn($a, $b) => $b['rating'] <=> $a['rating']);

        $baseline = [
            'rating' => $playerCount > 0 ? round($totalRating / $playerCount, 2) : 1.0,
            'adr'    => $playerCount > 0 ? round($totalAdr    / $playerCount, 1) : 0,
            'kast'   => $playerCount > 0 ? round($totalKast   / $playerCount, 1) : 0,
        ];

        // Include linked team members even if they have no demo data yet
        foreach ($members as $sid => $member) {
            if (!isset($byPlayer[$sid])) {
                $players[] = $this->emptyPlayerRow((string) $sid, $member, $roleMap[$member->id] ?? 'Member');
            }
        }

        // Include members who haven't linked Steam at all (no steamid)
        foreach ($unlinkedMembers as $member) {
            $players[] = $this->emptyPlayerRow('', $member, $roleMap[$member->id] ?? 'Member');
        }

        return [
            'players'      => $players,
            'baseline'     => $baseline,
            'demo_count'   => $demos->count(),
        ];
    }

    private function emptyPlayerRow(string $steamid, $member, string $role): array
    {
        return [
            'steamid'         => $steamid,
            'user_id'         => $member->id,
            'name'            => $member->name,
            'profile_picture' => $member->profile_picture ?? null,
            'role'            => $role,
            'steam_linked'    => $steamid !== '',
            'demos'           => 0,
            'rating'          => null,
            'kast'            => null,
            'adr'             => null,
            'kd'              => null,
            'avg_kills'       => null,
            'avg_deaths'      => null,
            'avg_assists'     => null,
            'hs_pct'          => null,
            'utility_dmg'     => null,
            'enemies_flashed' => null,
            'multi_kills'     => null,
            'clutches'        => null,
        ];
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function teamDemos(Team $team): Collection
    {
        $memberSteamIds = $this->memberSteamIds($team);

        $demos = $team->demos()
            ->with(['rounds:demo_id,round_index,meta', 'stats:demo_id,stats,KDA,kast,rating,hitgroups,team1,team2'])
            ->where('hide', 0)
            ->orderByDesc('demos.created_at')
            ->get();

        return $demos->filter(function ($demo) use ($memberSteamIds) {
            $lastRound = $demo->rounds->sortByDesc('round_index')->first();
            if (!$lastRound || !$lastRound->meta) return false;
            $participants = array_merge(
                array_map('strval', $lastRound->meta['winners'] ?? []),
                array_map('strval', $lastRound->meta['losers']  ?? []),
            );
            $matched = array_intersect($participants, $memberSteamIds);
            return count($matched) >= 2;
        })->values();
    }

    private function memberSteamIds(Team $team): array
    {
        return $team->users()->with('userInfo')->get()
            ->map(fn($u) => $u->userInfo?->steam_id)
            ->filter()
            ->map(fn($s) => (string) $s)
            ->values()
            ->toArray();
    }

    private function roleForUser(Team $team, int $userId): string
    {
        $role = $team->roles()->where('user_id', $userId)->first();
        return $role?->role ?? 'Member';
    }

    private function firstMatch(array $rows, string $steamid, string $side = 'all'): ?array
    {
        foreach ($rows as $row) {
            if ((string)($row['steamid'] ?? '') === $steamid && ($row['side'] ?? '') === $side) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Identify which side ("T", "CT", or null) our team occupied per round.
     * Uses the parser-populated winners/losers steamid arrays in meta.
     * Our side = the side (winning or losing) holding the majority of our member steamids.
     */
    private function roundSideForTeam(array $meta, array $memberSteamIds): ?string
    {
        $winner = strtoupper($meta['winner'] ?? '');
        if ($winner !== 'T' && $winner !== 'CT') return null;

        $winners = array_map('strval', $meta['winners'] ?? []);
        $losers  = array_map('strval', $meta['losers']  ?? []);

        $ourOnWinners = count(array_intersect($winners, $memberSteamIds));
        $ourOnLosers  = count(array_intersect($losers,  $memberSteamIds));

        if ($ourOnWinners === 0 && $ourOnLosers === 0) return null;

        if ($ourOnWinners >= $ourOnLosers) {
            return $winner;
        }
        return $winner === 'T' ? 'CT' : 'T';
    }

    /**
     * Compute summary for one demo from the team's perspective.
     * Falls back gracefully when side mapping isn't resolvable.
     */
    private function summarizeMatch(Demo $demo, array $memberSteamIds): array
    {
        $rounds = $demo->rounds;
        $tWins = 0; $ctWins = 0;
        $ourRoundsWon = 0; $ourRoundsPlayed = 0;
        $tRoundsPlayed = 0; $tRoundsWon = 0;
        $ctRoundsPlayed = 0; $ctRoundsWon = 0;
        $resultStrip = [];

        foreach ($rounds as $round) {
            $meta = $round->meta ?? [];
            $winner = strtoupper($meta['winner'] ?? '');
            if ($winner === 'T')  { $tWins++;  }
            if ($winner === 'CT') { $ctWins++; }

            $ourSide = $this->roundSideForTeam($meta, $memberSteamIds);
            if ($ourSide === 'T') {
                $tRoundsPlayed++;
                if ($winner === 'T') { $tRoundsWon++; $ourRoundsWon++; }
                $ourRoundsPlayed++;
                $resultStrip[] = $winner === 'T' ? 'W' : 'L';
            } elseif ($ourSide === 'CT') {
                $ctRoundsPlayed++;
                if ($winner === 'CT') { $ctRoundsWon++; $ourRoundsWon++; }
                $ourRoundsPlayed++;
                $resultStrip[] = $winner === 'CT' ? 'W' : 'L';
            } else {
                $resultStrip[] = '-';
            }
        }

        $result = '-';
        if ($ourRoundsPlayed > 0) {
            $oppRounds = $ourRoundsPlayed - $ourRoundsWon + ($tWins + $ctWins - $ourRoundsPlayed);
            $oppWon = ($tWins + $ctWins) - $ourRoundsWon;
            if ($ourRoundsWon > $oppWon) $result = 'W';
            elseif ($ourRoundsWon < $oppWon) $result = 'L';
            else $result = 'T';
        }

        return [
            'demo_id'          => $demo->id,
            'map'              => $demo->map,
            'date'             => $demo->created_at?->toIso8601String(),
            'name'             => $demo->name,
            'rounds_played'    => $tWins + $ctWins,
            't_wins'           => $tWins,
            'ct_wins'          => $ctWins,
            'our_rounds_won'   => $ourRoundsWon,
            'our_rounds_played' => $ourRoundsPlayed,
            't_rounds_played'  => $tRoundsPlayed,
            't_rounds_won'     => $tRoundsWon,
            'ct_rounds_played' => $ctRoundsPlayed,
            'ct_rounds_won'    => $ctRoundsWon,
            'result'           => $result,
            'result_strip'     => $resultStrip,
        ];
    }

    private function fullMatchDetail(Demo $demo, array $memberSteamIds): array
    {
        $summary = $this->summarizeMatch($demo, $memberSteamIds);

        // Add per-round outcome from team's perspective for the result tape
        $tape = [];
        foreach ($demo->rounds as $round) {
            $meta = $round->meta ?? [];
            $winner = strtoupper($meta['winner'] ?? '');
            $ourSide = $this->roundSideForTeam($meta, $memberSteamIds);
            $outcome = '-';
            if ($ourSide && $winner) {
                $outcome = $ourSide === $winner ? 'W' : 'L';
            }
            $tape[] = [
                'round'   => $round->round_index,
                'winner'  => $winner,
                'our_side' => $ourSide,
                'outcome' => $outcome,
                'win_reason' => $meta['win_reason'] ?? null,
            ];
        }

        $summary['round_tape'] = $tape;
        return $summary;
    }

    private function formTrend(Collection $demos, array $memberSteamIds): array
    {
        // chronological oldest → newest for sparkline left-to-right
        return $demos->reverse()->map(function ($d) use ($memberSteamIds) {
            $s = $this->summarizeMatch($d, $memberSteamIds);
            return [
                'date'         => $s['date'],
                'map'          => $s['map'],
                'rounds_won'   => $s['our_rounds_won'],
                'rounds_played'=> $s['our_rounds_played'],
                'win_rate'     => $s['our_rounds_played'] > 0 ? round($s['our_rounds_won'] / $s['our_rounds_played'] * 100, 1) : null,
                'result'       => $s['result'],
            ];
        })->values()->toArray();
    }

    private function headlineMetrics(Collection $demos, array $memberSteamIds): array
    {
        $totalRoundsPlayed = 0; $totalRoundsWon = 0;
        $totalTPlayed = 0; $totalTWon = 0;
        $totalCtPlayed = 0; $totalCtWon = 0;
        $wins = 0; $losses = 0; $ties = 0;

        foreach ($demos as $demo) {
            $s = $this->summarizeMatch($demo, $memberSteamIds);
            $totalRoundsPlayed += $s['our_rounds_played'];
            $totalRoundsWon    += $s['our_rounds_won'];
            $totalTPlayed      += $s['t_rounds_played'];
            $totalTWon         += $s['t_rounds_won'];
            $totalCtPlayed     += $s['ct_rounds_played'];
            $totalCtWon        += $s['ct_rounds_won'];
            if ($s['result'] === 'W') $wins++;
            elseif ($s['result'] === 'L') $losses++;
            elseif ($s['result'] === 'T') $ties++;
        }

        return [
            'matches_total'  => $wins + $losses + $ties,
            'matches_won'    => $wins,
            'matches_lost'   => $losses,
            'matches_tied'   => $ties,
            'match_win_rate' => ($wins + $losses) > 0 ? round($wins / ($wins + $losses) * 100, 1) : null,
            'round_win_rate' => $totalRoundsPlayed > 0 ? round($totalRoundsWon / $totalRoundsPlayed * 100, 1) : null,
            't_win_rate'     => $totalTPlayed  > 0 ? round($totalTWon  / $totalTPlayed  * 100, 1) : null,
            'ct_win_rate'    => $totalCtPlayed > 0 ? round($totalCtWon / $totalCtPlayed * 100, 1) : null,
            'rounds_played'  => $totalRoundsPlayed,
        ];
    }

    private function topMaps(Collection $demos, array $memberSteamIds, int $limit): array
    {
        $aggregate = [];
        foreach ($demos as $demo) {
            $mapKey = $demo->map ?: 'unknown';
            $s = $this->summarizeMatch($demo, $memberSteamIds);
            if (!isset($aggregate[$mapKey])) {
                $aggregate[$mapKey] = [
                    'map'           => $mapKey,
                    'played'        => 0,
                    'rounds_played' => 0,
                    'rounds_won'    => 0,
                ];
            }
            $aggregate[$mapKey]['played']++;
            $aggregate[$mapKey]['rounds_played'] += $s['our_rounds_played'];
            $aggregate[$mapKey]['rounds_won']    += $s['our_rounds_won'];
        }

        foreach ($aggregate as &$row) {
            $row['win_rate'] = $row['rounds_played'] > 0 ? round($row['rounds_won'] / $row['rounds_played'] * 100, 1) : null;
        }
        unset($row);

        usort($aggregate, fn($a, $b) => $b['played'] <=> $a['played']);
        return array_slice($aggregate, 0, $limit);
    }

    private function roundEconomy(Collection $demos, array $memberSteamIds): array
    {
        $buckets = [
            'pistol'   => ['played' => 0, 'won' => 0],
            'anti_eco' => ['played' => 0, 'won' => 0],
            'force'    => ['played' => 0, 'won' => 0],
            'full_buy' => ['played' => 0, 'won' => 0],
        ];

        foreach ($demos as $demo) {
            foreach ($demo->rounds as $round) {
                $meta = $round->meta ?? [];
                if (!isset($meta['winner'])) continue;
                $winner  = strtoupper($meta['winner']);
                $ourSide = $this->roundSideForTeam($meta, $memberSteamIds);
                if (!$ourSide) continue;

                $isWin = $winner === $ourSide;
                $idx   = (int) $round->round_index;

                // pistol rounds are 1 (T half start) and 13 (CT half start in MR12)
                $isPistol = $idx === 1 || $idx === 13;

                $ourBalance = $ourSide === 'T' ? ($meta['t_balance'] ?? null) : ($meta['ct_balance'] ?? null);
                $oppBalance = $ourSide === 'T' ? ($meta['ct_balance'] ?? null) : ($meta['t_balance'] ?? null);

                if ($isPistol) {
                    $buckets['pistol']['played']++;
                    if ($isWin) $buckets['pistol']['won']++;
                    continue;
                }

                if (is_numeric($ourBalance) && is_numeric($oppBalance)) {
                    $ratio = $oppBalance > 0 ? $ourBalance / max(1, $oppBalance) : 1;
                    if ($oppBalance < 5000 && $ourBalance > 15000) {
                        $buckets['anti_eco']['played']++;
                        if ($isWin) $buckets['anti_eco']['won']++;
                    } elseif ($ourBalance < 15000) {
                        $buckets['force']['played']++;
                        if ($isWin) $buckets['force']['won']++;
                    } else {
                        $buckets['full_buy']['played']++;
                        if ($isWin) $buckets['full_buy']['won']++;
                    }
                }
            }
        }

        $out = [];
        foreach ($buckets as $key => $b) {
            $out[$key] = [
                'played'   => $b['played'],
                'won'      => $b['won'],
                'win_rate' => $b['played'] > 0 ? round($b['won'] / $b['played'] * 100, 1) : null,
            ];
        }
        return $out;
    }

    private function patternSurfacer(Collection $demos, array $memberSteamIds): array
    {
        $totalRounds = 0;
        $openingKills = 0;
        $openingDuelsContested = 0;
        $multiKillRounds = 0;
        $clutchWins = 0;

        foreach ($demos as $demo) {
            foreach ($demo->rounds as $round) {
                $meta = $round->meta ?? [];
                $totalRounds++;

                $kills = $meta['kills'] ?? [];
                if (empty($kills)) continue;

                // First kill of the round (smallest tick), regardless of timing
                $firstTick = min(array_map('intval', array_keys($kills)));
                $firstKills = $kills[$firstTick] ?? [];
                if (empty($firstKills)) continue;

                $openingDuelsContested++;
                foreach ($firstKills as $kill) {
                    if (in_array((string)($kill['attacker_steamid'] ?? ''), $memberSteamIds, true)) {
                        $openingKills++;
                        break;
                    }
                }
            }
        }

        $statsRows = DemoStat::whereIn('demo_id', $demos->pluck('id'))->get();
        foreach ($statsRows as $stat) {
            foreach ($stat->stats ?? [] as $row) {
                if (($row['side'] ?? '') !== 'all') continue;
                if (!in_array((string)($row['steamid'] ?? ''), $memberSteamIds, true)) continue;
                $multiKillRounds += ($row['3k_rounds'] ?? 0) + ($row['4k_rounds'] ?? 0) + ($row['5k_rounds'] ?? 0);
                foreach (['1v1','1v2','1v3','1v4','1v5'] as $c) {
                    $clutchWins += $row[$c] ?? 0;
                }
            }
        }

        return [
            'opening_kill_rate' => $openingDuelsContested > 0
                ? round($openingKills / $openingDuelsContested * 100, 1)
                : null,
            'opening_duels'     => $openingDuelsContested,
            'multi_kill_rounds' => $multiKillRounds,
            'clutch_wins'       => $clutchWins,
            'rounds_analyzed'   => $totalRounds,
        ];
    }
}
