<template>
    <div class="p-4 md:p-10 xl:p-12 h-full w-full max-w-[2560px] mx-auto overflow-hidden flex flex-col">

        <!-- ── Actions + search + view toggle ───────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3 mb-6 shrink-0 px-2">
            <Button @click="openCreateFresh" class="whitespace-nowrap">Create Tactic</Button>
            <Button @click="browseLibrary" severity="secondary" class="whitespace-nowrap flex items-center gap-1.5">
                <span class="material-symbols" style="font-size:16px">library_books</span>
                Add from library
            </Button>
            <!-- <ExportStratbook :stratbookId="stratbookId" :allTactics="allTactics" /> -->

            <!-- Search input -->
            <div class="relative flex-1 max-w-md min-w-[180px]">
                <svg
                    class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500 pointer-events-none"
                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                >
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search strats…"
                    class="w-full bg-white/[0.04] border border-white/10 rounded-lg py-2 pl-10 pr-4 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-primary/50 transition-colors"
                />
                <button
                    v-if="searchQuery"
                    @click="searchQuery = ''"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-white transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Tactic count -->
            <span class="text-xs text-zinc-500 tabular-nums hidden sm:block ml-auto">
                {{ filteredTactics.length }} strat{{ filteredTactics.length !== 1 ? 's' : '' }}
            </span>

            <!-- Mobile filters trigger — opens the bottom sheet -->
            <button
                class="lg:hidden shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-white/10 text-[11px] font-medium text-zinc-300 hover:text-white hover:bg-white/5 transition-colors"
                @click="filtersCollapsed = false"
            >
                <span class="material-symbols" style="font-size:15px">tune</span>
                <span>Filters</span>
                <span
                    v-if="activeFilterCount"
                    class="inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-primary/20 text-primary text-[9px] font-bold tabular-nums"
                >{{ activeFilterCount }}</span>
            </button>

            <!-- View toggle -->
            <div class="flex items-center border border-white/10 rounded-lg overflow-hidden">
                <button
                    @click="viewMode = 'grid'"
                    class="px-2.5 py-1.5 transition-all"
                    :class="viewMode === 'grid' ? 'bg-primary/20 text-primary' : 'text-zinc-500 hover:text-white hover:bg-white/5'"
                    title="Grid view"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                    </svg>
                </button>
                <button
                    @click="viewMode = 'list'"
                    class="px-2.5 py-1.5 transition-all"
                    :class="viewMode === 'list' ? 'bg-primary/20 text-primary' : 'text-zinc-500 hover:text-white hover:bg-white/5'"
                    title="List view"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="4" width="18" height="2.5" rx="1"/>
                        <rect x="3" y="10.75" width="18" height="2.5" rx="1"/>
                        <rect x="3" y="17.5" width="18" height="2.5" rx="1"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- ── Body: content + docked filters ───────────────────────────────── -->
        <div class="flex flex-1 min-h-0 gap-4 md:gap-6">

            <!-- Main column -->
            <div class="flex-1 min-w-0 min-h-0 flex flex-col">
                <!-- Grid View -->
                <div
                    v-if="viewMode === 'grid'"
                    class="grid gap-4 xl:gap-5 flex-grow w-full overflow-auto px-2 mb-4 custom-track
                           grid-cols-[repeat(auto-fill,minmax(min(360px,100%),1fr))]
                           min-[2100px]:grid-cols-5"
                    style="grid-auto-rows: 240px;"
                    @scroll.passive="onScroll"
                >
                    <TacticCard
                        v-for="tactic in filteredTactics"
                        :key="tactic.id"
                        :tactic="tactic"
                        @click="show(tactic.id)"
                        @open-round-plan="openRoundPlanFromCard"
                    />

                    <div v-if="isFetchingNextPage" class="col-span-full text-center py-8 opacity-60">Loading more...</div>
                    <div v-if="!hasNextPage && !isLoading" class="col-span-full text-center py-8 opacity-60">No more tactics</div>
                </div>

                <!-- List View -->
                <StratbookListView
                    v-else
                    :tactics="filteredTactics"
                    :loading="!!isFetchingNextPage"
                    :hasMore="!!hasNextPage"
                    class="flex-grow mb-4"
                    @select="show"
                    @open-round-plan="openRoundPlanFromCard"
                    @reorder="handleReorder"
                    @scroll="onScroll"
                />
            </div>

            <!-- Docked filters -->
            <ContextPanel
                v-model:collapsed="filtersCollapsed"
                :overlay="false"
                :mobile-sheet="true"
                side="right"
                title="Filters"
                icon="tune"
                width="15rem"
            >
                <div class="flex flex-col gap-3 p-4">
                    <MapDropdown  v-model="filters.map"  class="w-full" />
                    <SideDropdown v-model="filters.side" class="w-full" />
                    <TypeDropdown v-model="filters.type" class="w-full" :teamTypes="team?.settings?.['types']" />
                    <TagsDropdown v-model="filters.tags" filter class="w-full" :teamTags="team?.settings?.['tags']" />
                </div>
            </ContextPanel>
        </div>
    </div>

    <!-- Tactic detail dialog -->
    <TacticDialog
        v-if="isDialogVisible"
        :tacticId="selectedTacticId"
        context="stratbook"
        v-model:visible="isDialogVisible"
        @edit="(tactic) => { tempTactic = tactic; isCreateVisible = true; }"
    />

    <!-- Tactic create / edit dialog -->
    <TacticCreateDialog
        v-if="isCreateVisible"
        v-model:visible="isCreateVisible"
        @update:visible="createDialogClose"
        :stratbookId="stratbookId"
        :tactic="tempTactic"
    />

    <!-- Round plan viewer — opened from card badge -->
    <RoundPlanViewer
        v-if="viewingRoundPlanId !== null"
        :plan-id="viewingRoundPlanId"
        @close="viewingRoundPlanId = null"
    />
</template>

<script setup lang="ts">
import { reactive, computed, watch, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import MapDropdown  from '@/componentsnew/MapDropdown.vue';
import SideDropdown from '@/componentsnew/SideDropdown.vue';
import TypeDropdown from '@/componentsnew/TypeDropdown.vue';
import TagsDropdown from '@/componentsnew/TagsDropdown.vue';
import TacticCard   from '@/componentsnew/tactics/TacticCard.vue';
import Button       from '@/components/volt/Button.vue';

import TacticDialog       from '@/componentsnew/tactics/TacticDialog.vue';
import TacticCreateDialog from '@/componentsnew/stratbooks/TacticCreateDialog.vue';
import ExportStratbook    from '@/componentsnew/stratbooks/ExportStratbook.vue';
import RoundPlanViewer    from '@/PagesNew/RoundPlanner/RoundPlanViewer.vue';
import StratbookListView  from '@/componentsnew/stratbooks/StratbookListView.vue';
import ContextPanel        from '@/componentsnew/ContextPanel.vue';

import { useStratbookTacticsScroll } from '@/composables/useStratbookTactics';
import useStratbookTactics from '@/composables/useStratbookTactics';
import { useStore } from '@/store';

const store = useStore();
const team  = computed(() => store.selectedTeam);

const route       = useRoute();
const router      = useRouter();
const stratbookId = computed(() => Number(route.params.id));

// Jump to the library. For legacy teams, pre-set the stratbook as the Add
// target so pro tactics flow straight back into the book. For map_based
// teams, auto-routing handles it (no target needed).
function browseLibrary() {
    const isMapBased = team.value?.entitlement_mode === 'map_based';
    if (! isMapBased) {
        localStorage.setItem('tactics-target-stratbook', String(stratbookId.value));
    }
    router.push('/tactics');
}

const isDialogVisible  = ref(false);
const isCreateVisible  = ref(false);
const selectedTacticId = ref<number | null>(null);
const tempTactic       = ref<any>(null);
const viewingRoundPlanId = ref<number | null>(null);

// ── View mode (persisted in localStorage) ──────────────────────────────────────
const STORAGE_KEY = 'stratbook-view-mode';
const viewMode = ref<'grid' | 'list'>(
    (localStorage.getItem(STORAGE_KEY) as 'grid' | 'list') || 'grid'
);
watch(viewMode, (v) => localStorage.setItem(STORAGE_KEY, v));

// ── Filters panel collapse (persisted) ─────────────────────────────────────────
const FILTERS_KEY = 'stratbook-filters-collapsed';
const storedFilters = localStorage.getItem(FILTERS_KEY);
const filtersCollapsed = ref<boolean>(storedFilters !== null ? storedFilters === 'true' : window.innerWidth < 1024);
watch(filtersCollapsed, (v) => localStorage.setItem(FILTERS_KEY, String(v)));

// ── Search ─────────────────────────────────────────────────────────────────────
const searchQuery = ref('');

// ── Reorder (uses the composable) ──────────────────────────────────────────────
const { StratbookTactics } = useStratbookTactics(stratbookId);

// ── Round plan viewer ──────────────────────────────────────────────────────────
function openRoundPlanFromCard(tactic: any) {
    const planId = tactic?.round_plan?.id ?? tactic?.id;
    if (!planId) return;
    viewingRoundPlanId.value = planId;
    // Close the tactic dialog if it was open so we don't stack modals
    isDialogVisible.value = false;
}

// ── Create / edit ──────────────────────────────────────────────────────────────
function openCreateFresh() {
    tempTactic.value    = null;
    isCreateVisible.value = true;
}

function createDialogClose() {
    tempTactic.value      = null;
    isCreateVisible.value = false;
}

// ── Filters + pagination ───────────────────────────────────────────────────────
const filters = reactive({
    map:  null as string | null,
    side: null as 'CT' | 'T' | null,
    type: null as string | null,
    tags: [] as string[],
});

const activeFilterCount = computed(() =>
    (filters.map ? 1 : 0) +
    (filters.side ? 1 : 0) +
    (filters.type ? 1 : 0) +
    filters.tags.length
);

const {
    data,
    fetchNextPage,
    isFetchingNextPage,
    hasNextPage,
    isLoading,
    refetch,
} = useStratbookTacticsScroll(stratbookId, filters);

const allTactics = computed(() => data.value?.pages.flatMap((p: any) => p.tactics) ?? []);

// ── Client-side search filter ──────────────────────────────────────────────────
const filteredTactics = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return allTactics.value;

    return allTactics.value.filter((t: any) => {
        return (
            t.name?.toLowerCase().includes(q) ||
            t.description?.toLowerCase().includes(q) ||
            t.type?.toLowerCase().includes(q) ||
            t.map?.toLowerCase().includes(q) ||
            (t.tags ?? []).some((tag: string) => tag.toLowerCase().includes(q))
        );
    });
});

watch(filters, () => {
    refetch({ refetchPage: (_: any, index: number) => index === 0 });
}, { deep: true });

function onScroll(e: Event) {
    const el = e.target as HTMLElement;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
        if (hasNextPage.value && !isFetchingNextPage.value) fetchNextPage();
    }
}

function show(tacticId: number) {
    selectedTacticId.value = tacticId;
    isDialogVisible.value  = true;
}

// ── Reorder handler (from list view drag-and-drop) ─────────────────────────────
function handleReorder(items: { id: number; sort_order: number }[]) {
    // Optimistic: update local data immediately so the list re-renders in the new order
    // The allTactics computed reads from the query cache pages, so we mutate the cache
    const pages = data.value?.pages;
    if (pages) {
        // Build a lookup for the new sort_order
        const orderMap = new Map(items.map(i => [i.id, i.sort_order]));

        // Flatten, apply new sort_order, re-sort, then put back into pages
        const flat = pages.flatMap((p: any) => p.tactics);
        for (const tactic of flat) {
            if (orderMap.has(tactic.id)) {
                tactic.sort_order = orderMap.get(tactic.id);
            }
        }
        flat.sort((a: any, b: any) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

        // Re-chunk back into pages (keep same page size)
        const perPage = 35;
        for (let i = 0; i < pages.length; i++) {
            pages[i].tactics = flat.splice(0, perPage);
        }
    }

    // Persist to backend
    StratbookTactics.reorder(items);
}
</script>

<style scoped>
.custom-track::-webkit-scrollbar { width: 6px; }
.custom-track::-webkit-scrollbar-thumb { background-color: var(--color-primary); border-radius: 10px; }
.custom-track::-webkit-scrollbar-track { background-color: transparent; }
.custom-track { scrollbar-width: thin; scrollbar-color: var(--color-primary) transparent; }
</style>