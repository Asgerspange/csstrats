<?php

namespace App\Services;

use Google\Client;
use Google\Service\YouTube;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeUploadService
{
    private ?YouTube $youtube = null;

    private function getClient(): YouTube
    {
        if ($this->youtube) {
            return $this->youtube;
        }

        $client = new Client;
        $client->setClientId(config('services.youtube.client_id'));
        $client->setClientSecret(config('services.youtube.client_secret'));
        $client->setRedirectUri(config('services.youtube.redirect_uri'));
        $client->setScopes([YouTube::YOUTUBE_UPLOAD]);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        $refreshToken = config('services.youtube.refresh_token');
        if ($refreshToken) {
            $client->refreshToken($refreshToken);
        }

        $this->youtube = new YouTube($client);

        return $this->youtube;
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.youtube.refresh_token');
    }

    /**
     * Upload a video to YouTube as unlisted (hidden from public).
     * Returns the YouTube video ID.
     */
    public function uploadVideo(
        string $videoUrl,
        string $title,
        string $description,
        array $tags = [],
    ): string {
        $youtube = $this->getClient();

        // Download the video to a temp file
        $tempPath = tempnam(sys_get_temp_dir(), 'yt_').'.mp4';
        $response = Http::timeout(300)->get($videoUrl);
        if ($response->failed()) {
            throw new \RuntimeException('Failed to download video from '.$videoUrl);
        }
        file_put_contents($tempPath, $response->body());

        // Build the video snippet
        $snippet = new \Google\Service\YouTube\VideoSnippet;
        $snippet->setTitle($title);
        $snippet->setDescription($description);
        $snippet->setTags(array_merge($tags, ['CS2', 'Counter-Strike', 'Tactics', 'CSStrats']));
        $snippet->setCategoryId('20'); // Gaming

        // Set privacy status to unlisted (hidden from search/public)
        $status = new \Google\Service\YouTube\VideoStatus;
        $status->setPrivacyStatus('unlisted');
        $status->setSelfDeclaredMadeForKids(false);

        $video = new \Google\Service\YouTube\Video;
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Resumable upload
        $client = $youtube->getClient();
        $mimeType = 'video/mp4';

        $params = [
            'uploadType' => 'resumable',
        ];

        // Create the resumable upload session
        $response = $client->execute(
            $youtube->videos->insert('snippet,status', $video, $params),
            $mimeType
        );

        // Upload the file in chunks
        $chunkSizeBytes = 10 * 1024 * 1024; // 10MB chunks
        $client->setDefer(true);

        $media = new \Google\Http\MediaFileUpload(
            $client,
            $response->getHeaders(),
            $mimeType,
            null,
            true,
            $chunkSizeBytes
        );

        $handle = fopen($tempPath, 'rb');
        $status = null;
        while (! $status && ! feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($handle);
        }
        fclose($handle);
        $client->setDefer(false);

        @unlink($tempPath);

        $videoId = $status['id'] ?? null;
        if (! $videoId) {
            throw new \RuntimeException('YouTube upload failed: no video ID returned');
        }

        Log::info('YouTubeUploadService: video uploaded', [
            'video_id' => $videoId,
            'title' => $title,
        ]);

        return $videoId;
    }

    /**
     * Build a YouTube description with chapter timestamps.
     * YouTube auto-detects chapters when timestamps start at 0:00.
     */
    public function buildDescription(
        string $teamName,
        string $patternType,
        string $map,
        int $round,
        array $povDurations = [],
        array $keyTicks = [],
        int $startTick = 0,
    ): string {
        $lines = [];
        $lines[] = "{$teamName} {$patternType} on {$map} - Round {$round}";
        $lines[] = '';
        $lines[] = 'Analyzed on CSStrats.dk - Pro CS2 tactics';
        $lines[] = '';
        $lines[] = 'Chapters:';

        // POV chapters if multi-POV
        if (! empty($povDurations)) {
            $offsetSeconds = 0;
            foreach ($povDurations as $i => $pov) {
                $mins = intdiv($offsetSeconds, 60);
                $secs = $offsetSeconds % 60;
                $time = sprintf('%d:%02d', $mins, $secs);
                $lines[] = "{$time} - {$pov['name']} POV";
                $offsetSeconds += (int) ceil(($pov['duration_ms'] ?? 0) / 1000);
            }
        } else {
            // Key tick chapters
            $tickRate = 64;
            $lines[] = '0:00 - Round Start';
            foreach ($keyTicks as $kt) {
                $seconds = max(0, intdiv((int) $kt['tick'] - $startTick, $tickRate));
                $mins = intdiv($seconds, 60);
                $secs = $seconds % 60;
                $time = sprintf('%d:%02d', $mins, $secs);
                $lines[] = "{$time} - {$kt['label']}";
            }
        }

        $lines[] = '';
        $lines[] = 'https://csstrats.dk';

        return implode("\n", $lines);
    }

    /**
     * Build a YouTube embed URL from a video ID.
     */
    public static function embedUrl(string $videoId): string
    {
        return "https://www.youtube.com/embed/{$videoId}";
    }
}
