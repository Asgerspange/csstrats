import { useInfiniteQuery } from '@tanstack/vue-query'
import axios from 'axios'

export function useTactics(filters: Record<string, any> = {}) {
    const perPage = 35

    return useInfiniteQuery({
        // The queryKey includes filters so the cache resets when filters change
        queryKey: ['tactics', filters],
        
        queryFn: async ({ pageParam = 1 }) => {
            const { data } = await axios.get('/api/tactics', {
                // pageParam is passed to the backend 'page' key
                params: { 
                    page: pageParam, 
                    perPage, 
                    ...filters 
                },
            })
            return data
        },

        initialPageParam: 1,

        getNextPageParam: (lastPage, allPages) => {
            // lastPage contains 'tactics' (the current slice) and 'count' (total total)
            const totalLoaded = allPages.flatMap(p => p.tactics).length
            
            // If we haven't reached the total count, increment the page
            return totalLoaded < lastPage.count ? allPages.length + 1 : undefined
        },
    })
}