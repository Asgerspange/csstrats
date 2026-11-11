import { useQuery } from '@tanstack/vue-query'
import { computed } from 'vue'

export interface Product {
    name: string
    description: string
    monthly_price: number
    price: number
    priceId: string
    features: string[]
}

export interface LandingData {
    user: Record<string, unknown> | null
    subscribed: boolean
    products: Product[]
    tactics: number
    demos: number
    users: number
}

async function fetchLandingData(): Promise<LandingData> {
    const res = await fetch('/welcome-data', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
    if (!res.ok) throw new Error('Failed to load landing data')
    return res.json()
}

/**
 * Shared landing-page data. TanStack Query dedupes by key, so calling this in
 * multiple sections reuses a single request/cache entry.
 */
export function useLandingData() {
    const query = useQuery({
        queryKey: ['landingData'],
        queryFn: fetchLandingData,
        staleTime: 1000 * 60 * 5,
    })

    return {
        query,
        user: computed(() => query.data.value?.user ?? null),
        subscribed: computed(() => query.data.value?.subscribed ?? false),
        products: computed<Product[]>(() => query.data.value?.products ?? []),
        tactics: computed(() => query.data.value?.tactics ?? 0),
        demos: computed(() => query.data.value?.demos ?? 0),
        users: computed(() => query.data.value?.users ?? 0),
    }
}
