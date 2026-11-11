<?php

namespace App\Services;

use App\Models\DetectedTactic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubtickRecordingClient
{
    private string $apiUrl;

    private string $key;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('services.subtick_record.api_url'), '/');
        $this->key = (string) config('services.subtick_record.key');
    }

    public function isConfigured(): bool
    {
        return $this->apiUrl !== '' && $this->key !== '';
    }

    /**
     * Dispatch a recording job to subtick for all player POVs.
     * Returns the clip IDs assigned by subtick.
     */
    public function dispatchRecording(DetectedTactic $tactic, string $demoUrl): array
    {
        $povs = [];
        $steamids = $tactic->player_steamids ?? [];
        $names = $tactic->player_names ?? [];

        for ($i = 0; $i < count($steamids); $i++) {
            $povs[] = [
                'steamid' => $steamids[$i],
                'name' => $names[$i] ?? 'Player '.$i,
            ];
        }

        $callbackUrl = rtrim((string) config('app.url'), '/').'/api/admin/detected-tactics/recording-callback';

        $response = Http::withHeaders([
            'X-Server-Key' => $this->key,
        ])->timeout(30)->post($this->apiUrl.'/record-external', [
            'demo_url' => $demoUrl,
            'start_tick' => $tactic->start_tick,
            'end_tick' => $tactic->end_tick,
            'povs' => $povs,
            'map' => $tactic->demo ? $tactic->demo->map : 'unknown',
            'external_ref' => (string) $tactic->id,
            'callback_url' => $callbackUrl,
            'callback_key' => config('services.subtick_record.callback_key'),
        ]);

        if ($response->failed()) {
            Log::error('SubtickRecordingClient: dispatch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Subtick recording dispatch failed: '.$response->body());
        }

        return $response->json();
    }
}
