<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    private $cacheService;

    public function __construct()
    {
        $this->cacheService = new CacheService;
    }

    public function index()
    {
        // Static shell: no per-request data, so the landing HTML is cacheable
        // (CDN/edge). The Vue app fetches logged-in state from `data()`.
        return $this->closingView('https://csstrats.dk/');
    }

    public function data()
    {
        $user = Auth::user();

        // Products are static config; never let a DB hiccup on the stats
        // (or a billing lookup) blank the pricing section.
        return response()->json([
            'user' => $user,
            'subscribed' => $user ? rescue(fn () => $user->subscribed('default'), false, false) : false,
            'products' => $this->getProducts(),
            'tactics' => rescue(fn () => $this->cacheService->getTacticsCount(), 0, false),
            'demos' => rescue(fn () => $this->cacheService->getDemosCount() * 3, 0, false),
            'users' => rescue(fn () => $this->cacheService->getActiveUsers(), 0, false),
        ]);
    }

    public function hltv()
    {
        return $this->closingView('https://csstrats.dk/hltv');
    }

    public function sampleDemo()
    {
        return $this->closingView('https://csstrats.dk/demo');
    }

    public function weeklyTactic()
    {
        return $this->closingView('https://csstrats.dk/tactics/weekly');
    }

    private function closingView(string $canonical)
    {
        return view('welcome', [
            'title' => 'CsStrats is closing',
            'metaDescription' => 'CsStrats is shutting down. Existing accounts can still log in. Billing and data questions: info@csstrats.dk.',
            'canonical' => $canonical,
        ]);
    }

    public function weeklyTacticData()
    {
        return rescue(fn () => \Illuminate\Support\Facades\Cache::remember('weekly-tactic-public', now()->addMinutes(10), function () {
            // Global 'released' scope on Tactic keeps unreleased rows out of
            // both queries. The featured tactic is the one public sample; the
            // rest of the latest drop renders locked.
            $tactic = \App\Models\Tactic::where('is_featured', true)
                ->orderByDesc('release_date')
                ->first(['id', 'team_name', 'map', 'side', 'type', 'description', 'video', 'release_date'])
                ?? \App\Models\Tactic::orderByDesc('release_date')
                    ->first(['id', 'team_name', 'map', 'side', 'type', 'description', 'video', 'release_date']);

            $drop = collect();
            if ($tactic) {
                $drop = \App\Models\Tactic::where('id', '!=', $tactic->id)
                    ->orderByDesc('release_date')
                    ->take(7)
                    ->get(['id', 'team_name', 'map', 'side', 'type', 'release_date']);
            }

            return [
                'tactic' => $tactic,
                'drop' => $drop,
            ];
        }), ['tactic' => null, 'drop' => []], false);
    }

    public function getProducts()
    {
        // Built from config/plans.php so the landing pricing surface and the
        // in-app plan gating never drift. The cadence order (monthly, every
        // 6 months, yearly) matches the billing-cadence tablist on the
        // pricing section.
        $tiers = config('plans.tiers', []);
        $cadences = config('plans.cadences', []);
        $cadenceCopy = [
            'monthly' => 'No commitments. Cancel anytime. Recurs every month.',
            'semi' => 'Save 20% with bi-annual billing.',
            'yearly' => 'Best value! Save 36.6% with annual billing.',
        ];

        $products = [];
        foreach ($tiers as $tier => $cfg) {
            foreach ($cadences as $cadence => $meta) {
                $products[] = [
                    'name' => "{$tier} - {$meta['label']}",
                    'description' => $cadenceCopy[$cadence] ?? '',
                    'priceId' => $cfg['price_ids'][$cadence] ?? null,
                    'features' => $cfg['features'],
                ];
            }
        }

        return $products;
    }
}
