import { useApi } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { ref, computed } from 'vue';

/**
 * Join-by-link: the shareable team invite URL. Fetches the token on mount
 * if not already on the team object; regenerates (rotates) on demand.
 * Replaces the raw-axios InviteLink.vue with the TanStack pattern.
 */
export function useInviteLink() {
    const { useGet, usePost } = useApi();
    const queryClient = useQueryClient();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    const linkQuery = useGet<{ token: string; url: string }>(
        ['team-invite-link', selectedTeamId],
        () => `/api/teams/${selectedTeamId.value}/invite`,
        undefined,
        computed(() => !!selectedTeamId.value),
    );

    const regenerateMutation = usePost<{ token: string; url: string }, unknown>(
        ['team-invite-link', selectedTeamId],
    );

    /**
     * Rotates the invite token. The backend endpoint is named "revoke" but
     * the behavior is to invalidate the old token and generate a new one;
     * we expose it as `regenerate` to match the UI verb the user sees.
     */
    async function regenerate(): Promise<void> {
        await regenerateMutation.mutateAsync({
            url: `/api/teams/${selectedTeamId.value}/invite/revoke`,
            payload: {},
        });
        await queryClient.invalidateQueries({ queryKey: ['team-invite-link', selectedTeamId] });
    }

    const inviteUrl = computed(() => {
        const data = linkQuery.data.value;
        if (data?.url) return data.url;
        if (data?.token) return `${window.location.origin}/invite/${data.token}`;
        return '';
    });

    return {
        linkQuery,
        inviteUrl,
        regenerate,
        isRegenerating: regenerateMutation.isPending,
    };
}
