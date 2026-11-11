import { useInfiniteQuery, useQueryClient } from '@tanstack/vue-query'
import axios from 'axios'
import { ref, Ref, reactive } from 'vue'
import { useApi } from '@/composables/useApi';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import api from '@/lib/axios'
import { toValue, type MaybeRefOrGetter } from 'vue';
import { useUXWizz } from '@/composables/useUXWizz'
const { track } = useUXWizz()
export { api }

export default function useStratbookTactics(stratbookId: Ref<number> = ref(0)) {
    const { useGet, usePost, useDelete, usePatch } = useApi();
    const queryClient = useQueryClient();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    // Keys: Use Refs directly so Vue Query watches them
    const listQueryKey = ['stratbooks', stratbookId, 'tactics'];
    const allQueryKey = ['stratbookTactics', selectedTeamId];

    // FIX: Create reactive params for the 'all' query so it updates when team changes
    const allQueryParams = reactive({ 
        team_id: selectedTeamId 
    });

    const customCreateMutation = usePost<any, any>(listQueryKey);

    // Mutations - These will automatically invalidate the 'listQueryKey' on success
    const createMutation = usePost<any, any>(listQueryKey);
    const deleteMutation = useDelete(listQueryKey);
    const updateMutation = usePatch<any, any>(listQueryKey);

    class StratbookTactics {
        /**
         * Get the list of tactics (Non-infinite version)
         */
        static get() {
            return useGet<any[]>(
                listQueryKey, 
                // URL thunk reads the current ref value when key changes
                () => `/api/stratbooks/${stratbookId.value}/tactics`
            );
        }

        static all() {
            return useGet<any[]>(
                allQueryKey,
                () => `/api/stratbooks/tactics/all`, 
                // FIX: Pass the reactive object, not the static value
                allQueryParams
            );
        }

        static async export(id: number) {
            const response = await axios.get(
                `/api/stratbooks/${stratbookId.value}/tactics/${id}/export`, 
                { responseType: 'blob' }
            );
            return response.data;
        }

        /**
         * Find and cache a specific tactic by ID
         * Uses a unique key for granular caching
         */
        static find(id: Ref<number | null>) {
            return useGet<any>(
                // Key is ['stratbookTactic', Ref<number>]
                ['stratbookTactic', id], 
                // URL resolves dynamically
                () => `/api/stratbooks/${stratbookId.value}/tactics/${id.value}`,
                // Params
                undefined,
                // Only run if we have a valid ID
                () => !!id.value
            );
        }

        static async create(payload: any = {}) {
            if (!stratbookId.value) {
                console.error("No Stratbook ID selected");
                return;
            }
            track('tactic:added')
            const result = await createMutation.mutateAsync({
                url: `/api/stratbooks/${stratbookId.value}/tactics`,
                payload
            });

            queryClient.invalidateQueries({ queryKey: ['tactics', stratbookId] });
            queryClient.invalidateQueries({ queryKey: allQueryKey });

            return result;
        }

        /**
         * Update an existing tactic
         */
        static async update(id: number, payload: any = {}) {

            const result = await updateMutation.mutateAsync({
                url: `/api/stratbooks/${stratbookId.value}/tactics/${id}`,
                payload
            });

            queryClient.invalidateQueries({ 
                queryKey: ['stratbookTactic', id], 
                exact: false 
            });

            // Also invalidate the list to reflect changes
            queryClient.invalidateQueries({ queryKey: ['tactics', stratbookId], exact: false });
            
            return result;
        }

        /**
         * Delete a tactic
         */
        static async delete(id: number) {
            const result = await deleteMutation.mutateAsync(
                `/api/stratbooks/${stratbookId.value}/tactics/${id}`
            );

            // Clean up the specific tactic cache after deletion
            queryClient.removeQueries({ queryKey: ['stratbookTactic', id] });

            return result;
        }

        static async createCustom(payload: any) {
            if (!stratbookId.value) return;

            track('tactic:added')
            const result = await customCreateMutation.mutateAsync({
                url: `/api/stratbooks/${stratbookId.value}/custom-tactics`,
                payload
            });

            // Invalidate the scroll list so the new tactic appears
            queryClient.invalidateQueries({ queryKey: ['tactics', stratbookId.value] }); // .value 👈
            return result;
        }

        /**
         * Persist a new sort order after drag-and-drop reorder
         */
        static async reorder(items: { id: number; sort_order: number }[]) {
            if (!stratbookId.value) return;

            const result = await api.put(
                `/api/stratbooks/${stratbookId.value}/tactics/reorder`,
                { items }
            );

            // Invalidate so the list re-fetches with new order
            queryClient.invalidateQueries({ queryKey: ['tactics', stratbookId], exact: false });

            return result.data;
        }
    }

    return {
        StratbookTactics,
        isCreating: createMutation.isPending,
        isUpdating: updateMutation.isPending,
        isDeleting: deleteMutation.isPending,
        isCreatingCustom: customCreateMutation.isPending,
        createError: createMutation.error,
        updateError: updateMutation.error,
    };
}

export function useStratbookTacticsScroll(
  stratbookId: Ref<number> = ref(0), 
  filters: Record<string, any> = {}
) {
  const perPage = 35

  return useInfiniteQuery({
    queryKey: ['tactics', stratbookId],
    
    // Using the 'api' instance from your useGet logic
    queryFn: async ({ pageParam = 1 }) => {
      const { data } = await api.get(`/api/stratbooks/${toValue(stratbookId)}/tactics`, {
        params: { 
          page: pageParam, 
          perPage, 
          ...toValue(filters) 
        },
      })
      return data
    },

    initialPageParam: 1,

    // Consistency: Copying the retry logic from your useGet
    retry: (failureCount, error: any) => {
      const status = error?.response?.status;
      if (status === 403 || status === 429) return false;
      return failureCount < 2;
    },

    getNextPageParam: (lastPage, allPages) => {
      const totalLoaded = allPages.flatMap(p => p.tactics).length
      return totalLoaded < lastPage.count ? allPages.length + 1 : undefined
    },
  })
}