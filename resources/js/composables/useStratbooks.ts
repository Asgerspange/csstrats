import { useApi } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { computed } from 'vue';
import axios from 'axios';
import type { StratbookSummary } from '@/PagesNew/Team/types';

export default function useStratbooks() {
    const { useGet, usePost, useDelete, usePatch } = useApi();
    const queryClient = useQueryClient();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    const queryKey = ['stratbooks', selectedTeamId];

    const stratbooksQuery = useGet<StratbookSummary[]>(
        queryKey, 
        `/api/stratbooks`, 
        () => ({ team_id: selectedTeamId.value }),
        computed(() => !!selectedTeamId.value)
    );

    const createMutation = usePost<any, any>(['teams']);
    const deleteMutation = useDelete(['teams']);
    const updateMutation = usePatch<any, any>(['teams']);

    class Stratbooks {
        static get() {
            return stratbooksQuery;
        }

        static async create(payload: { stratbook_name: string }) {
            if (!selectedTeamId.value) return;
            return await createMutation.mutateAsync({
                url: `/api/stratbooks`,
                payload: { ...payload, team_id: selectedTeamId.value }
            }, {
                onSuccess: () => {
                    queryClient.invalidateQueries({ queryKey: ['team', selectedTeamId.value] });
                    queryClient.invalidateQueries({ queryKey });
                }
            });
        }

        static async update(id: number, payload: { stratbook_name: string }) {
            return await updateMutation.mutateAsync({
                url: `/api/stratbooks/${id}`,
                payload
            }, {
                onSuccess: () => {
                    queryClient.invalidateQueries({ queryKey: ['team', selectedTeamId.value] });
                    queryClient.invalidateQueries({ queryKey });
                }
            });
        }

        static async delete(id: number) {
            return await deleteMutation.mutateAsync(`/api/stratbooks/${id}`, {
                onSuccess: () => {
                    queryClient.invalidateQueries({ queryKey: ['team', selectedTeamId.value] });
                    queryClient.invalidateQueries({ queryKey });
                }
            });
        }

        /**
         * Triggers a PDF export as a direct download
         */
        static async export(
            stratbookId: number, 
            tacticIds: number[], 
            sortRules: { column: string; dir: string }[],
            options: { include_toc: boolean, one_per_page: boolean }
        ) {
            if (!Array.isArray(tacticIds)) {
                console.error("Tactic IDs must be an array");
                return;
            }

            try {
                const response = await axios.post(`/api/stratbooks/${stratbookId}/export-batch`, {
                    tactic_ids: tacticIds,
                    sort_rules: sortRules,
                    include_toc: options.include_toc,
                    one_per_page: options.one_per_page
                }, {
                    responseType: 'blob'
                });

                const blob = new Blob([response.data], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                
                link.href = url;
                link.setAttribute('download', `Stratbook_Export_${new Date().getTime()}.pdf`);
                
                document.body.appendChild(link);
                link.click();
                
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error("PDF Export failed:", error);
            }
        }
    }

    return {
        Stratbooks,
        isCreating: createMutation.isPending,
        isUpdating: updateMutation.isPending,
        isDeleting: deleteMutation.isPending
    };
}