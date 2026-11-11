import { useApi } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { reactive } from 'vue';

export default function useBoards() {
    const { useGet, usePost, useDelete, usePatch } = useApi();
    const queryClient = useQueryClient();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    // 1. Use the Ref directly in the key (no .value) so Vue Query watches it.
    const queryKey = ['boards', selectedTeamId];

    // 2. Create a reactive object for params. 
    // This allows the 'team_id' property to automatically update when the store ref changes.
    const queryParams = reactive({
        team_id: selectedTeamId
    });

    const boardsQuery = useGet<any[]>(
        queryKey, 
        `/api/boards`, 
        queryParams // Pass the reactive object reference
    );

    const createMutation = usePost<any, any>(['boards']);
    const deleteMutation = useDelete(['boards']);
    const updateMutation = usePatch<any, any>(['boards']);

    class Boards {
        static get() {
            return boardsQuery;
        }

        static async create(payload: FormData) {
            if (!selectedTeamId.value) return;

            payload.append('team_id', selectedTeamId.value.toString());

            return await createMutation.mutateAsync({
                url: `/api/boards`,
                payload: payload 
            });
        }

        static async update(id: number, payload: { board_name: string }) {
            return await updateMutation.mutateAsync({
                url: `/api/boards/${id}`,
                payload
            }, {
                onSuccess: () => {
                    // Invalidate both the specific team list and the generic board list
                    queryClient.invalidateQueries({ queryKey: ['team', selectedTeamId.value] });
                    queryClient.invalidateQueries({ queryKey: ['boards'] }); 
                }
            });
        }

        static async delete(id: number) {
            return await deleteMutation.mutateAsync(`/api/boards/${id}`, {
                onSuccess: () => {
                    // Invalidate all board queries to be safe
                    queryClient.invalidateQueries({ queryKey: ['boards'] });
                }
            });
        }
    }

    return {
        Boards,
        isCreating: createMutation.isPending,
        isUpdating: updateMutation.isPending,
        isDeleting: deleteMutation.isPending
    };
}