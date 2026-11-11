<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\TeamStatsService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;

class TeamStatsController extends Controller
{
    public function __construct(private TeamStatsService $service) {}

    public function overview(Request $request, int $teamId): JsonResponse
    {
        $team = $this->guard($teamId);
        $limit = (int) $request->query('limit', 10);
        return new JsonResponse($this->service->overview($team, $limit));
    }

    public function matches(Request $request, int $teamId): JsonResponse
    {
        $team = $this->guard($teamId);
        $limit = (int) $request->query('limit', 50);
        return new JsonResponse($this->service->matches($team, $limit));
    }

    public function maps(Request $request, int $teamId): JsonResponse
    {
        $team = $this->guard($teamId);
        return new JsonResponse($this->service->maps($team));
    }

    public function roster(Request $request, int $teamId): JsonResponse
    {
        $team = $this->guard($teamId);
        return new JsonResponse($this->service->roster($team));
    }

    private function guard(int $teamId): Team
    {
        $teamIds = Auth::user()->teams->pluck('id')->toArray();
        if (!in_array($teamId, $teamIds, true)) {
            abort(403, 'You do not have access to this team.');
        }
        return Team::findOrFail($teamId);
    }
}
