<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $demo_id
 * @property int $round_index
 * @property string $side
 * @property string $pattern_type
 * @property string|null $pattern_subtype
 * @property string|null $bombsite
 * @property float $confidence
 * @property float $tactical_value_score
 * @property array|null $evidence
 * @property string|null $description
 * @property int|null $start_tick
 * @property int|null $end_tick
 * @property array|null $key_ticks
 * @property array|null $player_steamids
 * @property array|null $player_names
 * @property string|null $team_name
 * @property string|null $opponent_name
 * @property string $status
 * @property string|null $recording_error
 * @property string|null $video_url
 * @property array|null $video_renditions
 * @property int|null $duration_ms
 * @property int|null $tactic_id
 * @property array|null $pov_durations
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DetectedTactic extends Model
{
    protected $connection = 'mysql';

    protected $table = 'detected_tactics';

    protected $fillable = [
        'demo_id',
        'round_index',
        'side',
        'pattern_type',
        'pattern_subtype',
        'bombsite',
        'confidence',
        'tactical_value_score',
        'evidence',
        'description',
        'start_tick',
        'end_tick',
        'key_ticks',
        'player_steamids',
        'player_names',
        'team_name',
        'opponent_name',
        'status',
        'recording_error',
        'video_url',
        'video_renditions',
        'duration_ms',
        'tactic_id',
        'pov_durations',
    ];

    protected $casts = [
        'evidence' => 'array',
        'key_ticks' => 'array',
        'player_steamids' => 'array',
        'player_names' => 'array',
        'video_renditions' => 'array',
        'pov_durations' => 'array',
        'confidence' => 'float',
        'tactical_value_score' => 'float',
    ];

    public function demo(): BelongsTo
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }

    public function tactic(): BelongsTo
    {
        return $this->belongsTo(Tactic::class, 'tactic_id');
    }
}
