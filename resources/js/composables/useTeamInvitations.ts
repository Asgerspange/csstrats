import { useApi } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';
import { useStore } from '@/store';
import { storeToRefs } from 'pinia';

export interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    inviter: string | null;
    created_at: string | null;
    expires_at: string | null;
    expired: boolean;
}

export default function useTeamInvitations() {
    const { useGet, usePost } = useApi();
    const queryClient = useQueryClient();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    const listQuery = useGet<{ invitations: PendingInvitation[] }>(
        ['team-invitations', selectedTeamId],
        () => `/api/team/${selectedTeamId.value}/invitations`,
        {},
        () => !!selectedTeamId.value,
    );

    const mutation = usePost<any, any>(['team-invitations', selectedTeamId]);

    return {
        list: () => listQuery,

        async send(teamId: number, payload: { email: string; role?: string }) {
            const res = await mutation.mutateAsync({
                url: `/api/team/${teamId}/invitations`,
                payload,
            });
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['team-invitations', selectedTeamId] }),
                queryClient.invalidateQueries({ queryKey: ['team-stats', 'roster', selectedTeamId] }),
            ]);
            return res;
        },

        async revoke(teamId: number, inviteId: number) {
            const res = await mutation.mutateAsync({
                url: `/api/team/${teamId}/invitations/${inviteId}/revoke`,
                payload: {},
            });
            await queryClient.invalidateQueries({ queryKey: ['team-invitations', selectedTeamId] });
            return res;
        },
    };
}
