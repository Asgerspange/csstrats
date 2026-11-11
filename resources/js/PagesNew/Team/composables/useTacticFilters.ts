import { computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import type { TacticFilters } from '../types';

const STORAGE_PREFIX = 'team-filters:';

/**
 * Filter state lives in the URL (shareable, back-button works) and is
 * mirrored to localStorage keyed by teamId so a fresh visit restores the
 * last selection. UI state, not server state.
 */
export function useTacticFilters() {
    const route = useRoute();
    const router = useRouter();
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);

    const storageKey = computed(() => STORAGE_PREFIX + (selectedTeamId.value ?? 'none'));

    const filters = reactive<TacticFilters>({
        map: null,
        side: null,
        type: null,
        q: '',
    });

    function readFromUrl(): Partial<TacticFilters> {
        const q = route.query;
        return {
            map: typeof q.map === 'string' ? q.map : null,
            side: q.side === 'CT' || q.side === 'T' ? q.side : null,
            type: typeof q.type === 'string' ? q.type : null,
            q: typeof q.q === 'string' ? q.q : '',
        };
    }

    function readFromStorage(): Partial<TacticFilters> {
        if (!selectedTeamId.value) return {};
        try {
            const raw = localStorage.getItem(storageKey.value);
            return raw ? JSON.parse(raw) : {};
        } catch {
            return {};
        }
    }

    function applyToFilters() {
        const fromUrl = readFromUrl();
        const hasUrlFilters = fromUrl.map || fromUrl.side || fromUrl.type || fromUrl.q;
        const source = hasUrlFilters ? fromUrl : { ...fromUrl, ...readFromStorage() };
        filters.map = source.map ?? null;
        filters.side = (source.side as TacticFilters['side']) ?? null;
        filters.type = source.type ?? null;
        filters.q = source.q ?? '';
    }

    applyToFilters();

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;

    watch(filters, () => {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query: Record<string, string> = {};
            if (filters.map) query.map = filters.map;
            if (filters.side) query.side = filters.side;
            if (filters.type) query.type = filters.type;
            if (filters.q) query.q = filters.q;

            router.replace({ query }).catch(() => {});

            if (selectedTeamId.value) {
                try {
                    localStorage.setItem(storageKey.value, JSON.stringify({ ...filters }));
                } catch {
                    // localStorage unavailable (private mode); UI state just won't persist
                }
            }
        }, 200);
    }, { deep: true });

    watch(selectedTeamId, () => {
        applyToFilters();
    });

    function reset() {
        filters.map = null;
        filters.side = null;
        filters.type = null;
        filters.q = '';
    }

    return { filters, reset };
}
