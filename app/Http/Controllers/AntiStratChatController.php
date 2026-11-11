<?php

namespace App\Http\Controllers;

use App\Constants\AppErrorCode;
use App\Models\DemoStat;
use App\Models\OpponentTeam;
use App\Services\AntiStratAiService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AntiStratChatController extends Controller
{
    /**
     * POST /api/opponent-teams/{id}/chat
     *
     * Body:
     *   message   string   — user's question
     *   history   array    — prior messages [{ role: 'user'|'assistant', content: string }]
     */
    public function chat(Request $request, int $id, AntiStratAiService $aiService): JsonResponse
    {
        $plans = app(PlanService::class);
        if (! $plans->can($request->user(), 'ai_antistrat')) {
            return new JsonResponse([
                'app_code' => AppErrorCode::PLAN_LIMIT_EXCEEDED,
                'feature' => 'ai_antistrat',
                'required_tier' => $plans->requiredTierForFeature('ai_antistrat'),
                'message' => 'AI Antistrat chat is a Pro feature. Upgrade to Pro to use it.',
            ], 403);
        }

        $opponent = OpponentTeam::visibleTo($request->user())
            ->with(['players', 'demos', 'counterStrats.map'])
            ->findOrFail($id);

        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'array|max:20',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:4000',
        ]);

        $demoIds = $opponent->demos()->pluck('demos.id')->toArray();
        $statsRows = DemoStat::whereIn('demo_id', $demoIds)
            ->with('demo:id,map')
            ->get();
        $players = $opponent->players()->get()->keyBy('steamid');

        $reply = $aiService->chat(
            opponent: $opponent,
            statsRows: $statsRows,
            players: $players,
            message: $request->input('message'),
            history: $request->input('history', []),
        );

        return new JsonResponse(['reply' => $reply]);
    }
}
