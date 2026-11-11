import { useApi, api } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';
import { useStore } from '@/store';
import { storeToRefs } from 'pinia';
import type { Team } from '@/PagesNew/Team/types';

export default function useTeams() {
    const { useGet, usePost, useDelete } = useApi();
    const queryClient = useQueryClient();

    const store = useStore();
    
    const { selectedTeamId } = storeToRefs(store);

    const teamsQuery = useGet<Team[]>(['teams'], `/api/teams`);

    const findTeamQuery = useGet<Team>(
        ['team', selectedTeamId],
        () => `/api/teams/${selectedTeamId.value}`,
        {}, 
        () => !!selectedTeamId.value
    );

    const createMutation = usePost<any, any>(['teams']);
    const updateMutation = usePost<any, any>(['team', selectedTeamId]);
    const deleteMutation = useDelete<any>(['teams']);

    class Teams {
        /**
         * Returns the reactive query object (contains .data, .isLoading, etc.)
         */
        static get() {
            return teamsQuery;
        }

        /**
         * Fetches a single team. Using direct api call to avoid hook context issues.
         */
        static find() {
            return findTeamQuery;
        }

        static async create(payload: { team_name: string }) {
            return await createMutation.mutateAsync({
                url: `/api/teams`,
                payload: { ...payload }
            });
        }

        static async updateName(teamId: number, payload: { team_name?: string }) {
            return await updateMutation.mutateAsync({
                url: `/api/team/${teamId}/settings/name`,
                payload: { ...payload }
            });
        }

        static async updateRole(teamId: number, payload: { user_id: number, role: string }) {
            return await updateMutation.mutateAsync({
                url: `/api/team/${teamId}/settings/roles`,
                payload: { ...payload }
            });
        }

        static async removeMember(teamId: number, userId: number) {
            const r = await updateMutation.mutateAsync({
                url: `/api/team/${teamId}/remove-member`,
                payload: { user_id: userId }
            });
            queryClient.invalidateQueries({ queryKey: ['team-stats', 'roster', selectedTeamId] });
            queryClient.invalidateQueries({ queryKey: ['team-stats', 'overview', selectedTeamId] });
            return r;
        }

        /**
         * Soft-deletes a team (owner/coach only, enforced server-side).
         * After the delete we refetch the teams list, repoint
         * selectedTeamId at the next remaining team (or null), and drop any
         * cached queries scoped to the deleted team.
         */
        static async deleteTeam(teamId: number) {
            const r = await deleteMutation.mutateAsync(`/api/team/${teamId}`);
            await queryClient.refetchQueries({ queryKey: ['teams'] });
            const fresh = queryClient.getQueryData<Team[]>(['teams']) ?? [];
            selectedTeamId.value = fresh.length ? fresh[0].id : null;
            queryClient.removeQueries({ queryKey: ['team', teamId] });
            queryClient.invalidateQueries({ queryKey: ['team-stats'] });
            return r;
        }

        /**
         * Manually refresh the teams list
         */
        static async refresh() {
            return await queryClient.invalidateQueries({ queryKey: ['teams'] });
        }
    }

    return {
        Teams
    }
}