<?php

namespace App\Http\Controllers;

use App\Constants\AppErrorCode;
use App\Enums\QueuedDemoStatus;
use App\Models\Demo;
use App\Models\DemoEnrichmentJob;
use App\Models\DemoFolder;
use App\Models\DemoRound;
use App\Models\QueuedDemo;
use App\Services\EntitlementService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    private $imageController;

    public function __construct(ImageController $imageController)
    {
        $this->imageController = $imageController;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Curated demos for the admin tactic editor's demo picker.
        if ($request->boolean('curated')) {
            return new JsonResponse(
                Demo::where('is_admin', true)->orderBy('id', 'desc')->get(),
                JsonResponse::HTTP_OK
            );
        }

        $teams = $user->teams;

        $hideFolderDemos = $request->boolean('hideFolderDemos');

        $demos = $this->getDemos($teams, $hideFolderDemos);
        $queuedDemos = QueuedDemo::where('owner', $user->id)->whereIn('status', [QueuedDemoStatus::Uploading, QueuedDemoStatus::Pending, QueuedDemoStatus::Failed, QueuedDemoStatus::Processing])->get();

        $demos = array_merge($queuedDemos->toArray(), $demos->toArray());

        return new JsonResponse($demos, JsonResponse::HTTP_OK);
    }

    public function show(Request $request, int $id)
    {
        ini_set('memory_limit', '1024M');
        $teams = $request->user()->teams;
        $teamIds = $teams->pluck('id')->toArray();

        $demo = $this->secureDemoQuery($teamIds, true)
            ->with(['rounds' => function ($query) {
                $query->orderBy('round_index', 'asc')
                    ->where('round_index', '>', 0)
                    ->select('meta', 'demo_id', 'round_index', 'curated');
            }, 'stats', 'utility', 'heatmap', 'lastRound', 'mapMetadata'])
            ->orderBy('id', 'desc')
            ->findOrFail($id);

        // Map access gate for map_based teams
        $team = $teams->firstWhere('id', $demo->owner);
        if (! $team) {
            foreach ($teams as $t) {
                if ($t->owned_by === $demo->owner || $t->users->contains('id', $demo->owner)) {
                    $team = $t;
                    break;
                }
            }
        }
        if ($team && $team->entitlement_mode === 'map_based' && $demo->map) {
            $entitlement = app(EntitlementService::class);
            $mapId = $entitlement->mapNameToId($demo->map);
            if ($mapId !== null && ! $entitlement->canAccessMap($team, $mapId)) {
                return new JsonResponse([
                    'app_code' => AppErrorCode::NO_MAP_ACCESS,
                    'message' => 'This map is not in your plan.',
                ], 403);
            }
        }

        // array map each round
        $demo->rounds = $demo->rounds->map(function ($round) {
            $round->win_reason = $round->meta['reason'] ?? null;
            $round->start_tick = $round->meta['start'] ?? null;
            $round->end_tick = $round->meta['official_end'] ?? null;
            $round->freeze_end_tick = $round->meta['freeze_end'] ?? null;

            return $round;
        });

        // Surface the latest enrichment job so the UI can show "saving as admin demo…".
        $demo->enrichment_status = DemoEnrichmentJob::where('demo_id', $id)->latest('id')->value('status');

        return new JsonResponse($demo, JsonResponse::HTTP_OK);
    }

    public function requestUpload(Request $request)
    {
        $user = $request->user();

        // Tier gate: the demo-upload cap (Starter: 2, Team/Pro: effectively
        // unlimited). Counts the user's parsed demos plus their queued
        // uploads so an in-flight upload still consumes a slot. Resolved
        // through PlanService so admins switching plans via /admin are gated.
        $plan = app(PlanService::class)->forUser($user);
        $parsedCount = Demo::where('owner', $user->id)->count();
        $queuedCount = QueuedDemo::where('owner', $user->id)
            ->whereIn('status', [
                QueuedDemoStatus::Uploading,
                QueuedDemoStatus::Pending,
                QueuedDemoStatus::Processing,
            ])->count();
        $current = $parsedCount + $queuedCount;
        if ($current >= $plan['max_demos']) {
            return new JsonResponse([
                'app_code' => AppErrorCode::PLAN_LIMIT_EXCEEDED,
                'feature' => 'demos',
                'required_tier' => app(PlanService::class)->requiredTierForFeature('demos'),
                'limit' => $plan['max_demos'],
                'message' => 'You have reached the demo upload limit for your plan. Upgrade to upload more demos.',
            ], 403);
        }

        $metadata = [
            'folder_id' => $request->folderId ?? null,
        ];

        $demo = QueuedDemo::create([
            'status' => QueuedDemoStatus::Uploading,
            'owner' => $user->id,
            'name' => str_replace('.dem', '', $request->fileName),
            'metadata' => $metadata,
        ]);

        return response()->json(['demoId' => $demo->id]);
    }

    public function upload(Request $request, int $demoId)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        if (session_id()) {
            session_write_close();
        }

        $uploadedFile = $request->file('file');

        if (! $uploadedFile || ! $uploadedFile->isValid()) {
            return response()->json(['error' => 'Invalid file chunk'], 400);
        }

        \App\Models\QueuedDemoChunk::insert([
            'queued_demo_id' => $demoId,
            'chunk_index' => $request->chunkIndex,
            'data' => file_get_contents($uploadedFile->getRealPath()),
        ]);

        return response()->json(['message' => 'Chunk received']);
    }

    public function finalizeUpload(Request $request, int $demoId)
    {
        $user = $request->user();
        $queuedDemo = QueuedDemo::where('id', $demoId)
            ->where('owner', $user->id)
            ->firstOrFail();

        $queuedDemo->status = QueuedDemoStatus::Pending;
        $queuedDemo->save();

        // Push trigger: tell the demoparser to pick it up immediately
        // instead of waiting for the next poll. Fire-and-forget — if it
        // fails the 3s poll fallback catches it.
        try {
            \Illuminate\Support\Facades\Http::timeout(2)->post(
                config('demos.parser_trigger_url', 'http://127.0.0.1:9090').'/trigger'
            );
        } catch (\Throwable) {
            // Non-fatal — the poll loop is the safety net.
        }

        return response()->json(['message' => 'Demo queued for processing']);
    }

    /**
     * Promote a parsed user demo to a curated "admin" demo and mark the chosen
     * rounds as curated. Repeatable — admins can add more rounds over time.
     * Enqueues a callout-enrichment job; the demoparser service stamps callouts
     * onto the chosen rounds' stored frame_data.
     */
    public function curateRounds(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rounds' => 'required|array|min:1',
            'rounds.*' => 'integer|min:0',
        ]);
        $rounds = array_values(array_unique($validated['rounds']));

        $demo = Demo::findOrFail($id);
        $demo->is_admin = true;
        $demo->owner = config('demos.admin_owner', 1);
        $demo->save();

        DemoRound::where('demo_id', $id)->whereIn('round_index', $rounds)->update(['curated' => true]);

        $job = DemoEnrichmentJob::create([
            'demo_id' => $id,
            'round_indices' => $rounds,
            'status' => QueuedDemoStatus::Pending->value,
        ]);

        return new JsonResponse(['status' => 'enriching', 'job_id' => $job->id], JsonResponse::HTTP_OK);
    }

    /**
     * Remove rounds from an admin demo's curated set (does not delete the demo
     * or its frame_data — just stops exposing those rounds for tactics).
     */
    public function uncurateRounds(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rounds' => 'required|array|min:1',
            'rounds.*' => 'integer|min:0',
        ]);

        DemoRound::where('demo_id', $id)
            ->whereIn('round_index', $validated['rounds'])
            ->update(['curated' => false]);

        return new JsonResponse(['message' => 'Rounds uncurated'], JsonResponse::HTTP_OK);
    }

    public function getChunk(Request $request)
    {
        $teamIds = $request->user()->teams->pluck('id')->toArray();
        $demoId = $request->query('demo_id');
        $roundIndex = (int) $request->query('round_index', 0);

        if (! $demoId || $roundIndex < 0) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        $demo = Demo::viewableOrCurated(Auth::id(), $teamIds)
            ->with(['rounds' => function ($query) use ($roundIndex) {
                $query->where('round_index', $roundIndex);
            }])
            ->findOrFail($demoId);

        $rounds = $demo->rounds;
        if (count($rounds) === 0) {
            return response()->json(['error' => 'Rounds not found'], 404);
        }

        return response($rounds[0]->frame_data)
            ->header('Content-Encoding', 'gzip')
            ->header('Content-Type', 'application/json');
    }

    public function update(Request $request, int $demoId)
    {
        $demo = Demo::where('owner', Auth::id())->findOrFail($demoId);

        if ($request->has('demo_name')) {
            $demo->name = $request->demo_name;
        }

        if ($request->has('shared_teams')) {
            $demo->sharedTeams()->sync($request->shared_teams);
        }

        if ($request->has('demo_folder_id')) {
            $folderId = $request->demo_folder_id;

            if ($folderId == 0) {
                $demo->folder()->detach();
            } else {
                $demoFolder = DemoFolder::with('sharedTeams')->findOrFail($folderId);
                $demoFolder->demos()->syncWithoutDetaching([$demoId]);
                $teamIds = $demoFolder->sharedTeams->pluck('id')->toArray();
                $demo->sharedTeams()->sync($teamIds);
            }
        }

        $demo->save();

        return response()->json(['message' => 'Demo shared successfully']);
    }

    public function shareFolder(Request $request, int $folderId)
    {
        $request->validate([
            'teams' => 'array',
            'teams.*' => 'integer|exists:teams,id',
        ]);

        $folder = DemoFolder::where('user_id', Auth::id())->findOrFail($folderId);
        $teamIds = $request->teams;

        // Recursive Sharing: Update folder, subfolders, and all demos within
        $this->syncFolderPermissionsRecursive($folder, $teamIds);

        return response()->json(['message' => 'Folder and all contained demos shared successfully']);
    }

    private function syncFolderPermissionsRecursive(DemoFolder $folder, array $teamIds)
    {
        // 1. Sync the folder itself
        $folder->sharedTeams()->sync($teamIds);

        // 2. Sync all demos associated with this folder
        $folder->demos()->each(function ($demo) use ($teamIds) {
            $demo->sharedTeams()->sync($teamIds);
        });

        // 3. Recurse into child folders
        $folder->children()->each(function ($child) use ($teamIds) {
            $this->syncFolderPermissionsRecursive($child, $teamIds);
        });
    }

    public function destroy(Request $request, int $demoId)
    {
        $user = $request->user();

        $demo = Demo::where('owner', $user->id)->where('id', $demoId)->first();
        if (! $demo) {
            return response()->json(['error' => 'Demo not found'], 418);
        }

        $demo->delete();

        return response()->json(['message' => 'Demo deleted successfully']);
    }

    public function destroyFailed(Request $request, int $demoId)
    {
        $demo = QueuedDemo::where('owner', Auth::id())
            ->where('id', $demoId)
            ->where('status', QueuedDemoStatus::Failed)
            ->first();
        if (! $demo) {
            return response()->json(['error' => 'Demo not found or not failed'], 418);
        }

        $demo->delete();

        return response()->json(['message' => 'Failed demo deleted successfully']);
    }

    private function secureDemoQuery($teamIds, bool $includeCurated = false)
    {
        return Demo::withRoundsCount()->where(function ($query) use ($teamIds, $includeCurated) {
            $query->where('owner', Auth::id())
                ->orWhereHas('sharedTeams', function ($sq) use ($teamIds) {
                    $sq->whereIn('teams.id', $teamIds);
                });
            // Curated admin demos are readable by any authenticated user. Opt-in
            // per-call so the personal demo list (getDemos) stays clean.
            if ($includeCurated) {
                $query->orWhere('is_admin', true);
            }
        });
    }

    private function getDemos($teams, $hideFolderDemos = false)
    {
        $teamIds = $teams->pluck('id')->toArray();

        $q = $this->secureDemoQuery($teamIds)->where('hide', 0);

        if ($hideFolderDemos) {
            $q->whereDoesntHave('folder');
        }

        return $q->orderBy('id', 'desc')->get()->map(function ($demo) {
            $allRounds = DemoRound::select('meta')->where('demo_id', $demo->id)->get()->toArray();
            $demo->lastRound = $allRounds ? end($allRounds) : null;

            return $demo;
        });
    }

    public function getDemoInfo(Request $request, int $id)
    {
        $teamIds = $request->user()->teams->pluck('id')->toArray();
        $demo = $this->secureDemoQuery($teamIds, true)->find($id);

        if (! $demo) {
            return response()->json(['error' => 'Demo not found'], 404);
        }

        return response()->json([
            'id' => $demo->id,
            'name' => $demo->name,
            'map' => $demo->map,
            'rounds' => $demo->rounds_count,
            'version' => $demo->version,
            'owner' => $demo->owner,
        ]);
    }

    public function getDemoRounds(Request $request, int $id)
    {
        $teamIds = $request->user()->teams->pluck('id')->toArray();
        $demo = $this->secureDemoQuery($teamIds, true)->find($id);

        if (! $demo) {
            return response()->json(['error' => 'Demo not found or access denied'], 404);
        }

        $rounds = DemoRound::where('demo_id', $id)
            ->orderBy('round_index', 'asc')
            ->select('meta', 'demo_id', 'round_index', 'curated')
            ->get();

        return response()->json($rounds);
    }
}
