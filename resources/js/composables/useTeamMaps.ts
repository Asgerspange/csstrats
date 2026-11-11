import { computed } from 'vue';
import { useApi } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';
import { useStore } from '@/store';
import { storeToRefs } from 'pinia';
import { useToast } from '@/composables/useToast';

export interface TeamMap {
    id: number;
    name: string;
    display_name: string;
    image_path: string;
    tactic_count: number;
    access_state: 'accessible' | 'locked' | 'view-only';
    selected: boolean;
}

export interface TeamMapsResponse {
    maps: TeamMap[];
    lock_expires_at: string | null;
    entitlement_mode: 'legacy' | 'map_based';
    max_maps: number | null;
}

export default function useTeamMaps() {
    const { useGet, usePost } = useApi();
    const queryClient = useQueryClient();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);
    const toast = useToast();

    const queryKey = ['team-maps', selectedTeamId];

    const mapsQuery = useGet<TeamMapsResponse>(
        queryKey,
        () => `/api/team/${selectedTeamId.value}/maps`,
        undefined,
        computed(() => !!selectedTeamId.value),
    );

    const selectMutation = usePost<unknown, { map_ids: number[] }>(queryKey);

    class TeamMaps {
        static get() {
            return mapsQuery;
        }

        static async selectMaps(mapIds: number[]) {
            if (!selectedTeamId.value) return;
            try {
                await selectMutation.mutateAsync({
                    url: `/api/team/${selectedTeamId.value}/maps/select`,
                    payload: { map_ids: mapIds },
                });
                await queryClient.invalidateQueries({ queryKey });
                toast.success('Maps updated', 'Your map selection has been updated.');
            } catch (e: any) {
                const message = e?.response?.data?.message ?? 'Could not update map selection.';
                toast.error('Update failed', message);
            }
        }

        static async refresh() {
            return queryClient.invalidateQueries({ queryKey });
        }
    }

    return { TeamMaps, queryKey };
}
