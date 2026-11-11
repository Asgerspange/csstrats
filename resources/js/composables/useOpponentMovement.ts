import { useQuery } from '@tanstack/vue-query';
import { computed } from 'vue';

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const fetcher = async (url: string) => {
    const res = await fetch(url, { headers: { 'X-CSRF-TOKEN': getCsrf() } });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
};

export interface MovementPoint {
    x: number; y: number; round: number; demo_id: number; tick: number;
}
export interface HeatmapPoint {
    x: number; y: number; value: number;
}
export interface PlayerPath {
    steamid: string;
    name: string;
    points: MovementPoint[];
    heatmap_points: HeatmapPoint[];
}
export interface MovementData {
    paths: PlayerPath[];
    maps: string[];
    total_points: number;
}

export interface GrenadePoint {
    steamid: string; x: number; y: number; round: number; map: string;
}
export interface GrenadeData {
    grenades: Record<string, GrenadePoint[]>;
    types: string[];
    total_demos: number;
}

export function useOpponentMovement(
    opponentId: number | (() => number),
    sample = 8,
    enabled = () => true,
) {
    const getId = () => typeof opponentId === 'function' ? opponentId() : opponentId;

    return useQuery<MovementData>({
        queryKey: ['opponent-movement', computed(getId), sample],
        queryFn:  () => fetcher(`/api/opponent-teams/${getId()}/movement?sample=${sample}`),
        enabled:  computed(() => !!getId() && enabled()),
        staleTime: 1000 * 60 * 10, // 10 min — expensive to compute
    });
}

export function useOpponentGrenades(
    opponentId: number | (() => number),
    enabled = () => true,
) {
    const getId = () => typeof opponentId === 'function' ? opponentId() : opponentId;

    return useQuery<GrenadeData>({
        queryKey: ['opponent-grenades', computed(getId)],
        queryFn:  () => fetcher(`/api/opponent-teams/${getId()}/grenades`),
        enabled:  computed(() => !!getId() && enabled()),
        staleTime: 1000 * 60 * 10,
    });
}