<template>
    <div class="opm">

        <!-- Map tab bar -->
        <div
            v-if="availableMaps.length > 1"
            class="opm-maptabs"
            role="tablist"
            aria-label="Map"
        >
            <button
                v-for="m in availableMaps"
                :key="m"
                role="tab"
                :aria-selected="activeMap === m"
                :tabindex="activeMap === m ? 0 : -1"
                class="opm-maptab"
                :class="{ 'opm-maptab--active': activeMap === m }"
                @click="activeMap = m"
                @keydown.right.prevent="cycleMap(1)"
                @keydown.left.prevent="cycleMap(-1)"
            >
                {{ mapDisplayName(m) }}
            </button>
        </div>

        <div class="opm__layout">

            <!-- Map canvas -->
            <div ref="containerRef" class="opm-canvas">
                <!-- Loading spinner -->
                <div
                    v-if="isLoading || zonesLoading || (activeMap && !isMapLoaded)"
                    class="opm-state"
                >
                    <div class="opm-spinner" />
                    <p class="opm-state__text">
                        {{ isLoading ? 'Loading position data...' : zonesLoading ? 'Loading zones...' : 'Loading map...' }}
                    </p>
                </div>
                <div v-if="!isLoading && !availableMaps.length" class="opm-state">
                    <p class="opm-state__text">No position data yet. Process a demo first.</p>
                </div>
                <!-- Zone load error — distinct from the "no overlay" empty state -->
                <div
                    v-if="isMapLoaded && !zonesLoading && zonesError"
                    class="opm-nozones"
                >
                    <div class="opm-nozones__box opm-nozones__box--error">
                        <p>Could not load zone overlays. Check your connection and refresh.</p>
                    </div>
                </div>

                <!-- No zones warning -->
                <div
                    v-else-if="isMapLoaded && !zonesLoading && activeMap && !currentZones.length"
                    class="opm-nozones"
                >
                    <div class="opm-nozones__box">
                        <p>No zone overlay available for <strong>{{ activeMap }}</strong></p>
                    </div>
                </div>

                <div class="opm-canvas__inner">
                    <!-- Map image -->
                    <img
                        :key="activeMap"
                        :src="'/images/demo/' + activeMap + '.svg'"
                        class="opm-mapimg"
                        :class="{ 'opm-mapimg--loaded': isMapLoaded }"
                        ref="mapImgRef"
                        :alt="`${mapDisplayName(activeMap)} radar`"
                        @load="onMapLoad"
                    />

                    <!-- SVG overlay -->
                    <svg
                        v-if="isMapLoaded && svgSize.w > 0"
                        class="opm-svg"
                        :width="svgSize.w"
                        :height="svgSize.h"
                        :style="{ top: svgOffset.y + 'px', left: svgOffset.x + 'px' }"
                    >
                        <!-- Background zones (all, dimmed) -->
                        <polygon
                            v-for="zone in currentZones"
                            :key="'bg-' + zone.id"
                            :points="worldPolyToSvg(zone.polygon)"
                            fill="rgba(255,255,255,0.02)"
                            stroke="rgba(255,255,255,0.05)"
                            stroke-width="0.5"
                        />

                        <!-- Active dominance zones -->
                        <g
                            v-for="entry in activeZoneEntries"
                            :key="'dom-' + entry.zone_id"
                            class="opm-svg__zone"
                            @mouseenter="hoveredZoneId = entry.zone_id"
                            @mouseleave="hoveredZoneId = null"
                        >
                            <polygon
                                :points="worldPolyToSvg(getZonePolygon(entry.zone_id))"
                                :fill="entry.color + hexAlpha(entry.pct)"
                                :stroke="entry.color"
                                :stroke-width="entry.zone_type === 'point' ? 2 : 1"
                                :opacity="hoveredZoneId && hoveredZoneId !== entry.zone_id ? 0.25 : 1"
                                class="opm-svg__poly"
                            />
                            <text
                                v-if="entry.pct >= 4 && getZoneCentroid(entry.zone_id).x > 0"
                                :x="getZoneCentroid(entry.zone_id).x"
                                :y="getZoneCentroid(entry.zone_id).y - 5"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                fill="oklch(1 0 0 / 0.96)"
                                :font-size="entry.zone_type === 'point' ? 8 : 10"
                                font-weight="800"
                                style="text-shadow: 0 1px 4px oklch(0.04 0.01 250 / 0.7); pointer-events: none;"
                            >{{ entry.zone_name }}</text>
                            <text
                                v-if="entry.pct >= 4 && getZoneCentroid(entry.zone_id).x > 0"
                                :x="getZoneCentroid(entry.zone_id).x"
                                :y="getZoneCentroid(entry.zone_id).y + 7"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                fill="oklch(1 0 0 / 0.65)"
                                font-size="8"
                                style="text-shadow: 0 1px 4px oklch(0.04 0.01 250 / 0.7); pointer-events: none;"
                            >{{ entry.pct }}%</text>
                        </g>
                    </svg>

                    <!-- Hover tooltip -->
                    <div v-if="hoveredEntry" class="opm-tooltip">
                        <p class="opm-tooltip__name">{{ hoveredEntry.zone_name }}</p>
                        <p class="opm-tooltip__sub">{{ hoveredEntry.pct }}% of {{ activeSide }}-side time</p>
                        <p class="opm-tooltip__type">{{ hoveredEntry.zone_type }}</p>
                    </div>
                </div>
            </div>

            <!-- Controls sidebar -->
            <aside class="opm-side">
                <div class="opm-side__scroll">

                    <!-- Side toggle -->
                    <section class="opm-section">
                        <label class="opm-label" id="opm-side-label">Side</label>
                        <div
                            class="opm-segmented"
                            role="tablist"
                            aria-labelledby="opm-side-label"
                        >
                            <button
                                v-for="s in (['T', 'CT'] as const)"
                                :key="s"
                                role="tab"
                                :aria-selected="activeSide === s"
                                :tabindex="activeSide === s ? 0 : -1"
                                class="opm-seg"
                                :class="{
                                    'opm-seg--active': activeSide === s,
                                    'opm-seg--active-t': activeSide === s && s === 'T',
                                    'opm-seg--active-ct': activeSide === s && s === 'CT',
                                }"
                                @click="activeSide = s"
                                @keydown.right.prevent="cycleSide()"
                                @keydown.left.prevent="cycleSide()"
                            >
                                {{ s }}-side
                            </button>
                        </div>
                    </section>

                    <!-- Player selector -->
                    <section class="opm-section">
                        <label class="opm-label" id="opm-player-label">Player</label>
                        <div
                            class="opm-players"
                            role="tablist"
                            aria-labelledby="opm-player-label"
                        >
                            <button
                                v-for="(p, pi) in dominanceForMap"
                                :key="p.steamid"
                                role="tab"
                                :aria-selected="selectedSteamid === p.steamid"
                                :tabindex="isPlayerTabFocusable(p.steamid, pi) ? 0 : -1"
                                class="opm-player"
                                :class="{ 'opm-player--active': selectedSteamid === p.steamid }"
                                @click="selectedSteamid = selectedSteamid === p.steamid ? null : p.steamid"
                                @keydown.right.prevent="cyclePlayer(1)"
                                @keydown.left.prevent="cyclePlayer(-1)"
                            >
                                <div class="opm-player__avatar">{{ p.name[0]?.toUpperCase() }}</div>
                                <span class="opm-player__name">{{ p.name }}</span>
                                <span class="opm-player__zone">{{ topZoneName(p) }}</span>
                            </button>
                            <button
                                v-if="selectedSteamid"
                                class="opm-player__reset"
                                @click="selectedSteamid = null"
                            >
                                Show all players
                            </button>
                        </div>
                    </section>

                    <hr class="opm-divider" />

                    <!-- Top zones list -->
                    <section v-if="activeZoneEntries.length" class="opm-section">
                        <label class="opm-label">
                            {{ selectedPlayer ? selectedPlayer.name + "'s" : 'Team' }} positions
                            <span class="opm-label__sub">({{ activeSide }}-side)</span>
                        </label>
                        <div class="opm-zonelist">
                            <div
                                v-for="(entry, i) in activeZoneEntries.slice(0, 8)"
                                :key="entry.zone_id"
                                class="opm-zonerow"
                                @mouseenter="hoveredZoneId = entry.zone_id"
                                @mouseleave="hoveredZoneId = null"
                            >
                                <span class="opm-zonerow__idx">{{ i + 1 }}</span>
                                <div class="opm-zonerow__body">
                                    <div class="opm-zonerow__top">
                                        <span class="opm-zonerow__name">{{ entry.zone_name }}</span>
                                        <span class="opm-zonerow__pct" :style="{ color: entry.color }">{{ entry.pct }}%</span>
                                    </div>
                                    <div class="opm-zonerow__track">
                                        <div
                                            class="opm-zonerow__fill"
                                            :style="{ transform: `scaleX(${Math.min(1, entry.pct / 100)})`, background: entry.color }"
                                        />
                                    </div>
                                </div>
                                <span class="opm-zonerow__type">{{ entry.zone_type }}</span>
                            </div>
                        </div>
                    </section>

                    <div v-else-if="!isLoading && dominanceForMap.length" class="opm-side__empty">
                        No position data for {{ activeSide }}-side on this map yet.
                    </div>

                    <div v-else-if="!isLoading && !dominanceForMap.length" class="opm-side__empty">
                        No position data yet. Demos are still being processed.
                    </div>

                    <!-- Footer stats -->
                    <div class="opm-footstats">
                        <div class="opm-footstats__row">
                            <span>Zones shown</span>
                            <span class="opm-footstats__value">{{ activeZoneEntries.length }}</span>
                        </div>
                        <div class="opm-footstats__row">
                            <span>Demos analysed</span>
                            <span class="opm-footstats__value">{{ totalDemos }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import getScale from '@/scales.js';

const props = defineProps<{
    opponentId: number;
    /** Fallback map name if no data yet */
    defaultMap?: string;
}>();

// ── Store + callouts ──────────────────────────────────────────────────────────
const store              = useStore();
const { selectedTeamId } = storeToRefs(store);

const zones        = ref<any[]>([]);
const zonesLoading = ref(false);
const zonesError   = ref(false);

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const loadZones = async (teamId: number | null | undefined) => {
    if (!teamId) return;
    zonesLoading.value = true;
    zonesError.value   = false;
    try {
        const res = await fetch(`/api/callouts/all?team_id=${teamId}`, {
            headers: { 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) {
            console.error('[OpponentPositionMap] failed to load zones', res.status, res.statusText);
            zones.value      = [];
            zonesError.value = true;
        } else {
            zones.value = await res.json();
        }
    } catch (e) {
        console.error('[OpponentPositionMap] zone request failed', e);
        zones.value      = [];
        zonesError.value = true;
    } finally {
        zonesLoading.value = false;
    }
};

watch(selectedTeamId, (id) => { if (id) loadZones(id); }, { immediate: true });

// ── Data fetching ─────────────────────────────────────────────────────────────
const { data: dominanceData, isLoading } = useQuery({
    queryKey: ['opponent-position-dominance', props.opponentId],
    queryFn:  async () => {
        const res = await fetch(`/api/opponent-teams/${props.opponentId}/position-dominance`, {
            headers: { 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    },
    enabled:   !!props.opponentId,
    staleTime: 1000 * 60 * 5,
});

// ── Available maps ────────────────────────────────────────────────────────────
const availableMaps = computed<string[]>(() => dominanceData.value?.maps ?? []);

const activeMap = ref<string>('');

watch(availableMaps, (maps) => {
    if (!maps.length) return;
    if (!activeMap.value || !maps.includes(activeMap.value)) {
        activeMap.value = maps[0];
    }
}, { immediate: true });

// ── UI state ──────────────────────────────────────────────────────────────────
const activeSide      = ref<'T' | 'CT'>('CT');
const selectedSteamid = ref<string | null>(null);
const hoveredZoneId   = ref<number | null>(null);

// ── Roving-tabindex keyboard navigation for the in-component tablists ─────────
const cycleMap = (direction: 1 | -1) => {
    const maps = availableMaps.value;
    if (!maps.length) return;
    const idx  = Math.max(0, maps.indexOf(activeMap.value));
    activeMap.value = maps[(idx + direction + maps.length) % maps.length];
};

const cycleSide = () => {
    activeSide.value = activeSide.value === 'T' ? 'CT' : 'T';
};

const cyclePlayer = (direction: 1 | -1) => {
    const list = dominanceForMap.value;
    if (!list.length) return;
    const current = list.findIndex((p: any) => p.steamid === selectedSteamid.value);
    // When nothing is selected, arrow keys enter the list from either end.
    const base    = current === -1 ? (direction === 1 ? -1 : 0) : current;
    const next    = (base + direction + list.length) % list.length;
    selectedSteamid.value = list[next].steamid;
};

// The active player tab is focusable; when none is selected the first tab is.
const isPlayerTabFocusable = (steamid: string, index: number) =>
    selectedSteamid.value ? selectedSteamid.value === steamid : index === 0;

// ── Zone lookups ──────────────────────────────────────────────────────────────
const currentZones = computed(() =>
    (zones.value as any[]).filter(z => z.map_key === activeMap.value)
);

const zoneById = computed(() => {
    const m: Record<number, any> = {};
    for (const z of currentZones.value) m[z.id] = z;
    return m;
});

const getZonePolygon = (id: number) => zoneById.value[id]?.polygon ?? [];

// ── Dominance data for current map ───────────────────────────────────────────
const dominanceForMap = computed(() => {
    const all: any[]  = dominanceData.value?.dominance ?? [];
    const zoneIds     = new Set(currentZones.value.map((z: any) => z.id));

    return all.map(player => ({
        steamid: player.steamid,
        name:    player.name,
        T:  (player.T  ?? []).filter((z: any) => zoneIds.has(z.zone_id)),
        CT: (player.CT ?? []).filter((z: any) => zoneIds.has(z.zone_id)),
    })).filter(p => p.T.length > 0 || p.CT.length > 0);
});

const selectedPlayer = computed(() =>
    dominanceForMap.value.find((p: any) => p.steamid === selectedSteamid.value) ?? null
);

const totalDemos = computed(() => dominanceData.value?.total_demos ?? 0);

// ── Zone entries to render ────────────────────────────────────────────────────
const activeZoneEntries = computed(() => {
    if (selectedPlayer.value) {
        return (selectedPlayer.value[activeSide.value] ?? []).slice(0, 20);
    }

    const merged: Record<number, any> = {};
    for (const player of dominanceForMap.value) {
        for (const z of (player[activeSide.value] ?? [])) {
            if (!merged[z.zone_id]) merged[z.zone_id] = { ...z, ticks: 0 };
            merged[z.zone_id].ticks += z.ticks;
        }
    }

    const total = Math.max(1, Object.values(merged).reduce((s: number, z: any) => s + z.ticks, 0));
    return Object.values(merged)
        .map((z: any) => ({ ...z, pct: Math.round(z.ticks / total * 1000) / 10 }))
        .sort((a: any, b: any) => b.ticks - a.ticks)
        .slice(0, 20);
});

const hoveredEntry = computed(() =>
    hoveredZoneId.value
        ? activeZoneEntries.value.find((z: any) => z.zone_id === hoveredZoneId.value) ?? null
        : null
);

const topZoneName = (player: any) => (player[activeSide.value] ?? [])[0]?.zone_name ?? '—';

// ── Map helpers ───────────────────────────────────────────────────────────────
const MAP_DISPLAY: Record<string, string> = {
    de_overpass: 'Overpass', de_mirage: 'Mirage', de_dust2: 'Dust 2',
    de_inferno: 'Inferno', de_nuke: 'Nuke', de_ancient: 'Ancient',
    de_anubis: 'Anubis', de_vertigo: 'Vertigo',
};
const mapDisplayName = (key: string) => MAP_DISPLAY[key] ?? key.replace('de_', '');

// ── SVG coordinate transform ──────────────────────────────────────────────────
const containerRef = ref<HTMLElement | null>(null);
const mapImgRef    = ref<HTMLImageElement | null>(null);
const isMapLoaded  = ref(false);
const svgSize      = ref({ w: 0, h: 0 });
const svgOffset    = ref({ x: 0, y: 0 });

const mapScale = computed(() => getScale(activeMap.value));

function worldToSvgX(wx: number): number {
    const s = mapScale.value;
    return ((wx - s.pos_x) / s.scale / 1024) * svgSize.value.w;
}

function worldToSvgY(wy: number): number {
    const s = mapScale.value;
    return ((s.pos_y - wy) / s.scale / 1024) * svgSize.value.h;
}

function worldPolyToSvg(polygon: any[]): string {
    if (!polygon?.length || !svgSize.value.w) return '';
    // Guard: if the map scale is missing, coordinates would be NaN
    if (!mapScale.value) return '';
    return polygon.map((p: any) => {
        const wx = Array.isArray(p) ? p[0] : p.x;
        const wy = Array.isArray(p) ? p[1] : p.y;
        return `${worldToSvgX(wx).toFixed(1)},${worldToSvgY(wy).toFixed(1)}`;
    }).join(' ');
}

function getZoneCentroid(id: number): { x: number; y: number } {
    const poly = getZonePolygon(id);
    if (!poly?.length) return { x: 0, y: 0 };
    const pts = poly.map((p: any) => ({
        x: worldToSvgX(Array.isArray(p) ? p[0] : p.x),
        y: worldToSvgY(Array.isArray(p) ? p[1] : p.y),
    }));
    return {
        x: pts.reduce((s: number, p: any) => s + p.x, 0) / pts.length,
        y: pts.reduce((s: number, p: any) => s + p.y, 0) / pts.length,
    };
}

function hexAlpha(pct: number): string {
    const alpha = Math.round(20 + (pct / 100) * 55);
    return Math.min(255, alpha).toString(16).padStart(2, '0');
}

function updateDimensions() {
    const container = containerRef.value;
    const img       = mapImgRef.value;
    if (!container || !img) return;

    const cw    = container.clientWidth;
    const ch    = container.clientHeight;
    const natW  = img.naturalWidth  || 1024;
    const natH  = img.naturalHeight || 1024;
    const ratio = Math.min(cw / natW, ch / natH);
    const w     = Math.round(natW * ratio);
    const h     = Math.round(natH * ratio);

    svgSize.value   = { w, h };
    svgOffset.value = { x: Math.round((cw - w) / 2), y: Math.round((ch - h) / 2) };
}

function onMapLoad() {
    isMapLoaded.value = true;
    // Call directly — do NOT use nextTick. On production images may load from
    // cache synchronously, causing nextTick to fire before the layout is ready.
    updateDimensions();
    // Belt-and-suspenders: also schedule a deferred pass in case the container
    // hasn't finished its own layout cycle yet (e.g. flexbox settle).
    nextTick(updateDimensions);
}

// Reset map state when active map tab changes
watch(activeMap, () => {
    isMapLoaded.value     = false;
    svgSize.value         = { w: 0, h: 0 };
    selectedSteamid.value = null;
    hoveredZoneId.value   = null;
});

watch(activeSide, () => { hoveredZoneId.value = null; });

// ── ResizeObserver — replaces fragile window 'resize' listener ────────────────
// window resize does not fire when the container resizes due to layout changes
// (e.g. sidebar collapsing, tab switching). ResizeObserver catches all of that.
let ro: ResizeObserver | null = null;

onMounted(() => {
    ro = new ResizeObserver(() => updateDimensions());
    if (containerRef.value) ro.observe(containerRef.value);
});

onBeforeUnmount(() => ro?.disconnect());
</script>

<style scoped>
.opm {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--th-bg, oklch(0.125 0.012 250));
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Map tab bar ── */
.opm-maptabs {
    display: flex;
    gap: 4px;
    padding: 8px 16px 0;
    border-bottom: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    flex-shrink: 0;
    overflow-x: auto;
    scrollbar-width: none;
}

.opm-maptabs::-webkit-scrollbar { display: none; }

.opm-maptab {
    padding: 8px 12px;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
    font-size: 12px;
    font-weight: 600;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: none;
    border: 1px solid transparent;
    border-bottom: none;
    border-radius: 5px 5px 0 0;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: color 150ms ease, background 150ms ease;
}

.opm-maptab:hover { color: var(--th-text-2, oklch(1 0 0 / 0.70)); }

.opm-maptab--active {
    background: var(--th-surface, oklch(0.175 0.014 250));
    border-color: var(--th-border-2, oklch(1 0 0 / 0.14));
    color: var(--th-text, oklch(1 0 0 / 0.96));
}

.opm-maptab:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: -2px;
}

/* ── Layout ── */
.opm__layout {
    display: flex;
    flex: 1;
    overflow: hidden;
}

@media (max-width: 1024px) {
    .opm__layout { flex-direction: column; }
}

/* ── Canvas ── */
.opm-canvas {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    min-height: 380px;
}

@media (min-width: 1025px) {
    .opm-canvas { min-height: 0; }
}

.opm-canvas__inner {
    position: absolute;
    inset: 0;
}

.opm-mapimg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    opacity: 0;
    transition: opacity 300ms ease;
}

.opm-mapimg--loaded { opacity: 1; }

@media (prefers-reduced-motion: reduce) {
    .opm-mapimg { transition: none; }
}

.opm-svg {
    position: absolute;
    pointer-events: none;
    z-index: 10;
}

.opm-svg__zone { pointer-events: auto; }

.opm-svg__poly {
    transition: opacity 100ms ease;
    cursor: pointer;
}

@media (prefers-reduced-motion: reduce) {
    .opm-svg__poly { transition: none; }
}

/* ── States ── */
.opm-state {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    z-index: 50;
}

.opm-state__text {
    font-size: 12px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 0;
}

.opm-spinner {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid color-mix(in oklch, var(--th-accent, #ff8c00) 20%, transparent);
    border-top-color: var(--th-accent, #ff8c00);
    animation: opm-spin 0.8s linear infinite;
}

@keyframes opm-spin {
    to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .opm-spinner { animation: none; }
}

.opm-nozones {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 40;
    pointer-events: none;
}

.opm-nozones__box {
    padding: 12px 16px;
    border-radius: 6px;
    background: color-mix(in oklch, var(--th-surface, oklch(0.175 0.014 250)) 88%, transparent);
    border: 1px solid var(--th-border-2, oklch(1 0 0 / 0.14));
    text-align: center;
}

.opm-nozones__box p {
    font-size: 12px;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    margin: 0;
}

.opm-nozones__box strong { color: var(--th-text, oklch(1 0 0 / 0.96)); }

.opm-nozones__box--error {
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 10%, var(--th-surface, oklch(0.175 0.014 250)));
    border-color: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 32%, transparent);
}

.opm-nozones__box--error p { color: var(--th-loss, oklch(0.62 0.20 22)); }

/* ── Tooltip ── */
.opm-tooltip {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 30;
    padding: 8px 12px;
    border-radius: 6px;
    background: color-mix(in oklch, var(--th-surface, oklch(0.175 0.014 250)) 95%, transparent);
    border: 1px solid var(--th-border-2, oklch(1 0 0 / 0.14));
    pointer-events: none;
}

.opm-tooltip__name {
    font-size: 12px;
    font-weight: 700;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    margin: 0;
}

.opm-tooltip__sub {
    font-size: 11px;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    margin: 2px 0 0;
}

.opm-tooltip__type {
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    text-transform: capitalize;
    margin: 1px 0 0;
}

/* ── Sidebar ── */
.opm-side {
    width: 288px;
    flex-shrink: 0;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border-left: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    display: flex;
    flex-direction: column;
}

@media (max-width: 1024px) {
    .opm-side {
        width: 100%;
        border-left: none;
        border-top: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    }
}

.opm-side__scroll {
    flex: 1;
    overflow-y: auto;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    scrollbar-width: thin;
    scrollbar-color: var(--th-border-2, oklch(1 0 0 / 0.14)) transparent;
}

.opm-side__scroll::-webkit-scrollbar { width: 5px; }
.opm-side__scroll::-webkit-scrollbar-thumb {
    background: var(--th-border-2, oklch(1 0 0 / 0.14));
    border-radius: 3px;
}

.opm-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.opm-label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.opm-label__sub {
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    font-weight: 400;
    margin-left: 4px;
}

/* ── Segmented control ── */
.opm-segmented {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: var(--th-bg, oklch(0.125 0.012 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    border-radius: 6px;
}

.opm-seg {
    flex: 1;
    padding: 11px 0;
    min-height: 36px;
    border-radius: 4px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    font-weight: 600;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: none;
    border: none;
    cursor: pointer;
    transition: color 150ms ease, background 150ms ease;
}

.opm-seg:hover { color: var(--th-text-2, oklch(1 0 0 / 0.70)); }

.opm-seg--active-t {
    background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 20%, transparent);
    color: var(--th-warn, oklch(0.78 0.15 80));
}

.opm-seg--active-ct {
    background: color-mix(in oklch, var(--th-accent-2, oklch(0.62 0.20 50)) 24%, transparent);
    color: var(--th-accent-2, oklch(0.62 0.20 50));
}

.opm-seg:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

/* ── Player list ── */
.opm-players {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.opm-player {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border-radius: 5px;
    background: var(--th-bg, oklch(0.125 0.012 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    cursor: pointer;
    text-align: left;
    transition: border-color 150ms ease, background 150ms ease;
}

.opm-player:hover { border-color: var(--th-border-3, oklch(1 0 0 / 0.22)); }

.opm-player--active {
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 14%, transparent);
    border-color: color-mix(in oklch, var(--th-accent, #ff8c00) 45%, transparent);
    color: var(--th-text, oklch(1 0 0 / 0.96));
}

.opm-player__avatar {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 700;
}

.opm-player__name {
    flex: 1;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.opm-player__zone {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    flex-shrink: 0;
}

.opm-player__reset {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    background: none;
    border: none;
    padding: 4px 0;
    cursor: pointer;
    transition: color 150ms ease;
}

.opm-player__reset:hover { color: var(--th-accent, #ff8c00); }

/* ── Divider ── */
.opm-divider {
    border: none;
    border-top: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    margin: 0;
}

/* ── Zone list ── */
.opm-zonelist {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.opm-zonerow {
    display: flex;
    align-items: center;
    gap: 8px;
}

.opm-zonerow__idx {
    width: 16px;
    flex-shrink: 0;
    text-align: right;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

.opm-zonerow__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.opm-zonerow__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.opm-zonerow__name {
    font-size: 12px;
    font-weight: 500;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.opm-zonerow__pct {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    flex-shrink: 0;
}

.opm-zonerow__track {
    height: 4px;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    border-radius: 2px;
    overflow: hidden;
}

.opm-zonerow__fill {
    width: 100%;
    height: 100%;
    border-radius: 2px;
    transform-origin: left;
    transition: transform 300ms cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
    .opm-zonerow__fill { transition: none; }
}

.opm-zonerow__type {
    width: 32px;
    flex-shrink: 0;
    text-align: right;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    text-transform: capitalize;
}

.opm-side__empty {
    text-align: center;
    font-size: 12px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    padding: 16px 0;
}

/* ── Footer stats ── */
.opm-footstats {
    border-radius: 6px;
    background: var(--th-bg, oklch(0.125 0.012 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.opm-footstats__row {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.opm-footstats__value {
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-variant-numeric: tabular-nums;
}
</style>
