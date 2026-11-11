<template>
    <div class="ohm">

        <!-- Controls -->
        <div class="ohm-controls">

            <!-- Mode tabs -->
            <div class="ohm-seg" role="tablist" aria-label="Heatmap mode">
                <button
                    v-for="m in modes"
                    :key="m.id"
                    role="tab"
                    :aria-selected="activeMode === m.id"
                    :tabindex="activeMode === m.id ? 0 : -1"
                    class="ohm-seg__btn"
                    :class="{ 'ohm-seg__btn--active': activeMode === m.id }"
                    @click="setMode(m.id)"
                    @keydown.right.prevent="cycleMode(1)"
                    @keydown.left.prevent="cycleMode(-1)"
                >
                    {{ m.label }}
                </button>
            </div>

            <!-- Side filter -->
            <div v-if="showSideFilter" class="ohm-seg" role="tablist" aria-label="Side">
                <button
                    v-for="s in sides"
                    :key="s.id"
                    role="tab"
                    :aria-selected="activeSide === s.id"
                    :tabindex="activeSide === s.id ? 0 : -1"
                    class="ohm-seg__btn"
                    :class="{
                        'ohm-seg__btn--active': activeSide === s.id,
                        [`ohm-seg__btn--${s.tone}`]: activeSide === s.id,
                    }"
                    @click="activeSide = s.id"
                    @keydown.right.prevent="cycleSide(1)"
                    @keydown.left.prevent="cycleSide(-1)"
                >
                    {{ s.label }}
                </button>
            </div>

            <!-- Utility sub-type -->
            <div v-if="activeMode === 'utility'" class="ohm-seg" role="tablist" aria-label="Utility type">
                <button
                    v-for="u in utilityTypes"
                    :key="u.id"
                    role="tab"
                    :aria-selected="activeUtilityType === u.id"
                    :tabindex="activeUtilityType === u.id ? 0 : -1"
                    class="ohm-seg__btn"
                    :class="{ 'ohm-seg__btn--active': activeUtilityType === u.id }"
                    @click="activeUtilityType = u.id"
                    @keydown.right.prevent="cycleUtilityType(1)"
                    @keydown.left.prevent="cycleUtilityType(-1)"
                >
                    {{ u.label }}
                </button>
            </div>

            <!-- Round filter (multi-select: buttons with aria-pressed, not a tablist) -->
            <div
                v-if="availableRounds.length && ['entries','shots'].includes(activeMode)"
                class="ohm-filter"
            >
                <span class="ohm-filter__label">Round</span>
                <button
                    type="button"
                    class="ohm-chip"
                    :class="{ 'ohm-chip--active': activeRound === null }"
                    :aria-pressed="activeRound === null"
                    @click="activeRound = null"
                >All</button>
                <button
                    v-for="r in availableRounds.slice(0, 30)"
                    :key="r"
                    type="button"
                    class="ohm-chip"
                    :class="{ 'ohm-chip--active': activeRound === r }"
                    :aria-pressed="activeRound === r"
                    @click="activeRound = activeRound === r ? null : r"
                >
                    {{ r }}
                </button>
            </div>

            <!-- Weapon filter (shots mode; multi-select buttons with aria-pressed) -->
            <div
                v-if="activeMode === 'shots' && availableWeapons.length"
                class="ohm-filter"
            >
                <span class="ohm-filter__label">Weapon</span>
                <button
                    type="button"
                    class="ohm-chip"
                    :class="{ 'ohm-chip--active': activeWeapon === null }"
                    :aria-pressed="activeWeapon === null"
                    @click="activeWeapon = null"
                >All</button>
                <button
                    v-for="w in availableWeapons"
                    :key="w"
                    type="button"
                    class="ohm-chip ohm-chip--cap"
                    :class="{ 'ohm-chip--active': activeWeapon === w }"
                    :aria-pressed="activeWeapon === w"
                    @click="activeWeapon = activeWeapon === w ? null : w"
                >
                    {{ w.replace(/_/g, ' ') }}
                </button>
            </div>

            <!-- Player filter (multi-select buttons with aria-pressed) -->
            <div class="ohm-filter">
                <button
                    type="button"
                    class="ohm-chip"
                    :class="{ 'ohm-chip--active': selectedPlayers.length === 0 }"
                    :aria-pressed="selectedPlayers.length === 0"
                    @click="selectedPlayers = []"
                >All</button>
                <button
                    v-for="p in players"
                    :key="p.steamid"
                    type="button"
                    class="ohm-chip"
                    :class="{ 'ohm-chip--active': selectedPlayers.includes(String(p.steamid)) }"
                    :aria-pressed="selectedPlayers.includes(String(p.steamid))"
                    @click="togglePlayer(String(p.steamid))"
                >
                    <span class="ohm-chip__dot" :style="{ background: playerColorMap[String(p.steamid)] }" />
                    {{ p.alias ?? p.name }}
                </button>
            </div>

            <div class="ohm-controls__meta">
                <div v-if="isFetching" class="ohm-spinner ohm-spinner--sm" />
                <span class="ohm-controls__count">{{ totalDemos }} demo{{ totalDemos !== 1 ? 's' : '' }}</span>
            </div>
        </div>

        <!-- Map area -->
        <div ref="containerRef" class="ohm-map">

            <div v-if="isLoading && !hasData" class="ohm-state">
                <div class="ohm-spinner" />
            </div>
            <div v-else-if="!isLoading && isDataError && !hasData" class="ohm-state">
                <div class="ohm-state__box ohm-state__box--error">
                    <p class="ohm-state__title">Could not load {{ activeMode }} data</p>
                    <p class="ohm-state__body">Check your connection and refresh the page</p>
                </div>
            </div>
            <div v-else-if="!isLoading && !hasData" class="ohm-state">
                <div class="ohm-state__box">
                    <p class="ohm-state__title">No {{ activeMode }} data yet</p>
                    <p class="ohm-state__body">Re-process demos to generate spatial data</p>
                </div>
            </div>

            <!-- Map image — centered with object-contain -->
            <img
                ref="mapImgRef"
                :src="'/images/demo/' + mapName + '.svg'"
                :alt="`${mapDisplayName} radar`"
                class="ohm-mapimg"
                :style="{
                    left: imgOffX + 'px', top: imgOffY + 'px',
                    width: imgW + 'px',   height: imgH + 'px',
                }"
                @load="onMapLoad"
            />

            <!-- Canvas — full container size, for heatmap modes -->
            <canvas
                v-show="isHeatmapMode"
                ref="canvasRef"
                class="ohm-canvas"
            />

            <!-- SVG overlay — exactly covers the map image -->
            <svg
                v-if="isMapLoaded && imgW > 0 && !isHeatmapMode"
                class="ohm-svg"
                :width="imgW"
                :height="imgH"
                :style="{ left: imgOffX + 'px', top: imgOffY + 'px' }"
            >

                <!-- Positions: zone polygons -->
                <template v-if="activeMode === 'positions'">
                    <g v-for="e in zoneOverlayEntries" :key="e.zone_id">
                        <polygon
                            :points="worldPolyToSvg(e.polygon)"
                            :fill="e.color + toHex(e.alpha)"
                            :stroke="e.color"
                            stroke-width="1.5"
                        />
                        <text
                            v-if="e.pct >= 3"
                            :x="e.cx"
                            :y="e.cy - 5"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            fill="oklch(1 0 0 / 0.96)"
                            font-size="9"
                            font-weight="800"
                            style="text-shadow:0 1px 4px oklch(0.04 0.01 250 / 0.7)"
                        >{{ e.zone_name }}</text>
                        <text
                            v-if="e.pct >= 3"
                            :x="e.cx"
                            :y="e.cy + 7"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            fill="oklch(1 0 0 / 0.65)"
                            font-size="7"
                            style="text-shadow:0 1px 4px oklch(0.04 0.01 250 / 0.7)"
                        >{{ e.pct }}%</text>
                    </g>
                </template>

                <!-- Paths: polylines per player per round -->
                <template v-if="activeMode === 'paths'">
                    <polyline
                        v-for="(path, i) in pathLines"
                        :key="i"
                        :points="path.points"
                        :stroke="path.color"
                        stroke-width="1.5"
                        fill="none"
                        stroke-opacity="0.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle
                        v-for="(path, i) in pathLines"
                        :key="'e' + i"
                        :cx="path.endX"
                        :cy="path.endY"
                        r="2.5"
                        :fill="path.color"
                        fill-opacity="0.8"
                    />
                </template>

                <!-- Entries: dots showing round-start positions -->
                <template v-if="activeMode === 'entries'">
                    <circle
                        v-for="(e, i) in entryPoints"
                        :key="i"
                        :cx="e.x"
                        :cy="e.y"
                        r="5"
                        :fill="e.color"
                        fill-opacity="0.7"
                        :stroke="e.color"
                        stroke-width="1"
                    />
                </template>

                <!-- Plants: bomb site markers -->
                <template v-if="activeMode === 'plants'">
                    <circle
                        v-for="(p, i) in plantPoints"
                        :key="i"
                        :cx="p.x"
                        :cy="p.y"
                        r="6"
                        :fill="plantFill"
                        :stroke="plantStroke"
                        stroke-width="1.5"
                    />
                    <text
                        v-for="(p, i) in plantPoints"
                        :key="'t' + i"
                        :x="p.x"
                        :y="p.y + 1"
                        text-anchor="middle"
                        dominant-baseline="middle"
                        fill="oklch(1 0 0 / 0.96)"
                        font-size="6"
                        font-weight="900"
                    >C4</text>
                </template>
            </svg>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import simpleheat from 'simpleheat';
import getScale from '@/scales.js';

const props = defineProps<{
    opponentId: number;
    mapName:    string;
    players:    any[];
    zones:      any[];
}>();

// ── Types ──────────────────────────────────────────────────────────────────────
type Mode = 'kills' | 'deaths' | 'positions' | 'paths' | 'utility' | 'shots' | 'plants' | 'entries';

// ── Canvas / SVG colour constants ──────────────────────────────────────────────
// The <canvas> heatmap (simpleheat) cannot read CSS custom properties, so these
// values are kept as raw hex/rgba — they are hand-tuned approximations of the
// OKLCH `--th-*` design tokens:
//   #ff8c00            → --th-accent          (saffron strike)
//   #7c8da3            → cold-steel grey
//   #6db77a            → --th-win             (sage win)
//   #e6b450            → --th-warn            (amber)
//   #c0533a            → --th-loss            (forge-rust / round-lost)
// If the token palette changes, update these approximations to match.
const PLAYER_COLORS = ['#ff8c00', '#7c8da3', '#6db77a', '#e6b450', '#c0533a'];
const plantFill     = 'rgba(192,83,58,0.55)';   // --th-loss @ 55% alpha
const plantStroke   = '#c0533a';                // --th-loss

// ── State ──────────────────────────────────────────────────────────────────────
const activeMode        = ref<Mode>('kills');
const activeSide        = ref<'CT' | 'T' | 'Both'>('CT');
const activeUtilityType = ref<'smoke' | 'molotov' | 'flash' | 'he' | 'decoy'>('smoke');
const selectedPlayers   = ref<string[]>([]);
const activeRound       = ref<number | null>(null);   // null = all rounds
const activeWeapon      = ref<string | null>(null);   // null = all weapons (shots mode)

const containerRef = ref<HTMLElement | null>(null);
const mapImgRef    = ref<HTMLImageElement | null>(null);
const canvasRef    = ref<HTMLCanvasElement | null>(null);
const isMapLoaded  = ref(false);
let   heatInstance: any = null;

// Image rendered dimensions and offset within the container
const imgW    = ref(0);
const imgH    = ref(0);
const imgOffX = ref(0);
const imgOffY = ref(0);
const scaleV  = ref(1);

const modes = [
    { id: 'kills',     label: 'Kills' },
    { id: 'deaths',    label: 'Deaths' },
    { id: 'positions', label: 'Positions' },
    { id: 'paths',     label: 'Paths' },
    { id: 'utility',   label: 'Utility' },
    { id: 'shots',     label: 'Shots' },
    { id: 'plants',    label: 'Plants' },
    { id: 'entries',   label: 'Spawns' },
];

const sides = [
    { id: 'CT',   label: 'CT',   tone: 'ct'   },
    { id: 'T',    label: 'T',    tone: 't'    },
    { id: 'Both', label: 'Both', tone: 'both' },
];

const utilityTypes = [
    { id: 'smoke',   label: 'Smokes' },
    { id: 'molotov', label: 'Molotovs' },
    { id: 'flash',   label: 'Flashes' },
    { id: 'he',      label: 'HE' },
    { id: 'decoy',   label: 'Decoys' },
];

const playerColorMap = computed<Record<string, string>>(() => {
    const m: Record<string, string> = {};
    props.players.forEach((p, i) => { m[String(p.steamid)] = PLAYER_COLORS[i % PLAYER_COLORS.length]; });
    return m;
});

const isHeatmapMode  = computed(() => ['kills', 'deaths', 'utility', 'shots'].includes(activeMode.value));
const showSideFilter = computed(() => ['positions', 'paths', 'entries'].includes(activeMode.value));

const togglePlayer = (sid: string) => {
    const idx = selectedPlayers.value.indexOf(sid);
    if (idx >= 0) selectedPlayers.value.splice(idx, 1);
    else selectedPlayers.value.push(sid);
};

const setMode = (mode: Mode) => {
    activeMode.value  = mode;
    activeRound.value = null;
    activeWeapon.value = null;
};

// ── Map display name ───────────────────────────────────────────────────────────
const MAP_DISPLAY: Record<string, string> = {
    de_overpass: 'Overpass', de_mirage: 'Mirage', de_dust2: 'Dust 2',
    de_inferno: 'Inferno', de_nuke: 'Nuke', de_ancient: 'Ancient',
    de_anubis: 'Anubis', de_vertigo: 'Vertigo',
};
const mapDisplayName = computed(() =>
    MAP_DISPLAY[props.mapName] ?? props.mapName.replace('de_', '')
);

// ── Roving-tabindex keyboard navigation for the in-component tablists ─────────
const cycleMode = (direction: 1 | -1) => {
    const idx = modes.findIndex(m => m.id === activeMode.value);
    setMode(modes[(idx + direction + modes.length) % modes.length].id as Mode);
};

const cycleSide = (direction: 1 | -1) => {
    const idx = sides.findIndex(s => s.id === activeSide.value);
    activeSide.value = sides[(idx + direction + sides.length) % sides.length].id as 'CT' | 'T' | 'Both';
};

const cycleUtilityType = (direction: 1 | -1) => {
    const idx = utilityTypes.findIndex(u => u.id === activeUtilityType.value);
    activeUtilityType.value =
        utilityTypes[(idx + direction + utilityTypes.length) % utilityTypes.length].id as typeof activeUtilityType.value;
};

// Available rounds from current spatial data
const availableRounds = computed((): number[] => {
    const d = spatialData.value?.data;
    if (!d) return [];
    const rounds = new Set<number>();

    if (Array.isArray(d)) {
        // flat array [{x,y,s,r,sid}] — entries/plants
        d.forEach((p: any) => { if (p.r != null) rounds.add(Number(p.r)); });
    } else {
        // dict — could be paths {sid: {round: [pts]}} or shots {sid: [{x,y,w,r}]}
        for (const val of Object.values(d) as any[]) {
            if (Array.isArray(val)) {
                // shots: {sid: [{x,y,w,r}]}
                val.forEach((p: any) => { if (p.r != null) rounds.add(Number(p.r)); });
            } else if (typeof val === 'object' && val !== null) {
                // paths: {sid: {roundNum: [pts]}}
                Object.keys(val).forEach(k => rounds.add(Number(k)));
            }
        }
    }
    return [...rounds].sort((a, b) => a - b);
});

// Available weapons from shots data
const availableWeapons = computed((): string[] => {
    if (activeMode.value !== 'shots') return [];
    const d = spatialData.value?.data ?? {};
    const weapons = new Set<string>();
    for (const pts of Object.values(d) as any[]) {
        for (const p of pts) if (p.w) weapons.add(p.w);
    }
    return [...weapons].sort();
});

// ── Data fetching ──────────────────────────────────────────────────────────────
const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const { data: heatmapData, isLoading: heatLoading, isError: heatError } = useQuery({
    queryKey: ['opponent-heatmap', computed(() => props.opponentId), computed(() => props.mapName)],
    queryFn:  async () => {
        const res = await fetch(
            `/api/opponent-teams/${props.opponentId}/heatmap?map=${props.mapName}`,
            { headers: { 'X-CSRF-TOKEN': getCsrf() } }
        );
        if (!res.ok) {
            console.error('[OpponentHeatmap] failed to load heatmap data', res.status, res.statusText);
            throw new Error(`Failed to load heatmap data (${res.status})`);
        }
        return res.json();
    },
    retry: 1,
    staleTime: 1000 * 60 * 10,
});

const spatialType = computed<string | null>(() =>
    isHeatmapMode.value || activeMode.value === 'positions' ? null : activeMode.value
);

const { data: spatialData, isFetching, isError: spatialError } = useQuery({
    queryKey: computed(() => ['opponent-spatial', props.opponentId, props.mapName, spatialType.value]),
    queryFn:  async () => {
        if (!spatialType.value) return null;
        const res = await fetch(
            `/api/opponent-teams/${props.opponentId}/spatial?map=${props.mapName}&type=${spatialType.value}`,
            { headers: { 'X-CSRF-TOKEN': getCsrf() } }
        );
        if (!res.ok) {
            console.error('[OpponentHeatmap] failed to load spatial data', res.status, res.statusText);
            throw new Error(`Failed to load spatial data (${res.status})`);
        }
        return res.json();
    },
    enabled:   computed(() => !!spatialType.value),
    retry: 1,
    staleTime: 1000 * 60 * 10,
});

const totalDemos = computed(() => heatmapData.value?.total_demos ?? spatialData.value?.total_demos ?? 0);
const isLoading  = computed(() => heatLoading.value);

// True when the data fetch for the active mode failed — lets the map area show a
// distinct error state instead of the identical "no data yet" empty state.
const isDataError = computed(() => {
    if (isHeatmapMode.value || activeMode.value === 'positions') return heatError.value;
    return spatialError.value;
});

const hasData = computed(() => {
    if (activeMode.value === 'kills')     return !!Object.keys(heatmapData.value?.kill_data ?? {}).length;
    if (activeMode.value === 'deaths')    return !!Object.keys(heatmapData.value?.death_data ?? {}).length;
    if (activeMode.value === 'positions') return !!heatmapData.value?.position_dominance?.length;
    const d = spatialData.value?.data;
    if (!d) return false;
    if (activeMode.value === 'utility') return !!(d[activeUtilityType.value]?.length);
    if (activeMode.value === 'paths')   return Object.keys(d).length > 0;
    return Array.isArray(d) ? d.length > 0 : Object.keys(d).length > 0;
});

// ── Map dimensions ─────────────────────────────────────────────────────────────
const mapScale = computed(() => getScale(props.mapName));

const updateDimensions = () => {
    const container = containerRef.value;
    const img       = mapImgRef.value;
    if (!container || !img) return;

    const cw = container.clientWidth;
    const ch = container.clientHeight;
    if (!cw || !ch) return;

    const nw = img.naturalWidth  || 1024;
    const nh = img.naturalHeight || 1024;
    const r  = Math.min(cw / nw, ch / nh);

    scaleV.value  = r;
    imgW.value    = Math.round(nw * r);
    imgH.value    = Math.round(nh * r);
    imgOffX.value = Math.round((cw - imgW.value) / 2);
    imgOffY.value = Math.round((ch - imgH.value) / 2);

    if (canvasRef.value) {
        canvasRef.value.width  = cw;
        canvasRef.value.height = ch;
        // Resizing the canvas resets its backing store and invalidates
        // simpleheat's cached width/height — drop the instance so render()
        // re-instantiates it against the freshly sized canvas.
        if (heatCanvasEl === canvasRef.value) {
            heatInstance = null;
            heatCanvasEl = null;
        }
    }
    render();
};

const onMapLoad = () => { isMapLoaded.value = true; nextTick(updateDimensions); };

onMounted(() => {
    window.addEventListener('resize', updateDimensions);
    nextTick(() => {
        if (mapImgRef.value?.complete && mapImgRef.value.naturalWidth > 0) {
            isMapLoaded.value = true;
            updateDimensions();
        }
    });
});

// ── Coordinate transforms ──────────────────────────────────────────────────────
// All transforms produce coordinates relative to the top-left of the MAP IMAGE.
// The SVG is positioned at (imgOffX, imgOffY) so its 0,0 = image top-left.
// The canvas is full-container so we add imgOff for canvas coords.

const worldToImgX = (wx: number) => ((wx - mapScale.value.pos_x) / mapScale.value.scale) * scaleV.value;
const worldToImgY = (wy: number) => ((mapScale.value.pos_y - wy) / mapScale.value.scale) * scaleV.value;

// For SVG (coordinates relative to image origin — no offset needed)
const worldToSvgX = (wx: number) => worldToImgX(wx);
const worldToSvgY = (wy: number) => worldToImgY(wy);

// For canvas (coordinates relative to full container — add image offset)
const toCanvas = (x: number, y: number) => ({
    x: imgOffX.value + worldToImgX(x),
    y: imgOffY.value + worldToImgY(y),
});

const worldPolyToSvg = (polygon: any[]) => {
    if (!polygon?.length || !imgW.value) return '';
    return polygon.map((p: any) => {
        const wx = Array.isArray(p) ? p[0] : p.x;
        const wy = Array.isArray(p) ? p[1] : p.y;
        return `${worldToSvgX(wx).toFixed(1)},${worldToSvgY(wy).toFixed(1)}`;
    }).join(' ');
};

const toHex = (alpha: number) => Math.round(alpha * 255).toString(16).padStart(2, '0');

// ── Zone overlay (positions mode) ──────────────────────────────────────────────
const zonesByMap = computed(() => (props.zones as any[]).filter(z => z.map_key === props.mapName));

const zoneOverlayEntries = computed(() => {
    if (activeMode.value !== 'positions' || !imgW.value) return [];
    const dominance = heatmapData.value?.position_dominance ?? [];
    const filterSids = selectedPlayers.value.length > 0 ? new Set(selectedPlayers.value) : null;

    const zoneTicks: Record<number, { ticks: number; zone_name: string; color: string }> = {};
    let totalTicks = 0;

    for (const player of dominance) {
        if (filterSids && !filterSids.has(String(player.steamid))) continue;
        const sides = activeSide.value === 'Both'
            ? [...(player.CT ?? []), ...(player.T ?? [])]
            : (player[activeSide.value] ?? []);
        for (const z of sides) {
            if (!zoneTicks[z.zone_id]) {
                const zd = zonesByMap.value.find((zz: any) => zz.id === z.zone_id);
                zoneTicks[z.zone_id] = { ticks: 0, zone_name: z.zone_name, color: zd?.color ?? '#ff8c00' };
            }
            zoneTicks[z.zone_id].ticks += z.ticks;
            totalTicks += z.ticks;
        }
    }

    if (!totalTicks) return [];

    return Object.entries(zoneTicks).map(([zid, data]) => {
        const id      = Number(zid);
        const pct     = Math.round(data.ticks / totalTicks * 1000) / 10;
        const polygon = zonesByMap.value.find((z: any) => z.id === id)?.polygon ?? [];
        const pts     = polygon.map((p: any) => ({
            x: worldToSvgX(Array.isArray(p) ? p[0] : p.x),
            y: worldToSvgY(Array.isArray(p) ? p[1] : p.y),
        }));
        return {
            zone_id: id, zone_name: data.zone_name, color: data.color, pct,
            alpha:   Math.min(0.85, 0.08 + (pct / 100) * 0.9),
            polygon,
            cx: pts.length ? pts.reduce((s, p) => s + p.x, 0) / pts.length : 0,
            cy: pts.length ? pts.reduce((s, p) => s + p.y, 0) / pts.length : 0,
        };
    }).filter(e => e.polygon.length >= 3).sort((a, b) => a.pct - b.pct);
});

// ── Path lines ────────────────────────────────────────────────────────────────
const pathLines = computed(() => {
    if (activeMode.value !== 'paths' || !imgW.value) return [];
    const raw = spatialData.value?.data ?? {};
    const filterSids = selectedPlayers.value.length > 0 ? new Set(selectedPlayers.value) : null;
    const lines: any[] = [];

    for (const [sid, rounds] of Object.entries(raw) as any[]) {
        if (filterSids && !filterSids.has(String(sid))) continue;
        const color = playerColorMap.value[String(sid)] ?? '#ff8c00';

        for (const [rKey, pts] of Object.entries(rounds) as any[]) {
            if (activeRound.value !== null && Number(rKey) !== activeRound.value) continue;
            const filtered = (pts as any[]).filter((p: any) =>
                activeSide.value === 'Both' ||
                (activeSide.value === 'CT' && p.s === 'c') ||
                (activeSide.value === 'T'  && p.s === 't')
            );
            if (filtered.length < 2) continue;

            const svgPts = filtered.map((p: any) =>
                `${worldToSvgX(p.x).toFixed(1)},${worldToSvgY(p.y).toFixed(1)}`
            );
            const last = filtered[filtered.length - 1];
            lines.push({
                points: svgPts.join(' '),
                color,
                endX: worldToSvgX(last.x),
                endY: worldToSvgY(last.y),
            });
        }
    }
    return lines;
});

// ── Entry / plant points ──────────────────────────────────────────────────────
// entries data is a flat array: [{x, y, s, r, sid}]
const entryPoints = computed(() => {
    if (activeMode.value !== 'entries' || !imgW.value) return [];
    const raw = spatialData.value?.data;
    if (!raw) return [];
    const filterSids = selectedPlayers.value.length > 0 ? new Set(selectedPlayers.value) : null;
    const points: any[] = [];

    // Handle both flat array [{x,y,s,r,sid}] and dict {steamid: [{x,y,s,r}]}
    const items: any[] = Array.isArray(raw)
        ? raw
        : Object.entries(raw).flatMap(([sid, pts]: any) =>
            (pts as any[]).map((p: any) => ({ ...p, sid }))
          );

    for (const p of items) {
        if (filterSids && !filterSids.has(String(p.sid ?? ''))) continue;
        if (activeSide.value !== 'Both' && p.s !== activeSide.value[0].toLowerCase()) continue;
        if (activeRound.value !== null && p.r !== activeRound.value) continue;
        const color = playerColorMap.value[String(p.sid ?? '')] ?? '#ff8c00';
        points.push({ x: worldToSvgX(p.x), y: worldToSvgY(p.y), color });
    }
    return points;
});

const plantPoints = computed(() => {
    if (activeMode.value !== 'plants' || !imgW.value) return [];
    const raw = spatialData.value?.data ?? [];
    const filterSids = selectedPlayers.value.length > 0 ? new Set(selectedPlayers.value) : null;
    return (Array.isArray(raw) ? raw : [])
        .filter((p: any) => !filterSids || filterSids.has(String(p.sid ?? '')))
        .map((p: any) => ({ x: worldToSvgX(p.x), y: worldToSvgY(p.y) }));
});

// ── Canvas heatmap render ──────────────────────────────────────────────────────
// simpleheat is instantiated once per canvas element and cached. Re-creating it
// on every render() call is wasteful (it allocates an offscreen gradient canvas);
// we only rebuild the instance if the underlying <canvas> element itself changed.
let heatCanvasEl: HTMLCanvasElement | null = null;

const getHeatInstance = (canvas: HTMLCanvasElement) => {
    if (!heatInstance || heatCanvasEl !== canvas) {
        heatInstance = simpleheat(canvas);
        heatCanvasEl = canvas;
    }
    return heatInstance;
};

const render = () => {
    if (!isHeatmapMode.value) return;
    const canvas = canvasRef.value;
    if (!canvas || !isMapLoaded.value) return;
    if (canvas.width === 0 || canvas.height === 0) return;

    heatInstance = getHeatInstance(canvas);
    const filterSids = selectedPlayers.value.length > 0 ? new Set(selectedPlayers.value) : null;
    const points: [number, number, number][] = [];

    if (activeMode.value === 'kills' || activeMode.value === 'deaths') {
        const key  = activeMode.value === 'kills' ? 'kill_data' : 'death_data';
        const data = heatmapData.value?.[key] ?? {};
        for (const [sid, rounds] of Object.entries(data) as any[]) {
            if (filterSids && !filterSids.has(String(sid))) continue;
            for (const pts of Object.values(rounds) as any[])
                for (const pt of pts) {
                    const { x, y } = toCanvas(pt.x, pt.y);
                    points.push([x, y, pt.value ?? 1]);
                }
        }
        heatInstance.radius(12, 20); heatInstance.max(3);
    }

    if (activeMode.value === 'utility') {
        const data = spatialData.value?.data?.[activeUtilityType.value] ?? [];
        for (const pt of data) {
            if (filterSids && !filterSids.has(String(pt.sid ?? ''))) continue;
            const { x, y } = toCanvas(pt.x, pt.y);
            points.push([x, y, 1]);
        }
        heatInstance.radius(20, 30); heatInstance.max(2);
    }

    if (activeMode.value === 'shots') {
        const data = spatialData.value?.data ?? {};
        for (const [sid, pts] of Object.entries(data) as any[]) {
            if (filterSids && !filterSids.has(String(sid))) continue;
            for (const pt of (pts as any[])) {
                if (activeRound.value !== null && pt.r !== activeRound.value) continue;
                if (activeWeapon.value && pt.w !== activeWeapon.value) continue;
                const { x, y } = toCanvas(pt.x, pt.y);
                points.push([x, y, 1]);
            }
        }
        heatInstance.radius(10, 15); heatInstance.max(4);
    }

    if (!points.length) return;
    heatInstance.data(points);
    heatInstance.draw(0.05);
};

// ── Watchers ───────────────────────────────────────────────────────────────────
watch([heatmapData, spatialData, activeMode, activeSide, activeUtilityType, selectedPlayers, activeRound, activeWeapon],
    () => nextTick(render), { deep: true });
watch(isMapLoaded, (loaded) => { if (loaded) nextTick(render); });
watch(imgW, (w) => { if (w > 0) nextTick(render); });
watch(() => props.mapName, () => { isMapLoaded.value = false; imgW.value = 0; });

onBeforeUnmount(() => window.removeEventListener('resize', updateDimensions));
</script>

<style scoped>
.ohm {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--th-bg, oklch(0.125 0.012 250));
    user-select: none;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Controls ── */
.ohm-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    flex-shrink: 0;
    flex-wrap: wrap;
}

.ohm-controls__meta {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ohm-controls__count {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

/* ── Segmented control ── */
.ohm-seg {
    display: flex;
    gap: 3px;
    padding: 3px;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    border-radius: 6px;
}

.ohm-seg__btn {
    display: inline-flex;
    align-items: center;
    padding: 9px 12px;
    min-height: 32px;
    border-radius: 4px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    font-weight: 600;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: none;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: color 150ms ease, background 150ms ease;
}

.ohm-seg__btn:hover { color: var(--th-text-2, oklch(1 0 0 / 0.70)); }

.ohm-seg__btn--active {
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 18%, transparent);
    color: var(--th-accent, #ff8c00);
}

.ohm-seg__btn--ct {
    background: color-mix(in oklch, var(--th-accent-2, oklch(0.62 0.20 50)) 24%, transparent);
    color: var(--th-accent-2, oklch(0.62 0.20 50));
}

.ohm-seg__btn--t {
    background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 20%, transparent);
    color: var(--th-warn, oklch(0.78 0.15 80));
}

.ohm-seg__btn--both {
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 18%, transparent);
    color: var(--th-accent, #ff8c00);
}

.ohm-seg__btn:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

/* ── Filter chips ── */
.ohm-filter {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.ohm-filter__label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

.ohm-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 7px 10px;
    min-height: 32px;
    min-width: 32px;
    border-radius: 4px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    cursor: pointer;
    transition: border-color 150ms ease, color 150ms ease, background 150ms ease;
}

.ohm-chip:hover { border-color: var(--th-border-3, oklch(1 0 0 / 0.22)); }

.ohm-chip--cap { text-transform: capitalize; }

.ohm-chip--active {
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 18%, transparent);
    border-color: color-mix(in oklch, var(--th-accent, #ff8c00) 40%, transparent);
    color: var(--th-accent, #ff8c00);
}

.ohm-chip:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

.ohm-chip__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Map area ── */
.ohm-map {
    flex: 1;
    position: relative;
    overflow: hidden;
}

.ohm-mapimg {
    position: absolute;
    pointer-events: none;
    opacity: 0.4;
}

.ohm-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.ohm-svg {
    position: absolute;
    pointer-events: none;
    overflow: visible;
}

/* ── States ── */
.ohm-state {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.ohm-state__box { text-align: center; }

.ohm-state__box--error {
    padding: 12px 16px;
    border-radius: 6px;
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 10%, var(--th-surface, oklch(0.175 0.014 250)));
    border: 1px solid color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 32%, transparent);
}

.ohm-state__title {
    font-size: 13px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 0;
}

.ohm-state__box--error .ohm-state__title { color: var(--th-loss, oklch(0.62 0.20 22)); }

.ohm-state__body {
    font-size: 11px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 4px 0 0;
}

.ohm-spinner {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid color-mix(in oklch, var(--th-accent, #ff8c00) 20%, transparent);
    border-top-color: var(--th-accent, #ff8c00);
    animation: ohm-spin 0.8s linear infinite;
}

.ohm-spinner--sm {
    width: 12px;
    height: 12px;
    border-width: 1.5px;
}

@keyframes ohm-spin {
    to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .ohm-spinner { animation: none; }
}
</style>
