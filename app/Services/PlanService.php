<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLimit;

/**
 * Resolves a user's effective plan: which tier they're on and what limits
 * apply. The single read path for every feature gate in the app.
 *
 * Resolution order (first non-null wins):
 *   1. user_limits row (Paddle webhook or admin override writes this)
 *   2. legacy Stripe product (max_members/max_teams/max_stratbooks columns)
 *   3. config/plans.php fallback_limits (Starter-equivalent baseline)
 *
 * Tier is derived from user_limits.paddle_price_id via the config/plans.php
 * price_id → tier map. A user with no Paddle sub and no admin override has
 * tier = null (the gating code still works against the fallback limits).
 *
 * Team inheritance: a member with no personal plan (or a lower tier than a
 * team owner) inherits the owner's plan while on that team. The highest tier
 * across the member's own plan and every team owner's plan wins. This flows
 * through every feature gate and the /api/user plan object automatically,
 * since they all call forUser(). is_admin_override and needs_migration are
 * per-user and never inherited.
 *
 * Limit values for a known tier are read from config/plans.tiers (the single
 * source of truth), not the per-column user_limits values. This keeps owners
 * and inherited members aligned with the tier catalogue regardless of which
 * columns the Paddle webhook happens to write.
 */
class PlanService
{
    /** @var array<string, mixed> */
    private array $tiers;

    /** @var array<string, string> price_id → tier name */
    private array $priceToTier;

    /** @var array<string, int> tier name → rank (for "highest tier wins" comparisons) */
    private array $tierOrder;

    public function __construct()
    {
        $this->tiers = config('plans.tiers', []);
        $this->priceToTier = [];
        foreach ($this->tiers as $tier => $cfg) {
            foreach ($cfg['price_ids'] ?? [] as $priceId) {
                $this->priceToTier[$priceId] = $tier;
            }
        }
        $this->tierOrder = array_flip(array_keys($this->tiers));
    }

    /**
     * The full plan descriptor for a user: tier name + every limit + flags.
     * Inherits the team owner's plan when it's higher than the user's own.
     *
     * @return array{tier: ?string, max_members: int, max_teams: int, max_stratbooks: int, max_maps: int|null, max_demos: int, ai_antistrat: bool, round_planner: bool, is_admin_override: bool, needs_migration: bool, paddle_price_id: ?string}
     */
    public function forUser(User $user): array
    {
        $own = $this->resolveOwnPlan($user);
        $inherited = $this->resolveInheritedPlan($user);

        // Highest tier wins; ties stay on the member's own plan.
        if ($inherited !== null && $this->tierRank($inherited['tier']) > $this->tierRank($own['tier'])) {
            // Inherited plans are never admin overrides; needs_migration is
            // a per-user billing flag and stays whatever the member's own
            // state resolved to.
            $inherited['is_admin_override'] = false;
            $inherited['needs_migration'] = $own['needs_migration'];

            return $inherited;
        }

        return $own;
    }

    /**
     * The member's own plan, ignoring any team-owner inheritance.
     */
    private function resolveOwnPlan(User $user): array
    {
        $limit = $user->userLimit;
        $fallback = config('plans.fallback_limits');

        if ($limit) {
            return $this->planFromLimit($limit);
        }

        // Legacy Stripe path: these subscriptions are being retired. Squeeze
        // feature creation so the user hits the upgrade modal and migrates to
        // Paddle. max_members / max_teams stay permissive so their existing
        // roster doesn't break mid-migration. needs_migration drives the
        // tailored copy in the in-app upgrade modal.
        $product = $user->product();
        if ($product && isset($product->max_members)) {
            $migration = config('plans.migration_limits');

            return [
                'tier' => null,
                'max_members' => (int) ($product->max_members ?? $migration['max_members']),
                'max_teams' => (int) ($product->max_teams ?? $migration['max_teams']),
                'max_stratbooks' => $migration['max_stratbooks'],
                'max_maps' => $migration['max_maps'],
                'max_demos' => $migration['max_demos'],
                'ai_antistrat' => $migration['ai_antistrat'],
                'round_planner' => $migration['round_planner'],
                'is_admin_override' => false,
                'needs_migration' => true,
                'paddle_price_id' => null,
            ];
        }

        return [
            'tier' => null,
            'max_members' => $fallback['max_members'],
            'max_teams' => $fallback['max_teams'],
            'max_stratbooks' => $fallback['max_stratbooks'],
            'max_maps' => $fallback['max_maps'],
            'max_demos' => $fallback['max_demos'],
            'ai_antistrat' => $fallback['ai_antistrat'],
            'round_planner' => $fallback['round_planner'],
            'is_admin_override' => false,
            'needs_migration' => false,
            'paddle_price_id' => null,
        ];
    }

    /**
     * Walk the user's teams and return the highest-tier owner plan, or null
     * if no owner has a plan worth inheriting. The member's own team (where
     * they are the owner) is skipped — their own plan is already considered.
     */
    private function resolveInheritedPlan(User $user): ?array
    {
        $user->loadMissing(['teams.owner.userLimit']);

        $best = null;
        foreach ($user->teams as $team) {
            if ($team->owned_by === $user->id) {
                continue;
            }
            $owner = $team->owner;
            if (! $owner) {
                continue;
            }
            $ownerLimit = $owner->userLimit;
            if (! $ownerLimit) {
                continue;
            }

            $candidate = $this->planFromLimit($ownerLimit);
            if ($best === null || $this->tierRank($candidate['tier']) > $this->tierRank($best['tier'])) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Build a plan descriptor from a user_limits row. When the price_id maps
     * to a known tier, every limit is read from config/plans.tiers (the
     * single source of truth) so owners and inherited members get the
     * catalogue values regardless of which columns the Paddle webhook wrote.
     *
     * Paused subscriptions write max_members = 0 to the row while keeping the
     * price_id; since no tier defines 0 members, that's used as the paused
     * signal and all limits are zeroed so gated features lock.
     *
     * @return array<string, mixed>
     */
    private function planFromLimit(UserLimit $limit): array
    {
        $fallback = config('plans.fallback_limits');
        $tier = $this->priceToTier[$limit->paddle_price_id] ?? null;
        $tierLimits = $tier !== null ? ($this->tiers[$tier]['limits'] ?? null) : null;

        if ($tier !== null && $tierLimits !== null) {
            if ((int) ($limit->max_members ?? $tierLimits['max_members']) === 0) {
                return [
                    'tier' => $tier,
                    'max_members' => 0,
                    'max_teams' => 0,
                    'max_stratbooks' => 0,
                    'max_maps' => 0,
                    'max_demos' => 0,
                    'ai_antistrat' => false,
                    'round_planner' => false,
                    'is_admin_override' => $limit->paddle_subscription_id === config('plans.admin_override_marker'),
                    'needs_migration' => false,
                    'paddle_price_id' => $limit->paddle_price_id,
                ];
            }

            return [
                'tier' => $tier,
                'max_members' => $tierLimits['max_members'],
                'max_teams' => $tierLimits['max_teams'],
                'max_stratbooks' => $tierLimits['max_stratbooks'],
                'max_maps' => $tierLimits['max_maps'],
                'max_demos' => $tierLimits['max_demos'],
                'ai_antistrat' => (bool) $tierLimits['ai_antistrat'],
                'round_planner' => (bool) $tierLimits['round_planner'],
                'is_admin_override' => $limit->paddle_subscription_id === config('plans.admin_override_marker'),
                'needs_migration' => false,
                'paddle_price_id' => $limit->paddle_price_id,
            ];
        }

        // Unknown price_id (legacy or custom): fall back to the per-column
        // values stored on the row.
        return [
            'tier' => $tier,
            'max_members' => $limit->max_members ?? $fallback['max_members'],
            'max_teams' => $limit->max_teams ?? $fallback['max_teams'],
            'max_stratbooks' => $limit->max_stratbooks ?? $fallback['max_stratbooks'],
            'max_maps' => $fallback['max_maps'],
            'max_demos' => $limit->max_demos ?? $fallback['max_demos'],
            'ai_antistrat' => (bool) ($limit->ai_antistrat ?? $fallback['ai_antistrat']),
            'round_planner' => (bool) ($limit->round_planner ?? $fallback['round_planner']),
            'is_admin_override' => $limit->paddle_subscription_id === config('plans.admin_override_marker'),
            'needs_migration' => false,
            'paddle_price_id' => $limit->paddle_price_id,
        ];
    }

    /**
     * Rank a tier for "highest wins" comparisons. Known tiers rank by their
     * position in config/plans.tiers (Starter < Team < Pro); null ranks -1.
     */
    private function tierRank(?string $tier): int
    {
        if ($tier === null) {
            return -1;
        }

        return $this->tierOrder[$tier] ?? -1;
    }

    public function tierFor(User $user): ?string
    {
        return $this->forUser($user)['tier'];
    }

    /**
     * Feature gate. $feature is one of: 'stratbooks', 'demos', 'ai_antistrat',
     * 'round_planner'. 'stratbooks' and 'demos' are count-based and need the
     * current usage; 'ai_antistrat' and 'round_planner' are boolean entitlements.
     */
    public function can(User $user, string $feature, ?int $usage = null): bool
    {
        $plan = $this->forUser($user);

        return match ($feature) {
            'ai_antistrat' => $plan['ai_antistrat'] === true,
            'round_planner' => $plan['round_planner'] === true,
            'demos' => $usage === null ? true : $usage < $plan['max_demos'],
            'stratbooks' => $usage === null ? true : $usage < $plan['max_stratbooks'],
            default => false,
        };
    }

    /**
     * The tier a user would need to unlock the given feature, or null if the
     * feature isn't tier-gated. Used to populate the upgrade modal's
     * "required tier" copy and the 706 response body.
     */
    public function requiredTierForFeature(string $feature): ?string
    {
        return match ($feature) {
            'ai_antistrat' => 'Pro',
            'demos' => 'Team',
            'stratbooks' => 'Team',
            'round_planner' => 'Team',
            default => null,
        };
    }

    /**
     * The limits config for a tier, or null if the tier is unknown.
     *
     * @return array{max_members: int, max_teams: int, max_stratbooks: int, max_demos: int, ai_antistrat: bool, round_planner: bool}|null
     */
    public function limitsForTier(string $tier): ?array
    {
        $cfg = $this->tiers[$tier] ?? null;
        if (! $cfg) {
            return null;
        }

        return $cfg['limits'];
    }

    /**
     * All tier names in canonical display order.
     *
     * @return string[]
     */
    public function tiers(): array
    {
        return array_keys($this->tiers);
    }
}
