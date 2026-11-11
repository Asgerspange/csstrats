<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { useTeamTactics } from '@/composables/useTeamTactics';
import useTeams from '@/composables/useTeams';
import useUser from '@/composables/useUser';
import useTeamMaps from '@/composables/useTeamMaps';
import { useTacticFilters } from './composables/useTacticFilters';
import { useTeamNudges } from './composables/useTeamNudges';
import FilterBar from './components/FilterBar.vue';
import TacticOverview from './components/TacticOverview.vue';
import WidgetTray from './components/WidgetTray.vue';
import SkeletonBlock from './components/SkeletonBlock.vue';
import ErrorState from './components/ErrorState.vue';
import MapCard from './components/MapCard.vue';
import MapSelectionGrid from './components/MapSelectionGrid.vue';
import { useUpgradeModal } from '@/composables/useUpgradeModal';
import type { TacticFilters } from './types';

const { Teams } = useTeams();
const { User } = useUser();
const { TeamMaps } = useTeamMaps();
const store = useStore();
const { user: storeUser } = storeToRefs(store);
const router = useRouter();
const { open: openUpgrade } = useUpgradeModal();

const { data: user } = User.get();
const { data: team } = Teams.find();
const { data: mapsData, isLoading: mapsLoading, isError: mapsError, refetch: refetchMaps } = TeamMaps.get();
const { data: tactics, isLoading: tacticsLoading, isError: tacticsError, refetch: refetchTactics } = useTeamTactics();

const { filters } = useTacticFilters();

const teamTypes = computed(() => team.value?.settings?.types ?? []);
const tacticsCount = computed(() => tactics.value?.length ?? 0);
const stratbooksList = computed(() => team.value?.stratbooks ?? []);

const { activeNudge, dismiss } = useTeamNudges({
    team,
    user,
    stratbooks: stratbooksList,
    tacticsCount,
});

const maps = computed(() => mapsData.value?.maps ?? []);
const lockExpiresAt = computed(() => mapsData.value?.lock_expires_at ?? null);
const maxMaps = computed(() => mapsData.value?.max_maps ?? null);
const isAllMaps = computed(() => maxMaps.value === null);

const lockActive = computed(() => {
    if (!lockExpiresAt.value) return false;
    return new Date(lockExpiresAt.value) > new Date();
});

const lockLabel = computed(() => {
    if (!lockExpiresAt.value) return '';
    try {
        return new Date(lockExpiresAt.value).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    } catch { return ''; }
});

const selectedMapIds = computed(() =>
    maps.value.filter(m => m.selected).map(m => m.id),
);

// Selection modal
const selectVisible = ref(false);

function openMap(stratbookId: number) {
    // Find the stratbook for this map (map_based: stratbook.map_id = map.id)
    // The stratbook ID is what we need for the /stratbook/:id route
    // For map_based teams, maps are 1:1 with stratbooks via map_id
    const stratbook = stratbooksList.value.find((s: any) => s.map_id === stratbookId);
    if (stratbook) {
        router.push({ name: 'Stratbook', params: { id: stratbook.id } });
    }
}

function onUpgrade() {
    openUpgrade({ feature: 'stratbooks', requiredTier: 'Team' });
}

async function onConfirmSelection(mapIds: number[]) {
    await TeamMaps.selectMaps(mapIds);
    selectVisible.value = false;
}

function onNudgeAction() {
    if (!activeNudge.value) return;
    if (activeNudge.value.id === 'invite-teammates') router.push('/team/members');
    else if (activeNudge.value.id === 'rename-team') {
        document.getElementById('team-name-display')?.click();
    }
}

function updateFilters(value: TacticFilters) {
    Object.assign(filters, value);
}
</script>

<template>
    <div class="maps-view">
        <!-- Maps grid section -->
        <div class="maps-view__head">
            <div class="maps-view__title-group">
                <h1 class="maps-view__heading">Your maps</h1>
                <span v-if="isAllMaps" class="maps-view__subtitle">All maps included in your plan</span>
                <span v-else-if="lockActive" class="maps-view__subtitle">
                    Selection set until {{ lockLabel }}
                </span>
                <span v-else class="maps-view__subtitle">
                    {{ selectedMapIds.length }} of {{ maxMaps }} maps in your plan
                </span>
            </div>
            <button
                v-if="!isAllMaps"
                class="maps-view__change"
                :disabled="lockActive"
                :title="lockActive ? `Your maps lock for 30 days. You can change them on ${lockLabel}.` : ''"
                @click="selectVisible = true"
            >
                Change maps
            </button>
        </div>

        <!-- Loading -->
        <div v-if="mapsLoading" class="maps-view__grid">
            <SkeletonBlock v-for="i in 8" :key="i" width="100%" height="10rem" rounded="0.75rem" />
        </div>

        <!-- Error -->
        <ErrorState
            v-else-if="mapsError"
            message="Could not load maps."
            hint="Check your connection and try again."
            @retry="refetchMaps"
        />

        <!-- Map grid -->
        <div v-else class="maps-view__grid">
            <MapCard
                v-for="map in maps"
                :key="map.id"
                :map="map"
                @open="openMap"
                @upgrade="onUpgrade"
            />
        </div>

        <!-- Selection modal -->
        <div v-if="selectVisible" class="maps-view__modal" @click.self="selectVisible = false">
            <div class="maps-view__modal-inner">
                <MapSelectionGrid
                    :maps="maps"
                    :max-maps="maxMaps ?? 4"
                    :selected-ids="selectedMapIds"
                    @confirm="onConfirmSelection"
                    @cancel="selectVisible = false"
                />
            </div>
        </div>

        <!-- Tactic overview -->
        <div class="maps-view__divider" />

        <template v-if="tacticsLoading">
            <SkeletonBlock width="100%" height="2.5rem" rounded="0.5rem" />
            <div class="maps-view__skel-group">
                <SkeletonBlock v-for="i in 4" :key="i" width="100%" height="2.75rem" rounded="0" />
            </div>
        </template>

        <ErrorState
            v-else-if="tacticsError"
            message="Could not load the team's tactics."
            hint="Check your connection and try again."
            @retry="refetchTactics"
        />

        <template v-else>
            <FilterBar
                v-if="tactics && tactics.length > 0"
                :filters="filters"
                :team-types="teamTypes"
                @update:filters="updateFilters"
            />
            <TacticOverview
                :tactics="tactics ?? []"
                :filters="filters"
                :has-stratbooks="true"
                @create-stratbook="router.push('/tactics')"
            />
            <WidgetTray />
        </template>
    </div>
</template>

<style scoped>
.maps-view {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    height: 100%;
    overflow-y: auto;
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

@media (min-width: 768px) {
    .maps-view {
        padding: 2rem 2.5rem;
    }
}

.maps-view__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.maps-view__title-group {
    display: flex;
    align-items: baseline;
    gap: 0.625rem;
}
.maps-view__heading {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.02em;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}
.maps-view__subtitle {
    font-size: 0.8125rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}
.maps-view__change {
    padding: 0.4rem 0.85rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms, border-color 150ms;
}
.maps-view__change:hover:not(:disabled) {
    border-color: color-mix(in oklch, var(--main-color, #ff8c00) 45%, var(--app-border, oklch(1 0 0 / 0.09)));
}
.maps-view__change:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.maps-view__grid {
    display: grid;
    gap: 0.875rem;
    grid-template-columns: 1fr;
}
@media (min-width: 560px) { .maps-view__grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 768px) { .maps-view__grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1600px) { .maps-view__grid { grid-template-columns: repeat(4, 1fr); } }

.maps-view__divider {
    height: 1px;
    background: var(--app-border, oklch(1 0 0 / 0.09));
    margin: 1.5rem 0 0.5rem;
}

.maps-view__skel-group {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.maps-view__modal {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background: oklch(0 0 0 / 0.6);
}
.maps-view__modal-inner {
    max-width: 48rem;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 1.5rem;
    border-radius: 1rem;
    background: var(--app-surface, oklch(0.20 0.014 250));
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
}
</style>
