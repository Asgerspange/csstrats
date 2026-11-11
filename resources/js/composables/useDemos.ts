import { ref, reactive } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { useApi, api } from '@/composables/useApi';

export default function useDemos() {
    const { useGet, usePost, useDelete, usePatch } = useApi();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);
    const queryClient = useQueryClient();
    const hideFolderDemos = ref(false);
    const enabled = ref(false);
    const queryKey = reactive(['demos', { hideFolderDemos }]);

    const demosQuery = useQuery({
        queryKey,
        enabled: () => enabled.value,
        retry: (failureCount: number, error: any) => {
            const status = error?.response?.status;
            if (status === 403 || status === 429) return false;
            return failureCount < 2;
        },
        refetchInterval: (query: any) => {
            const data = query.state.data;
            const hasProcessing = data?.some((demo: any) => demo.status);
            return hasProcessing ? 10000 : false;
        },
        refetchIntervalInBackground: true,
        queryFn: async () => {
            const { data } = await api.get(`/api/demos?hideFolderDemos=${hideFolderDemos.value}`);
            return data;
        },
    });

    const createMutation = usePost<any, any>(['demos']);
    const deleteMutation = useDelete(['demos']);
    const updateMutation = usePatch<any, any>(['demos']);
    const curateMutation = usePost<any, any>(['demos']);

    class Demos {
        static get(hideDemos = false) {
            hideFolderDemos.value = hideDemos;
            enabled.value = true;
            return demosQuery;
        }

        static show(id: number) {
            console.log(`Fetching demo with ID: ${id}`);
            return useGet<any>(
                ['demo', id],
                `/api/demos/${id}`,
            );
        }

        // Curated admin demos (for the admin management view + tactic editor).
        static curated(options = {}) {
            return useGet<any[]>(['demos', 'curated'], '/api/demos?curated=1', options);
        }

        static async create(payload: FormData) {
            if (!selectedTeamId.value) return;

            return await createMutation.mutateAsync({
                url: `/api/demos`,
                payload: payload 
            });
        }

        static async update(id: number, payload: { demo_name: string, shared_teams?: number[], demo_folder_id?: number | null }) {
            return await updateMutation.mutateAsync({
                url: `/api/demos/${id}/update`,
                payload
            }).then(() => {
                queryClient.invalidateQueries({ queryKey: ['demofolders'] });
                queryClient.invalidateQueries({ queryKey: ['demofolder', payload.demo_folder_id] });
            });
        }

        static async delete(id: number) {
            return await deleteMutation.mutateAsync(`/api/demos/${id}`);
        }

        // Promote a parsed demo to a curated admin demo (mark the given rounds).
        static async curateRounds(id: number, rounds: number[]) {
            const res = await curateMutation.mutateAsync({
                url: `/api/demos/${id}/curate-rounds`,
                payload: { rounds },
            });
            queryClient.invalidateQueries({ queryKey: ['demo', id] });
            queryClient.invalidateQueries({ queryKey: ['demos'] });
            return res;
        }

        // Remove rounds from an admin demo's curated set.
        static async uncurateRounds(id: number, rounds: number[]) {
            const res = await curateMutation.mutateAsync({
                url: `/api/demos/${id}/uncurate-rounds`,
                payload: { rounds },
            });
            queryClient.invalidateQueries({ queryKey: ['demo', id] });
            queryClient.invalidateQueries({ queryKey: ['demos'] });
            return res;
        }

        static async refresh() {
            return await queryClient.invalidateQueries({ queryKey: queryKey });
        }
    }

    return {
        Demos,
        isCreating: createMutation.isPending,
        isUpdating: updateMutation.isPending,
        isDeleting: deleteMutation.isPending
    };
}