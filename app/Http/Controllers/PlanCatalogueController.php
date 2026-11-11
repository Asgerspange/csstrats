<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Public plan catalogue for the authenticated app. Returns the tier presets
 * + cadences from config/plans.php so the in-app upgrade modal can render the
 * tier list and open Paddle checkout without leaving the app. Price IDs are
 * not secret (they're already shipped to the marketing bundle via
 * WelcomeController::getProducts()), so this route is safe behind auth.
 *
 * Admin-only fields (admin_override_marker etc.) are NOT exposed here; the
 * admin plan switcher reads those from AdminPlanController.
 */
class PlanCatalogueController extends Controller
{
    public function catalogue(): JsonResponse
    {
        $tiers = [];
        foreach (config('plans.tiers', []) as $tier => $cfg) {
            $tiers[] = [
                'tier' => $tier,
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
}
