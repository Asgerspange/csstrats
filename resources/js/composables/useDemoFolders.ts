import { useApi } from '@/composables/useApi';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { ref, computed } from 'vue';
export default function useDemoFolders() {
    const { useGet, usePost, useDelete, usePatch } = useApi();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    const queryKey = ['demofolders'];
    const folderId = ref<number | null>(null);

    const demosQuery = useGet<any[]>(
        queryKey, 
        `/api/demos/folders`,
    );

    const findQuery = useGet<any>(
        ['demofolder', folderId],
        computed(() => folderId.value ? `/api/demos/folders/${folderId.value}` : ''),
        undefined,
        computed(() => !!folderId.value)
    );

    const createMutation = usePost<any, any>(queryKey);
    const deleteMutation = useDelete(queryKey);
    const updateMutation = usePatch<any, any>(queryKey);

    class DemoFolders {
        static get() {
            return demosQuery;
        }

        static find(id: number) {
            folderId.value = id;
            return findQuery;
        }

        static async create() {
            if (!selectedTeamId.value) return;

            return await createMutation.mutateAsync({
                url: `/api/demos/folders`,
                payload: { team_id: selectedTeamId.value }
            });
        }

        static async update(id: number, payload: { folder_name: string, color: string, teams?: number[] }) {
            return await updateMutation.mutateAsync({
                url: `/api/demos/folders/${id}`,
                payload
            });
        }

        static async delete(id: number) {
            return await deleteMutation.mutateAsync(`/api/demos/folders/${id}`);
        }
    }

    return {
        DemoFolders,
        isCreating: createMutation.isPending,
        isUpdating: updateMutation.isPending,
        isDeleting: deleteMutation.isPending
    };
}