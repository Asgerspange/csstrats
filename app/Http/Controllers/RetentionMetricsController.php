<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * RetentionMetricsController
 *
 * Exposes JSON endpoints for Grafana (SimpleJSON / Infinity datasource).
 * All metrics exclude users who are members of a paid team, since those
 * users behave differently and would skew solo-user funnel numbers.
 *
 * Protect with a static Bearer token set in .env: GRAFANA_METRICS_TOKEN
 *
 * Routes (add to routes/api.php, no auth middleware — token-protected instead):
 *   GET /metrics/retention
 *   GET /metrics/funnel
 *   GET /metrics/churn-risk
 *   GET /metrics/trial
 */
class RetentionMetricsController extends Controller
{
    /**
     * Verify the static Grafana bearer token.
     */
    private function verifyGrafanaToken(Request $request): bool
    {
        $token = config('app.grafana_metrics_token');
        if (!$token) return true; // Skip check if not configured (dev)

        return $request->bearerToken() === $token;
    }

    /**
     * GET /metrics/retention
     *
     * Returns daily new registrations and day-7 retention rate
     * for the past N days, excluding team members.
     *
     * Grafana: use Infinity datasource -> JSON -> time series or table.
     */
    public function retention(Request $request): JsonResponse
    {
        if (!$this->verifyGrafanaToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $days = (int) $request->query('days', 30);

        // Solo users = users not in any team_members record
        $rows = DB::select("
            SELECT
                DATE(u.created_at)                          AS date,
                COUNT(DISTINCT u.id)                        AS registrations,
                COUNT(DISTINCT CASE
                    WHEN ui.last_online >= DATE_ADD(u.created_at, INTERVAL 7 DAY)
                    THEN u.id
                END)                                        AS retained_day7,
                ROUND(
                    COUNT(DISTINCT CASE
                        WHEN ui.last_online >= DATE_ADD(u.created_at, INTERVAL 7 DAY)
                        THEN u.id
                    END) * 100.0 /
                    NULLIF(COUNT(DISTINCT u.id), 0),
                2)                                          AS retention_rate_day7
            FROM users u
            LEFT JOIN users_info ui    ON ui.uuid = u.uuid
            -- Exclude users who belong to any team
            WHERE u.id NOT IN (
                SELECT DISTINCT tm.user_id FROM team_members tm
            )
            AND u.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND u.is_admin = 0
            GROUP BY DATE(u.created_at)
            ORDER BY date ASC
        ", ['days' => $days]);

        return response()->json([
            'meta' => [
                'description' => 'Daily registrations and day-7 retention for solo (non-team) users',
                'days'        => $days,
            ],
            'data' => $rows,
        ]);
    }

    /**
     * GET /metrics/funnel
     *
     * Returns funnel step completion counts for solo users.
     * Steps:
     *   1. Registered
     *   2. Visited tactics page (last_online set = logged in at least once after registration)
     *   3. Created a team (owned_by = user.id)
     *   4. Added a tactic to a stratbook
     *   5. Returned on day 7+
     */
    public function funnel(Request $request): JsonResponse
    {
        if (!$this->verifyGrafanaToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $days = (int) $request->query('days', 30);

        $base = "
            SELECT u.id
            FROM users u
            WHERE u.id NOT IN (SELECT DISTINCT tm.user_id FROM team_members tm)
            AND u.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            AND u.is_admin = 0
        ";

        $step1 = DB::select("SELECT COUNT(*) as cnt FROM ({$base}) AS t")[0]->cnt;

        $step2 = DB::select("
            SELECT COUNT(*) as cnt FROM (
                SELECT u.id
                FROM users u
                LEFT JOIN users_info ui ON ui.uuid = u.uuid
                WHERE u.id NOT IN (SELECT DISTINCT tm.user_id FROM team_members tm)
                AND u.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                AND u.is_admin = 0
                AND ui.last_online > u.created_at
            ) AS t
        ")[0]->cnt;

        $step3 = DB::select("
            SELECT COUNT(*) as cnt FROM (
                SELECT u.id
                FROM users u
                INNER JOIN teams t ON t.owned_by = u.id AND t.deleted_at IS NULL
                WHERE u.id NOT IN (SELECT DISTINCT tm.user_id FROM team_members tm)
                AND u.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                AND u.is_admin = 0
            ) AS t
        ")[0]->cnt;

        $step4 = DB::select("
            SELECT COUNT(*) as cnt FROM (
                SELECT DISTINCT u.id
                FROM users u
                INNER JOIN teams te ON te.owned_by = u.id AND te.deleted_at IS NULL
                INNER JOIN stratbooks sb ON sb.team_id = te.id
                INNER JOIN stratbook_tactics st ON st.stratbook_id = sb.id
                WHERE u.id NOT IN (SELECT DISTINCT tm.user_id FROM team_members tm)
                AND u.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                AND u.is_admin = 0
            ) AS t
        ")[0]->cnt;

        $step5 = DB::select("
            SELECT COUNT(*) as cnt FROM (
                SELECT u.id
                FROM users u
                LEFT JOIN users_info ui ON ui.uuid = u.uuid
                WHERE u.id NOT IN (SELECT DISTINCT tm.user_id FROM team_members tm)
                AND u.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                AND u.is_admin = 0
                AND ui.last_online >= DATE_ADD(u.created_at, INTERVAL 7 DAY)
            ) AS t
        ")[0]->cnt;

        return response()->json([
            'meta' => [
                'description' => 'Onboarding funnel for solo users (non-team members)',
                'days'        => $days,
            ],
            'data' => [
                ['step' => 1, 'label' => 'Registered',              'count' => $step1, 'pct_of_top' => 100],
                ['step' => 2, 'label' => 'Logged in after register', 'count' => $step2, 'pct_of_top' => $step1 ? round($step2 / $step1 * 100, 1) : 0],
                ['step' => 3, 'label' => 'Created a team',           'count' => $step3, 'pct_of_top' => $step1 ? round($step3 / $step1 * 100, 1) : 0],
                ['step' => 4, 'label' => 'Added tactic to stratbook','count' => $step4, 'pct_of_top' => $step1 ? round($step4 / $step1 * 100, 1) : 0],
                ['step' => 5, 'label' => 'Returned on day 7+',       'count' => $step5, 'pct_of_top' => $step1 ? round($step5 / $step1 * 100, 1) : 0],
            ],
        ]);
    }

    /**
     * GET /metrics/churn-risk
     *
     * Returns solo users who registered 3-10 days ago
     * and have NOT created a team — the highest-risk churn segment.
     * Useful for Grafana stat panel or table to act on.
     */
    public function churnRisk(Request $request): JsonResponse
    {
        if (!$this->verifyGrafanaToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rows = DB::select("
            SELECT
                u.id,
                u.name,
                u.email,
                u.created_at,
                ui.last_online,
                DATEDIFF(NOW(), u.created_at) AS days_since_register,
                CASE
                    WHEN EXISTS (SELECT 1 FROM teams t WHERE t.owned_by = u.id AND t.deleted_at IS NULL)
                    THEN 1 ELSE 0
                END AS has_team
            FROM users u
            LEFT JOIN users_info ui ON ui.uuid = u.uuid
            WHERE u.id NOT IN (SELECT DISTINCT tm.user_id FROM team_members tm)
            AND u.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 10 DAY)
                                 AND DATE_SUB(NOW(), INTERVAL 3 DAY)
            AND u.is_admin = 0
            AND NOT EXISTS (SELECT 1 FROM teams t WHERE t.owned_by = u.id AND t.deleted_at IS NULL)
            ORDER BY u.created_at DESC
        ");

        return response()->json([
            'meta' => [
                'description' => 'Solo users, 3-10 days old, no team created — highest churn risk',
                'count'       => count($rows),
            ],
            'data' => $rows,
        ]);
    }

    /**
     * GET /metrics/trial
     *
     * Returns trial (GrantedAccess with expires_at set) conversion stats:
     * - Active trials
     * - Trials expiring in 48h
     * - Converted (had trial, now has paid sub)
     * - Expired without converting
     */
    public function trial(Request $request): JsonResponse
    {
        if (!$this->verifyGrafanaToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $active = DB::table('granted_access')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->count();

        $expiringSoon = DB::table('granted_access')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours(48)])
            ->count();

        $converted = DB::table('granted_access as ga')
            ->whereNotNull('ga.expires_at')
            ->join('subscriptions as s', 's.user_id', '=', 'ga.user_id')
            ->where('s.stripe_status', 'active')
            ->count();

        $expiredNoConversion = DB::table('granted_access as ga')
            ->whereNotNull('ga.expires_at')
            ->where('ga.expires_at', '<', now())
            ->whereNotIn('ga.user_id', function ($q) {
                $q->select('user_id')
                  ->from('subscriptions')
                  ->where('stripe_status', 'active');
            })
            ->count();

        // Also exclude team members from expired no-conversion
        // (they may have joined a team instead)
        $expiredJoinedTeam = DB::table('granted_access as ga')
            ->whereNotNull('ga.expires_at')
            ->where('ga.expires_at', '<', now())
            ->whereIn('ga.user_id', function ($q) {
                $q->select('user_id')->from('team_members');
            })
            ->whereNotIn('ga.user_id', function ($q) {
                $q->select('user_id')->from('subscriptions')->where('stripe_status', 'active');
            })
            ->count();

        return response()->json([
            'meta' => ['description' => 'Trial (GrantedAccess) conversion funnel'],
            'data' => [
                ['label' => 'Active trials',               'count' => $active],
                ['label' => 'Expiring in 48h',             'count' => $expiringSoon],
                ['label' => 'Converted to paid',           'count' => $converted],
                ['label' => 'Expired, joined a team',      'count' => $expiredJoinedTeam],
                ['label' => 'Expired, no conversion',      'count' => $expiredNoConversion - $expiredJoinedTeam],
            ],
        ]);
    }

    /**
     * GET /metrics/invite-funnel
     *
     * Returns invite link funnel stats:
     * - How many users viewed an invite page
     * - How many accepted
     * - Conversion rate
     */
    public function inviteFunnel(Request $request): JsonResponse
    {
        if (!$this->verifyGrafanaToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    
        $days = (int) $request->query('days', 30);
    
        $teamsWithInvite = DB::table('teams')
            ->whereNotNull('invite_token')
            ->whereNull('deleted_at')
            ->count();

        $recentJoins = DB::table('team_members as tm')
            ->join('users as u', 'u.id', '=', 'tm.user_id')
            ->join('teams as t', 't.id', '=', 'tm.team_id')
            ->whereNull('t.deleted_at')
            ->whereRaw('t.owned_by != tm.user_id')
            ->where('u.created_at', '>=', now()->subDays($days))
            ->count();

        $recentTeamCreations = DB::table('teams')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    
        return response()->json([
            'meta' => [
                'description' => 'Invite link funnel metrics',
                'days' => $days,
            ],
            'data' => [
                ['label' => 'Teams with invite link',     'count' => $teamsWithInvite],
                ['label' => 'New members joined (all)',   'count' => $recentJoins],
                ['label' => 'New teams created',          'count' => $recentTeamCreations],
            ],
        ]);
    }
}