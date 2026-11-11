/**
 * useCallouts.ts
 * Drop in: resources/js/composables/useCallouts.ts
 *
 * Loads all callout zones for the current team and provides them
 * via Vue's provide/inject so CalloutMentionTextarea and CalloutRenderer
 * can access them without prop-drilling.
 *
 * Usage in a parent component (e.g. TacticCreateDialog.vue):
 *
 *   import { useCallouts } from '@/composables/useCallouts'
 *   const { provideCallouts } = useCallouts(teamId)
 *   provideCallouts()
 *
 * Then CalloutMentionTextarea and CalloutRenderer inside that tree
 * will automatically have access to the zones via inject('callout-zones').
 */

import { ref, provide, watch } from 'vue'
import axios from 'axios'

export interface CalloutZone {
    id: number
    map_id: number
    map_name: string
    map_key: string
    map_image_path?: string
    canonical_name: string
    display_name: string  // team's custom name, falls back to canonical
    short_code: string | null
    polygon: [number, number][]
    color: string
}

const _cache = new Map<number, CalloutZone[]>() // teamId → zones

export function useCallouts(teamId: number | null | undefined) {
    const zones = ref<CalloutZone[]>([])
    const loading = ref(false)

    async function load() {
        if (!teamId) return

        // Use cache to avoid re-fetching on every dialog open
        if (_cache.has(teamId)) {
            zones.value = _cache.get(teamId)!
            return
        }

        loading.value = true
        try {
            const res = await axios.get<CalloutZone[]>(`/api/callouts/all?team_id=${teamId}`)
            zones.value = res.data
            _cache.set(teamId, res.data)
        } catch {
            zones.value = []
        } finally {
            loading.value = false
        }
    }

    /** Call this in the parent component's setup() to make zones available to children */
    function provideCallouts() {
        provide('callout-zones', zones)
        load()
        // Re-provide after load (reactive provide doesn't work with arrays directly,
        // so we use a ref and provide the ref itself)
        watch(zones, (v) => {
            // zones is already reactive, provide already shares the ref
        })
    }

    /** Invalidate cache (call after saving team custom names) */
    function invalidateCache() {
        if (teamId) _cache.delete(teamId)
    }

    return { zones, loading, load, provideCallouts, invalidateCache }
}