import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { computed } from 'vue';

const API = '/api/opponent-teams';

const fetcher = async (url: string, opts: RequestInit = {}) => {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        ...opts,
    });
    if (!res.ok) {
        // Attach the parsed body when possible so callers can branch on
        // app_code (e.g. PLAN_LIMIT_EXCEEDED → upgrade modal). These calls
        // bypass the axios interceptor because they use raw fetch().
        let body: any = null;
        try { body = await res.json(); } catch { /* non-JSON body */ }
        const err = new Error(body?.message ?? (await res.text().catch(() => '')));
        (err as any).status = res.status;
        (err as any).body = body;
        throw err;
    }
    return res.json();
};

const getCsrf = (): string =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface OpponentPlayer {
    id: number;
    steamid: string;
    name: string;
    alias: string | null;
    notes: string | null;
    tendency_cache: PlayerTendencies | null;
}

export interface PlayerTendencies {
    role: string;
    rating_assessment: string;
    strengths: string[];
    weaknesses: string[];
    clutch_performance: string;
    headshot_tendency: string;
    watch_out_for: string;
    aggression_score: number;
}

export interface AiInsights {
    summary: string;
    threat_level: 'low' | 'medium' | 'high' | 'very_high';
    key_players: {
        steamid: string;
        name: string;
        role_assessment: string;
        strengths: string[];
        weaknesses: string[];
        watch_out_for: string;
    }[];
    team_tendencies: {
        t_side: string;
        ct_side: string;
        eco_behavior: string;
        aggression_style: string;
    };
    map_specific: Record<string, {
        preferred_side: string;
        typical_setup: string;
        exploitable_habits: string[];
    }>;
    counter_recommendations: { situation: string; recommendation: string }[];
    danger_score: number;
    error?: string;
}

export interface CounterStrat {
    id: number;
    name: string;
    map_id: number;
    map?: { id: number; name: string; display_name: string };
    side: 'T' | 'CT';
    description: string | null;
    tags: string[] | null;
    demo_id: number | null;
    demo_round: number | null;
    created_by: number;
    created_at: string;
}

export interface SharedTeam {
    id: number;
    name: string;
}

export interface OpponentTeam {
    id: number;
    user_id: number;
    name: string;
    tag: string | null;
    notes: string | null;
    platform_url: string | null;
    ai_insights: AiInsights | null;
    insights_updated_at: string | null;
    demos_count?: number;
    players: OpponentPlayer[];
    counter_strats: CounterStrat[];
    demos?: { id: number; name: string; map: string; created_at: string }[];
    map_stats?: { map: string; played: number; wins: number; losses: number }[];
    match_record?: { total: number; linked: number };
    /** Teams the owner has shared this profile with. */
    shared_teams?: SharedTeam[];
    /** True when the current user owns this profile (set by the show endpoint). */
    is_owner?: boolean;
}

// ─── Composable ───────────────────────────────────────────────────────────────

export default function useOpponentTeams() {
    const qc = useQueryClient();

    const OpponentTeams = {
        list: () => useQuery({
            queryKey: ['opponent-teams'],
            queryFn:  () => fetcher(API),
        }),

        show: (id: number | (() => number)) => {
            const getId = () => typeof id === 'function' ? id() : id;
            return useQuery({
                queryKey: ['opponent-teams', computed(getId)],
                queryFn:  () => fetcher(`${API}/${getId()}`),
                enabled:  computed(() => !!getId()),
            });
        },
    };

    const create = useMutation({
        mutationFn: (data: Partial<OpponentTeam>) =>
            fetcher(API, { method: 'POST', body: JSON.stringify(data) }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['opponent-teams'] }),
    });

    const update = useMutation({
        mutationFn: ({ id, ...data }: Partial<OpponentTeam> & { id: number }) =>
            fetcher(`${API}/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
        onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['opponent-teams', vars.id] }),
    });

    const remove = useMutation({
        mutationFn: (id: number) => fetcher(`${API}/${id}`, { method: 'DELETE' }),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['opponent-teams'] }),
    });

    // Owner-only — sync which of the owner's teams this profile is shared with.
    const updateShares = useMutation({
        mutationFn: ({ id, team_ids }: { id: number; team_ids: number[] }) =>
            fetcher(`${API}/${id}/shares`, { method: 'PUT', body: JSON.stringify({ team_ids }) }),
        onSuccess: (_d, vars) => {
            qc.invalidateQueries({ queryKey: ['opponent-teams', vars.id] });
            qc.invalidateQueries({ queryKey: ['opponent-teams'] });
        },
    });

    const attachDemo = useMutation({
        mutationFn: ({ id, demo_id }: { id: number; demo_id: number }) =>
            fetcher(`${API}/${id}/demos`, { method: 'POST', body: JSON.stringify({ demo_id }) }),
        onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['opponent-teams', vars.id] }),
    });

    const detachDemo = useMutation({
        mutationFn: ({ id, demo_id }: { id: number; demo_id: number }) =>
            fetcher(`${API}/${id}/demos`, { method: 'DELETE', body: JSON.stringify({ demo_id }) }),
        onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['opponent-teams', vars.id] }),
    });

    const addCounterStrat = useMutation({
        mutationFn: ({ id, ...data }: Partial<CounterStrat> & { id: number }) =>
            fetcher(`${API}/${id}/counter-strats`, { method: 'POST', body: JSON.stringify(data) }),
        onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['opponent-teams', vars.id] }),
    });

    const deleteCounterStrat = useMutation({
        mutationFn: ({ opponentId, stratId }: { opponentId: number; stratId: number }) =>
            fetcher(`${API}/${opponentId}/counter-strats/${stratId}`, { method: 'DELETE' }),
        onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['opponent-teams', vars.opponentId] }),
    });

    const refreshInsights = useMutation({
        mutationFn: (id: number) =>
            fetcher(`${API}/${id}/insights/refresh`, { method: 'POST' }),
        onSuccess: (_d, id) => qc.invalidateQueries({ queryKey: ['opponent-teams', id] }),
    });

    const updatePlayer = useMutation({
        mutationFn: ({ opponentId, playerId, ...data }: { opponentId: number; playerId: number; alias?: string; notes?: string }) =>
            fetcher(`${API}/${opponentId}/players/${playerId}`, { method: 'PATCH', body: JSON.stringify(data) }),
        onSuccess: (_d, vars) => qc.invalidateQueries({ queryKey: ['opponent-teams', vars.opponentId] }),
    });

    return {
        OpponentTeams,
        create,
        update,
        remove,
        updateShares,
        attachDemo,
        detachDemo,
        addCounterStrat,
        deleteCounterStrat,
        refreshInsights,
        updatePlayer,
    };
}