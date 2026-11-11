<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $table = 'social_posts';

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_SENT       = 'sent';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_SKIPPED    = 'skipped';

    public const PLATFORM_X              = 'x';
    public const PLATFORM_TIKTOK         = 'tiktok';
    public const PLATFORM_YOUTUBE_SHORTS = 'youtube_shorts';
    public const PLATFORM_THREADS        = 'threads';

    public const PILLAR_TACTIC_OF_THE_DAY   = 'tactic_of_the_day';
    public const PILLAR_DEMO_MOMENT         = 'demo_moment';
    public const PILLAR_PRO_MATCH_BREAKDOWN = 'pro_match_breakdown';
    public const PILLAR_WORKFLOW            = 'workflow';
    public const PILLAR_QUIET_PRODUCT_SHOW  = 'quiet_product_show';
    public const PILLAR_REPLY_QT            = 'reply_qt';

    public const SERIES_NADE_OF_THE_DAY = 'nade_of_the_day';
    public const SERIES_TACTIC_TUESDAY  = 'tactic_tuesday';
    public const SERIES_TOP_PLAYS       = 'top_plays';
    public const SERIES_ROUND_BREAKDOWN = 'round_breakdown';

    protected $guarded = ['id'];

    protected $casts = [
        'platforms'           => 'array',
        'overrides'           => 'array',
        'published_urls'      => 'array',
        'scheduled_at'        => 'datetime',
        'approved_at'         => 'datetime',
        'published_at'        => 'datetime',
        'last_attempted_at'   => 'datetime',
    ];

    /**
     * Returns the body to use for a given platform, falling back to the
     * default body if no override exists.
     */
    public function bodyFor(string $platform): string
    {
        $overrides = $this->overrides ?? [];

        if (isset($overrides[$platform]['body'])) {
            return $overrides[$platform]['body'];
        }

        if ($platform === self::PLATFORM_TIKTOK && isset($overrides[$platform]['caption'])) {
            return $overrides[$platform]['caption'];
        }

        if ($platform === self::PLATFORM_YOUTUBE_SHORTS && isset($overrides[$platform]['description'])) {
            return $overrides[$platform]['description'];
        }

        return $this->body;
    }

    /**
     * The UTM-tagged link for a platform.
     */
    public function ctaUrlFor(string $platform): string
    {
        $params = http_build_query([
            'utm_source'   => $platform,
            'utm_medium'   => $this->utm_medium,
            'utm_campaign' => $this->campaign,
        ]);

        return 'https://csstrats.dk/?' . $params;
    }

    public function isPublishable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }
}
