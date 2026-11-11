<template>
    <div class="p-4 md:p-10 xl:p-12 h-full w-full max-w-[2560px] mx-auto overflow-hidden flex flex-col">

        <!-- ── First-session captain checklist ─────────────────────────────── -->
        <CaptainChecklist />

        <!-- ── Body: content + docked filters ───────────────────────────────── -->
        <div class="flex flex-1 min-h-0 gap-4 md:gap-6">

            <!-- Main column -->
            <div class="flex-1 min-w-0 min-h-0 flex flex-col">

                <!-- Toolbar: count + scope · add-target · view toggle -->
                <div class="flex flex-wrap items-center gap-3 mb-3 px-2 shrink-0">
                    <span class="shrink-0 text-xs text-zinc-500 tabular-nums hidden sm:inline">
                        <template v-if="totalCount">{{ allTactics.length }} of {{ totalCount }}</template>
                        <template v-else>{{ allTactics.length }}</template>
                        tactic{{ totalCount !== 1 ? 's' : '' }}
                    </span>

                    <!-- Scope: full library vs tactics the team has saved -->
                    <div class="shrink-0 flex items-center rounded-lg border border-white/10 overflow-hidden text-[11px] font-medium">
                        <button
                            @click="filters.team_used = null"
                            class="px-2.5 py-1 transition-colors"
                            :class="!filters.team_used ? 'bg-primary/20 text-primary' : 'text-zinc-500 hover:text-white hover:bg-white/5'"
                        >All</button>
                        <button
                            @click="filters.team_used = selectedTeamId ?? null"
                            :disabled="!selectedTeamId"
                            class="px-2.5 py-1 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="filters.team_used ? 'bg-primary/20 text-primary' : 'text-zinc-500 hover:text-white hover:bg-white/5'"
                            title="Tactics your team has saved to a stratbook"
                        >My team</button>
                    </div>

                    <!-- Target stratbook for one-click Add (flexes into the middle) -->
                    <div v-if="!isMapBased" class="ml-auto flex min-w-0 items-center gap-2">
                        <span class="shrink-0 text-[11px] text-zinc-500 hidden md:inline">Add to</span>
                        <StratbookSwitcher
                            v-model="targetStratbookId"
                            :options="stratbooks ?? []"
                            placeholder="Pick a stratbook…"
                            :loading="stratbooksLoading"
                            class="w-44 max-w-[45vw]"
                        />
                    </div>

                    <!-- Mobile filters trigger — opens the bottom sheet -->
                    <button
                        class="lg:hidden shrink-0 flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-white/10 text-[11px] font-medium text-zinc-300 hover:text-white hover:bg-white/5 transition-colors"
                        @click="filtersCollapsed = false"
                    >
                        <span class="material-symbols" style="font-size:15px">tune</span>
                        <span>Filters</span>
                        <span
                            v-if="activeFilterChips.length"
                            class="inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-primary/20 text-primary text-[9px] font-bold tabular-nums"
                        >{{ activeFilterChips.length }}</span>
                    </button>

                    <!-- View toggle: pinned far right, never splits -->
                    <div class="shrink-0 flex items-center border border-white/10 rounded-lg overflow-hidden">
                        <button
                            @click="viewMode = 'grid'"
                            class="px-2.5 py-1.5 transition-colors"
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
                            class="px-2.5 py-1.5 transition-colors"
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

                <!-- Active filter chips -->
                <div v-if="activeFilterChips.length" class="flex flex-wrap items-center gap-1.5 mb-3 px-2 shrink-0">
                    <button
                        v-for="chip in activeFilterChips"
                        :key="chip.key"
                        class="group inline-flex items-center gap-1 rounded-md border border-white/10 bg-white/[0.04] pl-2 pr-1 py-1 text-[11px] text-zinc-300 hover:border-white/20 transition-colors"
                        @click="chip.clear"
                    >
                        <span class="font-mono uppercase tracking-wide text-[10px] text-zinc-500">{{ chip.group }}</span>
                        <span>{{ chip.label }}</span>
                        <span class="material-symbols text-zinc-500 group-hover:text-white" style="font-size:13px">close</span>
                    </button>
                    <button
                        class="text-[11px] text-zinc-500 hover:text-white underline underline-offset-2 ml-1"
                        @click="clearAllFilters"
                    >Clear all</button>
                </div>

                <!-- Loading skeleton (initial / on filter change) -->
                <div
                    v-if="isLoading"
                    class="tactics-grid flex-grow w-full overflow-hidden px-2 mb-4"
                    style="grid-auto-rows: 240px;"
                >
                    <div
                        v-for="i in 12"
                        :key="i"
                        class="rounded-lg border border-white/10 bg-white/[0.02] p-3 animate-pulse"
                    >
                        <div class="h-3 w-2/3 rounded bg-white/10"></div>
                        <div class="mt-2 h-2.5 w-1/3 rounded bg-white/5"></div>
                        <div class="mt-4 h-3 w-5/6 rounded bg-white/10"></div>
                        <div class="mt-2 h-2.5 w-1/2 rounded bg-white/5"></div>
                    </div>
                </div>

                <!-- Zero results -->
                <div
                    v-else-if="!allTactics.length"
                    class="flex flex-grow flex-col items-center justify-center gap-3 text-center px-6"
                >
                    <span class="material-symbols text-3xl text-zinc-600">search_off</span>
                    <template v-if="hasActiveFilters">
                        <p class="text-sm text-zinc-400">No tactics match these filters.</p>
                        <button
                            class="text-xs font-semibold text-primary hover:brightness-110"
                            @click="clearAllFilters"
                        >Clear filters</button>
                    </template>
                    <template v-else>
                        <p class="text-sm text-zinc-400">No tactics in the library yet.</p>
                        <p class="text-xs text-zinc-600">New Tier 1/2 pro tactics are added weekly.</p>
                    </template>
                </div>

                <!-- Grid View -->
                <div
                    v-else-if="viewMode === 'grid'"
                    class="tactics-grid flex-grow w-full
                        overflow-x-hidden overflow-y-auto
                        px-2 mb-4 custom-track"
                    style="grid-auto-rows: 240px;"
                    @scroll.passive="onScroll"
                >
                    <TacticCard
                        v-for="tactic in allTactics"
                        :key="tactic.id"
                        :tactic="tactic"
                        :can-add="canAddTactic(tactic)"
                        :added="isTacticAdded(tactic)"
                        :add-target-name="addTargetName(tactic)"
                        @click="show(tactic.id)"
                        @add="handleAdd"
                    />

                    <div v-if="isFetchingNextPage" class="col-span-full text-center py-10 opacity-60">
                        Loading more...
                    </div>
                    <div v-if="!hasNextPage" class="col-span-full text-center py-10 text-zinc-600 text-sm">
                        End of library
                    </div>
                </div>

                <!-- List View -->
                <TacticsListView
                    v-else
                    :tactics="allTactics"
                    :loading="!!isFetchingNextPage"
                    :hasMore="!!hasNextPage"
                    :can-add="isMapBased || !!targetStratbookId"
                    :added-ids="isMapBased ? addedTacticIds : addedIds"
                    :add-target-name="isMapBased ? '' : targetStratbookName"
                    class="flex-grow mb-4"
                    @select="show"
                    @scroll="onScroll"
                    @add="handleAdd"
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
                <div class="flex flex-col gap-4 p-4">
                    <div class="flex flex-col gap-1.5">
                        <span class="tac-filter-label">Map</span>
                        <MapDropdown v-model="filters.map" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span class="tac-filter-label">Teams</span>
                        <TeamDropdown v-model="filters.teams" :is-multi-select="true" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span class="tac-filter-label">Side</span>
                        <SideDropdown v-model="filters.side" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span class="tac-filter-label">Type</span>
                        <TypeDropdown v-model="filters.type" class="w-full" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span class="tac-filter-label">Tags</span>
                        <TagsDropdown v-model="filters.tags" class="w-full" />
                    </div>
                </div>
            </ContextPanel>
        </div>
    </div>

    <TacticDialog
        v-if="isDialogVisible"
        :tacticId="selectedTacticId"
        context="library"
        v-model:visible="isDialogVisible"
    />
</template>

<script setup lang="ts">
import { reactive, computed, watch, ref } from 'vue'
import MapDropdown from '@/componentsnew/MapDropdown.vue'
import TeamDropdown from '@/componentsnew/TeamDropdown.vue'
import SideDropdown from '@/componentsnew/SideDropdown.vue'
import TypeDropdown from '@/componentsnew/TypeDropdown.vue'
import TagsDropdown from '@/componentsnew/TagsDropdown.vue'
import TacticCard from '@/componentsnew/tactics/TacticCard.vue'
import TacticDialog from '@/componentsnew/tactics/TacticDialog.vue'
import TacticsListView from '@/componentsnew/tactics/TacticsListView.vue'
import CaptainChecklist from '@/componentsnew/CaptainChecklist.vue'
import ContextPanel from '@/componentsnew/ContextPanel.vue'
import StratbookSwitcher from '@/componentsnew/stratbooks/StratbookSwitcher.vue'

import { useTactics } from '@/composables/useTactics'
import { useApi, api } from '@/composables/useApi'
import useStratbooks from '@/composables/useStratbooks'
import useStratbookTactics from '@/composables/useStratbookTactics'
import { useTeamTactics } from '@/composables/useTeamTactics'
import { useToast } from '@/composables/useToast'
import { useQueryClient } from '@tanstack/vue-query'
import { useStore } from '@/store'
import { storeToRefs } from 'pinia'
import { tacticLabel } from '@/lib/tacticLabel'

const toast = useToast()
const store = useStore()
const { selectedTeamId } = storeToRefs(store)

// ── View mode (shares preference with Stratbook page) ──────────────────────────
const STORAGE_KEY = 'tactics-view-mode';
const viewMode = ref<'grid' | 'list'>(
    (localStorage.getItem(STORAGE_KEY) as 'grid' | 'list') || 'grid'
);
watch(viewMode, (v) => localStorage.setItem(STORAGE_KEY, v));

// ── Filters panel collapse (persisted; defaults to collapsed on mobile) ───────
const FILTERS_KEY = 'tactics-filters-collapsed';
const storedFilters = localStorage.getItem(FILTERS_KEY);
const filtersCollapsed = ref<boolean>(storedFilters !== null ? storedFilters === 'true' : window.innerWidth < 1024);
watch(filtersCollapsed, (v) => localStorage.setItem(FILTERS_KEY, String(v)));

// ── Filters ────────────────────────────────────────────────────────────────────
const filters = reactive({
    map: null as number | null,
    teams: [] as string[],
    side: null as 'CT' | 'T' | null,
    type: null as string | null,
    tags: [] as string[],
    team_used: null as number | null,
})

const isDialogVisible = ref(false)
const selectedTacticId = ref<number | null>(null)

// ── Infinite query ─────────────────────────────────────────────────────────────
const { data, fetchNextPage, isFetchingNextPage, hasNextPage, isLoading, refetch } = useTactics(filters)

const allTactics = computed(() => data.value?.pages.flatMap(p => p.tactics) ?? [])
const totalCount = computed(() => data.value?.pages?.[0]?.count ?? 0)

// ── Maps (cached; shared with MapDropdown) for resolving the map filter chip ────
const { useGet } = useApi()
const { data: mapsData } = useGet<any[]>(['maps'], '/api/maps')
const mapName = (id: number) => mapsData.value?.find(m => m.id === id)?.display_name ?? `Map ${id}`

// ── Active filter chips ─────────────────────────────────────────────────────────
const hasActiveFilters = computed(() =>
    !!filters.map || filters.teams.length > 0 || !!filters.side || !!filters.type || filters.tags.length > 0 || !!filters.team_used
)

const activeFilterChips = computed(() => {
    const chips: { key: string; group: string; label: string; clear: () => void }[] = []
    if (filters.map) chips.push({ key: 'map', group: 'Map', label: mapName(filters.map), clear: () => (filters.map = null) })
    filters.teams.forEach(t => chips.push({ key: `team:${t}`, group: 'Team', label: t, clear: () => (filters.teams = filters.teams.filter(x => x !== t)) }))
    if (filters.side) chips.push({ key: 'side', group: 'Side', label: filters.side, clear: () => (filters.side = null) })
    if (filters.type) chips.push({ key: 'type', group: 'Type', label: filters.type, clear: () => (filters.type = null) })
    filters.tags.forEach(t => chips.push({ key: `tag:${t}`, group: 'Tag', label: t, clear: () => (filters.tags = filters.tags.filter(x => x !== t)) }))
    return chips
})

function clearAllFilters() {
    filters.map = null
    filters.teams = []
    filters.side = null
    filters.type = null
    filters.tags = []
    filters.team_used = null
}

// ── Target stratbook (remembered) for one-click Add ─────────────────────────────
const TARGET_KEY = 'tactics-target-stratbook'
const { Stratbooks } = useStratbooks()
const { data: stratbooks, isLoading: stratbooksLoading } = Stratbooks.get()

const stored = localStorage.getItem(TARGET_KEY)
const targetStratbookId = ref<number | null>(stored ? Number(stored) : null)
watch(targetStratbookId, v => {
    if (v) localStorage.setItem(TARGET_KEY, String(v))
    else localStorage.removeItem(TARGET_KEY)
})

// If the remembered stratbook no longer exists, drop it once the list loads.
watch(stratbooks, list => {
    if (targetStratbookId.value && list && !list.some((s: any) => s.id === targetStratbookId.value)) {
        targetStratbookId.value = null
    }
})

const targetStratbookName = computed(() =>
    stratbooks.value?.find((s: any) => s.id === targetStratbookId.value)?.stratbook_name ?? 'stratbook'
)

const { StratbookTactics } = useStratbookTactics(targetStratbookId as any)
const queryClient = useQueryClient()

// Which library tactics are already in the target stratbook (so we can flag them).
const { data: targetTacticIds } = useGet<number[]>(
    ['stratbook-tactic-ids', targetStratbookId],
    () => `/api/stratbooks/${targetStratbookId.value}/tactic-ids`,
    undefined,
    () => !!targetStratbookId.value
)
const addedIds = computed(() => new Set(targetTacticIds.value ?? []))

// ── Map-based auto-routing ────────────────────────────────────────────────────
// For map_based teams: no StratbookSwitcher. Tactics auto-route to the
// stratbook matching the tactic's map. The "already added" check uses
// useTeamTactics (which includes tactic_id for library tactics).
const { data: teamTactics } = useTeamTactics()

const isMapBased = computed(() => store.selectedTeam?.entitlement_mode === 'map_based')

// Build map_id → stratbook_id lookup from the team's stratbooks
const mapIdToStratbookId = computed<Record<number, number>>(() => {
    const lookup: Record<number, number> = {}
    for (const sb of (stratbooks.value as any[]) ?? []) {
        if (sb.map_id) lookup[sb.map_id] = sb.id
    }
    return lookup
})

// Build a set of library tactic IDs already added across all stratbooks
const addedTacticIds = computed(() => {
    const set = new Set<number>()
    for (const t of teamTactics.value ?? []) {
        if (t.tactic_id) set.add(t.tactic_id)
    }
    return set
})

// Resolve a map display_name to a map_id using the cached maps data
const displayNameToId = computed<Record<string, number>>(() => {
    const lookup: Record<string, number> = {}
    for (const m of mapsData.value ?? []) {
        lookup[m.display_name] = m.id
        lookup[m.name] = m.id
    }
    return lookup
})

function canAddTactic(tactic: any): boolean {
    if (isMapBased.value) {
        const mapId = displayNameToId.value[tactic.map]
        if (!mapId) return false
        return mapId in mapIdToStratbookId.value
    }
    return !!targetStratbookId.value
}

function isTacticAdded(tactic: any): boolean {
    if (isMapBased.value) {
        return addedTacticIds.value.has(tactic.id)
    }
    return addedIds.value.has(tactic.id)
}

function addTargetName(tactic: any): string {
    if (isMapBased.value) {
        return tactic.map ?? 'map'
    }
    return targetStratbookName.value
}

function handleAdd(tactic: any) {
    if (isMapBased.value) {
        const mapId = displayNameToId.value[tactic.map]
        if (!mapId) return
        const stratbookId = mapIdToStratbookId.value[mapId]
        if (!stratbookId) return
        if (addedTacticIds.value.has(tactic.id)) return
        api.post(`/api/stratbooks/${stratbookId}/tactics`, { tacticId: tactic.id })
            .then(() => {
                toast.success('Added', `"${tacticLabel(tactic)}" added to ${tactic.map}.`)
                queryClient.invalidateQueries({ queryKey: ['team-tactics', selectedTeamId.value] })
                queryClient.invalidateQueries({ queryKey: ['stratbookTactics', selectedTeamId.value] })
            })
            .catch(() => toast.error('Add failed', 'Could not add the tactic. Please try again.'))
        return
    }
    if (!targetStratbookId.value) return
    if (addedIds.value.has(tactic.id)) return
    StratbookTactics.create({ tacticId: tactic.id })
        .then(() => {
            toast.success('Added', `"${tacticLabel(tactic)}" added to ${targetStratbookName.value}.`)
            queryClient.invalidateQueries({ queryKey: ['stratbook-tactic-ids', targetStratbookId.value] })
        })
        .catch(() => toast.error('Add failed', 'Could not add the tactic. Please try again.'))
}

// ── React to filter changes ────────────────────────────────────────────────────
watch(
    filters,
    () => {
        refetch({ refetchPage: (_, index) => index === 0 })
    },
    { deep: true }
)

// ── Scroll handler ─────────────────────────────────────────────────────────────
function onScroll(e: Event) {
    const el = e.target as HTMLElement
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
        if (hasNextPage.value && !isFetchingNextPage.value) {
            fetchNextPage()
        }
    }
}

function show(tacticId: number) {
    selectedTacticId.value = tacticId
    isDialogVisible.value = true
}
</script>

<style scoped>
.tactics-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: 1fr;
}
@media (min-width: 560px) {
    .tactics-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (min-width: 768px) {
    .tactics-grid { grid-template-columns: repeat(3, 1fr) !important; }
}
@media (min-width: 1600px) {
    .tactics-grid { grid-template-columns: repeat(4, 1fr) !important; }
}

.tac-filter-label {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--app-text-3, oklch(1 0 0 / 0.5));
}
.custom-track::-webkit-scrollbar { width: 6px; }
.custom-track::-webkit-scrollbar-thumb { background-color: oklch(var(--app-sidebar-ink, 1 0 0) / 0.12); border-radius: 10px; }
.custom-track::-webkit-scrollbar-track { background-color: transparent; }
.custom-track:hover::-webkit-scrollbar-thumb { background-color: oklch(var(--app-sidebar-ink, 1 0 0) / 0.20); }
.custom-track { scrollbar-width: thin; scrollbar-color: oklch(var(--app-sidebar-ink, 1 0 0) / 0.12) transparent; }
</style>
