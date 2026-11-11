<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referrer;
use App\Models\ReferralCommission;
use App\Models\ReferralSource;
use App\Models\ReferralVisit;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of referral partners: create logins, set commission terms,
 * manage their utm_source codes, and review per-referrer performance.
 */
class AdminReferrerController extends Controller
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    public function index(): JsonResponse
    {
        $referrers = Referrer::with('sources')
            ->orderBy('name')
            ->get()
            ->map(fn (Referrer $r) => $this->summarise($r));

        return response()->json(['referrers' => $referrers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $referrer = Referrer::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'], // hashed via the model cast
            'commission_rate' => $data['commission_rate'],
            'commission_type' => $data['commission_type'],
            'recurring_months' => $data['recurring_months'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['code'])) {
            $this->createSource($referrer, $data['code'], $data['label'] ?? null);
        }

        return response()->json(['referrer' => $this->summarise($referrer->load('sources'))], 201);
    }

    public function update(Request $request, Referrer $referrer): JsonResponse
    {
        $data = $this->validatePayload($request, $referrer);

        $referrer->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'commission_rate' => $data['commission_rate'],
            'commission_type' => $data['commission_type'],
            'recurring_months' => $data['recurring_months'] ?? null,
            'is_active' => $data['is_active'] ?? $referrer->is_active,
        ]);

        if (! empty($data['password'])) {
            $referrer->password = $data['password']; // hashed via the model cast
        }

        $referrer->save();

        return response()->json(['referrer' => $this->summarise($referrer->load('sources'))]);
    }

    public function destroy(Referrer $referrer): JsonResponse
    {
        $referrer->delete();

        return response()->json(['ok' => true]);
    }

    public function show(Referrer $referrer): JsonResponse
    {
        $sourceIds = $referrer->sources()->pluck('id');

        $visits = ReferralVisit::whereIn('referral_source_id', $sourceIds)
            ->latest('created_at')
            ->limit(100)
            ->get(['id', 'referral_source_id', 'country_code', 'referrer_url', 'landing_path', 'utm_campaign', 'created_at']);

        $signups = User::whereIn('referral_source_id', $sourceIds)
            ->latest('referred_at')
            ->limit(100)
            ->get(['id', 'name', 'email', 'referral_source_id', 'referred_at', 'created_at']);

        $commissions = ReferralCommission::where('referrer_id', $referrer->id)
            ->with('user:id,name,email')
            ->latest('billed_at')
            ->limit(100)
            ->get();

        return response()->json([
            'referrer' => $this->summarise($referrer->load('sources')),
            'visits' => $visits,
            'signups' => $signups,
            'commissions' => $commissions,
        ]);
    }

    public function storeSource(Request $request, Referrer $referrer): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:120', 'unique:referral_sources,code'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $source = $this->createSource($referrer, $data['code'], $data['label'] ?? null);

        return response()->json(['source' => $source], 201);
    }

    public function destroySource(ReferralSource $source): JsonResponse
    {
        $source->delete();

        return response()->json(['ok' => true]);
    }

    private function createSource(Referrer $referrer, string $code, ?string $label): ReferralSource
    {
        return $referrer->sources()->create([
            'code' => strtolower(trim($code)),
            'label' => $label,
        ]);
    }

    private function validatePayload(Request $request, ?Referrer $referrer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('referrers', 'email')->ignore($referrer?->id)],
            'password' => [$referrer ? 'nullable' : 'required', 'string', 'min:8'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_type' => ['required', Rule::in(['first', 'recurring'])],
            'recurring_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            // Optional inline first source on create.
            'code' => ['nullable', 'string', 'max:120', 'unique:referral_sources,code'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Decorate a referrer with rolled-up performance stats for the admin table.
     */
    private function summarise(Referrer $referrer): array
    {
        $sourceIds = $referrer->sources->pluck('id');

        $visits = ReferralVisit::whereIn('referral_source_id', $sourceIds)->count();
        $uniqueVisitors = ReferralVisit::whereIn('referral_source_id', $sourceIds)
            ->distinct('visitor_id')->count('visitor_id');
        $signups = User::whereIn('referral_source_id', $sourceIds)->count();
        $payingCustomers = ReferralCommission::where('referrer_id', $referrer->id)
            ->distinct('user_id')->count('user_id');
        $orders = ReferralCommission::where('referrer_id', $referrer->id)->count();

        $byCurrency = ReferralCommission::where('referrer_id', $referrer->id)
            ->selectRaw('currency, COUNT(*) as orders, SUM(order_total) as gross, SUM(commission_amount) as earned')
            ->groupBy('currency')
            ->orderByDesc('earned')
            ->get()
            ->map(fn ($r) => [
                'currency' => $r->currency,
                'orders' => (int) $r->orders,
                'gross' => round((float) $r->gross, 2),
                'earned' => round((float) $r->earned, 2),
            ])
            ->all();

        // A single comparable figure for the table: everything in EUR. Null if
        // FX rates are unavailable so the UI can fall back to per-currency.
        $earnedEur = 0.0;
        $fxOk = true;
        foreach ($byCurrency as $b) {
            $converted = $this->fx->convert($b['earned'], $b['currency'], 'EUR');
            if ($converted === null) {
                $fxOk = false;
                break;
            }
            $earnedEur += $converted;
        }

        return [
            'id' => $referrer->id,
            'name' => $referrer->name,
            'email' => $referrer->email,
            'commission_rate' => (float) $referrer->commission_rate,
            'commission_type' => $referrer->commission_type,
            'recurring_months' => $referrer->recurring_months,
            'is_active' => (bool) $referrer->is_active,
            'sources' => $referrer->sources->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'label' => $s->label,
            ]),
            'stats' => [
                'visits' => $visits,
                'unique_visitors' => $uniqueVisitors,
                'signups' => $signups,
                'paying_customers' => $payingCustomers,
                'orders' => $orders,
                'by_currency' => $byCurrency,
                'earned_eur' => $fxOk ? round($earnedEur, 2) : null,
            ],
        ];
    }
}
