<?php

namespace App\Http\Controllers;

use App\Models\OpponentTeam;
use App\Models\OpponentTeamSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpponentSuggestionsController extends Controller
{
    /**
     * GET /api/opponent-suggestions
     * Returns undismissed suggestions detected from the user's own demos.
     */
    public function index(Request $request): JsonResponse
    {
        $suggestions = OpponentTeamSuggestion::where('user_id', Auth::id())
            ->where('dismissed', false)
            ->with('demo:id,name,map,created_at')
            ->orderByDesc('updated_at')
            ->get();

        return new JsonResponse($suggestions);
    }

    /**
     * POST /api/opponent-suggestions/{id}/dismiss
     * User doesn't want to create a profile for this team.
     */
    public function dismiss(int $id): JsonResponse
    {
        $suggestion = OpponentTeamSuggestion::where('user_id', Auth::id())->findOrFail($id);

        $suggestion->update(['dismissed' => true]);

        return new JsonResponse(['message' => 'Dismissed']);
    }

    /**
     * POST /api/opponent-suggestions/{id}/create-profile
     * Creates an OpponentTeam from the suggestion and links the source demo.
     * Returns the new opponent team.
     */
    public function createProfile(Request $request, int $id): JsonResponse
    {
        $suggestion = OpponentTeamSuggestion::where('user_id', Auth::id())->findOrFail($id);

        // Create the opponent profile, owned by the user.
        $opponent = OpponentTeam::create([
            'user_id' => $suggestion->user_id,
            'name' => $suggestion->clan_name,
        ]);

        // Link the source demo
        $opponent->demos()->syncWithoutDetaching([$suggestion->demo_id]);

        // Upsert all known players
        foreach (($suggestion->steamids ?? []) as $i => $steamid) {
            $steamid = (string) $steamid;
            if (! $steamid || $steamid === '0') {
                continue;
            }

            \App\Models\OpponentTeamPlayer::updateOrCreate(
                ['opponent_team_id' => $opponent->id, 'steamid' => $steamid],
                ['name' => $suggestion->player_names[$i] ?? 'Unknown'],
            );
        }

        // Dismiss the suggestion
        $suggestion->update(['dismissed' => true]);

        return new JsonResponse($opponent->load('players', 'sharedTeams:id,name'), 201);
    }
}
