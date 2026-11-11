import { computed, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import { useQueryClient } from '@tanstack/vue-query'

interface MapEntry {
    id: number;
    name: string;
    slug: string;
    display_name: string;
    image_path: string;
    width: number;
    height: number;
    active: boolean;
}

export default function useMaps() {
    const { useGet } = useApi()
    const queryClient = useQueryClient()

    const mapId = ref<number | null>(null)

    const mapQuery = useGet<MapEntry[]>(
        ['maps'],
        '/api/maps'
    )

    const findQuery = useGet<MapEntry>(
        ['map', mapId],
        computed(() => mapId.value ? `/api/maps/${mapId.value}` : ''),
        undefined,
        computed(() => !!mapId.value)
    )

    class Maps {
        static get() {
            return mapQuery
        }

        static find(id: number) {
            mapId.value = id
            return findQuery
        }

        static async refresh() {
            return queryClient.invalidateQueries({ queryKey: ['maps'] })
        }
    }

    return { Maps }
}