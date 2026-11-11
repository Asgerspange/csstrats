<?php

namespace App\Http\Controllers;

use App\Core\Repositories\StratbookRepository;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StratbookTacticController extends Controller
{
    public function __construct(
        private StratbookRepository $stratbookRepository
    ) {}

    public function index(Request $request, Stratbook $stratbook): JsonResponse
    {
        $filters = $request->only(['side', 'tags', 'map', 'type']);
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 35);

        return $this->stratbookRepository->getTactics($stratbook, $filters, $page, $perPage);
    }

    public function find(Stratbook $stratbook, int $tacticId): JsonResponse
    {
        return $this->stratbookRepository->getTactic($stratbook->id, $tacticId);
    }

    /**
     * Source library-tactic ids present in this stratbook, so the library
     * can flag tactics already added to the user's target stratbook.
     */
    public function tacticIds(Stratbook $stratbook): JsonResponse
    {
        $ids = StratbookTactic::where('stratbook_id', $stratbook->id)
            ->whereNotNull('tactic_id')
            ->pluck('tactic_id')
            ->unique()
            ->values();

        return new JsonResponse($ids, JsonResponse::HTTP_OK);
    }

    public function store(Request $request, Stratbook $stratbook): JsonResponse
    {
        $tacticId = $request->input('tacticId');

        return $this->stratbookRepository->addTacticToStratbook($stratbook->id, $tacticId);
    }

    public function all(Request $request): JsonResponse
    {
        $teamId = $request->input('team_id');
        $team = Team::find($teamId);

        $query = StratbookTactic::with('stratbook')
            ->whereHas('stratbook', function ($q) use ($teamId) {
                $q->where('team_id', $teamId);
            });

        // For map_based teams, filter to accessible maps only
        if ($team && $team->entitlement_mode === 'map_based') {
            $entitlement = app(\App\Services\EntitlementService::class);
            $accessibleIds = $entitlement->accessibleMapIds($team);
            $query->whereHas('stratbook', function ($q) use ($accessibleIds) {
                $q->whereIn('map_id', $accessibleIds);
            });
        }

        $tactics = $query->get()
            ->groupBy(fn ($item) => $item->stratbook->stratbook_name)
            ->map(function ($items, $stratbookName) {
                return [
                    'label' => $stratbookName,
                    'items' => $items->map(function ($item) {
                        return [
                            'label' => $item->name,
                            'value' => $item->id,
                            'map' => $item->map,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return new JsonResponse($tactics, JsonResponse::HTTP_OK);
    }

    public function storeCustom(Request $request, Stratbook $stratbook): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'map' => 'required|int',
            'side' => 'required|in:CT,T',
            'type' => 'required|string',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'players' => 'required|array|min:1|max:10',
            'players.*.name' => 'required|string',
            'players.*.description' => 'nullable|string',
            'players.*.loadout' => 'nullable|array',
            'players.*.role' => 'nullable|string',
        ]);

        return $this->stratbookRepository->createCustomTactic($stratbook->id, $validated);
    }

    public function export(Stratbook $stratbook, int $tacticId)
    {
        $tactic = StratbookTactic::findOrFail($tacticId);

        // Map the player data together for easier looping in Blade
        // Since loadouts and descriptions are separate JSON arrays in your DB
        $players = collect($tactic->team['players'])->map(function ($player) use ($tactic) {
            $desc = collect($tactic->player_descriptions)->firstWhere('player', $player['name']);
            $load = collect($tactic->player_loadouts)->firstWhere('player', $player['name']);
            if (is_null($desc)) {
                $desc = collect($tactic->player_descriptions)->firstWhere(fn ($d) => strtolower($d['player']['name']) === strtolower($player['name']));
            }
            if (is_null($load)) {
                $load = collect($tactic->player_loadouts)->firstWhere(fn ($l) => strtolower($l['player']['name']) === strtolower($player['name']));
            }

            return [
                'name' => $player['name'],
                'role' => $player['role'],
                'description' => $desc['descriptions'] ?? 'No instructions.',
                'loadout' => $load['loadouts'] ?? [],
            ];
        });

        $pdf = Pdf::loadView('pdf.tactic', compact('tactic', 'players'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("{$tactic->map}_{$tactic->name}.pdf");
    }

    public function update(Request $request, Stratbook $stratbook, int $tacticId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'map' => 'required|int',
            'side' => 'required|in:CT,T',
            'type' => 'required|string',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'players' => 'required|array|min:1|max:10',
            'players.*.name' => 'required|string',
            'players.*.description' => 'nullable|string',
            'players.*.loadout' => 'nullable|array',
            'players.*.role' => 'nullable|string',
        ]);

        return $this->stratbookRepository->updateCustomTactic($tacticId, $validated);
    }

    public function deleteCustom(Request $request, Stratbook $stratbook, int $tacticId): JsonResponse
    {
        return $this->stratbookRepository->deleteCustomTactic($stratbook, $tacticId);
    }

    /**
     * Reorder tactics within a stratbook (drag-and-drop sort)
     */
    public function reorder(Request $request, Stratbook $stratbook): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        return $this->stratbookRepository->reorderTactics($stratbook, $validated['items']);
    }
}
