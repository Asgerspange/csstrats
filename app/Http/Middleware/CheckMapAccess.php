<?php

namespace App\Http\Middleware;

use App\Constants\AppErrorCode;
use App\Enums\EntitlementMode;
use App\Models\Stratbook;
use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gate map-based teams from accessing stratbooks on maps they don't have.
 *
 * For routes with a {stratbook} route-model binding, resolves the stratbook's
 * map_id and checks EntitlementService. Legacy teams and legacy stratbooks
 * (map_id = null) pass through.
 *
 * GET/HEAD/OPTIONS → canAccessMap (read)
 * POST/PATCH/PUT/DELETE → canEditMap (write)
 */
class CheckMapAccess
{
    public function __construct(
        private EntitlementService $entitlement
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $stratbook = $request->route('stratbook');

        if (! $stratbook instanceof Stratbook) {
            return $next($request);
        }

        // Legacy stratbook (no map_id) or legacy team — no gating
        if ($stratbook->map_id === null) {
            return $next($request);
        }

        $team = $stratbook->team;
        if (! $team || $team->entitlement_mode === EntitlementMode::Legacy->value) {
            return $next($request);
        }

        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS']);

        if ($isWrite && ! $this->entitlement->canEditMap($team, $stratbook->map_id)) {
            return $this->denied(AppErrorCode::NO_MAP_EDIT_ACCESS);
        }

        if (! $this->entitlement->canAccessMap($team, $stratbook->map_id)) {
            return $this->denied(AppErrorCode::NO_MAP_ACCESS);
        }

        return $next($request);
    }

    private function denied(int $code): JsonResponse
    {
        return new JsonResponse([
            'app_code' => $code,
            'message' => $code === AppErrorCode::NO_MAP_EDIT_ACCESS
                ? 'This map is not in your plan. Upgrade to edit.'
                : 'This map is not in your plan.',
        ], 403);
    }
}
