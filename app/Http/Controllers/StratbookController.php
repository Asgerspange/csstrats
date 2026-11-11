<?php

namespace App\Http\Controllers;

use App\Core\Repositories\StratbookRepository;
use App\Enums\EntitlementMode;
use App\Models\Stratbook;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StratbookController extends Controller
{
    public function __construct(
        private StratbookRepository $stratbookRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teamId = (int) $request->team_id;

        // Membership check: only team members can list their stratbooks.
        $team = Team::findOrFail($teamId);
        if (! Auth::user()->belongsToTeam($team)) {
            return new JsonResponse(['message' => 'You do not have access to this team.'], 403);
        }

        return new JsonResponse($this->stratbookRepository->get($teamId), JsonResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $team = Team::findOrFail($request->team_id);
        if ($team->entitlement_mode === EntitlementMode::MapBased->value) {
            return new JsonResponse(['message' => 'Stratbooks are auto-provisioned for your team.'], 403);
        }

        return $this->stratbookRepository->create($request->team_id, $request->stratbook_name);
    }

    public function destroy(Stratbook $stratbook): JsonResponse
    {
        $team = $stratbook->team;
        if ($team && $team->entitlement_mode === EntitlementMode::MapBased->value) {
            return new JsonResponse(['message' => 'Stratbooks cannot be deleted for your team.'], 403);
        }

        return $this->stratbookRepository->destroy($stratbook->id);
    }

    public function update(Request $request, Stratbook $stratbook): JsonResponse
    {
        $team = $stratbook->team;
        if ($team && $team->entitlement_mode === EntitlementMode::MapBased->value) {
            return new JsonResponse(['message' => 'Stratbook names cannot be changed for your team.'], 403);
        }

        return $this->stratbookRepository->update($stratbook->id, $request->stratbook_name);
    }

    public function export(Request $request, Stratbook $stratbook)
    {
        return $this->stratbookRepository->export($stratbook, $request);
    }
}
