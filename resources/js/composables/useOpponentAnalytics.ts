import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';

const fetcher = async (url: string) => {
    const res = await fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
};

// ─── Types ────────────────────────────────────────────────────────────────────

export interface PlayerStat {
    steamid: string;
    name: string;
    demos: number;
    avg_rating: number;
    avg_kast: number;
    avg_adr: number;
    avg_kills: number;
    avg_deaths: number;
    kd_ratio: number;
    hs_percent: number;
    avg_utility_dmg: number;
    avg_flashed: number;
    avg_mvps: number;
    multi_kills: { '3k': number; '4k': number; '5k': number };
    clutches: Record<string, number>;
    hitgroups: { head: number; chest: number; stomach: number; arms: number; legs: number };
}

export interface HeatmapPlayer {
    steamid: string;
    name: string;
    points: { x: number; y: number; value: number }[];
}

export interface RoundOutcomes {
    t_wins: number;
    ct_wins: number;
    total_rounds: number;
    t_win_rate: number;
    ct_win_rate: number;
    impact_players: { steamid: string; name: string; avg_round_kills: number; rounds: number }[];
}

export interface WeaponUsage {
    steamid: string;
    name: string;
    weapons: Record<string, number>;
    top_weapon: string;
}

export interface KillDetail {
    steamid: string;
    name: string;
    total_kills: number;
    headshots: number;
    thru_smoke: number;
    blind_kills: number;
    noscopes: number;
}

export interface FirstKillTiming {
    steamid: string;
    name: string;
    avg_first_kill_seconds: number;
    samples: number;
}

export interface OpponentAnalytics {
    player_stats: PlayerStat[];
    kill_heatmap: HeatmapPlayer[];
    death_heatmap: HeatmapPlayer[];
    round_outcomes: RoundOutcomes;
    weapon_usage: WeaponUsage[];
    grenade_patterns: KillDetail[];
    first_kill_timing: FirstKillTiming[];
    map: string[];
    total_demos: number;
    total_rounds: number;
}

// ─── Composable ───────────────────────────────────────────────────────────────

export default function useOpponentAnalytics(opponentId: number | (() => number)) {
    const getId = () => typeof opponentId === 'function' ? opponentId() : opponentId;

    const query = useQuery<OpponentAnalytics>({
        queryKey: ['opponent-analytics', computed(getId)],
        queryFn:  () => fetcher(`/api/opponent-teams/${getId()}/analytics`),
        enabled:  computed(() => !!getId()),
        staleTime: 1000 * 60 * 5, // 5 min — analytics are expensive
    });

    return query;
}