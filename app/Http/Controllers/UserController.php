<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = User::with(['profilePicture', 'userInfo', 'userRole'])
            ->find(Auth::id());

        if (! $user->is_admin) {
            unset($user->is_admin);
        }

        $user->load('teams');

        $userSubscription = $user->subscription('default');

        if ($userSubscription) {
            $recursAt = $userSubscription->created_at->addMonths(6);
            $userSubscriptionsEndsAt = $userSubscription->ends_at;

            $user->product = $user->product();
            $user->active_subscription = $userSubscription;
            $user->recurs_at = $recursAt;
            $user->subscription_ends_at = $userSubscriptionsEndsAt;
        }

        $user->role = $user->userRole;

        // Plan: tier + per-feature limits + admin-override flag. The frontend
        // reads this to gate buttons client-side and to power the admin plan
        // switcher. PlanService resolves through user_limits → legacy
        // product → config fallback. See App\Services\PlanService.
        $user->plan = app(PlanService::class)->forUser($user);

        return new JsonResponse($user, JsonResponse::HTTP_OK);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        return response()->json($user);
    }

    public function updateTheme(Request $request): JsonResponse
    {
        $data = $request->validate(['theme' => 'required|string|max:32']);

        $themesPath = resource_path('themes.json');
        $themes = json_decode(file_get_contents($themesPath), true) ?: [];
        if (! isset($themes[$data['theme']])) {
            return new JsonResponse(['message' => 'Unknown theme.'], 422);
        }

        $user = $request->user();
        $user->setSetting('theme', $data['theme']);
        Cache::forget('global-user:'.$user->id);

        return new JsonResponse(['message' => 'Theme updated.', 'theme' => $data['theme']]);
    }

    public function dismissChecklist(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->setSetting('captain_checklist_dismissed', true);
        Cache::forget('global-user:'.$user->id);

        return new JsonResponse(['message' => 'Checklist dismissed.']);
    }
}
