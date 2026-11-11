<?php

namespace App\Core\Repositories;

use App\Models\StratbookTactic;
use App\Models\Tactic;
use App\Models\User;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TacticRepository
{
    private $cacheService;

    public function __construct()
    {
        $this->cacheService = new CacheService;
    }

    public function get(Request $request)
    {
        $perPage = $request->get('perPage', 20);
        $page = $request->get('page', 1);
        $maps = $this->cacheService->getMaps();

        $tactics = $this->cacheService->getTactics();

        if ($request->has('map') && ! empty($request->map)) {
            $mapName = $maps->firstWhere('id', $request->map)->display_name ?? null;
            $tactics = $tactics->where('map', $mapName);
        }

        if ($request->has('teams') && ! empty($request->input('teams'))) {
            // Accept either ['Vitality', ...] (what the dropdown emits) or
            // [['name' => 'Vitality'], ...] (legacy object form).
            $teams = array_values(array_filter(array_map(function ($team) {
                return is_array($team) ? ($team['name'] ?? null) : $team;
            }, $request->input('teams'))));
            $tactics = $tactics->whereIn('team_name', $teams);
        }

        if ($request->has('side') && ! empty($request->side)) {
            $tactics = $tactics->where('side', $request->side);
        }

        if ($request->has('tags') && ! empty($request->tags)) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            foreach ($tags as $tag) {
                $tactics = $tactics->filter(function ($tactic) use ($tag) {
                    return in_array($tag, $tactic->tags);
                });
            }
        }

        if ($request->has('type') && ! empty($request->type)) {
            $tactics = $tactics->where('type', $request->type);
        }

        // "Tactics my team uses": library tactics the team has saved into any
        // of its stratbooks. Derived from stratbook_tactics, no schema change.
        if ($request->filled('team_used')) {
            $ids = StratbookTactic::whereNotNull('tactic_id')
                ->whereHas('stratbook', function ($q) use ($request) {
                    $q->where('team_id', $request->team_used);
                })
                ->pluck('tactic_id')
                ->unique()
                ->all();
            $tactics = $tactics->whereIn('id', $ids);
        }

        $paginated = $tactics->forPage($page, $perPage)->values();

        return [
            'tactics' => $paginated,
            'count' => $tactics->count(),
        ];
    }

    public function findById(int $id)
    {
        return Tactic::with('demo')->find($id);
    }

    public function create(array $team, array $player_descriptions, array $player_timestamps, array $player_loadouts, string $type, string $description, array $tags, string $map, string $side, string $video, string $matchdate, string $matchlink)
    {
        return Tactic::create([
            'team_name' => $team['name'],
            'team' => $team,
            'player_descriptions' => $player_descriptions,
            'player_loadouts' => $player_loadouts,
            'player_timestamps' => $player_timestamps,
            'type' => $type,
            'description' => $description,
            'tags' => $tags,
            'map' => $map,
            'matchdate' => $matchdate,
            'matchlink' => $matchlink,
            'video' => $video,
            'side' => $side,
            'created_by' => app('user')->id,
        ]);
    }

    public function update(int $id, array $team, array $player_descriptions, array $player_timestamps, array $player_loadouts, string $type, string $description, array $tags, string $map, string $side, string $video, string $matchdate, string $matchlink)
    {
        Tactic::withUnreleased()->where('id', $id)->first()
            ->update([
                'team_name' => $team['name'],
                'team' => $team,
                'player_descriptions' => $player_descriptions,
                'player_loadouts' => $player_loadouts,
                'player_timestamps' => $player_timestamps,
                'type' => $type,
                'description' => $description,
                'tags' => $tags,
                'map' => $map,
                'matchdate' => $matchdate,
                'matchlink' => $matchlink,
                'video' => $video,
                'side' => $side,
            ]);
    }

    public function delete(int $id, string $name)
    {
        $tactic = Tactic::withUnreleased()->find($id);

        $tactic->deleted_by = $name;
        $tactic->deleted_at = now();
        $tactic->save();
    }

    public function stalker()
    {
        $endDate = Carbon::now()->endOfMonth();
        $startDate = $endDate->copy()->subMonths(12)->startOfMonth();

        $months = collect();
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addMonth()) {
            $months->put($date->format('F Y'), 0);
        }

        $tactics = Tactic::with('user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('created_by')
            ->map(function ($userTactics) use ($months) {
                $monthlyCounts = $userTactics->groupBy(function ($tactic) {
                    return $tactic->created_at->format('F Y');
                })->map(function ($monthGroup) {
                    return $monthGroup->count();
                });

                return $months->merge($monthlyCounts);
            })
            ->mapWithKeys(function ($monthlyCounts, $userId) {
                $user = User::find($userId);
                $userName = $user ? $user->name : 'Unknown User';

                return [$userName => $monthlyCounts];
            })
            ->sortKeys();

        return $tactics;
    }

    public function stalkerLastMonth()
    {
        $endDate = Carbon::now()->endOfMonth();
        $startDate = $endDate->copy()->subMonths(1)->startOfMonth();

        $days = collect();
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $days->put($date->format('Y-m-d'), 0);
        }

        $tactics = Tactic::with('user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('created_by')
            ->map(function ($userTactics) use ($days) {
                $dailyCounts = $userTactics->groupBy(function ($tactic) {
                    return $tactic->created_at->format('Y-m-d');
                })->map(function ($dateGroup) {
                    return $dateGroup->count();
                });

                return $days->merge($dailyCounts);
            })
            ->mapWithKeys(function ($dailyCounts, $userId) {
                $user = User::find($userId);
                $userName = $user ? $user->name : 'Unknown User';

                return [$userName => $dailyCounts];
            })
            ->sortKeys();

        return [
            'tactics' => $tactics,
            'total' => $tactics->sum(function ($dailyCounts) {
                return $dailyCounts->sum();
            }),
        ];
    }

    public function getSideTactics(string $side)
    {
        return Tactic::select('type')
            ->where('side', $side)
            ->groupBy('type')
            ->selectRaw('count(*) as count')
            ->get();
    }

    public function countCreations()
    {
        return Tactic::join('users', 'tactics.created_by', '=', 'users.id')
            ->select('users.name as user_name')
            ->selectRaw('count(*) as total')
            ->groupBy('users.name')
            ->get()
            ->mapWithKeys(function ($tactic) {
                return [$tactic->user_name => $tactic->total];
            })
            ->sortKeys()->toArray();
    }

    public function totalStratbookTactics()
    {
        $startThisMonth = Carbon::now()->startOfMonth();
        $endThisMonth = Carbon::now()->endOfMonth();
        $startLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endLastMonth = Carbon::now()->subMonth()->endOfMonth();
        $stratbooktactics = StratbookTactic::withTrashed()->get();

        $active = $stratbooktactics->whereNull('deleted_at');
        $stratbookTacticsThisMonth = $stratbooktactics->whereBetween('created_at', [$startThisMonth, $endThisMonth]);
        $stratbookTacticsLastMonth = $stratbooktactics->whereBetween('created_at', [$startLastMonth, $endLastMonth]);

        return [
            'total' => $stratbooktactics->count(),
            'this_month' => $stratbookTacticsThisMonth->count(),
            'last_month' => $stratbookTacticsLastMonth->count(),
            'active' => $active->count(),
        ];
    }

    public function totalTactics()
    {
        $startThisMonth = Carbon::now()->startOfMonth();
        $endThisMonth = Carbon::now()->endOfMonth();
        $startLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endLastMonth = Carbon::now()->subMonth()->endOfMonth();
        $tactics = Tactic::withTrashed()->get();

        $active = $tactics->whereNull('deleted_at');
        $tacticsThisMonth = $tactics->whereBetween('created_at', [$startThisMonth, $endThisMonth]);
        $tacticsLastMonth = $tactics->whereBetween('created_at', [$startLastMonth, $endLastMonth]);

        return [
            'total' => $tactics->count(),
            'this_month' => $tacticsThisMonth->count(),
            'last_month' => $tacticsLastMonth->count(),
            'active' => $active->count(),
        ];
    }

    public function tacticMaps()
    {
        return Tactic::select('map')
            ->selectRaw('count(*) as count')
            ->groupBy('map')
            ->get()
            ->toArray();
    }
}
