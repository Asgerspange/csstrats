import { useApi } from '@/composables/useApi';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { computed } from 'vue';

export interface TeamTactic {
    id: number;
    name: string;
    type: string;
    map: string;
    side: 'CT' | 'T';
    tags: string[];
    stratbook_id: number;
    stratbook_name: string;
    tactic_id: number | null;
    sort_order: number;
    updated_at: string;
}

/**
 * Cross-stratbook tactic overview: all tactic rows for the selected team,
 * in one request. Client-side filtered by the Stratbooks sub-route.
 */
export function useTeamTactics() {
    const { useGet } = useApi();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    const queryKey = ['team-tactics', selectedTeamId];

    const tacticsQuery = useGet<TeamTactic[]>(
        queryKey,
        () => `/api/teams/${selectedTeamId.value}/tactics`,
        undefined,
        computed(() => !!selectedTeamId.value),
    );

    return {
        ...tacticsQuery,
        queryKey,
    };
}
