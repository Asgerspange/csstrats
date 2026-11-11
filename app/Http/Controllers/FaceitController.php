<?php

namespace App\Http\Controllers;

use App\Models\QueuedDemo;
use App\Enums\QueuedDemoStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FaceitController extends Controller
{
    private $dataApiKey;
    private $downloadsToken;

    public function __construct()
    {
        $this->dataApiKey = config('services.faceit.data_api_key');
        $this->downloadsToken = config('services.faceit.data_api_key'); // Exclusive Access Token
    }
    public function getRecentMatches()
    {
        $user = auth()->user();
        if (!$user->faceit_id) {
            return response()->json(['error' => 'Faceit not linked'], 400);
        }

        $apiKey = config('services.faceit.data_api_key');

        // 1. Fetch History
        $historyResponse = Http::withToken($apiKey)
            ->get("https://open.faceit.com/data/v4/players/{$user->faceit_id}/history", [
                'game' => 'cs2',
                'limit' => 20
            ]);

        $historyItems = $historyResponse->json()['items'] ?? [];
        
        // 2. Optimization: Get all already queued IDs in one query to mark them in the UI
        $existingMatchIds = QueuedDemo::whereIn('faceit_match_id', collect($historyItems)->pluck('match_id'))
            ->pluck('faceit_match_id')
            ->toArray();

        $matches = collect($historyItems)->map(function ($match) use ($apiKey, $user, $existingMatchIds) {
            $matchId = $match['match_id'];

            // 3. Fetch Detailed Stats for the expansion view
            $statsResponse = Http::withToken($apiKey)
                ->get("https://open.faceit.com/data/v4/matches/{$matchId}/stats")
                ->json();

            $round = $statsResponse['rounds'][0] ?? null;
            $mapName = $round['round_stats']['Map'] ?? ($match['i1'] ?? 'Unknown');
            $mapNameShort = strtoupper(str_replace('de_', '', $mapName));

            $userFaction = collect($match['teams']['faction1']['players'])
                ->contains('player_id', $user->faceit_id) ? 'faction1' : 'faction2';
            $oppFaction = ($userFaction === 'faction1') ? 'faction2' : 'faction1';

            $teamStats = collect($round['teams'] ?? [])
                ->first(fn($t) => collect($t['players'])->contains('player_id', $user->faceit_id));

            $players = collect($teamStats['players'] ?? [])->map(fn($p) => [
                'nickname' => $p['nickname'],
                'k'  => $p['player_stats']['Kills'],
                'd'  => $p['player_stats']['Deaths'],
                'kd' => $p['player_stats']['K/D Ratio'],
                'hs' => $p['player_stats']['Headshots %'] . '%',
            ])->sortByDesc('k')->values();

            return [
                'match_id'  => $matchId,
                'is_queued' => in_array($matchId, $existingMatchIds), // Boolean for the Vue button
                'map'       => $mapNameShort,
                'date'      => $match['finished_at'],
                'my_team'   => $match['teams'][$userFaction]['nickname'],
                'opp_team'  => $match['teams'][$oppFaction]['nickname'],
                'score'     => "{$match['results']['score'][$userFaction]} - {$match['results']['score'][$oppFaction]}",
                'result'    => $match['results']['winner'] === $userFaction ? 'WIN' : 'LOSS',
                'players'   => $players,
            ];
        });

        return response()->json($matches);
    }

    public function queueMatch(Request $request)
    {
        $request->validate([
            'match_id' => 'required',
            'my_team'  => 'required',
            'opp_team' => 'required'
        ]);

        $user = auth()->user();

        // 1. Prevent Duplicates
        if (QueuedDemo::where('faceit_match_id', $request->match_id)->exists()) {
            return response()->json(['error' => 'Match already queued'], 409);
        }

        // 2. Fetch the Raw Resource URL (Uses Data API Key)
        $matchData = Http::withToken($this->dataApiKey)
            ->get("https://open.faceit.com/data/v4/matches/{$request->match_id}")
            ->throw()
            ->json();

        $resourceUrl = $matchData['demo_url'][0] ?? null;

        if (!$resourceUrl) {
            return response()->json(['error' => 'Demo resource URL not found'], 404);
        }

        // 4. Request the Signed URL (Uses Exclusive Downloads Token)
        $signedResponse = Http::withToken($this->downloadsToken)
            ->post("https://open.faceit.com/download/v2/demos/download", [
                'resource_url' => $resourceUrl
            ]);

        // Check if the API actually returned a success code
        if ($signedResponse->failed()) {
            return response()->json([
                'error' => 'FACEIT Downloads API rejected the request',
                'details' => $signedResponse->json() ?? $signedResponse->body()
            ], $signedResponse->status());
        }

        $signedUrl = $signedResponse->json()['payload']['download_url'] ?? null;

        if (!$signedUrl) {
            return response()->json(['error' => 'Signed URL missing from payload'], 500);
        }

        // 5. Create the Queued Record
        QueuedDemo::create([
            'faceit_match_id' => $request->match_id,
            'owner'           => $user->id,
            'status'          => QueuedDemoStatus::Pending,
            'name'            => "{$request->my_team} vs {$request->opp_team}",
            'metadata'        => [
                'source'   => 'faceit',
                'demo_url' => $signedUrl
            ]
        ]);

        return response()->json(['success' => true]);
    }
}