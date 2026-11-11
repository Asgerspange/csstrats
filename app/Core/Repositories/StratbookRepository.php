<?php

namespace App\Core\Repositories;

use App\Constants\AppErrorCode;
use App\Models\AdminDemoTactic;
use App\Models\Map;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Tactic;
use App\Models\Team;
use App\Services\PlanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StratbookRepository
{
    public function get(int $teamId)
    {
        return Stratbook::where('team_id', $teamId)->withCount('tactics')->get();
    }

    public function create($teamId, $stratbookName)
    {
        $team = Team::find($teamId);
        $id = Auth::id();

        if (! $this->isAdministrator($team, true)) {
            return new JsonResponse([
                'message' => 'You do not have permission to create a Stratbook for this team',
            ], 403);
        }

        // Tier gate: the team owner's plan sets the stratbook cap (Starter: 3,
        // Team/Pro: 8). Resolved through PlanService's full chain
        // (user_limits → legacy product → config fallback) so admins who
        // switch plans via /admin/users/{id}/plan are gated too.
        $owner = $team->owner;
        if ($owner) {
            $plan = app(PlanService::class)->forUser($owner);
            $current = $team->stratbooks()->count();
            if ($current >= $plan['max_stratbooks']) {
                return new JsonResponse([
                    'app_code' => AppErrorCode::PLAN_LIMIT_EXCEEDED,
                    'feature' => 'stratbooks',
                    'required_tier' => app(PlanService::class)->requiredTierForFeature('stratbooks'),
                    'limit' => $plan['max_stratbooks'],
                    'message' => 'You have reached the maximum number of stratbooks for this team. Upgrade to create more.',
                ], 403);
            }
        }

        $stratbook = Stratbook::create([
            'team_id' => $team->id,
            'created_by' => $id,
            'stratbook_name' => $stratbookName,
        ]);

        $team->save();

        return new JsonResponse($stratbook, JsonResponse::HTTP_CREATED);
    }

    public function destroy($id)
    {
        $stratbook = Stratbook::find($id);
        if (! $stratbook) {
            return response()->json([
                'message' => 'Stratbook not found',
            ], 404);
        }

        $team = $stratbook->team;
        if (! $this->isAdministrator($team, false)) {
            return response()->json([
                'message' => 'You do not have permission to delete this Stratbook',
            ], 403);
        }

        $stratbook->tactics()->delete();
        $stratbook->delete();

        return new JsonResponse([
            'message' => 'Stratbook deleted successfully',
        ], JsonResponse::HTTP_OK);
    }

    public function update(int $id, string $name)
    {
        $stratbook = Stratbook::find($id);

        $team = $stratbook->team;
        if (! $this->isAdministrator($team, true)) {
            return response()->json([
                'message' => 'You do not have permission to rename this Stratbook',
            ], 403);
        }

        $stratbook->update([
            'stratbook_name' => $name,
        ]);

        return new JsonResponse($stratbook, JsonResponse::HTTP_OK);
    }

    public function getTactics(Stratbook $stratbook, array $filters, int $page, int $perPage): JsonResponse
    {
        $stratbook = $stratbook->load('tactics.board', 'tactics.roundPlan');
        $tactics = $stratbook->tactics;

        if (isset($filters['side'])) {
            $tactics = $tactics->where('side', $filters['side']);
        }
        if (isset($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $tactics = $tactics->filter(fn ($tactic) => count(array_intersect($tags, $tactic->tags)) > 0);
        }
        if (isset($filters['map'])) {
            $tactics = $tactics->where('map', $filters['map']);
        }
        if (isset($filters['type'])) {
            $tactics = $tactics->where('type', $filters['type']);
        }

        $total = $tactics->count();
        $tactics = $tactics->sortBy('sort_order')->forPage($page, $perPage)->values();

        return new JsonResponse([
            'tactics' => $tactics,
            'count' => $total,
        ], JsonResponse::HTTP_OK);
    }

    public function reorderTactics(Stratbook $stratbook, array $items): JsonResponse
    {
        $team = $stratbook->team;
        if (! $this->isAdministrator($team, true)) {
            return new JsonResponse([
                'message' => 'You do not have permission to reorder tactics in this Stratbook',
            ], 403);
        }

        $tacticIds = collect($items)->pluck('id')->all();

        // Verify all tactics belong to this stratbook
        $count = StratbookTactic::where('stratbook_id', $stratbook->id)
            ->whereIn('id', $tacticIds)
            ->count();

        if ($count !== count($tacticIds)) {
            return new JsonResponse([
                'message' => 'One or more tactics do not belong to this Stratbook',
            ], 422);
        }

        foreach ($items as $item) {
            StratbookTactic::where('id', $item['id'])
                ->where('stratbook_id', $stratbook->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return new JsonResponse([
            'message' => 'Tactics reordered successfully',
        ], JsonResponse::HTTP_OK);
    }

    public function getTactic(int $stratbookId, int $tacticId): JsonResponse
    {
        $tactic = StratbookTactic::where('id', $tacticId)->with(['snapshot', 'demo', 'roundPlan'])->first();

        if (! $tactic) {
            return new JsonResponse([
                'message' => 'Tactic not found in this Stratbook',
            ], 404);
        }

        return new JsonResponse($tactic, JsonResponse::HTTP_OK);
    }

    public function addTacticToStratbook(int $stratbookId, int $tacticId): JsonResponse
    {
        $stratbook = Stratbook::find($stratbookId);
        if (! $stratbook) {
            return new JsonResponse([
                'message' => 'Stratbook not found',
            ], 404);
        }

        $team = $stratbook->team;
        if (! $this->isAdministrator($team, true)) {
            return new JsonResponse([
                'message' => 'You do not have permission to add tactics to this Stratbook',
            ], 403);
        }

        $tactic = Tactic::find($tacticId);
        if (! $tactic) {
            return new JsonResponse([
                'message' => 'Tactic not found',
            ], 404);
        }

        $existing = StratbookTactic::where('stratbook_id', $stratbookId)
            ->where('tactic_id', $tacticId)
            ->first();

        if ($existing) {
            return new JsonResponse([
                'message' => 'Tactic already exists in this Stratbook',
            ], 409);
        }

        $stratbookTactic = StratbookTactic::create($tactic->toArray() + [
            'stratbook_id' => $stratbookId,
            'tactic_id' => $tacticId,
            'name' => $this->generateTacticName($tactic),
        ]);

        $tacticDemo = $tactic->demo;
        if ($tacticDemo) {
            AdminDemoTactic::create([
                'tactic_id' => $tactic->id,
                'stratbook_tactic_id' => $stratbookTactic->id,
                'demo_id' => $tactic->demo->demo_id,
                'map' => $tactic->demo->map,
                'round' => $tactic->demo->round,
            ]);
        }

        return new JsonResponse([
            'message' => 'Tactic added to Stratbook successfully',
        ], JsonResponse::HTTP_CREATED);
    }

    public function createCustomTactic(int $stratbookId, array $data): JsonResponse
    {
        $stratbook = Stratbook::findOrFail($stratbookId);

        if (! $this->isAdministrator($stratbook->team, true)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $formattedPlayers = [];
        $formattedLoadouts = [];
        $formattedDescriptions = [];

        foreach ($data['players'] as $index => $pData) {
            $playerName = $pData['name'] ?: 'Player '.($index + 1);
            $description = $pData['description'] ?? '';

            // 1. For the "team" JSON column
            $formattedPlayers[] = [
                'name' => $playerName,
                'role' => $pData['role'] ?? 'Player',
            ];

            $formattedDescriptions[] = [
                'player' => $playerName,
                'descriptions' => $description,
            ];

            $formattedLoadouts[] = [
                'player' => $playerName,
                'loadouts' => $pData['loadout'] ?? [],
            ];
        }

        $tactic = StratbookTactic::create([
            'stratbook_id' => $stratbookId,
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? '',
            'map' => Map::find($data['map'])->display_name ?? $data['map'],
            'side' => $data['side'],
            'team' => [
                'name' => $stratbook->team->name,
                'players' => $formattedPlayers,
            ],
            'player_loadouts' => $formattedLoadouts,
            'player_descriptions' => $formattedDescriptions, // Added this
            'player_timestamps' => [],                 // Added this to prevent next error
            'tags' => $data['tags'] ?? [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new JsonResponse($tactic, 201);
    }

    public function updateCustomTactic(int $id, array $data): JsonResponse
    {
        $tactic = StratbookTactic::findOrFail($id);
        $stratbook = $tactic->stratbook;

        if (! $this->isAdministrator($stratbook->team, true)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        // Similar formatting logic as createCustomTactic
        $formattedPlayers = [];
        $formattedLoadouts = [];
        $formattedDescriptions = [];

        foreach ($data['players'] as $index => $pData) {
            $playerName = $pData['name'] ?: 'Player '.($index + 1);
            $description = $pData['description'] ?? '';

            $formattedPlayers[] = [
                'name' => $playerName,
                'role' => $pData['role'] ?? 'Player',
            ];

            $formattedDescriptions[] = [
                'player' => $playerName,
                'descriptions' => $description,
            ];

            $formattedLoadouts[] = [
                'player' => $playerName,
                'loadouts' => $pData['loadout'] ?? [],
            ];
        }

        $tactic->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? '',
            'map' => Map::find($data['map'])->display_name ?? $data['map'],
            'side' => $data['side'],
            'team' => [
                'name' => $stratbook->team->name,
                'players' => $formattedPlayers,
            ],
            'player_loadouts' => $formattedLoadouts,
            'player_descriptions' => $formattedDescriptions, // Added this
            'player_timestamps' => [],                 // Added this to prevent next error
            'tags' => $data['tags'] ?? [],
            'updated_at' => now(),
        ]);

        return new JsonResponse($tactic, 200);
    }

    public function deleteCustomTactic(Stratbook $stratbook, int $tacticId): JsonResponse
    {
        $tactic = StratbookTactic::findOrFail($tacticId);

        if (! $this->isAdministrator($stratbook->team, true)) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $tactic->delete();

        return new JsonResponse(['message' => 'Tactic deleted successfully'], 200);
    }

    /**
     * Generate a descriptive name when copying a library tactic into a
     * stratbook. The library tactics table has no `name` column, so we
     * derive one from the pro team, type, side, and map.
     */
    private function generateTacticName(Tactic $tactic): string
    {
        $parts = [];
        if ($tactic->team_name) $parts[] = $tactic->team_name;
        if ($tactic->type) $parts[] = $tactic->type;
        if ($tactic->side) $parts[] = $tactic->side;
        if ($tactic->map) $parts[] = $tactic->map;

        if (empty($parts)) {
            return 'Tactic';
        }

        return implode(' ', $parts);
    }

    private function isAdministrator($team, $iglCheck = true)
    {
        $userId = Auth::id();

        $userRole = $team->roles->where('user_id', $userId)->first();
        $role = $userRole->role ?? 'Player';

        $editUsers = $team->edit_users;
        $adminUsers = $team->admin_users;

        if ($iglCheck) {
            if ((isset($editUsers) && in_array($userId, $editUsers)) || (isset($adminUsers) && in_array($userId, $adminUsers))) {
                return true;
            }

            return $team->owned_by == $userId || $role == 'Coach' || $role == 'IGL' || $role == 'Owner' || $role == 'Captain';
        }

        if (isset($adminUsers) && in_array($userId, $adminUsers)) {
            return true;
        }

        return $team->owned_by == $userId || $role == 'Coach' || $role == 'Owner';
    }

    public function export(Stratbook $stratbook, $request)
    {
        $query = StratbookTactic::where('stratbook_id', $stratbook->id);

        if ($request->has('tactic_ids') && is_array($request->tactic_ids)) {
            $query->whereIn('id', $request->tactic_ids);
        }

        $sortRules = $request->get('sort_rules', []);
        $allowedColumns = ['name', 'side', 'map', 'type', 'created_at'];

        if (! empty($sortRules)) {
            foreach ($sortRules as $rule) {
                $column = $rule['column'] ?? null;
                $direction = strtolower($rule['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                if (in_array($column, $allowedColumns)) {
                    $query->orderBy($column, $direction);
                }
            }
        } else {
            $query->orderBy('side', 'asc')->orderBy('name', 'asc');
        }

        $tactics = $query->get();

        $formattedTactics = $tactics->map(function ($tactic) {
            $players = collect($tactic->team['players'])->map(function ($player, $i) use ($tactic) {

                $desc = $tactic->player_descriptions[$i] ?? null;
                $load = $tactic->player_loadouts[$i] ?? null;

                return [
                    'name' => $player['name'],
                    'role' => $player['role'] ?? 'Player',
                    'description' => $desc['descriptions'] ?? 'No instructions.',
                    'loadout' => $load['loadouts'] ?? [],
                ];
            });

            return [
                'data' => $tactic,
                'players' => $players,
            ];
        });

        $pdf = Pdf::loadView('pdf.stratbook', [
            'tactics' => $formattedTactics,
            'bookName' => 'Stratbook Export - '.now()->format('Y-m-d'),
            'includeToc' => $request->boolean('include_toc', true),
            'onePerPage' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('stratbook_export_'.now()->timestamp.'.pdf');
    }
}
