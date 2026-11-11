<?php

namespace App\Http\Controllers;

use App\Models\Demo;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Http, Log};

class DemoChatController extends Controller
{
    /**
     * POST /api/demos/{id}/chat
     *
     * Body:
     *   message  string  — user question
     *   history  array   — prior messages
     *   context  array   — { context: 'round'|'demo', round_index?, score?, winner?, ct_players?, t_players? }
     */
    public function chat(Request $request, int $id): JsonResponse
    {
        $teams   = Auth::user()->teams;
        $teamIds = $teams->pluck('id')->toArray();

        // Secure access — user must own or have shared access to this demo
        $demo = Demo::where(function ($q) use ($teamIds) {
            $q->where('owner', Auth::id())
              ->orWhereHas('sharedTeams', fn($sq) => $sq->whereIn('teams.id', $teamIds));
        })->with('stats')->findOrFail($id);

        $request->validate([
            'message'           => 'required|string|max:2000',
            'history'           => 'array|max:20',
            'history.*.role'    => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:4000',
            'context'           => 'array',
        ]);

        $context = $request->input('context', []);
        $message = $request->input('message');
        $history = $request->input('history', []);

        $systemPrompt = $this->buildSystemPrompt($demo, $context);
        $reply        = $this->callAi($systemPrompt, $message, $history);

        return new JsonResponse(['reply' => $reply]);
    }

    private function buildSystemPrompt(Demo $demo, array $context): string
    {
        $mapName  = $demo->map  ?? 'unknown';
        $demoName = $demo->name ?? 'Demo';
        $ctxType  = $context['context'] ?? 'demo';

        if ($ctxType === 'round') {
            $roundIndex = $context['round_index'] ?? '?';
            $score      = $context['score']       ?? [];
            $winner     = $context['winner']      ?? 'unknown';
            $winReason  = $context['win_reason']  ?? '';
            $utility    = $context['utility']     ?? [];
            $ctPlayers  = json_encode($context['ct_players'] ?? [], JSON_PRETTY_PRINT);
            $tPlayers   = json_encode($context['t_players']  ?? [], JSON_PRETTY_PRINT);
            $killsJson  = json_encode($context['kills']      ?? [], JSON_PRETTY_PRINT);

            // Build human-readable utility summary
            $utilLines = [];
            foreach ($utility['smokes']            ?? [] as $s)
                $utilLines[] = "Smoke by {$s['thrower']} ({$s['side']})";
            foreach ($utility['molotovs_infernos'] ?? [] as $m)
                $utilLines[] = "Molotov/inferno by {$m['thrower']} ({$m['side']})";
            // Flashbangs summarised by thrower: { "Clemme": 3, "H3WAL0": 1 }
            foreach ($utility['flashbangs_by_thrower'] ?? [] as $name => $count)
                $utilLines[] = "{$name} threw {$count} flashbang" . ($count > 1 ? 's' : '');
            // HE grenades summarised by thrower
            foreach ($utility['he_grenades_by_thrower'] ?? [] as $name => $count)
                $utilLines[] = "{$name} threw {$count} HE grenade" . ($count > 1 ? 's' : '');

            $utilBlock     = empty($utilLines) ? "No utility data." : implode("\n", $utilLines);
            $totalSmokes   = count($utility['smokes']            ?? []);
            $totalMolotovs = count($utility['molotovs_infernos'] ?? []);
            $totalFlashes  = $utility['total_flashes']            ?? 0;
            $totalHe       = $utility['total_he']                 ?? 0;

            return <<<PROMPT
You are an elite CS2 analyst reviewing a specific round from demo "{$demoName}" on {$mapName}.

ROUND {$roundIndex} CONTEXT:
- Score at end of round: CT {$score['ct']} — T {$score['t']}
- Winner: {$winner}
- Win reason: {$winReason}

CT-SIDE PLAYERS (health, armor, weapon, kills/deaths, balance):
{$ctPlayers}

T-SIDE PLAYERS (health, armor, weapon, kills/deaths, balance):
{$tPlayers}

KILLS THIS ROUND (in order):
{$killsJson}

UTILITY USED THIS ROUND (use this data — do not say "no data" if entries exist below):
Total thrown — Smokes: {$totalSmokes} | Molotovs: {$totalMolotovs} | Flashes: {$totalFlashes} | HE: {$totalHe}
Detail:
{$utilBlock}

Rules:
- Answer questions about this specific round using the data above
- Be concise — 2-4 sentences unless more detail is needed
- Cite specific player names from the context
- If asked about something not present in the data, say so clearly
- Do not use markdown formatting — write in plain prose
- Always put a space between team abbreviations and player names (e.g. "CT player H3WAL0" not "CTH3WAL0")
- Refer to sides as "CT-side" and "T-side", never abbreviate inline with a name
- Never place a side abbreviation directly before a player name. Write "H3WAL0 (CT)" or "CT-side player H3WAL0", never "CTH3WAL0"
PROMPT;
        }

        // Full demo context — $demo->stats is a DemoStat model (HasOne relationship)
        $demoStat  = $demo->stats;
        $allStats  = $demoStat?->stats    ?? [];
        $allKda    = $demoStat?->KDA      ?? [];
        $allRating = $demoStat?->rating   ?? [];
        $allKast   = $demoStat?->kast     ?? [];
        $team1     = $demoStat?->team1    ?? [];
        $team2     = $demoStat?->team2    ?? [];

        // Build player index from team lists
        $playerNames = [];
        foreach (array_merge($team1, $team2) as $p) {
            $sid = (string)($p['steamid'] ?? '');
            if ($sid) $playerNames[$sid] = $p['name'] ?? $sid;
        }

        // Aggregate per-player stats (side === 'all' rows)
        $playerStats = [];
        foreach ($allStats as $row) {
            if (($row['side'] ?? '') !== 'all') continue;
            $sid  = (string)($row['steamid'] ?? '');
            $name = $playerNames[$sid] ?? $row['name'] ?? $sid;
            if (!$sid) continue;

            $kda    = collect($allKda)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
            $rating = collect($allRating)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');
            $kast   = collect($allKast)->first(fn($p) => (string)($p['steamid'] ?? '') === $sid && ($p['side'] ?? '') === 'all');

            $kills  = (int)($kda['kills']  ?? 0);
            $deaths = max(1, (int)($kda['deaths'] ?? 1));

            $playerStats[] = [
                'name'    => $name,
                'kills'   => $kills,
                'deaths'  => (int)($kda['deaths'] ?? 0),
                'assists' => (int)($kda['assists'] ?? 0),
                'kd'      => round($kills / $deaths, 2),
                'adr'     => round($row['adr'] ?? 0, 1),
                'hs_pct'  => $kills > 0 ? round(($row['headshot_kills'] ?? 0) / $kills * 100, 1) : 0,
                'rating'  => round($rating['rating'] ?? 0, 2),
                'kast'    => round($kast['kast']     ?? 0, 1),
                'util_dmg'=> round($row['utility_damage'] ?? 0, 1),
            ];
        }

        // Sort by rating desc
        usort($playerStats, fn($a, $b) => $b['rating'] <=> $a['rating']);

        $playerStatsJson = json_encode($playerStats, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are an elite CS2 analyst reviewing demo "{$demoName}" on {$mapName}.

PLAYER STATS (sorted by rating, all sides combined):
{$playerStatsJson}

Rules:
- Answer questions about this demo using the stats above
- Always cite specific numbers (rating, ADR, K/D, kills etc.) when answering
- Be concise — 2-4 sentences unless detail is requested
- Do not use markdown formatting — write in plain prose
PROMPT;
    }

    private function callAi(string $systemPrompt, string $message, array $history): string
    {
        $baseUrl = config('services.odin.base_url', 'https://api.ordbogen.ai/v1');
        $apiKey  = config('services.odin.api_key');
        $model   = config('services.odin.model', 'odin-medium');

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::withToken($apiKey)
                ->baseUrl($baseUrl)
                ->timeout(60)
                ->post('/chat/completions', [
                    'model'       => $model,
                    'temperature' => 0.4,
                    'messages'    => $messages,
                ]);

            if ($response->failed()) {
                return 'Sorry, I could not process that right now. Please try again.';
            }

            return trim($response->json('choices.0.message.content') ?? 'No response');

        } catch (\Exception $e) {
            Log::error('DemoChat AI error', ['message' => $e->getMessage()]);
            return 'Error: ' . $e->getMessage();
        }
    }
}