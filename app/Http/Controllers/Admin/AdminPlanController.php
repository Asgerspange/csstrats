<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLimit;
use App\Services\EntitlementService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin plan management. Lets an admin set any user's effective plan to one
 * of the three tiers (for testing the in-app gating end to end), or write
 * custom limit overrides. Writes go to user_limits with the
 * `admin-override` marker on paddle_subscription_id so the code can tell an
 * admin-set plan apart from a real Paddle-driven subscription.
 *
 * Clearing the override deletes the user_limits row, which hands resolution
 * back to the real Paddle sub (if any) or the config fallback.
 */
class AdminPlanController extends Controller
{
    public function __construct(private PlanService $plans) {}

    /**
     * GET /api/admin/plans — the tier presets + their limits + cadences.
     * Powers the admin switcher UI.
     */
    public function index(): JsonResponse
    {
        $tiers = [];
        foreach (config('plans.tiers', []) as $tier => $cfg) {
            $tiers[] = [
                'tier' => $tier,
                'product_id' => $cfg['product_id'],
                'price_ids' => $cfg['price_ids'],
                'limits' => $cfg['limits'],
                'features' => $cfg['features'],
            ];
        }

        return response()->json([
            'tiers' => $tiers,
            'cadences' => config('plans.cadences', []),
        ]);
    }

    /**
     * POST /api/admin/users/{id}/plan
     *
     * Body: either { tier: "Starter"|"Team"|"Pro" } to apply a preset, or
     * { max_members, max_teams, max_stratbooks, max_demos, ai_antistrat } to
     * write a custom override. A tier preset always wins over any individual
     * limit fields sent alongside it.
     */
    public function setPlan(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $this->validatePlanPayload($request);
        $marker = config('plans.admin_override_marker');

        if (! empty($validated['tier'])) {
            $limits = $this->plans->limitsForTier($validated['tier']);
            if (! $limits) {
                return response()->json(['message' => 'Unknown tier'], 422);
            }
            // Use the monthly price_id as the marker for "which plan" so
            // PlanService can derive the tier back out.
            $priceId = config("plans.tiers.{$validated['tier']}.price_ids.monthly");
            UserLimit::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($limits, [
                    'paddle_subscription_id' => $marker,
                    'paddle_price_id' => $priceId,
                ])
            );
        } else {
            // Custom limit override — no tier association (price_id null).
            UserLimit::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'max_members' => (int) $validated['max_members'],
                    'max_teams' => (int) $validated['max_teams'],
                    'max_stratbooks' => (int) $validated['max_stratbooks'],
                    'max_demos' => (int) $validated['max_demos'],
                    'ai_antistrat' => (bool) $validated['ai_antistrat'],
                    'round_planner' => (bool) $validated['round_planner'],
                    'paddle_subscription_id' => $marker,
                    'paddle_price_id' => null,
                ]
            );
        }

        $this->bustUserCache($user->id);

        // Sync map selections to match the new plan's max_maps.
        app(EntitlementService::class)->syncMapSelectionsForUser($user);

        return response()->json([
            'message' => 'Plan updated.',
            'plan' => $this->plans->forUser($user->fresh()),
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}/plan
     *
     * Removes the admin override (deletes user_limits). Resolution falls
     * back to the real Paddle sub or the config fallback.
     */
    public function clearPlan(int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Only clear admin overrides — never delete a row that belongs to a
        // real Paddle subscription.
        $limit = $user->userLimit;
        if ($limit && $limit->paddle_subscription_id === config('plans.admin_override_marker')) {
            $limit->delete();
            $this->bustUserCache($user->id);
        }

        return response()->json([
            'message' => 'Admin override cleared.',
            'plan' => $this->plans->forUser($user->fresh()),
        ]);
    }

    /**
     * @return array{tier?: string, max_members?: int, max_teams?: int, max_stratbooks?: int, max_demos?: int, ai_antistrat?: bool, round_planner?: bool}
     */
    private function validatePlanPayload(Request $request): array
    {
        return $request->validate([
            'tier' => ['nullable', 'string', 'in:Starter,Team,Pro'],
            'max_members' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_teams' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_stratbooks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'max_demos' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'ai_antistrat' => ['nullable', 'boolean'],
            'round_planner' => ['nullable', 'boolean'],
        ]);
    }

    private function bustUserCache(int $userId): void
    {
        // UserController::updateTheme/​dismissChecklist use this cache key;
        // the user query on the frontend refetches on its own, but busting
        // here keeps any server-side cached user fresh too.
        Cache::forget('global-user:'.$userId);
    }
}
