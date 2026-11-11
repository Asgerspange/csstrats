<?php

namespace App\Http\Controllers;

use App\Constants\AppErrorCode;
use App\Models\Demo;
use App\Models\DemoStat;
use App\Models\OpponentCounterStrat;
use App\Models\OpponentTeam;
use App\Models\OpponentTeamPlayer;
use App\Models\Team;
use App\Models\User;
use App\Services\AntiStratAiService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpponentTeamController extends Controller
{
    public function __construct(private readonly AntiStratAiService $aiService) {}

    // ─── Opponent Teams ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $opponents = OpponentTeam::visibleTo($request->user())
            ->with([
                'players',
                'sharedTeams:id,name',
                'demos' => fn ($q) => $q->select('demos.id', 'demos.name', 'demos.map', 'demos.created_at'),
            ])
            ->withCount('demos')
            ->orderBy('name')
            ->get();

        return new JsonResponse($opponents);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'tag' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'platform_url' => 'nullable|url',
        ]);

        $data['user_id'] = $request->user()->id;

        $opponent = OpponentTeam::create($data);

        return new JsonResponse($opponent->load('sharedTeams:id,name'), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        $opponent->load([
            'players',
            'counterStrats',
            'sharedTeams:id,name',
            'demos.stats',
            'demos.rounds' => function ($q) {
                $q->where('round_index', '>', 0)
                    ->select('demo_id', 'round_index');
            },
        ]);

        // Attach aggregated per-map record against this opponent
        $opponent->map_stats = $this->buildMapStats($opponent);
        $opponent->match_record = $this->buildMatchRecord($opponent);
        $opponent->is_owner = $opponent->isOwnedBy($request->user());

        return new JsonResponse($opponent);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'tag' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'platform_url' => 'nullable|url',
        ]);

        $opponent->update($data);

        return new JsonResponse($opponent->load('sharedTeams:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        // Only the owner can delete a profile outright.
        $this->resolveOwnedOpponent($id)->delete();

        return new JsonResponse(['message' => 'Deleted'], 200);
    }

    // ─── Sharing ──────────────────────────────────────────────────────────────

    /**
     * PUT /api/opponent-teams/{id}/shares
     * Owner-only. Syncs which of the owner's teams this profile is shared with.
     */
    public function updateShares(Request $request, int $id): JsonResponse
    {
        $opponent = $this->resolveOwnedOpponent($id);

        $data = $request->validate([
            'team_ids' => 'present|array',
            'team_ids.*' => 'integer',
        ]);

        // Only allow sharing with teams the owner actually belongs to.
        $ownTeamIds = $this->userTeamIds($request->user());
        $teamIds = array_values(array_intersect($data['team_ids'], $ownTeamIds));

        $opponent->sharedTeams()->sync($teamIds);

        return new JsonResponse($opponent->load('sharedTeams:id,name'));
    }

    /**
     * POST /api/opponent-teams/{id}/counter-strats/generate
     * Dispatches AI generation job.
     */
    public function generateCounterStrats(int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        if (($gate = $this->ensureProFeature('ai_antistrat'))) {
            return $gate;
        }

        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();
        if (empty($demoIds)) {
            return new JsonResponse(['error' => 'No demos linked — link demos first'], 422);
        }

        // Mark as generating
        $opponent->update(['counter_strats_generated_at' => null]);

        \App\Jobs\GenerateCounterStratsJob::dispatch($id, Auth::id());

        return new JsonResponse(['message' => 'Generating counter strats in background…'], 202);
    }

    // ─── Demo Linking ─────────────────────────────────────────────────────────

    /**
     * Attach a demo to an opponent profile.
     * Also upserts any players found in that demo into opponent_team_players.
     */
    public function attachDemo(Request $request, int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        $request->validate(['demo_id' => 'required|integer|exists:demos,id']);
        $demoId = $request->integer('demo_id');

        $opponent->demos()->syncWithoutDetaching([$demoId]);
        $this->syncPlayersFromDemo($opponent, $demoId);

        return new JsonResponse(['message' => 'Demo attached'], 200);
    }

    public function detachDemo(Request $request, int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);
        $request->validate(['demo_id' => 'required|integer']);

        $opponent->demos()->detach($request->integer('demo_id'));

        return new JsonResponse(['message' => 'Demo detached'], 200);
    }

    /**
     * Auto-detect which opponent profile a demo belongs to by matching:
     *   1. team_clan_name from demo_stats
     *   2. steamid overlap against opponent_team_players
     *
     * Scoped to the profiles the current user can see. Returns the best-match
     * opponent or null.
     */
    public function autoMatch(Request $request): JsonResponse
    {
        $request->validate(['demo_id' => 'required|integer|exists:demos,id']);
        $demoId = $request->integer('demo_id');

        $match = $this->detectOpponentMatch($request->user(), $demoId);

        return new JsonResponse(['match' => $match]);
    }

    // ─── Players ──────────────────────────────────────────────────────────────

    public function updatePlayer(Request $request, int $opponentId, int $playerId): JsonResponse
    {
        $this->resolveAuthorizedOpponent($opponentId);

        $player = OpponentTeamPlayer::where('opponent_team_id', $opponentId)
            ->findOrFail($playerId);

        $data = $request->validate([
            'alias' => 'nullable|string|max:64',
            'notes' => 'nullable|string',
        ]);

        $player->update($data);

        return new JsonResponse($player);
    }

    // ─── Counter Strats ───────────────────────────────────────────────────────

    public function storeCounterStrat(Request $request, int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'map_id' => 'required|integer|exists:maps,id',
            'side' => 'required|in:T,CT',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'demo_id' => 'nullable|integer|exists:demos,id',
            'demo_round' => 'nullable|integer',
        ]);

        $strat = OpponentCounterStrat::create([
            ...$data,
            'opponent_team_id' => $opponent->id,
            'created_by' => Auth::id(),
        ]);

        return new JsonResponse($strat, 201);
    }

    public function destroyCounterStrat(int $opponentId, int $stratId): JsonResponse
    {
        $this->resolveAuthorizedOpponent($opponentId);

        OpponentCounterStrat::where('opponent_team_id', $opponentId)
            ->findOrFail($stratId)
            ->delete();

        return new JsonResponse(['message' => 'Deleted'], 200);
    }

    // ─── AI Insights ──────────────────────────────────────────────────────────

    /**
     * Trigger AI analysis for this opponent.
     * Collects all demo stats and runs them through AntiStratAiService.
     * Results are cached on the opponent_teams row.
     */
    public function refreshInsights(int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        if (($gate = $this->ensureProFeature('ai_antistrat'))) {
            return $gate;
        }

        // Already queued — don't double-dispatch
        if ($opponent->insights_status === 'pending') {
            return new JsonResponse([
                'status' => 'pending',
                'message' => 'Analysis already in progress.',
            ], 202);
        }

        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();

        if (empty($demoIds)) {
            return new JsonResponse(['error' => 'No demos linked to this opponent'], 422);
        }

        // Mark as pending immediately so the UI can reflect it
        $opponent->update([
            'insights_status' => 'pending',
            'insights_error' => null,
        ]);

        \App\Jobs\AnalyzeOpponentJob::dispatch($opponent->id);

        return new JsonResponse([
            'status' => 'pending',
            'message' => 'Analysis queued. Check back in a moment.',
        ], 202);
    }

    public function insightsStatus(int $id): JsonResponse
    {
        $opponent = $this->resolveAuthorizedOpponent($id);

        return new JsonResponse([
            'status' => $opponent->insights_status,      // null|pending|complete|failed
            'insights_updated_at' => $opponent->insights_updated_at,
            'insights_error' => $opponent->insights_error,
            // Only return full insights when complete — avoids heavy response on polling
            'ai_insights' => $opponent->insights_status === 'complete'
                                        ? $opponent->ai_insights
                                        : null,
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function userTeamIds(User $user): array
    {
        return $user->teams->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Tier gate for AI antistrat features (Pro only). Returns a 403 JsonResponse
     * (with the PLAN_LIMIT_EXCEEDED app_code the frontend turns into an upgrade
     * modal) when the acting user's plan doesn't include the feature, or null
     * when the action may proceed. Resolved via PlanService so admins who
     * switch themselves to Team/Starter hit the same gate a real user would.
     */
    private function ensureProFeature(string $feature): ?JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $plans = app(PlanService::class);
        if ($plans->can($user, $feature)) {
            return null;
        }

        return new JsonResponse([
            'app_code' => AppErrorCode::PLAN_LIMIT_EXCEEDED,
            'feature' => $feature,
            'required_tier' => $plans->requiredTierForFeature($feature),
            'message' => 'AI Antistrat is a Pro feature. Upgrade to Pro to use it.',
        ], 403);
    }

    /**
     * Profile the user can see (owns it, or it's shared with one of their teams).
     */
    private function resolveAuthorizedOpponent(int $id): OpponentTeam
    {
        return OpponentTeam::visibleTo(Auth::user())->findOrFail($id);
    }

    /**
     * Profile the user owns — required for destructive / sharing actions.
     */
    private function resolveOwnedOpponent(int $id): OpponentTeam
    {
        return OpponentTeam::where('user_id', Auth::id())->findOrFail($id);
    }

    /**
     * Sync players found in the opposing team's side of a demo.
     */
    private function syncPlayersFromDemo(OpponentTeam $opponent, int $demoId): void
    {
        $stat = DemoStat::where('demo_id', $demoId)->first();
        if (! $stat) {
            return;
        }

        $knownSteamIds = $opponent->players()->pluck('steamid')->toArray();

        // team1 and team2 are stored in demo_stats
        $allPlayers = array_merge(
            $stat->team1 ?? [],
            $stat->team2 ?? [],
        );

        // Determine which team is the opponent by matching existing steamids or clan name
        $opponentSide = $this->resolveOpponentSide($allPlayers, $opponent, $knownSteamIds);
        if (! $opponentSide) {
            return;
        }

        foreach ($opponentSide as $player) {
            $steamid = (string) ($player['steamid'] ?? '');
            if (! $steamid || $steamid === '0') {
                continue;
            }

            OpponentTeamPlayer::updateOrCreate(
                [
                    'opponent_team_id' => $opponent->id,
                    'steamid' => $steamid,
                ],
                [
                    'name' => $player['name'] ?? 'Unknown',
                ]
            );
        }
    }

    /**
     * Given both team arrays from a demo_stat row, return whichever array
     * is most likely to be the opponent team based on:
     *   1. steamid overlap with known opponent players  (primary signal)
     *   2. team_clan_name fuzzy match against opponent->name (fallback)
     */
    private function resolveOpponentSide(array $allPlayers, OpponentTeam $opponent, array $knownSteamIds): ?array
    {
        $stat = DemoStat::whereHas('demo', fn ($q) => $q->whereIn(
            'id',
            $opponent->demos()->pluck('demos.id')
        ))->first();

        if (! $stat) {
            return null;
        }

        $team1 = $stat->team1 ?? [];
        $team2 = $stat->team2 ?? [];

        // Primary: steamid overlap
        if (! empty($knownSteamIds)) {
            $t1Ids = array_column($team1, 'steamid');
            $t2Ids = array_column($team2, 'steamid');
            $t1Over = count(array_intersect($t1Ids, $knownSteamIds));
            $t2Over = count(array_intersect($t2Ids, $knownSteamIds));

            if ($t1Over > $t2Over) {
                return $team1;
            }
            if ($t2Over > $t1Over) {
                return $team2;
            }
        }

        // Fallback: clan name similarity
        $nameNorm = strtolower(trim($opponent->name));
        $t1Name = strtolower(trim($team1[0]['team_clan_name'] ?? ''));
        $t2Name = strtolower(trim($team2[0]['team_clan_name'] ?? ''));

        similar_text($nameNorm, $t1Name, $t1Pct);
        similar_text($nameNorm, $t2Name, $t2Pct);

        if ($t1Pct >= 60 && $t1Pct >= $t2Pct) {
            return $team1;
        }
        if ($t2Pct >= 60) {
            return $team2;
        }

        return null;
    }

    /**
     * Try to detect which opponent profile a fresh demo belongs to.
     * Returns opponent data if a confident match is found.
     */
    private function detectOpponentMatch(User $user, int $demoId): ?array
    {
        $stat = DemoStat::where('demo_id', $demoId)->first();
        if (! $stat) {
            return null;
        }

        $opponents = OpponentTeam::visibleTo($user)->with('players')->get();

        $bestOpponent = null;
        $bestScore = 0;

        $team1Ids = array_map('strval', array_column($stat->team1 ?? [], 'steamid'));
        $team2Ids = array_map('strval', array_column($stat->team2 ?? [], 'steamid'));
        $team1Name = strtolower(trim($stat->team1[0]['team_clan_name'] ?? ''));
        $team2Name = strtolower(trim($stat->team2[0]['team_clan_name'] ?? ''));

        foreach ($opponents as $opponent) {
            $knownIds = array_map('strval', $opponent->steamIds());
            $normName = strtolower(trim($opponent->name));
            $score = 0;

            // Steamid overlap on either side
            $overlap1 = count(array_intersect($team1Ids, $knownIds));
            $overlap2 = count(array_intersect($team2Ids, $knownIds));
            $score += max($overlap1, $overlap2) * 20; // 20 pts per matching player

            // Clan name match
            similar_text($normName, $team1Name, $pct1);
            similar_text($normName, $team2Name, $pct2);
            $score += max($pct1, $pct2) * 0.5; // up to 50 extra pts at 100% match

            if ($score > $bestScore && $score >= 20) { // require at least 1 steamid match or 40% name
                $bestScore = $score;
                $bestOpponent = $opponent;
            }
        }

        return $bestOpponent ? [
            'id' => $bestOpponent->id,
            'name' => $bestOpponent->name,
            'confidence' => min(100, (int) $bestScore),
        ] : null;
    }

    private function buildMapStats(OpponentTeam $opponent): array
    {
        $result = [];

        foreach ($opponent->demos as $demo) {
            $map = $demo->map ?? 'unknown';
            $stat = $demo->stats;
            if (! $stat) {
                continue;
            }

            if (! isset($result[$map])) {
                $result[$map] = ['map' => $map, 'played' => 0, 'wins' => 0, 'losses' => 0];
            }

            $result[$map]['played']++;
        }

        return array_values($result);
    }

    private function buildMatchRecord(OpponentTeam $opponent): array
    {
        $total = $opponent->demos()->count();

        return [
            'total' => $total,
            'linked' => $total,
        ];
    }
}
