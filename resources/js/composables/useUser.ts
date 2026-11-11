import { useApi, api } from '@/composables/useApi';
import { useQueryClient } from '@tanstack/vue-query';

export default function useUser() {
    const { useGet } = useApi();
    const queryClient = useQueryClient();

    const userQuery = useGet<any[]>(['user'], `/api/user`);

    class User {
        /**
         * Returns the reactive query object (contains .data, .isLoading, etc.)
         */
        static get() {
            return userQuery;
        }

        /**
         * Manually refresh the user
         */
        static async refresh() {
            return await queryClient.invalidateQueries({ queryKey: ['user'] });
        }
    }

    return {
        User
    }
}