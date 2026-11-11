<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import Select from '@/components/volt/Select.vue';
import useMaps from '@/composables/useMaps';
import useTeams from '@/composables/useTeams';
import { useStore } from '@/store';
import type { TacticFilters, Side, TacticType } from '../types';
import { BASE_TYPES } from '../constants';

const props = defineProps<{
    filters: TacticFilters;
    teamTypes?: string[];
}>();

const emit = defineEmits<{
    (e: 'update:filters', value: TacticFilters): void;
}>();

const { Maps } = useMaps();
const { data: maps } = Maps.get();
const store = useStore();
const { selectedTeam } = storeToRefs(store);

const availableTypes = computed<TacticType[]>(() => {
    const team = props.teamTypes ?? [];
    return Array.from(new Set([...BASE_TYPES, ...team]));
});

const isMapBased = computed(() => selectedTeam.value?.entitlement_mode === 'map_based');

const availableMaps = computed(() => {
    const list = (maps.value as Array<{ id: number; display_name: string; active: boolean }> | undefined) ?? [];
    if (! isMapBased.value) {
        return list.map((m) => m.display_name);
    }
    // For map_based teams, only show maps that are in the team's stratbooks
    // (which are the accessible ones — the backend already filters)
    const stratbookMapIds = new Set(
        (selectedTeam.value?.stratbooks ?? [])
            .filter((s: any) => s.map_id !== null)
            .map((s: any) => s.map_id)
    );
    return list.filter((m) => stratbookMapIds.has(m.id)).map((m) => m.display_name);
});

const sideOptions = [
    { label: 'All sides', value: null },
    { label: 'T', value: 'T' as Side },
    { label: 'CT', value: 'CT' as Side },
];

const mapOptions = computed(() => [
    { label: 'All maps', value: null },
    ...availableMaps.value.map((m) => ({ label: m, value: m })),
]);

const typeOptions = computed(() => [
    { label: 'All types', value: null },
    ...availableTypes.value.map((t) => ({ label: t, value: t })),
]);

const searchInput = ref<HTMLInputElement | null>(null);

function onKeydown(e: KeyboardEvent) {
    if (e.key === '/' && document.activeElement !== searchInput.value) {
        e.preventDefault();
        searchInput.value?.focus();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

function setFilter<K extends keyof TacticFilters>(key: K, value: TacticFilters[K]) {
    emit('update:filters', { ...props.filters, [key]: value });
}

const hasActiveFilters = computed(() =>
    !!props.filters.map || !!props.filters.side || !!props.filters.type || !!props.filters.q,
);
</script>

<template>
    <div class="filter-bar">
        <div class="filter-bar__selects">
            <Select
                :model-value="filters.map"
                :options="mapOptions"
                option-label="label"
                option-value="value"
                placeholder="All maps"
                aria-label="Filter by map"
                class="filter-bar__select"
                @update:model-value="setFilter('map', $event)"
            />
            <Select
                :model-value="filters.side"
                :options="sideOptions"
                option-label="label"
                option-value="value"
                placeholder="All sides"
                aria-label="Filter by side"
                class="filter-bar__select"
                @update:model-value="setFilter('side', $event)"
            />
            <Select
                :model-value="filters.type"
                :options="typeOptions"
                option-label="label"
                option-value="value"
                placeholder="All types"
                aria-label="Filter by type"
                class="filter-bar__select"
                @update:model-value="setFilter('type', $event)"
            />
        </div>

        <div class="filter-bar__search-wrap">
            <span class="material-symbols filter-bar__search-icon" aria-hidden="true">search</span>
            <input
                ref="searchInput"
                :value="filters.q"
                type="search"
                placeholder="Search tactics"
                class="filter-bar__search"
                aria-label="Search tactics by name"
                @input="setFilter('q', ($event.target as HTMLInputElement).value)"
            />
        </div>

        <button
            v-if="hasActiveFilters"
            class="filter-bar__clear"
            @click="emit('update:filters', { map: null, side: null, type: null, q: '' })"
        >
            Clear
        </button>
    </div>
</template>

<style scoped>
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.625rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.filter-bar__selects {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-bar__select {
    width: 10rem;
    max-width: 100%;
}

.filter-bar__search-wrap {
    position: relative;
    flex: 1;
    min-width: 10rem;
    max-width: 20rem;
}

.filter-bar__search-icon {
    position: absolute;
    left: 0.625rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.125rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    pointer-events: none;
}

.filter-bar__search {
    width: 100%;
    padding: 0.5rem 0.75rem 0.5rem 2.25rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface, oklch(0.20 0.014 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    outline: none;
    transition: border-color 150ms;
}

.filter-bar__search:focus {
    border-color: var(--main-color, #ff8c00);
}

.filter-bar__search:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.filter-bar__clear {
    padding: 0.375rem 0.625rem;
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: color 150ms;
}

.filter-bar__clear:hover {
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

@media (max-width: 640px) {
    .filter-bar__select {
        width: 100%;
    }
    .filter-bar__search-wrap {
        max-width: 100%;
    }
}
</style>
