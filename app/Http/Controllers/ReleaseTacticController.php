<?php

namespace App\Http\Controllers;

use App\Jobs\UploadTacticToYouTubeJob;
use App\Models\DetectedTactic;
use App\Models\Tactic;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReleaseTacticController extends Controller
{
    private CacheService $cacheService;

    public function __construct()
    {
        $this->cacheService = new CacheService;
    }

    /**
     * JSON release-management lists for the vue-router admin page.
     * Unreleased tactics with a future release_date are "scheduled".
     */
    public function apiIndex()
    {
        $unreleasedTactics = Tactic::onlyUnreleased()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'team', 'type', 'description', 'created_at', 'updated_at', 'release_date']);

        $releasedTactics = Tactic::onlyReleased()
            ->orderBy('release_date', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'team', 'type', 'description', 'created_at', 'updated_at', 'release_date', 'is_featured']);

        return response()->json([
            'unreleased' => $unreleasedTactics,
            'released' => $releasedTactics,
        ]);
    }

    /**
     * Release a single tactic immediately
     */
    public function releaseSingle(Request $request, $id)
    {
        $tactic = Tactic::withUnreleased()->findOrFail($id);

        $tactic->update([
            'is_released' => true,
            'release_date' => now(),
        ]);

        $this->cacheService->refreshTacticsCache();

        return response()->json(['success' => true, 'message' => 'Tactic released successfully']);
    }

    /**
     * Release multiple tactics immediately
     */
    public function releaseMultiple(Request $request)
    {
        $request->validate([
            'tactic_ids' => 'required|array',
            'tactic_ids.*' => 'exists:tactics,id',
        ]);

        $count = Tactic::withUnreleased()
            ->whereIn('id', $request->tactic_ids)
            ->update([
                'is_released' => true,
                'release_date' => now(),
            ]);

        $this->cacheService->refreshTacticsCache();

        return response()->json(['success' => true, 'message' => "{$count} tactics released successfully"]);
    }

    /**
     * Schedule tactics for release on a specific date
     */
    public function scheduleRelease(Request $request)
    {
        $request->validate([
            'tactic_ids' => 'required|array',
            'tactic_ids.*' => 'exists:tactics,id',
            'release_date' => 'required|date|after:now',
        ]);

        $releaseDate = Carbon::parse($request->release_date);

        $count = Tactic::withUnreleased()
            ->whereIn('id', $request->tactic_ids)
            ->update([
                'release_date' => $releaseDate,
            ]);

        return response()->json(['success' => true, 'message' => "{$count} tactics scheduled for release on {$releaseDate->format('Y-m-d H:i:s')}"]);
    }

    /**
     * Cancel scheduled release for a tactic
     */
    public function cancelScheduledRelease(Request $request, $id)
    {
        $tactic = Tactic::withUnreleased()->findOrFail($id);

        if (! $tactic->release_date) {
            return response()->json([
                'success' => false,
                'message' => 'Tactic is not scheduled for release',
            ], 400);
        }

        $tactic->update([
            'release_date' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Scheduled release cancelled successfully']);
    }

    /**
     * Release X amount of tactics on a specific date
     */
    public function releaseAmount(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'release_date' => 'required|date|after:now',
        ]);

        $releaseDate = Carbon::parse($request->release_date);

        // Get the specified amount of unreleased tactics (you can add ordering logic here)
        $tactics = Tactic::onlyUnreleased()
            ->orderBy('created_at', 'asc') // or any other ordering logic
            ->take($request->amount)
            ->get();

        if ($tactics->count() < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => "Only {$tactics->count()} unreleased tactics available, but {$request->amount} requested",
            ], 400);
        }

        $tacticIds = $tactics->pluck('id')->toArray();

        Tactic::whereIn('id', $tacticIds)
            ->update([
                'release_date' => $releaseDate,
            ]);

        return response()->json(['success' => true, 'message' => "{$tactics->count()} tactics scheduled for release on {$releaseDate->format('Y-m-d H:i:s')}"]);
    }

    /**
     * Process scheduled releases (call this from a scheduled job)
     */
    public function processScheduledReleases()
    {
        $tacticsToRelease = Tactic::withUnreleased()
            ->where('is_released', false)
            ->where('release_date', '<=', now())
            ->whereNotNull('release_date')
            ->get();

        foreach ($tacticsToRelease as $tactic) {
            $tactic->update(['is_released' => true]);
        }

        $this->cacheService->refreshTacticsCache();

        return response()->json([
            'success' => true,
            'message' => 'Scheduled tactics processed successfully',
            'count' => $tacticsToRelease->count(),
        ]);
    }

    /**
     * Unreleased a tactic (admin function)
     */
    public function unreleased(Request $request, $id)
    {
        $tactic = Tactic::withUnreleased()->findOrFail($id);

        $tactic->update([
            'is_released' => false,
            'release_date' => null,
        ]);

        $this->cacheService->refreshTacticsCache();

        return response()->json(['success' => true, 'message' => 'Tactic unreleased successfully']);
    }

    /**
     * Feature a released tactic as the public tactic of the week.
     * Only one tactic is featured at a time.
     */
    public function feature(Request $request, $id)
    {
        $tactic = Tactic::findOrFail($id);

        Tactic::withUnreleased()->where('is_featured', true)->update(['is_featured' => false]);
        $tactic->update(['is_featured' => true]);

        $this->cacheService->refreshTacticsCache();

        return response()->json(['success' => true, 'message' => 'Tactic featured as tactic of the week']);
    }

    /**
     * Remove the featured flag from a tactic
     */
    public function unfeature(Request $request, $id)
    {
        $tactic = Tactic::withUnreleased()->findOrFail($id);
        $tactic->update(['is_featured' => false]);

        $this->cacheService->refreshTacticsCache();

        return response()->json(['success' => true, 'message' => 'Tactic unfeatured']);
    }

    /**
     * Get tactics scheduled for release
     */
    public function getScheduled()
    {
        $scheduledTactics = Tactic::withUnreleased()
            ->where('is_released', false)
            ->whereNotNull('release_date')
            ->orderBy('release_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $scheduledTactics,
        ]);
    }

    /**
     * Release a detected tactic: dispatches YouTube upload job (hidden/unlisted)
     * and marks the tactic as released when upload completes.
     */
    public function releaseDetectedTactic(int $id)
    {
        $tactic = DetectedTactic::findOrFail($id);

        if (! in_array($tactic->status, ['recorded'])) {
            return response()->json(['message' => 'Tactic must be recorded before releasing'], 422);
        }

        if (! $tactic->tactic_id) {
            return response()->json(['message' => 'Tactic must be linked to a library tactic first'], 422);
        }

        UploadTacticToYouTubeJob::dispatch($tactic->id);

        return response()->json(['message' => 'YouTube upload dispatched']);
    }
}
