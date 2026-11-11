<?php

namespace App\Http\Controllers;

use App\Core\Repositories\TacticRepository;
use App\Models\AdminDemoTactic;
use App\Models\Tactic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TacticController extends Controller
{
    public function __construct(
        private TacticRepository $tacticRepository
    ) {}

    public function index(Request $request)
    {
        return new JsonResponse($this->tacticRepository->get($request), JsonResponse::HTTP_OK);
    }

    public function show($id)
    {
        $tactic = $this->tacticRepository->findById($id);
        if (! $tactic) {
            return new JsonResponse([], 404);
        }

        return new JsonResponse($tactic, 200);
    }

    public function updateTactic(Request $request)
    {
        if (! $this->checkAuthToken(Auth::user()->auth_token)) {
            return new JsonResponse([], 403);
        }
        $this->tacticRepository->update($request->id, $request->team, $request->player_descriptions, $request->player_timestamps, $request->player_loadouts, $request->type, $request->description, $request->tags, $request->map, $request->side, $request->video, $request->matchdate, $request->matchlink);

        return new JsonResponse([], 200);
    }

    public function store(Request $request)
    {
        $this->tacticRepository->create($request->team, $request->player_descriptions, $request->player_timestamps, $request->player_loadouts, $request->type, $request->description, $request->tags, $request->map, $request->side, $request->video, $request->matchdate, $request->matchlink);

        return new JsonResponse([], 201);
    }

    public function destroy(Request $request)
    {
        if (! $this->checkAuthToken(Auth::user()->auth_token)) {
            return new JsonResponse([], 403);
        }

        return new JsonResponse($this->tacticRepository->delete($request->id, $request->user['name']));
    }

    /* ----------------------------------------------------------------------
     | Admin CRUD (guarded by the `admin` middleware on /api/admin/tactics)
     | The repository reads the current user via app('user'); that binding
     | only exists on the /{vue?} web route, so we bind it explicitly here.
     * -------------------------------------------------------------------- */

    public function adminStore(Request $request)
    {
        app()->instance('user', $request->user());

        $data = $this->validateTactic($request);

        $tactic = $this->tacticRepository->create(
            $data['team'],
            $data['player_descriptions'],
            $data['player_timestamps'],
            $data['player_loadouts'],
            $data['type'],
            $data['description'],
            $data['tags'],
            $data['map'],
            $data['side'],
            $data['video'],
            $data['matchdate'],
            $data['matchlink'],
        );

        $tacticId = is_object($tactic) ? ($tactic->id ?? null) : ($tactic['id'] ?? null);
        $this->syncDemoLink($tacticId, $data);

        return new JsonResponse($tactic, JsonResponse::HTTP_CREATED);
    }

    public function adminShow($id)
    {
        $tactic = Tactic::withUnreleased()->with('demo')->find($id);

        if (! $tactic) {
            return new JsonResponse([], 404);
        }

        return new JsonResponse($tactic, 200);
    }

    public function adminUpdate(Request $request, $id)
    {
        app()->instance('user', $request->user());

        $data = $this->validateTactic($request);

        $this->tacticRepository->update(
            (int) $id,
            $data['team'],
            $data['player_descriptions'],
            $data['player_timestamps'],
            $data['player_loadouts'],
            $data['type'],
            $data['description'],
            $data['tags'],
            $data['map'],
            $data['side'],
            $data['video'],
            $data['matchdate'],
            $data['matchlink'],
        );

        $this->syncDemoLink((int) $id, $data);

        return new JsonResponse([], 200);
    }

    public function adminDestroy(Request $request, $id)
    {
        $this->tacticRepository->delete((int) $id, $request->user()->name);

        return new JsonResponse([], 200);
    }

    /**
     * Validate and normalise an incoming tactic payload. Optional fields are
     * coerced to the types the repository expects (arrays / strings).
     */
    private function validateTactic(Request $request): array
    {
        $validated = $request->validate([
            'team' => 'required|array',
            'team.name' => 'required|string',
            'type' => 'required|string',
            'map' => 'required|string',
            'side' => 'required|string|in:CT,T',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'player_descriptions' => 'nullable|array',
            'player_timestamps' => 'nullable|array',
            'player_loadouts' => 'nullable|array',
            'video' => 'nullable|string',
            'matchdate' => 'nullable|string',
            'matchlink' => 'nullable|string',
            'demo_id' => 'nullable|integer',
            'demo_round' => 'nullable|integer',
            'demo_map' => 'nullable|string',
        ]);

        return [
            'team' => $validated['team'],
            'player_descriptions' => $validated['player_descriptions'] ?? [],
            'player_timestamps' => $validated['player_timestamps'] ?? [],
            'player_loadouts' => $validated['player_loadouts'] ?? [],
            'type' => $validated['type'],
            'description' => (string) ($validated['description'] ?? ''),
            'tags' => $validated['tags'] ?? [],
            'map' => $validated['map'],
            'side' => $validated['side'],
            'video' => (string) ($validated['video'] ?? ''),
            'matchdate' => (string) ($validated['matchdate'] ?? ''),
            'matchlink' => (string) ($validated['matchlink'] ?? ''),
            'demo_id' => $validated['demo_id'] ?? null,
            'demo_round' => $validated['demo_round'] ?? null,
            'demo_map' => $validated['demo_map'] ?? null,
        ];
    }

    /**
     * Link (or unlink) a curated demo + round to a template tactic. The
     * template link has a null stratbook_tactic_id; StratbookRepository copies
     * it per stratbook when a tactic is added to a stratbook.
     */
    private function syncDemoLink(?int $tacticId, array $data): void
    {
        if (! $tacticId) {
            return;
        }

        if (empty($data['demo_id'])) {
            AdminDemoTactic::where('tactic_id', $tacticId)
                ->whereNull('stratbook_tactic_id')
                ->delete();
            return;
        }

        AdminDemoTactic::updateOrCreate(
            ['tactic_id' => $tacticId, 'stratbook_tactic_id' => null],
            ['demo_id' => $data['demo_id'], 'round' => $data['demo_round'], 'map' => $data['demo_map']]
        );
    }

    private function checkAuthToken($auth_token)
    {
        $auth_tokens = [
            '1ccb8cb34f44d974072df121671fcf333a601eb90622b833a87634d17eb4',
            'a99391e2c474f11aacf75cfcf7715c3aff89f935b95bbe6bccd816b80d38',
            '7ea20137fac4c3e1248c06342ee551e76c427e145f26c5b32ef952d1b66d',
            'ef5aca95222421f6da2b9f89e659db998b5792fd497dc3247c74d8720aae',
            '33f75c4af49357e8bd98d5935a3d4a66982f67807543c9526c32b1af5cb7',
            'ac291a3ca0ba7c8740bf53f743a01db96cef4dd78fce7a822051c035dd37',
        ];

        return in_array($auth_token, $auth_tokens);
    }
}
