<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class OnboardingController extends Controller
{
    /**
     * Mark onboarding as completed for the authenticated user.
     * Also optionally saves role selected during onboarding.
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['nullable', 'string', 'in:igl,player,coach,analyst'],
        ]);

        $user = Auth::user();

        $settings = $user->settings ?? [];

        $settings['onboarding_completed'] = true;

        if ($request->filled('role')) {
            $settings['onboarding_role'] = $request->input('role');
        }

        $user->update(['settings' => $settings]);

        Cache::forget('global-user:' . Auth::id());

        return new JsonResponse(['success' => true], 200);
    }

    /**
     * Reset onboarding so user can view it again (useful from profile settings).
     */
    public function reset(): JsonResponse
    {
        $user = Auth::user();
        $settings = $user->settings ?? [];
        $settings['onboarding_completed'] = false;
        $user->update(['settings' => $settings]);

        return new JsonResponse(['success' => true], 200);
    }
}