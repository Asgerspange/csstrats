import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref, watch } from 'vue';

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const fetcher = async (url: string) => {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
};

export interface InsightsStatus {
    status: 'pending' | 'complete' | 'failed' | null;
    insights_updated_at: string | null;
    insights_error: string | null;
    ai_insights: Record<string, any> | null;
}

export default function useOpponentInsightsStatus(opponentId: number | (() => number)) {
    const qc       = useQueryClient();
    const getId    = () => typeof opponentId === 'function' ? opponentId() : opponentId;
    const showSuccess = ref(false);

    const query = useQuery<InsightsStatus>({
        queryKey: ['opponent-insights-status', computed(getId)],
        queryFn:  () => fetcher(`/api/opponent-teams/${getId()}/insights/status`),
        enabled:  computed(() => !!getId()),
        refetchInterval: (q) => {
            const status = q.state.data?.status;
            return status === 'pending' ? 3000 : false;
        },
        refetchIntervalInBackground: false,
    });

    watch(
        () => query.data.value?.status,
        (newStatus, oldStatus) => {
            if (newStatus === 'complete' && oldStatus === 'pending') {
                qc.invalidateQueries({ queryKey: ['opponent-teams', getId()] });

                // Flash success banner for 3 seconds
                showSuccess.value = true;
                setTimeout(() => { showSuccess.value = false; }, 3000);
            }
        }
    );

    return { ...query, showSuccess };
}