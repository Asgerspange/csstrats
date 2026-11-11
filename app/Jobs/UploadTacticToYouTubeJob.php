<?php

namespace App\Jobs;

use App\Models\DetectedTactic;
use App\Services\YouTubeUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadTacticToYouTubeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly int $detectedTacticId
    ) {}

    public function handle(): void
    {
        $tactic = DetectedTactic::with('demo')->find($this->detectedTacticId);
        if (! $tactic) {
            Log::error('UploadTacticToYouTube: tactic not found', ['id' => $this->detectedTacticId]);

            return;
        }

        if (! $tactic->video_url) {
            Log::error('UploadTacticToYouTube: no video URL', ['id' => $this->detectedTacticId]);

            return;
        }

        $service = app(YouTubeUploadService::class);
        if (! $service->isConfigured()) {
            Log::warning('UploadTacticToYouTube: YouTube not configured, skipping');

            return;
        }

        $mapName = $tactic->demo ? $tactic->demo->map : 'unknown';
        if (! $mapName) {
            $mapName = 'unknown';
        }        $title = sprintf(
            '%s %s on %s - Round %d',
            $tactic->team_name ?? 'Team',
            $tactic->pattern_type,
            $mapName,
            $tactic->round_index
        );

        $description = $service->buildDescription(
            teamName: $tactic->team_name ?? 'Team',
            patternType: $tactic->pattern_type,
            map: $mapName,
            round: $tactic->round_index,
            povDurations: $tactic->pov_durations ?? [],
            keyTicks: $tactic->key_ticks ?? [],
            startTick: $tactic->start_tick ?? 0,
        );

        try {
            $videoId = $service->uploadVideo(
                videoUrl: $tactic->video_url,
                title: $title,
                description: $description,
                tags: [$mapName, $tactic->pattern_type, $tactic->side],
            );

            // Update the linked tactic with the YouTube embed
            if ($tactic->tactic_id) {
                $tactic->tactic()->update([
                    'video' => YouTubeUploadService::embedUrl($videoId),
                    'is_released' => true,
                ]);
            }

            $tactic->status = 'released';
            $tactic->save();

            Log::info('UploadTacticToYouTube: released', [
                'tactic_id' => $tactic->id,
                'youtube_video_id' => $videoId,
            ]);
        } catch (\Throwable $e) {
            Log::error('UploadTacticToYouTube: failed', [
                'tactic_id' => $tactic->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
