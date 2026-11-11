<?php

namespace App\Jobs;

use App\Models\DemoStat;
use App\Models\MapCalloutZone;
use App\Models\OpponentCounterStrat;
use App\Models\OpponentTeam;
use App\Services\AntiStratAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCounterStratsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        private readonly int $opponentTeamId,
        private readonly int $createdBy,
    ) {}

    public function handle(): void
    {
        $opponent = OpponentTeam::with(['players', 'demos'])->find($this->opponentTeamId);
        if (! $opponent) {
            return;
        }

        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();
        $statsRows = DemoStat::whereIn('demo_id', $demoIds)->with('demo:id,map')->get();
        $players = $opponent->players()->get()->keyBy('steamid');

        if ($statsRows->isEmpty()) {
            Log::warning("GenerateCounterStratsJob: no stats for opponent {$this->opponentTeamId}");

            return;
        }

        // Build position summary for the prompt
        $positionSummary = $this->aggregatePositionDominance($statsRows, $players);

        // Build aggregated stats
        $aggregated = $this->aggregateStats($statsRows, $players);

        // Load all zones for maps played (for zone_id lookup)
        $maps = $aggregated['maps_played'] ?? [];
        $zones = MapCalloutZone::whereHas('map', fn ($q) => $q->whereIn('name', $maps))
            ->with('map:id,name')
            ->get()
            ->keyBy('canonical_name');

        $prompt = $this->buildPrompt($opponent, $aggregated, $positionSummary, $maps);
        $service = app(AntiStratAiService::class);
        $result = $service->rawComplete($prompt);

        if (isset($result['error']) || empty($result['counter_strats'])) {
            Log::warning('GenerateCounterStratsJob: AI returned no strats', $result);

            return;
        }

        foreach ($result['counter_strats'] as $strat) {
            // Resolve zone names to IDs
            $zoneIds = [];
            foreach ($strat['zone_names'] ?? [] as $zoneName) {
                if (isset($zones[$zoneName])) {
                    $zoneIds[] = $zones[$zoneName]->id;
                }
            }

            // Find map_id from maps table
            $mapModel = \App\Models\Map::where('name', $strat['map'] ?? '')->first();
            if (! $mapModel) {
                continue;
            }

            // Skip if a very similar strat already exists
            $exists = OpponentCounterStrat::where('opponent_team_id', $this->opponentTeamId)
                ->where('map_id', $mapModel->id)
                ->where('side', $strat['side'])
                ->where('name', $strat['name'])
                ->exists();
            if ($exists) {
                continue;
            }

            OpponentCounterStrat::create([
                'opponent_team_id' => $this->opponentTeamId,
                'name' => $strat['name'],
                'map_id' => $mapModel->id,
                'side' => $strat['side'],
                'description' => $strat['description'],
                'tags' => $strat['tags'] ?? [],
                'zone_ids' => $zoneIds,
                'created_by' => $this->createdBy,
                'ai_generated' => true,
            ]);
        }

        // Update opponent to mark generation as complete
        $opponent->update(['counter_strats_generated_at' => now()]);

        Log::info('GenerateCounterStratsJob: created '.count($result['counter_strats'])." strats for opponent {$this->opponentTeamId}");
    }

    private function buildPrompt(OpponentTeam $opponent, array $aggregated, array $positionSummary, array $maps): string
    {
        $mapsStr = implode(', ', $maps);
        $statsJson = json_encode($aggregated['players'] ?? [], JSON_PRETTY_PRINT);

        // Position block
        $posBlock = '';
        foreach ($positionSummary as $p) {
            $ct = implode(', ', array_map(fn ($z) => "{$z['zone_name']} {$z['pct']}%", array_slice($p['CT'] ?? [], 0, 3)));
            $t = implode(', ', array_map(fn ($z) => "{$z['zone_name']} {$z['pct']}%", array_slice($p['T'] ?? [], 0, 3)));
            $posBlock .= "{$p['name']} — CT: {$ct} | T: {$t}\n";
        }

        return <<<PROMPT
You are generating CS2 counter-strats for team facing opponent "{$opponent->name}".
Maps: {$mapsStr}

OPPONENT POSITION DATA (time in zones, freeze-time excluded):
{$posBlock}

OPPONENT PLAYER STATS:
{$statsJson}

Generate 4-6 specific, actionable counter-strats. For each strat you MUST:
- Reference specific callout zones from the position data
- Base the counter on observed opponent patterns (not generic advice)
- Specify which side YOUR team plays this strat (T or CT)

Return JSON with EXACTLY this structure:
{
  "counter_strats": [
    {
      "name": "Short strat name e.g. 'Smoke B-short early'",
      "map": "de_mapname (must be one of: {$mapsStr})",
      "side": "T or CT (your team's side)",
      "description": "2-3 sentences. What the opponent does, why this counter works, how to execute. Must cite specific zones and percentages.",
      "tags": ["max 3 short tags e.g. 'anti-flash', 'eco-punish', 'B-site'"],
      "zone_names": ["exact canonical zone names from the position data that this strat targets"]
    }
  ]
}
PROMPT;
    }

    private function aggregatePositionDominance($statsRows, $players): array
    {
        $byPlayer = [];
        foreach ($statsRows as $stat) {
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
                    $byPlayer[$steamid] = ['steamid' => $steamid, 'name' => $players[$steamid]->alias ?? $players[$steamid]->name, 'T' => [], 'CT' => []];
                }
                foreach (['T', 'CT'] as $side) {
                    foreach ($sides[$side] ?? [] as $z) {
                        $zid = $z['zone_id'];
                        if (! isset($byPlayer[$steamid][$side][$zid])) {
                            $byPlayer[$steamid][$side][$zid] = $z + ['ticks' => 0];
                        }
                        $byPlayer[$steamid][$side][$zid]['ticks'] += $z['ticks'];
                    }
                }
            }
        }
        $result = [];
        foreach ($byPlayer as $steamid => $data) {
            $row = ['steamid' => $steamid, 'name' => $data['name']];
            foreach (['T', 'CT'] as $side) {
                $zones = array_values($data[$side]);
                $total = max(1, array_sum(array_column($zones, 'ticks')));
                usort($zones, fn ($a, $b) => $b['ticks'] <=> $a['ticks']);
                $row[$side] = array_slice(array_map(fn ($z) => ['zone_name' => $z['zone_name'], 'pct' => round($z['ticks'] / $total * 100, 1)], $zones), 0, 4);
            }
            $result[] = $row;
        }

        return $result;
    }

    private function aggregateStats($statsRows, $players): array
    {
        $maps = [];
        $players_out = [];
        foreach ($statsRows as $stat) {
            if ($stat->demo?->map) {
                $maps[] = $stat->demo->map;
            }
            foreach ($stat->stats ?? [] as $row) {
                if (($row['side'] ?? '') !== 'all') {
                    continue;
                }
                $sid = (string) ($row['steamid'] ?? '');
                if (! $sid || ! isset($players[$sid])) {
                    continue;
                }
                if (! isset($players_out[$sid])) {
                    $players_out[$sid] = ['name' => $players[$sid]->alias ?? $players[$sid]->name, 'avg_adr' => 0, 'avg_rating' => 0, 'n' => 0];
                }
                $players_out[$sid]['avg_adr'] += $row['adr'] ?? 0;
                $players_out[$sid]['avg_rating'] += $row['rating'] ?? 0;
                $players_out[$sid]['n']++;
            }
        }
        foreach ($players_out as &$p) {
            $n = max(1, $p['n']);
            $p['avg_adr'] = round($p['avg_adr'] / $n, 1);
            $p['avg_rating'] = round($p['avg_rating'] / $n, 2);
            unset($p['n']);
        }

        return ['players' => array_values($players_out), 'maps_played' => array_values(array_unique($maps))];
    }
}
