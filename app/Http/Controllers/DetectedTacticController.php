<?php

namespace App\Http\Controllers;

use App\Models\Demo;
use App\Models\DetectedTactic;
use App\Services\SubtickRecordingClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DetectedTacticController extends Controller
{
    /**
     * List demos that have detected tactics, with counts.
     */
    public function scannedDemos(Request $request): JsonResponse
    {
        $query = DetectedTactic::query()
            ->join('demos', 'detected_tactics.demo_id', '=', 'demos.id')
            ->select('demos.id', 'demos.name', 'demos.map')
            ->selectRaw('COUNT(detected_tactics.id) as tactic_count')
            ->selectRaw('SUM(CASE WHEN detected_tactics.status = "detected" THEN 1 ELSE 0 END) as pending_count')
            ->selectRaw('MAX(detected_tactics.created_at) as latest_detection')
            ->groupBy('demos.id', 'demos.name', 'demos.map')
            ->orderByDesc('latest_detection');

        if ($request->has('status')) {
            $query->where('detected_tactics.status', $request->status);
        }

        $demos = $query->get()->map(function ($d) {
            return [
                'id' => (int) $d->id,
                'name' => $d->name,
                'map' => $d->map,
                'tactic_count' => (int) $d->tactic_count,
                'pending_count' => (int) $d->pending_count,
                'latest_detection' => $d->latest_detection,
            ];
        });

        return response()->json($demos);
    }

    /**
     * List detected tactics for a demo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DetectedTactic::query()
            ->orderByDesc('tactical_value_score');

        if ($request->has('demo_id')) {
            $query->where('demo_id', (int) $request->demo_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('pattern_type')) {
            $query->where('pattern_type', $request->pattern_type);
        }

        $tactics = $query->get();

        return response()->json($tactics);
    }

    /**
     * Show a single detected tactic with full evidence.
     */
    public function show(int $id): JsonResponse
    {
        $tactic = DetectedTactic::with(['demo:id,name,map'])->findOrFail($id);

        return response()->json($tactic);
    }

    /**
     * Reject a detected tactic.
     */
    public function reject(int $id): JsonResponse
    {
        $tactic = DetectedTactic::findOrFail($id);
        $tactic->status = 'rejected';
        $tactic->save();

        return response()->json(['message' => 'Tactic rejected']);
    }

    /**
     * Approve a detected tactic for recording.
     * Dispatches a multi-POV recording job to subtick.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $tactic = DetectedTactic::with('demo')->findOrFail($id);

        if ($tactic->status !== 'detected') {
            return response()->json(['message' => 'Tactic already processed'], 422);
        }

        $client = app(SubtickRecordingClient::class);
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Subtick recording not configured'], 503);
        }

        // Build the demo download URL — either from Bunny CDN (meta.dem_cdn_url)
        // or a signed URL to the csstrats demo download endpoint.
        $demoUrl = $tactic->demo->meta['dem_cdn_url'] ?? null;
        if (! $demoUrl) {
            // Fallback: signed URL to the csstrats .dem download endpoint
            $expires = time() + 3600;
            $token = hash_hmac('sha256', $tactic->demo_id.'|'.$expires, config('app.key'));
            $demoUrl = rtrim((string) config('app.url'), '/')."/api/demos/dl/{$expires}/{$token}/{$tactic->demo_id}.dem";
        }

        try {
            $result = $client->dispatchRecording($tactic, $demoUrl);

            $tactic->status = 'recording';
            $tactic->recording_error = null;
            $tactic->save();

            Log::info('DetectedTactic: approved for recording', [
                'tactic_id' => $tactic->id,
                'subtick_match_id' => $result['match_id'] ?? null,
                'clip_ids' => $result['clip_ids'] ?? [],
            ]);

            return response()->json([
                'message' => 'Recording dispatched',
                'subtick_match_id' => $result['match_id'] ?? null,
                'clip_ids' => $result['clip_ids'] ?? [],
            ]);
        } catch (\Throwable $e) {
            $tactic->status = 'detected';
            $tactic->recording_error = $e->getMessage();
            $tactic->save();

            return response()->json(['message' => 'Recording failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * Recording callback — called by subtick when all POV clips are done.
     * Updates the detected tactic with video URLs and POV durations.
     */
    public function recordingCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_ref' => 'required|string',
            'status' => 'required|string',
            'povs' => 'array',
            'povs.*.steamid' => 'string',
            'povs.*.name' => 'string',
            'povs.*.video_url' => 'string',
            'povs.*.renditions' => 'array',
            'povs.*.duration_ms' => 'integer',
            'povs.*.status' => 'string',
            'povs.*.error' => 'string',
        ]);

        $tactic = DetectedTactic::where('id', (int) $validated['external_ref'])->first();
        if (! $tactic) {
            return response()->json(['message' => 'Tactic not found'], 404);
        }

        if ($validated['status'] === 'ready') {
            $povs = $validated['povs'] ?? [];

            // Store all POV video URLs and durations
            $tactic->video_url = $povs[0]['video_url'] ?? null;
            $tactic->video_renditions = $povs[0]['renditions'] ?? null;
            $tactic->duration_ms = $povs[0]['duration_ms'] ?? null;
            $tactic->pov_durations = collect($povs)->map(fn ($p) => [
                'steamid' => $p['steamid'] ?? null,
                'name' => $p['name'] ?? null,
                'video_url' => $p['video_url'] ?? null,
                'duration_ms' => $p['duration_ms'] ?? null,
            ])->toArray();
            $tactic->status = 'recorded';
            $tactic->recording_error = null;
        } else {
            $errors = collect($validated['povs'] ?? [])->pluck('error')->filter()->implode('; ');
            $tactic->status = 'detected'; // Back to detected so admin can retry
            $tactic->recording_error = $errors ?: 'Recording failed';
        }

        $tactic->save();

        Log::info('DetectedTactic: recording callback', [
            'tactic_id' => $tactic->id,
            'status' => $validated['status'],
        ]);

        return response()->json(['message' => 'Callback received']);
    }

    /**
     * Retry a failed recording.
     */
    public function retryRecording(int $id): JsonResponse
    {
        $tactic = DetectedTactic::with('demo')->findOrFail($id);

        if (! in_array($tactic->status, ['detected']) && ! $tactic->recording_error) {
            return response()->json(['message' => 'Cannot retry'], 422);
        }

        return $this->approve(request(), $id);
    }

    /**
     * Link a detected tactic to a created library tactic.
     */
    public function link(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tactic_id' => 'required|integer',
        ]);

        $tactic = DetectedTactic::findOrFail($id);
        $tactic->tactic_id = $validated['tactic_id'];
        $tactic->status = 'recorded';
        $tactic->save();

        return response()->json(['message' => 'Tactic linked']);
    }
}
