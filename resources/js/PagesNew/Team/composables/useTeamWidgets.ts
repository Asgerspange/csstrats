import { computed, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import type { WidgetId, WidgetPrefs } from '../types';

const STORAGE_PREFIX = 'team-widgets:';

/**
 * Widget selection persists per user, per team in localStorage. UI state,
 * not server state. Default: zero widgets.
 */
export function useTeamWidgets() {
    const store = useStore();
    const { user, selectedTeamId } = storeToRefs(store);

    const storageKey = computed(() => {
        const uid = user.value?.id ?? 'anon';
        const tid = selectedTeamId.value ?? 'none';
        return `${STORAGE_PREFIX}${uid}:${tid}`;
    });

    const widgets = ref<WidgetPrefs>([]);

    function load() {
        try {
            const raw = localStorage.getItem(storageKey.value);
            widgets.value = raw ? JSON.parse(raw) : [];
        } catch {
            widgets.value = [];
        }
    }

    load();

    watch(storageKey, () => load());

    function persist() {
        try {
            localStorage.setItem(storageKey.value, JSON.stringify(widgets.value));
        } catch {
            // localStorage unavailable; prefs just won't persist
        }
    }

    function add(id: WidgetId) {
        if (!widgets.value.includes(id)) {
            widgets.value = [...widgets.value, id];
            persist();
        }
    }

    function remove(id: WidgetId) {
        widgets.value = widgets.value.filter((w) => w !== id);
        persist();
    }

    function has(id: WidgetId): boolean {
        return widgets.value.includes(id);
    }

    return { widgets, add, remove, has };
}
