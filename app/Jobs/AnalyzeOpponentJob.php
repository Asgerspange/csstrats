<?php

namespace App\Jobs;

use App\Models\{DemoStat, OpponentTeam};
use App\Services\AntiStratAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

class AnalyzeOpponentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 3 minutes — AI calls can be slow.
     */
    public int $timeout = 180;

    /**
     * Only retry once on failure — AI timeouts shouldn't spam the queue.
     */
    public int $tries = 2;

    public function __construct(
        private readonly int $opponentTeamId
    ) {}

    public function handle(AntiStratAiService $aiService): void
    {
        $opponent = OpponentTeam::find($this->opponentTeamId);

        if (!$opponent) {
            Log::warning("AnalyzeOpponentJob: opponent {$this->opponentTeamId} not found");
            return;
        }

        try {
            $demoIds = $opponent->demos()->pluck('demos.id')->toArray();

            if (empty($demoIds)) {
                $opponent->update([
                    'insights_status' => 'failed',
                    'insights_error'  => 'No demos linked to this opponent.',
                ]);
                return;
            }

            $statsRows = DemoStat::whereIn('demo_id', $demoIds)->with('demo:id,map')->get();
            $players   = $opponent->players()->get()->keyBy('steamid');

            $insights = $aiService->analyzeOpponent($opponent, $statsRows, $players);

            if (isset($insights['error'])) {
                $opponent->update([
                    'insights_status' => 'failed',
                    'insights_error'  => $insights['error'],
                ]);
                return;
            }

            $opponent->update([
                'ai_insights'         => $insights,
                'insights_updated_at' => now(),
                'insights_status'     => 'complete',
                'insights_error'      => null,
            ]);

            Log::info("AnalyzeOpponentJob: completed for opponent {$this->opponentTeamId}");

        } catch (\Exception $e) {
            Log::error("AnalyzeOpponentJob: failed for opponent {$this->opponentTeamId}", [
                'message' => $e->getMessage(),
            ]);

            $opponent->update([
                'insights_status' => 'failed',
                'insights_error'  => $e->getMessage(),
            ]);

            throw $e; // re-throw so queue marks it as failed
        }
    }
}