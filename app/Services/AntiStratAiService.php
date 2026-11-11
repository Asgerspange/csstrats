<?php

namespace App\Services;

use App\Models\{DemoStat, MapCalloutZone, OpponentTeam, OpponentTeamPlayer};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AntiStratAiService
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = config('services.odin.base_url', 'https://api.ordbogen.ai/v1');
        $this->apiKey  = config('services.odin.api_key');
        $this->model   = config('services.odin.model', 'odin-medium');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC METHODS
    // ─────────────────────────────────────────────────────────────────────────

    public function analyzeOpponent(
        OpponentTeam $opponent,
        Collection $statsRows,
        Collection $players
    ): array {
        $aggregated      = $this->aggregateStats($statsRows, $players);
        $positionSummary = $this->aggregatePositionDominance($statsRows, $players);

        if (empty($aggregated['players'])) {
            return ['error' => 'No stats available for analysis'];
        }

        $prompt = $this->buildAnalysisPrompt($opponent, $aggregated, $positionSummary);

        try {
            $response = Http::withToken($this->apiKey)
                ->baseUrl($this->baseUrl)
                ->timeout(300)
                ->post('/chat/completions', [
                    'model'           => $this->model,
                    'temperature'     => 0.25,
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('AntiStratAI: API failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return ['error' => 'AI service unavailable'];
            }

            $decoded = json_decode($response->json('choices.0.message.content'), true);
            if (!$decoded) return ['error' => 'Invalid AI response'];

            $mapsPlayed = $aggregated['maps_played'] ?? [];
            return $this->resolveZoneTokens($decoded, $mapsPlayed);

        } catch (\Exception $e) {
            Log::error('AntiStratAI: Exception', ['message' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Conversational chat about an opponent team.
     * Full context: stats, position dominance, AI insights, counter strats.
     */
    public function chat(
        OpponentTeam $opponent,
        Collection $statsRows,
        Collection $players,
        string $message,
        array $history = [],
    ): string {
        $aggregated      = $this->aggregateStats($statsRows, $players);
        $positionSummary = $this->aggregatePositionDominance($statsRows, $players);
        $positionBlock   = $this->buildPositionBlock($positionSummary);

        $counterStrats = $opponent->counterStrats->map(fn($s) => [
            'name'         => $s->name,
            'map'          => $s->map?->name,
            'side'         => $s->side,
            'description'  => $s->description,
            'ai_generated' => $s->ai_generated,
        ])->toArray();

        $statsJson    = json_encode($aggregated['players'] ?? [], JSON_PRETTY_PRINT);
        $insightsJson = json_encode($opponent->ai_insights ?? [], JSON_PRETTY_PRINT);
        $stratsJson   = json_encode($counterStrats, JSON_PRETTY_PRINT);
        $totalDemos   = $aggregated['total_demos'] ?? 0;

        $systemPrompt = <<<SYSTEM
You are an elite CS2 analyst embedded in the CsStrats platform.
You are answering questions about opponent team "{$opponent->name}".

You have full access to:
- Player stats across {$totalDemos} demos
- Position dominance data (where each player spends time on each map/side, freeze-time excluded)
- Existing AI insights and counter strats

POSITION DATA:
{$positionBlock}

PLAYER STATS (avg per demo):
{$statsJson}

EXISTING AI INSIGHTS:
{$insightsJson}

COUNTER STRATS:
{$stratsJson}

Rules:
- Be concise and tactical — max 3-4 sentences unless more detail is specifically needed
- Always cite specific numbers, percentages, or zone names when available
- If asked about something not in the data, say so clearly rather than guessing
- Use the player's actual names (aliases preferred)
- Do not invent stats or positions not present in the data
SYSTEM;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::withToken($this->apiKey)
                ->baseUrl($this->baseUrl)
                ->timeout(120)
                ->post('/chat/completions', [
                    'model'       => $this->model,
                    'temperature' => 0.4,
                    'messages'    => $messages,
                ]);

            if ($response->failed()) {
                return 'Sorry, I could not process that right now. Please try again.';
            }

            return trim($response->json('choices.0.message.content') ?? 'No response');

        } catch (\Exception $e) {
            Log::error('AntiStratAI chat exception', ['message' => $e->getMessage()]);
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Generic JSON completion — used by GenerateCounterStratsJob.
     */
    public function rawComplete(string $userPrompt): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->baseUrl($this->baseUrl)
                ->timeout(300)
                ->post('/chat/completions', [
                    'model'           => $this->model,
                    'temperature'     => 0.3,
                    'messages'        => [
                        ['role' => 'system', 'content' => 'You are an elite CS2 tactical analyst. Always respond with valid JSON only.'],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                return ['error' => 'AI service unavailable'];
            }

            return json_decode($response->json('choices.0.message.content'), true) ?? ['error' => 'Parse failed'];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — PROMPT BUILDING
    // ─────────────────────────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are an elite CS2 tactical analyst specialising in opponent scouting.
Your reports must be precise, map-specific, and grounded entirely in the data provided.

Rules:
- Every claim must cite a specific number from the data (e.g. "71% of CT rounds", "avg 235 utility damage")
- Never invent callouts, positions, or patterns not present in the position data
- Use the exact callout names provided in the position data — do not substitute generic descriptions
- When position data is available for a player, their "watch_out_for" and role assessment MUST reference their primary zone
- Counter recommendations must be actionable in-game tactics, not generic advice
- Always respond with valid JSON matching the requested schema exactly
- Always put a space between team abbreviations and player names (e.g. "CT player H3WAL0" not "CTH3WAL0")
- Refer to sides as "CT-side" and "T-side", never abbreviate inline with a name
- Never place a side abbreviation directly before a player name. Write "H3WAL0 (CT)" or "CT-side player H3WAL0", never "CTH3WAL0"
PROMPT;
    }

    private function buildAnalysisPrompt(
        OpponentTeam $opponent,
        array $aggregated,
        array $positionSummary
    ): string {
        $statsJson     = json_encode($aggregated, JSON_PRETTY_PRINT);
        $maps          = implode(', ', $aggregated['maps_played'] ?? ['unknown']);
        $demos         = $aggregated['total_demos'] ?? 1;
        $positionBlock = $this->buildPositionBlock($positionSummary);

        return <<<PROMPT
Scouting report for opponent team "{$opponent->name}".
Maps: {$maps} | Demos: {$demos}

All numeric stats are AVERAGES PER DEMO unless marked as totals.

=== POSITION DOMINANCE (time spent in each callout zone, freeze-time excluded) ===
{$positionBlock}

=== PLAYER PERFORMANCE STATS ===
{$statsJson}

=== OUTPUT SCHEMA ===
Return EXACTLY this JSON structure. Do not add or remove keys.

{
  "summary": "3-4 sentences covering playstyle, biggest threat, and the one thing your team must prepare for. Must reference specific callout positions if position data is available.",
  "threat_level": "low|medium|high|very_high",
  "danger_score": 0,
  "key_players": [
    {
      "steamid": "...",
      "name": "...",
      "role_assessment": "One of: Entry Fragger | AWPer | Lurker | Support | IGL | Hybrid",
      "primary_positions": {
        "T":  "Primary T-side callout zone name and % e.g. 'B Main (68%)' — use position data, null if unavailable",
        "CT": "Primary CT-side callout zone name and % e.g. 'B-short (71%)' — use position data, null if unavailable"
      },
      "avg_rating": 0.0,
      "avg_adr": 0.0,
      "hs_percent": 0.0,
      "strengths": ["max 3, cite actual numbers"],
      "weaknesses": ["max 3, cite actual numbers"],
      "watch_out_for": "One tactical warning referencing their primary position and stats e.g. 'Anchors B-short 71% of CT rounds — smoke it before executing'"
    }
  ],
  "team_tendencies": {
    "aggression_style": "passive|default|aggressive|very_aggressive",
    "aggression_reasoning": "Cite specific stats",
    "utility_style": "low|moderate|heavy",
    "utility_reasoning": "Cite utility_damage and enemies_flashed averages",
    "clutch_threat": "low|medium|high",
    "clutch_reasoning": "Cite 1vX win totals",
    "eco_behavior": "Describe eco tendencies with specific numbers"
  },
  "map_specific": {
    "<map_name>": {
      "preferred_side": "T|CT|balanced",
      "preferred_side_reasoning": "cite data",
      "ct_setup": "Describe their CT positions using the callout names from position data e.g. 'Hyppe anchors B-short, Fr3dZ1ll4 plays A-main'",
      "t_tendencies": "Describe their T-side patterns using callout names from position data",
      "key_threats": ["max 3, reference specific callout zones"],
      "exploitable_habits": ["max 3, reference specific callout zones and percentages"]
    }
  },
  "counter_recommendations": [
    {
      "situation": "Specific situation referencing callout zone e.g. 'When Hyppe anchors B-short on CT'",
      "recommendation": "Specific counter — max 2 sentences, reference callouts",
      "priority": "high|medium|low"
    }
  ],
  "player_matchups": [
    {
      "their_player": "name",
      "primary_zone": "Their dominant zone on CT or T side e.g. 'B-short (CT)' — null if no data",
      "advice": "One sentence referencing their position and stats"
    }
  ]
}

danger_score 0-100:
  0-25  = below average club team
  26-50 = average semi-competitive
  51-75 = organised competitive
  76-100 = tournament-level
PROMPT;
    }

    private function buildPositionBlock(array $positionSummary): string
    {
        if (empty($positionSummary)) {
            return "No position data available yet — position dominance is computed after demo processing.";
        }

        $lines = [];
        foreach ($positionSummary as $entry) {
            $ct = implode(', ', array_map(
                fn($z) => "{$z['zone_name']} {$z['pct']}%",
                array_slice($entry['CT'] ?? [], 0, 4)
            ));
            $t = implode(', ', array_map(
                fn($z) => "{$z['zone_name']} {$z['pct']}%",
                array_slice($entry['T'] ?? [], 0, 4)
            ));

            $lines[] = "{$entry['name']} — CT: " . ($ct ?: 'no data') . " | T: " . ($t ?: 'no data');
        }

        return implode("\n", $lines);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — DATA AGGREGATION
    // ─────────────────────────────────────────────────────────────────────────

    private function aggregatePositionDominance(Collection $statsRows, Collection $players): array
    {
        $byPlayer = [];

        foreach ($statsRows as $stat) {
            $dominance = $stat->position_dominance ?? [];
            if (empty($dominance)) continue;

            foreach ($dominance as $steamid => $sides) {
                $steamid = (string) $steamid;
                if (!isset($players[$steamid])) continue;

                if (!isset($byPlayer[$steamid])) {
                    $byPlayer[$steamid] = [
                        'steamid' => $steamid,
                        'name'    => $players[$steamid]->alias ?? $players[$steamid]->name,
                        'T'       => [],
                        'CT'      => [],
                    ];
                }

                foreach (['T', 'CT'] as $side) {
                    foreach ($sides[$side] ?? [] as $zoneData) {
                        $zid = $zoneData['zone_id'];
                        if (!isset($byPlayer[$steamid][$side][$zid])) {
                            $byPlayer[$steamid][$side][$zid] = [
                                'zone_id'   => $zid,
                                'zone_name' => $zoneData['zone_name'],
                                'zone_type' => $zoneData['zone_type'],
                                'ticks'     => 0,
                            ];
                        }
                        $byPlayer[$steamid][$side][$zid]['ticks'] += $zoneData['ticks'];
                    }
                }
            }
        }

        $result = [];
        foreach ($byPlayer as $steamid => $data) {
            $row = ['steamid' => $steamid, 'name' => $data['name']];

            foreach (['T', 'CT'] as $side) {
                $zones      = array_values($data[$side]);
                $totalTicks = max(1, array_sum(array_column($zones, 'ticks')));
                usort($zones, fn($a, $b) => $b['ticks'] <=> $a['ticks']);

                $row[$side] = array_slice(
                    array_map(fn($z) => [
                        'zone_name' => $z['zone_name'],
                        'zone_type' => $z['zone_type'],
                        'pct'       => round($z['ticks'] / $totalTicks * 100, 1),
                    ], $zones),
                    0, 5
                );
            }

            $result[] = $row;
        }

        return $result;
    }

    private function aggregateStats(Collection $statsRows, Collection $players): array
    {
        $byPlayer = [];
        $mapsSeen = [];

        foreach ($statsRows as $stat) {
            if ($stat->demo?->map) $mapsSeen[] = $stat->demo->map;

            $allStats  = $stat->stats     ?? [];
            $allKda    = $stat->KDA       ?? [];
            $allKast   = $stat->kast      ?? [];
            $allRating = $stat->rating    ?? [];
            $allHits   = $stat->hitgroups ?? [];

            foreach ($allStats as $row) {
                if (($row['side'] ?? '') !== 'all') continue;
                $sid = (string)($row['steamid'] ?? '');
                if (!$sid || !isset($players[$sid])) continue;

                if (!isset($byPlayer[$sid])) {
                    $byPlayer[$sid] = [
                        'steamid' => $sid,
                        'name'    => $players[$sid]->alias ?? $players[$sid]->name,
                        'demos'   => 0,
                        'totals'  => [],
                    ];
                }

                $byPlayer[$sid]['demos']++;

                foreach ([
                    'dmg', 'adr', 'utility_damage', 'enemies_flashed', 'mvps',
                    'headshot_kills', '3k_rounds', '4k_rounds', '5k_rounds',
                    '1v1', '1v2', '1v3', '1v4', '1v5', 'n_rounds',
                ] as $k) {
                    if (isset($row[$k])) {
                        $byPlayer[$sid]['totals'][$k] = ($byPlayer[$sid]['totals'][$k] ?? 0) + $row[$k];
                    }
                }

                $kda = collect($allKda)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($kda) {
                    foreach (['kills', 'deaths', 'assists'] as $k) {
                        $byPlayer[$sid]['totals'][$k] = ($byPlayer[$sid]['totals'][$k] ?? 0) + ($kda[$k] ?? 0);
                    }
                }

                $kastRow = collect($allKast)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($kastRow) {
                    $byPlayer[$sid]['totals']['kast_sum'] = ($byPlayer[$sid]['totals']['kast_sum'] ?? 0) + ($kastRow['kast'] ?? 0);
                }

                $ratingRow = collect($allRating)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($ratingRow) {
                    $byPlayer[$sid]['totals']['rating_sum'] = ($byPlayer[$sid]['totals']['rating_sum'] ?? 0) + ($ratingRow['rating'] ?? 0);
                }

                $hitRow = collect($allHits)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
                if ($hitRow) {
                    foreach (['headshots', 'chest', 'stomach', 'left_arm', 'right_arm', 'left_leg', 'right_leg'] as $k) {
                        $byPlayer[$sid]['totals']['hg_' . $k] = ($byPlayer[$sid]['totals']['hg_' . $k] ?? 0) + ($hitRow[$k] ?? 0);
                    }
                }
            }
        }

        $out = [];
        foreach ($byPlayer as $sid => $data) {
            $n       = max(1, $data['demos']);
            $t       = $data['totals'];
            $kills   = $t['kills']  ?? 0;
            $deaths  = max(1, $t['deaths'] ?? 1);
            $hsKills = $t['headshot_kills'] ?? 0;

            $totalHits = max(1,
                ($t['hg_headshots'] ?? 0) + ($t['hg_chest']     ?? 0) + ($t['hg_stomach']   ?? 0) +
                ($t['hg_left_arm']  ?? 0) + ($t['hg_right_arm'] ?? 0) +
                ($t['hg_left_leg']  ?? 0) + ($t['hg_right_leg'] ?? 0)
            );

            $out[] = [
                'steamid'                => $sid,
                'name'                   => $data['name'],
                'demos_seen'             => $data['demos'],
                'avg_rating'             => round(($t['rating_sum']      ?? 0) / $n, 2),
                'avg_kast_pct'           => round(($t['kast_sum']        ?? 0) / $n, 1),
                'avg_adr'                => round(($t['adr']             ?? 0) / $n, 1),
                'avg_kills'              => round($kills                        / $n, 1),
                'avg_deaths'             => round($deaths                       / $n, 1),
                'avg_assists'            => round(($t['assists']         ?? 0)  / $n, 1),
                'kd_ratio'               => round($kills / $deaths, 2),
                'hs_percent'             => $kills > 0 ? round($hsKills / $kills * 100, 1) : 0,
                'avg_utility_damage'     => round(($t['utility_damage']  ?? 0) / $n, 1),
                'avg_enemies_flashed'    => round(($t['enemies_flashed'] ?? 0) / $n, 1),
                'avg_mvps'               => round(($t['mvps']            ?? 0) / $n, 1),
                'total_3k'               => (int)($t['3k_rounds'] ?? 0),
                'total_4k'               => (int)($t['4k_rounds'] ?? 0),
                'total_5k'               => (int)($t['5k_rounds'] ?? 0),
                'clutch_1v1'             => (int)($t['1v1'] ?? 0),
                'clutch_1v2'             => (int)($t['1v2'] ?? 0),
                'clutch_1v3'             => (int)($t['1v3'] ?? 0),
                'clutch_1v4'             => (int)($t['1v4'] ?? 0),
                'clutch_1v5'             => (int)($t['1v5'] ?? 0),
                'total_clutch_wins'      => (int)(($t['1v1'] ?? 0) + ($t['1v2'] ?? 0) + ($t['1v3'] ?? 0) + ($t['1v4'] ?? 0) + ($t['1v5'] ?? 0)),
                'hitgroup_breakdown_pct' => [
                    'head'    => round(($t['hg_headshots'] ?? 0) / $totalHits * 100, 1),
                    'chest'   => round(($t['hg_chest']    ?? 0) / $totalHits * 100, 1),
                    'stomach' => round(($t['hg_stomach']  ?? 0) / $totalHits * 100, 1),
                    'arms'    => round((($t['hg_left_arm']  ?? 0) + ($t['hg_right_arm'] ?? 0)) / $totalHits * 100, 1),
                    'legs'    => round((($t['hg_left_leg']  ?? 0) + ($t['hg_right_leg'] ?? 0)) / $totalHits * 100, 1),
                ],
            ];
        }

        usort($out, fn($a, $b) => $b['avg_rating'] <=> $a['avg_rating']);

        return [
            'players'     => $out,
            'total_demos' => $statsRows->count(),
            'maps_played' => array_values(array_unique($mapsSeen)),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE — ZONE TOKEN RESOLUTION
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveZoneTokens(array $data, array $mapsPlayed): array
    {
        if (empty($mapsPlayed)) return $data;

        $zones = MapCalloutZone::whereHas('map', function ($q) use ($mapsPlayed) {
            $q->whereIn('name', $mapsPlayed);
        })
        ->with('map:id,name')
        ->orderByRaw("CASE zone_type WHEN 'point' THEN 2 WHEN 'zone' THEN 1 ELSE 0 END DESC")
        ->orderBy('sort_order')
        ->get();

        if ($zones->isEmpty()) return $data;

        $lookup = [];
        foreach ($zones as $zone) {
            $lookup[strtolower($zone->canonical_name)] = $zone;
        }
        uksort($lookup, fn($a, $b) => strlen($b) - strlen($a));

        return $this->walkAndReplace($data, $lookup);
    }

    private function walkAndReplace(mixed $value, array $lookup): mixed
    {
        if (is_string($value)) {
            return $this->replaceZoneNames($value, $lookup);
        }

        if (is_array($value)) {
            $skipKeys = [
                'steamid', 'zone_id', 'zone_ids', 'map', 'side', 'priority',
                'zone_type', 'threat_level', 'aggression_style', 'utility_style', 'clutch_threat',
            ];
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = in_array($k, $skipKeys) ? $v : $this->walkAndReplace($v, $lookup);
            }
            return $result;
        }

        return $value;
    }

    private function replaceZoneNames(string $text, array $lookup): string
    {
        foreach ($lookup as $name => $zone) {
            if (str_contains($text, "[callout:{$zone->id}:")) continue;
            $escaped = preg_quote($zone->canonical_name, '/');
            $pattern = '/(?<!\[|:)\b' . $escaped . '\b(?!\])/i';
            $token   = "[callout:{$zone->id}:{$zone->canonical_name}]";
            $text    = preg_replace($pattern, $token, $text);
        }
        return $text;
    }
}