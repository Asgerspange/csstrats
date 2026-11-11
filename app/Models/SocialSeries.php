<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialSeries extends Model
{
    protected $table = 'social_series';

    protected $guarded = ['id'];

    protected $casts = [
        'coverage_log' => 'array',
    ];

    /**
     * Atomically reserves the next entry number for this series and appends
     * a coverage entry. Use this from the API when the agent inserts a new
     * series post — guarantees no duplicate entry numbers under concurrency.
     */
    public function reserveNextEntry(array $coverage): int
    {
        return \DB::transaction(function () use ($coverage) {
            $row = self::lockForUpdate()->find($this->id);
            $next = $row->last_entry + 1;

            $log = $row->coverage_log ?? [];
            $log[] = array_merge($coverage, [
                'n'            => $next,
                'reserved_at'  => now()->toIso8601String(),
            ]);

            // Keep the log bounded — only the last 200 entries matter for
            // the "have we covered this already?" check.
            if (count($log) > 200) {
                $log = array_slice($log, -200);
            }

            $row->update([
                'last_entry'   => $next,
                'coverage_log' => $log,
            ]);

            return $next;
        });
    }
}
