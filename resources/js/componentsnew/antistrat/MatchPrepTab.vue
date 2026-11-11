<template>
    <div class="mpt">

        <!-- Map selector -->
        <div class="mpt-mapsel">
            <label class="mpt-mapsel__label" id="mpt-mapsel-label">Match map</label>
            <div
                class="mpt-mapsel__btns"
                role="tablist"
                aria-labelledby="mpt-mapsel-label"
            >
                <button
                    v-for="map in availableMaps"
                    :key="map.key"
                    role="tab"
                    :aria-selected="activeMap === map.key"
                    :tabindex="activeMap === map.key ? 0 : -1"
                    class="mpt-mapchip"
                    :class="{ 'mpt-mapchip--active': activeMap === map.key }"
                    @click="activeMap = map.key"
                    @keydown.right.prevent="cycleMap(1)"
                    @keydown.left.prevent="cycleMap(-1)"
                >
                    {{ map.label }}
                </button>
            </div>
            <span v-if="!availableMaps.length" class="mpt-mapsel__empty">No map data yet. Link demos first.</span>
        </div>

        <div v-if="activeMap" class="mpt-grid">

            <!-- Left: Map with zone overlays -->
            <div class="mpt-col">
                <!-- Side toggle -->
                <div class="mpt-segmented" role="tablist" aria-label="Side">
                    <button
                        v-for="s in (['CT', 'T'] as const)"
                        :key="s"
                        role="tab"
                        :aria-selected="activeSide === s"
                        :tabindex="activeSide === s ? 0 : -1"
                        class="mpt-seg"
                        :class="{
                            'mpt-seg--ct': activeSide === s && s === 'CT',
                            'mpt-seg--t': activeSide === s && s === 'T',
                        }"
                        @click="activeSide = s"
                        @keydown.right.prevent="cycleSide()"
                        @keydown.left.prevent="cycleSide()"
                    >
                        {{ s }}-side
                    </button>
                </div>

                <!-- Map with zone overlays -->
                <div ref="mapContainer" class="mpt-map">

                    <div v-if="!isMapLoaded" class="mpt-map__loading">
                        <div class="mpt-spinner" />
                    </div>

                    <img
                        :key="activeMap"
                        :src="'/images/demo/' + activeMap + '.svg'"
                        class="mpt-mapimg"
                        :class="{ 'mpt-mapimg--loaded': isMapLoaded }"
                        ref="mapImgRef"
                        :alt="`${mapDisplayName(activeMap)} radar`"
                        @load="onMapLoad"
                    />

                    <!-- Zone overlay SVG -->
                    <svg
                        v-if="isMapLoaded && svgSize.w > 0"
                        class="mpt-svg"
                        :width="svgSize.w"
                        :height="svgSize.h"
                        :style="{ top: svgOffset.y + 'px', left: svgOffset.x + 'px' }"
                    >

                        <!-- All zones dimmed -->
                        <polygon
                            v-for="zone in mapZones"
                            :key="'bg-' + zone.id"
                            :points="worldPolyToSvg(zone.polygon)"
                            fill="rgba(255,255,255,0.02)"
                            stroke="rgba(255,255,255,0.05)"
                            stroke-width="0.5"
                        />

                        <!-- Top dominant zones per player, coloured -->
                        <g v-for="entry in dominantZoneEntries" :key="'dom-' + entry.zone_id + entry.steamid">
                            <polygon
                                :points="worldPolyToSvg(getZonePolygon(entry.zone_id))"
                                :fill="entry.color + '55'"
                                :stroke="entry.color"
                                stroke-width="1.5"
                            />
                            <text
                                v-if="getZoneCentroid(entry.zone_id).x > 0"
                                :x="getZoneCentroid(entry.zone_id).x"
                                :y="getZoneCentroid(entry.zone_id).y - 4"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                fill="oklch(1 0 0 / 0.96)"
                                font-size="9"
                                font-weight="800"
                                style="text-shadow: 0 1px 4px oklch(0.04 0.01 250 / 0.7)"
                            >
                                {{ entry.zone_name }}
                            </text>
                            <text
                                v-if="getZoneCentroid(entry.zone_id).x > 0"
                                :x="getZoneCentroid(entry.zone_id).x"
                                :y="getZoneCentroid(entry.zone_id).y + 7"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                fill="oklch(1 0 0 / 0.6)"
                                font-size="7"
                                style="text-shadow: 0 1px 4px oklch(0.04 0.01 250 / 0.7)"
                            >
                                {{ entry.player_name }} · {{ entry.pct }}%
                            </text>
                        </g>
                    </svg>
                </div>

                <!-- Zone legend -->
                <div v-if="dominantZoneEntries.length" class="mpt-legend">
                    <p class="mpt-legend__label">Top positions on {{ activeSide }}-side</p>
                    <div
                        v-for="entry in dominantZoneEntries"
                        :key="entry.zone_id + entry.steamid"
                        class="mpt-legend__row"
                    >
                        <span class="mpt-legend__dot" :style="{ background: entry.color }" />
                        <span class="mpt-legend__zone">{{ entry.zone_name }}</span>
                        <span class="mpt-legend__player">{{ entry.player_name }}</span>
                        <span class="mpt-legend__pct" :style="{ color: entry.color }">{{ entry.pct }}%</span>
                    </div>
                </div>
                <p v-else class="mpt-col__empty">
                    No position data for {{ activeMap }} yet
                </p>
            </div>

            <!-- Right: Counter strat checklist + AI intel -->
            <div class="mpt-col">

                <!-- AI map intel -->
                <div v-if="mapIntel" class="mpt-panel">
                    <h3 class="mpt-panel__title">AI Map Intel</h3>
                    <div class="mpt-intel__pref">
                        <span class="mpt-intel__pref-label">Prefers</span>
                        <span
                            class="side-chip"
                            :class="mapIntel.preferred_side === 'T' ? 'side-chip--t' : mapIntel.preferred_side === 'CT' ? 'side-chip--ct' : 'side-chip--neutral'"
                        >
                            {{ mapIntel.preferred_side }}-side
                        </span>
                    </div>
                    <div v-if="mapIntel.ct_setup" class="mpt-intel__line">
                        <span class="mpt-intel__line-label">CT setup: </span>
                        <CalloutRenderer :text="mapIntel.ct_setup" />
                    </div>
                    <div v-if="mapIntel.t_tendencies" class="mpt-intel__line">
                        <span class="mpt-intel__line-label">T tendencies: </span>
                        <CalloutRenderer :text="mapIntel.t_tendencies" />
                    </div>
                    <ul v-if="mapIntel.exploitable_habits?.length" class="mpt-intel__habits">
                        <li v-for="habit in mapIntel.exploitable_habits" :key="habit" class="mpt-habit">
                            <Icon icon="lucide:alert-triangle" class="mpt-habit__icon" />
                            <CalloutRenderer :text="habit" />
                        </li>
                    </ul>
                </div>

                <!-- Counter strat checklist -->
                <div class="mpt-panel mpt-panel--flush">
                    <div class="mpt-panel__head">
                        <h3 class="mpt-panel__title">Counter Strats</h3>
                        <span class="mpt-panel__count">{{ checkedCount }}/{{ mapStrats.length }} reviewed</span>
                    </div>

                    <div v-if="mapStrats.length" class="mpt-checklist">
                        <label
                            v-for="strat in mapStrats"
                            :key="strat.id"
                            class="mpt-check"
                        >
                            <div class="mpt-check__box-wrap">
                                <input
                                    type="checkbox"
                                    class="mpt-check__box"
                                    :checked="checked.has(strat.id)"
                                    :aria-label="`Mark counter strat reviewed: ${strat.name}`"
                                    @change="toggleCheck(strat.id)"
                                >
                                <Icon v-if="checked.has(strat.id)" icon="lucide:check" class="mpt-check__tick" />
                            </div>
                            <div class="mpt-check__body">
                                <div class="mpt-check__chips">
                                    <span class="side-chip" :class="strat.side === 'T' ? 'side-chip--t' : 'side-chip--ct'">
                                        {{ strat.side }}
                                    </span>
                                    <span v-if="strat.ai_generated" class="ai-chip">
                                        <Icon icon="lucide:sparkles" class="ai-chip__icon" /> AI
                                    </span>
                                    <span v-for="tag in (strat.tags ?? [])" :key="tag" class="tag-chip">{{ tag }}</span>
                                </div>
                                <p class="mpt-check__name" :class="{ 'mpt-check__name--done': checked.has(strat.id) }">
                                    {{ strat.name }}
                                </p>
                                <p v-if="strat.description" class="mpt-check__desc">
                                    <CalloutRenderer :text="strat.description" />
                                </p>
                                <!-- Zone chips -->
                                <div v-if="strat.zone_ids?.length" class="mpt-check__zones">
                                    <div
                                        v-for="zid in strat.zone_ids"
                                        :key="zid"
                                        class="zone-chip"
                                        :style="{
                                            borderColor: (getZoneById(zid)?.color ?? 'oklch(1 0 0 / 0.50)') + '60',
                                            background:  (getZoneById(zid)?.color ?? 'transparent') + '18',
                                            color:       getZoneById(zid)?.color ?? 'var(--th-text-3, oklch(1 0 0 / 0.62))',
                                        }"
                                    >
                                        {{ getZoneById(zid)?.canonical_name ?? 'Zone ' + zid }}
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div v-else class="mpt-checklist__empty">
                        No counter strats for {{ mapDisplayName(activeMap) }} yet.
                        <br>
                        <span>Go to the Counters tab to generate them.</span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div v-if="mapStrats.length" class="mpt-progress">
                    <div class="mpt-progress__head">
                        <span class="mpt-progress__label">Match prep progress</span>
                        <span class="mpt-progress__pct">{{ Math.round(checkedCount / mapStrats.length * 100) }}%</span>
                    </div>
                    <div class="mpt-progress__track">
                        <div
                            class="mpt-progress__fill"
                            :style="{ transform: `scaleX(${mapStrats.length ? checkedCount / mapStrats.length : 0})` }"
                        />
                    </div>
                    <button
                        v-if="checkedCount > 0"
                        class="mpt-progress__reset"
                        @click="resetChecklist"
                    >
                        Reset checklist
                    </button>
                </div>
            </div>
        </div>

        <!-- No map selected yet -->
        <div v-else class="mpt-empty">
            <Icon icon="lucide:map" class="mpt-empty__icon" />
            <p>Select a map above to start match prep</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Icon } from '@iconify/vue';
import CalloutRenderer from '@/componentsnew/callouts/CalloutRenderer.vue';
import getScale from '@/scales.js';

const props = defineProps<{
    opponent:          any;
    analytics:         any;
    positionDominance: any;   // from position-dominance endpoint
    strats:            any[]; // all counter strats
    zones:             any[]; // all callout zones
    aiInsights:        any;
}>();

// ── Map selection ─────────────────────────────────────────────────────────────
const MAP_NAMES: Record<string, string> = {
    de_overpass: 'Overpass', de_mirage: 'Mirage', de_dust2: 'Dust 2',
    de_inferno: 'Inferno', de_nuke: 'Nuke', de_ancient: 'Ancient',
    de_anubis: 'Anubis', de_vertigo: 'Vertigo',
};
const mapDisplayName = (key: string) => MAP_NAMES[key] ?? key.replace('de_', '');

const availableMaps = computed(() => {
    const maps: string[] = props.positionDominance?.maps ?? [];
    if (!maps.length && props.analytics?.map) return [props.analytics.map[0]].filter(Boolean).map((k: string) => ({ key: k, label: mapDisplayName(k) }));
    return maps.map((k: string) => ({ key: k, label: mapDisplayName(k) }));
});

const activeMap  = ref('');
const activeSide = ref<'CT' | 'T'>('CT');

watch(availableMaps, (maps) => {
    if (maps.length && !activeMap.value) activeMap.value = maps[0].key;
}, { immediate: true });

// ── Roving-tabindex keyboard navigation for the in-component tablists ─────────
const cycleMap = (direction: 1 | -1) => {
    const maps = availableMaps.value;
    if (!maps.length) return;
    const idx  = Math.max(0, maps.findIndex(m => m.key === activeMap.value));
    activeMap.value = maps[(idx + direction + maps.length) % maps.length].key;
};

const cycleSide = () => {
    activeSide.value = activeSide.value === 'CT' ? 'T' : 'CT';
};

// ── Zones for active map ──────────────────────────────────────────────────────
const mapZones = computed(() =>
    (props.zones as any[]).filter(z => z.map_key === activeMap.value)
);

const zoneById = computed(() => {
    const m: Record<number, any> = {};
    for (const z of mapZones.value) m[z.id] = z;
    return m;
});

const getZoneById     = (id: number) => zoneById.value[id] ?? null;
const getZonePolygon  = (id: number) => zoneById.value[id]?.polygon ?? [];

// ── Dominant zone entries for overlay ────────────────────────────────────────
// Top 3 zones per side, across all players — deduplicated
const dominantZoneEntries = computed(() => {
    const dominance = props.positionDominance?.dominance ?? [];
    const seen      = new Set<number>();
    const entries: any[] = [];

    // For each player, take their top zone for this map/side
    for (const player of dominance) {
        const zones = (player[activeSide.value] ?? [])
            .filter((z: any) => zoneById.value[z.zone_id]) // only zones we have polygons for
            .slice(0, 2); // top 2 per player

        for (const zone of zones) {
            if (seen.has(zone.zone_id)) continue;
            seen.add(zone.zone_id);
            entries.push({
                ...zone,
                player_name: player.name,
                steamid:     player.steamid,
            });
            if (entries.length >= 6) break; // max 6 zones total
        }
        if (entries.length >= 6) break;
    }

    return entries;
});

// ── AI map intel ──────────────────────────────────────────────────────────────
const mapIntel = computed(() => {
    if (!props.aiInsights?.map_specific) return null;
    // Try exact match and de_mapname format
    return props.aiInsights.map_specific[activeMap.value]
        ?? props.aiInsights.map_specific[mapDisplayName(activeMap.value)]
        ?? null;
});

// ── Counter strats for active map ─────────────────────────────────────────────
const mapStrats = computed(() =>
    props.strats.filter((s: any) =>
        s.map?.name === activeMap.value || s.map?.display_name === mapDisplayName(activeMap.value)
    )
);

// ── Checklist (persisted to localStorage per opponent + map) ──────────────────
const checked = ref(new Set<number>());
const checkedCount = computed(() => checked.value.size);

const storageKey = computed(() => {
    const oppId = props.opponent?.id ?? 'unknown';
    return `antistrat-matchprep-${oppId}-${activeMap.value || 'none'}`;
});

const loadChecked = () => {
    if (!activeMap.value) { checked.value = new Set(); return; }
    try {
        const raw = localStorage.getItem(storageKey.value);
        const ids: number[] = raw ? JSON.parse(raw) : [];
        checked.value = new Set(Array.isArray(ids) ? ids : []);
    } catch {
        checked.value = new Set();
    }
};

const saveChecked = () => {
    if (!activeMap.value) return;
    try {
        localStorage.setItem(storageKey.value, JSON.stringify([...checked.value]));
    } catch { /* storage unavailable / quota — non-fatal */ }
};

// Reload the checklist whenever the opponent or active map changes.
watch(storageKey, loadChecked, { immediate: true });

const toggleCheck = (id: number) => {
    const next = new Set(checked.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    checked.value = next;
    saveChecked();
};

const resetChecklist = () => {
    checked.value = new Set();
    saveChecked();
};

// ── Map rendering (same pattern as OpponentPositionMap) ───────────────────────
const mapContainer = ref<HTMLElement | null>(null);
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

function updateDimensions() {
    const container = mapContainer.value;
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
    nextTick(updateDimensions);
}

watch(activeMap, () => {
    isMapLoaded.value = false;
    svgSize.value = { w: 0, h: 0 };
});

onMounted(() => window.addEventListener('resize', updateDimensions));
onBeforeUnmount(() => window.removeEventListener('resize', updateDimensions));
</script>

<style scoped>
.mpt {
    display: flex;
    flex-direction: column;
    gap: 24px;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Map selector ── */
.mpt-mapsel {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.mpt-mapsel__label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.mpt-mapsel__btns {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.mpt-mapsel__empty {
    font-size: 12px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

.mpt-mapchip {
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    cursor: pointer;
    transition: border-color 150ms ease, color 150ms ease, background 150ms ease;
}

.mpt-mapchip:hover { border-color: var(--th-border-3, oklch(1 0 0 / 0.22)); }

.mpt-mapchip--active {
    background: var(--th-accent, #ff8c00);
    border-color: var(--th-accent, #ff8c00);
    color: oklch(0.15 0.005 53);
}

.mpt-mapchip:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

/* ── Grid ── */
.mpt-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 1024px) {
    .mpt-grid { grid-template-columns: 1fr; }
}

.mpt-col {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}

.mpt-col__empty {
    text-align: center;
    font-size: 12px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    padding: 8px 0;
}

/* ── Side toggle ── */
.mpt-segmented {
    display: flex;
    gap: 4px;
    padding: 4px;
    width: fit-content;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    border-radius: 6px;
}

.mpt-seg {
    padding: 6px 16px;
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

.mpt-seg:hover { color: var(--th-text-2, oklch(1 0 0 / 0.70)); }

.mpt-seg--ct {
    background: color-mix(in oklch, var(--th-accent-2, oklch(0.62 0.20 50)) 24%, transparent);
    color: var(--th-accent-2, oklch(0.62 0.20 50));
}

.mpt-seg--t {
    background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 20%, transparent);
    color: var(--th-warn, oklch(0.78 0.15 80));
}

.mpt-seg:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

/* ── Map ── */
.mpt-map {
    position: relative;
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    background: var(--th-bg, oklch(0.125 0.012 250));
}

.mpt-map__loading {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.mpt-spinner {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid color-mix(in oklch, var(--th-accent, #ff8c00) 20%, transparent);
    border-top-color: var(--th-accent, #ff8c00);
    animation: mpt-spin 0.8s linear infinite;
}

@keyframes mpt-spin {
    to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .mpt-spinner { animation: none; }
}

.mpt-mapimg {
    width: 100%;
    height: 100%;
    object-fit: contain;
    opacity: 0;
    transition: opacity 300ms ease;
}

.mpt-mapimg--loaded { opacity: 1; }

@media (prefers-reduced-motion: reduce) {
    .mpt-mapimg { transition: none; }
}

.mpt-svg {
    position: absolute;
    pointer-events: none;
}

/* ── Legend ── */
.mpt-legend {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.mpt-legend__label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 0;
}

.mpt-legend__row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.mpt-legend__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

.mpt-legend__zone {
    font-weight: 500;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.mpt-legend__player {
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

.mpt-legend__pct {
    margin-left: auto;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

/* ── Panel ── */
.mpt-panel {
    border-radius: 6px;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.mpt-panel--flush {
    padding: 0;
    gap: 0;
    overflow: hidden;
}

.mpt-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    background: var(--th-surface-2, oklch(0.215 0.016 250));
}

.mpt-panel__title {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 0;
}

.mpt-panel__count {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    font-variant-numeric: tabular-nums;
}

/* ── AI intel ── */
.mpt-intel__pref {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mpt-intel__pref-label {
    font-size: 12px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.mpt-intel__line {
    font-size: 12px;
    line-height: 1.5;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.mpt-intel__line-label {
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    font-weight: 600;
}

.mpt-intel__habits {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.mpt-habit {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 12px;
    color: var(--th-warn, oklch(0.78 0.15 80));
}

.mpt-habit__icon {
    flex-shrink: 0;
    margin-top: 2px;
    font-size: 10px;
}

/* ── Checklist ── */
.mpt-checklist {
    display: flex;
    flex-direction: column;
}

.mpt-check {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    min-height: 44px;
    cursor: pointer;
    border-bottom: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    transition: background 150ms ease;
}

.mpt-check:last-child { border-bottom: none; }
.mpt-check:hover { background: var(--th-surface-2, oklch(0.215 0.016 250)); }

/* The whole label row is the hit target (>=44px tall); the visual box itself
   stays compact but is a real <input type="checkbox"> for keyboard + a11y. */
.mpt-check__box-wrap {
    position: relative;
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    margin-top: 2px;
}

.mpt-check__box {
    appearance: none;
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    margin: 0;
    border-radius: 3px;
    border: 2px solid var(--th-border-3, oklch(1 0 0 / 0.22));
    background: transparent;
    cursor: pointer;
    transition: background 150ms ease, border-color 150ms ease;
}

.mpt-check__box:hover { border-color: var(--th-text-3, oklch(1 0 0 / 0.62)); }

.mpt-check__box:checked {
    background: var(--th-accent, #ff8c00);
    border-color: var(--th-accent, #ff8c00);
}

.mpt-check__box:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

.mpt-check__tick {
    position: absolute;
    inset: 0;
    margin: auto;
    width: 10px;
    height: 10px;
    font-size: 10px;
    color: oklch(0.15 0.005 53);
    pointer-events: none;
}

.mpt-check__body {
    flex: 1;
    min-width: 0;
}

.mpt-check__chips {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 5px;
}

.mpt-check__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    margin: 0;
}

.mpt-check__name--done {
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    text-decoration: line-through;
}

.mpt-check__desc {
    font-size: 12px;
    line-height: 1.5;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 4px 0 0;
}

.mpt-check__zones {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}

.mpt-checklist__empty {
    padding: 24px 16px;
    text-align: center;
    font-size: 12px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.mpt-checklist__empty span { color: var(--th-text-4, oklch(1 0 0 / 0.50)); }

/* ── Progress ── */
.mpt-progress {
    border-radius: 6px;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    padding: 12px 14px;
}

.mpt-progress__head {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 8px;
}

.mpt-progress__label { color: var(--th-text-3, oklch(1 0 0 / 0.62)); }

.mpt-progress__pct {
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-variant-numeric: tabular-nums;
}

.mpt-progress__track {
    height: 6px;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    border-radius: 3px;
    overflow: hidden;
}

.mpt-progress__fill {
    width: 100%;
    height: 100%;
    background: var(--th-accent, #ff8c00);
    border-radius: 3px;
    transform-origin: left;
    transition: transform 300ms cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
    .mpt-progress__fill { transition: none; }
}

.mpt-progress__reset {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    background: none;
    border: none;
    padding: 0;
    margin-top: 8px;
    cursor: pointer;
    transition: color 150ms ease;
}

.mpt-progress__reset:hover { color: var(--th-text-3, oklch(1 0 0 / 0.62)); }

/* ── Empty ── */
.mpt-empty {
    padding: 56px 32px;
    text-align: center;
    border: 1px dashed var(--th-border-2, oklch(1 0 0 / 0.14));
    border-radius: 6px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.mpt-empty__icon {
    font-size: 30px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin-bottom: 8px;
}

.mpt-empty p {
    font-size: 13px;
    margin: 0;
}

/* ── Shared chips ── */
.side-chip {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 2px 7px;
    border-radius: 3px;
}

.side-chip--t  { color: var(--th-warn, oklch(0.78 0.15 80)); background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 16%, transparent); }
.side-chip--ct { color: var(--th-accent-2, oklch(0.62 0.20 50)); background: color-mix(in oklch, var(--th-accent-2, oklch(0.62 0.20 50)) 20%, transparent); }
.side-chip--neutral { color: var(--th-text-3, oklch(1 0 0 / 0.62)); background: var(--th-surface-3, oklch(0.26 0.018 250)); }

.ai-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 3px;
    color: var(--th-accent, #ff8c00);
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 16%, transparent);
}

.ai-chip__icon { font-size: 8px; }

.tag-chip {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 3px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: var(--th-surface-3, oklch(0.26 0.018 250));
}

.zone-chip {
    display: inline-flex;
    align-items: center;
    font-size: 9px;
    font-weight: 500;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid;
}
</style>
