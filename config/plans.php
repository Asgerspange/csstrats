<?php

/*
|---------------------------------------------------------------------------
| Plan catalogue — the single source of truth.
|---------------------------------------------------------------------------
|
| Three tiers (Starter, Team, Pro) across three billing cadences
| (monthly, every 6 months, yearly). The Paddle product + price IDs, the
| feature limits, and the feature lists shown on the landing page all live
| here. Read by:
|
|   - WelcomeController::getProducts()   → marketing pricing surface
|   - App\Services\PlanService           → in-app limit + tier resolution
|   - App\Http\Controllers\Admin\AdminPlanController → admin plan switcher
|
| Keep the price IDs in sync with the Paddle dashboard. The `tier` key is the
| canonical name used everywhere in the app (never the cadence-suffixed
| product name).
*/

return [

    'tiers' => [
        'Starter' => [
            'product_id' => 'pro_01kxt3dm64cxmx5b3jc7tehsb7',
            'price_ids' => [
                'monthly' => 'pri_01kxt3feqsyhv1m2691g6kj77t',
                'semi' => 'pri_01kxt3j0kx4eyzpfabpad69eh6',
                'yearly' => 'pri_01kxt3mq4yr43fqev0mkx3akyk',
            ],
            'limits' => [
                'max_members' => 8,
                'max_teams' => 1,
                'max_stratbooks' => 4,
                'max_maps' => 4,
                'max_demos' => 5,
                'ai_antistrat' => false,
                'round_planner' => false,
            ],
            'features' => [
                '1 team with up to 8 additional members',
                '4 maps',
                'Unlimited tactics',
                '5 demo uploads',
                '2D Demo Viewer',
                'Drawing Board',
            ],
        ],

        'Team' => [
            'product_id' => 'pro_01kxt1r6sj0jxkkgkqthqfp23d',
            'price_ids' => [
                'monthly' => 'pri_01kxt1vzm8nmpjxx1310zrfndx',
                'semi' => 'pri_01kxt2mec8f8ktr9hq1gg4kr6s',
                'yearly' => 'pri_01kxt2p68pfew8rj3hwvdy93vx',
            ],
            'limits' => [
                'max_members' => 8,
                'max_teams' => 1,
                'max_stratbooks' => 8,
                'max_maps' => 6,
                'max_demos' => 100000,
                'ai_antistrat' => false,
                'round_planner' => true,
            ],
            'features' => [
                'Everything in Starter, plus:',
                '2 additional maps (6 total)',
                'Unlimited demos',
                '2D Demo Viewer',
                'Round Planner',
                'Fast support',
            ],
        ],

        'Pro' => [
            'product_id' => 'pro_01knkqqfagz4d150ed0jzvfk92',
            'price_ids' => [
                'monthly' => 'pri_01knkqr9rkfh17krq0g8ststp9',
                'semi' => 'pri_01knkqv141eagxvm04mhz8k3a4',
                'yearly' => 'pri_01knkr0de7mhft5agb5ywhgyrf',
            ],
            'limits' => [
                'max_members' => 8,
                'max_teams' => 1,
                'max_stratbooks' => 8,
                'max_maps' => null,
                'max_demos' => 100000,
                'ai_antistrat' => true,
                'round_planner' => true,
            ],
            'features' => [
                'Everything in Team, plus:',
                'All maps unlocked',
                'AI Antistrat',
                '3D Demo Viewer',
                'Priority support',
            ],
        ]
    ],

    // Cadence display metadata + savings copy. Order matters: the tab order
    // on the pricing surface and the admin switcher both read this array.
    'cadences' => [
        'monthly' => ['label' => 'Monthly',       'save' => null,           'note' => 'No commitment'],
        'semi' => ['label' => 'Every 6 months', 'save' => 'Save 20%',   'note' => ''],
        'yearly' => ['label' => 'Yearly',         'save' => 'Save 36.6%',   'note' => ''],
    ],

    // Marker written to user_limits.paddle_subscription_id when an admin
    // overrides a plan, so the code can tell an admin-set plan apart from a
    // real Paddle-driven subscription.
    'admin_override_marker' => 'admin-override',

    // Default limits applied when a user has no plan at all (no Paddle sub,
    // no admin override). Mirrors Starter so the gating code always has a
    // baseline to compare against.
    'fallback_limits' => [
        'max_members' => 8,
        'max_teams' => 1,
        'max_stratbooks' => 4,
        'max_maps' => 4,
        'max_demos' => 5,
        'ai_antistrat' => false,
        'round_planner' => false,
    ],

    // Limits applied to users still on the legacy Stripe billing path.
    // Stripe subs are being retired; these users keep access (CheckPermissions
    // still passes) but feature creation is squeezed so they hit the upgrade
    // modal and migrate to Paddle. max_members / max_teams stay permissive so
    // an existing roster doesn't break while they migrate. The frontend reads
    // `needs_migration` to tailor the upgrade modal's copy.
    'migration_limits' => [
        'max_members' => 8,
        'max_teams' => 1,
        'max_stratbooks' => 0,
        'max_maps' => 0,
        'max_demos' => 0,
        'ai_antistrat' => false,
        'round_planner' => false,
    ],
];
