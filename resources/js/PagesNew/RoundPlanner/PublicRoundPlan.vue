<template>
    <div class="fixed inset-0 bg-[oklch(0.04_0.01_250)] flex flex-col">
        <!-- Minimal chrome — this is a public, read-only surface. -->
        <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--th-border)] flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="material-symbols text-[var(--th-accent)] text-lg">route</span>
                <h1 class="text-sm font-bold text-[var(--th-text)]">{{ planData?.name ?? 'Round plan' }}</h1>
                <span v-if="planData?.side" class="text-[10px] font-semibold px-2 py-0.5 rounded border" :class="planData.side === 'T' ? 'bg-[var(--th-t)]/10 text-[var(--th-t)] border-[var(--th-t)]/30' : 'bg-[var(--th-ct)]/10 text-[var(--th-ct)] border-[var(--th-ct)]/30'">{{ planData.side }}</span>
                <span class="text-[10px] text-[var(--th-text-4)] ml-1">read-only</span>
            </div>
            <div class="flex items-center gap-1">
                <button class="w-7 h-7 flex items-center justify-center text-[var(--th-text-3)] hover:text-[var(--th-text)] rounded-lg transition-colors" title="Zoom in" @click="zoomBy(0.15)"><span class="material-symbols" style="font-size:14px">add</span></button>
                <button class="w-7 h-7 flex items-center justify-center text-[var(--th-text-3)] hover:text-[var(--th-text)] rounded-lg transition-colors" title="Reset zoom" @click="resetZoom"><span class="material-symbols" style="font-size:14px">center_focus_strong</span></button>
                <button class="w-7 h-7 flex items-center justify-center text-[var(--th-text-3)] hover:text-[var(--th-text)] rounded-lg transition-colors" title="Zoom out" @click="zoomBy(-0.15)"><span class="material-symbols" style="font-size:14px">remove</span></button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center gap-3 text-[var(--th-text-3)]">
            <span class="material-symbols text-[var(--th-accent)] text-3xl animate-spin">autorenew</span>
            <p class="text-sm">Loading plan…</p>
        </div>

        <!-- Error -->
        <div v-else-if="loadError" class="flex-1 flex flex-col items-center justify-center gap-3 text-center px-6">
            <span class="material-symbols text-[var(--th-text-4)] text-4xl">link_off</span>
            <p class="text-sm font-semibold text-[var(--th-text-2)]">This link is no longer valid.</p>
            <p class="text-xs text-[var(--th-text-4)] max-w-sm">The owner disabled sharing, or the plan was deleted. Ask them for a new link.</p>
        </div>

        <!-- Body -->
        <div v-else-if="planData" class="flex flex-col flex-1 overflow-hidden">
            <!-- Player chips -->
            <div class="flex items-center gap-1.5 px-4 py-2.5 border-b border-[var(--th-border)] flex-wrap flex-shrink-0">
                <button
                    v-for="(player, slot) in players"
                    :key="slot"
                    class="flex items-center gap-1.5 px-3 py-1 rounded-lg border text-xs font-semibold transition-all"
                    :class="focusedSlot === slot ? 'border-[var(--th-text)]/20 bg-[var(--th-text)]/5 text-[var(--th-text)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)]'"
                    @click="focusedSlot = focusedSlot === slot ? -1 : slot"
                >
                    <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: player.color }" />
                    <span>{{ player.name }}</span>
                    <span v-if="player.role && player.role !== 'none'" class="text-[10px] font-semibold px-1 py-0.5 rounded" :style="{ background: player.color + '22', color: player.color }">{{ getRoleLabel(player.role) }}</span>
                </button>
            </div>

            <!-- Canvas -->
            <div
                id="canvas-container"
                class="flex-1 relative overflow-hidden bg-[var(--th-bg)] min-h-[200px]"
                :style="{ cursor: isPanning ? 'grabbing' : 'grab' }"
                @mousedown="onMouseDown"
                @mousemove="onMouseMove"
                @mouseup="onMouseUp"
                @mouseleave="onMouseLeave"
                @wheel.prevent="onWheel"
            >
                <canvas id="mapCanvas"  class="absolute inset-0 w-full h-full" style="z-index:1" />
                <canvas id="gameCanvas" class="absolute inset-0 w-full h-full" style="z-index:2" />

                <!-- Instruction bubbles -->
                <template v-for="(player, slot) in players" :key="'b' + slot">
                    <transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition-all duration-150" leave-to-class="opacity-0 -translate-y-1">
                        <div v-if="getPlayerInstructionAtTime(slot, currentSecond)" class="absolute pointer-events-none" style="z-index:10; transform:translateX(-50%)" :style="getBubbleStyle(slot)">
                            <div class="bg-[var(--th-bg)]/90 border rounded-xl px-3 py-1.5 backdrop-blur-sm flex flex-col gap-0.5 max-w-[150px]" :style="{ borderColor: player.color }">
                                <span class="text-[10px] font-semibold" :style="{ color: player.color }">{{ player.name }}</span>
                                <span class="text-[11px] text-[var(--th-text-2)] leading-snug">{{ getPlayerInstructionAtTime(slot, currentSecond)?.text }}</span>
                            </div>
                        </div>
                    </transition>
                </template>
            </div>

            <!-- Timeline -->
            <div class="h-14 bg-[var(--th-surface)] border-t border-[var(--th-border)] flex items-center gap-2 px-3 flex-shrink-0">
                <button class="w-9 h-9 rounded-full bg-[var(--th-surface-2)] border border-[var(--th-border-2)] text-[var(--th-accent)] flex items-center justify-center hover:bg-[var(--th-surface-3)] transition-colors flex-shrink-0" :aria-label="isPlaying ? 'Pause' : 'Play'" @click="togglePlayback"><span class="material-symbols text-xl">{{ isPlaying ? 'pause' : 'play_arrow' }}</span></button>
                <button class="text-[11px] font-semibold text-[var(--th-text-3)] hover:text-[var(--th-text)] bg-transparent border-none cursor-pointer w-7 flex-shrink-0 transition-colors" aria-label="Cycle playback speed" @click="togglePlaybackSpeed">{{ playbackSpeed }}x</button>
                <div ref="scrubRef" class="flex-1 h-2 bg-[var(--th-surface-2)] rounded-full overflow-visible cursor-pointer relative" @mousedown="startScrubDrag" @mousemove="onScrubHover" @mouseleave="scrubHoverX = null">
                    <div class="absolute inset-0 overflow-hidden rounded-full">
                        <div class="absolute top-0 left-0 h-full bg-[var(--th-accent)]/40 rounded-full" :style="{ width: `${(currentSecond / duration) * 100}%` }" />
                        <div class="absolute top-1/2 w-2.5 h-2.5 rounded-full bg-[var(--th-accent)] -translate-y-1/2 -translate-x-1/2 pointer-events-none" style="box-shadow:0 0 6px #FF8C00" :style="{ left: `${(currentSecond / duration) * 100}%` }" />
                    </div>
                    <div v-if="scrubHoverX !== null" class="absolute -top-7 pointer-events-none z-20 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1.5 py-0.5 text-[10px] font-semibold text-[var(--th-text)] whitespace-nowrap tabular-nums" style="transform:translateX(-50%)" :style="{ left: scrubHoverX + 'px' }">{{ fmt(scrubHoverSeconds) }}</div>
                </div>
                <span class="text-[11px] font-semibold text-[var(--th-text-3)] tabular-nums min-w-[64px] flex-shrink-0">{{ fmt(currentSecond) }} / {{ fmt(duration) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useCanvas }               from '@/PagesNew/Demo/2D/composables/useCanvas';
import { useRoundPlanner, PLAYER_ROLES } from './composables/useRoundPlanner';
import { useRoundPlannerRenderer } from './composables/useRoundPlannerRenderer';

const route = useRoute();
const token = computed(() => String(route.params.token ?? ''));

const isLoading   = ref(true);
const loadError   = ref(false);
const planData    = ref<any>(null);
const focusedSlot = ref(-1);
const scrubRef    = ref<HTMLDivElement | null>(null);
const scrubHoverX = ref<number | null>(null);

function getRoleLabel(key: string) { return PLAYER_ROLES.find(r => r.key === key)?.label ?? key; }

const mapMetadataRef = ref<any>(null);
const mapNameRef     = ref<string | null>(null);
const demoProxy      = ref<any>({ map_metadata: null });
const { cachedImage, mapPosition, getCanvases, toGameCoords } =
    useCanvas({ mapMetadata: mapMetadataRef, demo: demoProxy });

function _drawCachedMap() {
    const { mapCanvas, mapCtx, gameCanvas } = getCanvases();
    const container = document.getElementById('canvas-container');
    if (!container || !mapCanvas || !mapCtx || !cachedImage.value) return;
    mapCanvas.width = container.clientWidth; mapCanvas.height = container.clientHeight;
    if (gameCanvas) { gameCanvas.width = container.clientWidth; gameCanvas.height = container.clientHeight; }
    const img = cachedImage.value;
    const x = Math.floor((container.offsetWidth - img.width) / 2);
    const y = Math.floor((container.offsetHeight - img.height) / 2);
    mapPosition.value = { x, y, scale: (mapPosition.value as any).scale || 1 };
    mapCtx.setTransform(1, 0, 0, 1, 0, 0);
    mapCtx.clearRect(0, 0, mapCanvas.width, mapCanvas.height);
    mapCtx.setTransform(1, 0, 0, 1, x, y);
    mapCtx.drawImage(img, 0, 0);
}

async function loadAndDrawMap(path: string) {
    await new Promise<void>(resolve => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.src = path + (path.includes('?') ? '&' : '?') + 'cb=' + Date.now();
        img.onerror = () => resolve();
        img.onload  = () => { cachedImage.value = img; _drawCachedMap(); resolve(); };
    });
}

const {
    players, duration, currentSecond, isPlaying, playbackSpeed,
    togglePlayback, togglePlaybackSpeed, seekTo,
    getPlayerPosAtTime, getPlayerInstructionAtTime,
    pause, deserialize,
} = useRoundPlanner({ mapMetadata: mapMetadataRef });

const { render: rendererRender } = useRoundPlannerRenderer({
    getCanvases,
    toGameCoords: (x: number, y: number) => toGameCoords(x, y) as any,
    mapPosition: mapPosition as any,
    cachedImage,
});

// ── Pan + zoom ────────────────────────────────────────────────────────────────
const isPanning = ref(false);
let _panActive = false;
function onMouseDown() { _panActive = true; isPanning.value = true; }
function onMouseMove(e: MouseEvent) {
    if (!_panActive) return;
    const mp = mapPosition.value as any;
    mapPosition.value = { ...mp, x: mp.x + e.movementX, y: mp.y + e.movementY };
    triggerRender();
}
function onMouseUp()    { _panActive = false; isPanning.value = false; }
function onMouseLeave() { _panActive = false; isPanning.value = false; }
function onWheel(e: WheelEvent) {
    const d = e.deltaY > 0 ? -0.1 : 0.1;
    const mp = mapPosition.value as any;
    const next = Math.min(6, Math.max(0.2, mp.scale + d)), z = next / mp.scale;
    const el = document.getElementById('canvas-container'), rect = el?.getBoundingClientRect();
    const ox = e.clientX - (rect?.left ?? 0), oy = e.clientY - (rect?.top ?? 0);
    mapPosition.value = { x: ox - (ox - mp.x) * z, y: oy - (oy - mp.y) * z, scale: next };
    triggerRender();
}
function zoomBy(delta: number) {
    const mp = mapPosition.value as any;
    const el = document.getElementById('canvas-container');
    const cx = (el?.clientWidth ?? 0) / 2, cy = (el?.clientHeight ?? 0) / 2;
    const next = Math.min(6, Math.max(0.2, mp.scale + delta)), z = next / mp.scale;
    mapPosition.value = { x: cx - (cx - mp.x) * z, y: cy - (cy - mp.y) * z, scale: next };
    triggerRender();
}
function resetZoom() { _drawCachedMap(); triggerRender(); }

// ── Load ──────────────────────────────────────────────────────────────────────
async function loadPlan() {
    isLoading.value = true;
    try {
        const r = await fetch('/api/public/round-plans/' + token.value);
        if (!r.ok) { loadError.value = true; isLoading.value = false; return; }
        const data = await r.json();
        planData.value = data;

        // Resolve the map — fetch the list, match by name, then load metadata + SVG.
        if (data.map) {
            try {
                const mr = await fetch('/api/maps');
                if (mr.ok) {
                    const mapList: any[] = await mr.json();
                    const match = mapList.find((m: any) =>
                        m.name === data.map ||
                        m.display_name === data.map ||
                        m.name?.toLowerCase() === data.map?.toLowerCase() ||
                        m.display_name?.toLowerCase() === data.map?.toLowerCase());
                    if (match) {
                        const detailRes = await fetch('/api/maps/' + match.id);
                        if (detailRes.ok) {
                            const detail = await detailRes.json();
                            mapMetadataRef.value = detail.map_metadata ?? null;
                            demoProxy.value.map_metadata = detail.map_metadata ?? null;
                            mapNameRef.value = detail.name ?? null;
                            await new Promise<void>(resolve => {
                                const img = new Image();
                                img.crossOrigin = 'anonymous';
                                img.src = '/images/demo/' + detail.name + '.svg';
                                img.onerror = () => resolve();
                                img.onload  = () => { cachedImage.value = img; _drawCachedMap(); resolve(); };
                            });
                        }
                    }
                }
            } catch (e) { console.warn('Public plan: map load failed', e); }
        }

        deserialize(data.data ?? data);
        isLoading.value = false;
        await nextTick();
        triggerRender();
    } catch (e) {
        console.error('Public plan load failed', e);
        loadError.value = true;
        isLoading.value = false;
    }
}

// ── Render loop ───────────────────────────────────────────────────────────────
let _raf: number | null = null, _loop: number | null = null;
function triggerRender() {
    if (_raf !== null) return;
    _raf = requestAnimationFrame(() => {
        _raf = null;
        rendererRender({
            players: players.value,
            selectedSlot: focusedSlot.value >= 0 ? focusedSlot.value : 0,
            currentSecond: currentSecond.value,
            getPlayerPosAtTime,
            getActiveUtilsAtTime: () => [],
            activeMode: 'none',
            pendingUtilThrow: null,
            pendingCursorPos: null,
            pendingUtilThrowAt: null,
            isPlayback: true,
            draggingWaypoint: null,
            hoveredWaypoint: null,
            planName: planData.value?.name ?? '',
            side: planData.value?.side ?? 'T',
            mapMetadata: mapMetadataRef.value,
        });
    });
}
watch(isPlaying, (playing: boolean) => {
    if (playing) { const loop = () => { triggerRender(); if (isPlaying.value) _loop = requestAnimationFrame(loop); }; loop(); }
    else { if (_loop !== null) cancelAnimationFrame(_loop); triggerRender(); }
});
watch(currentSecond, triggerRender);

// ── Scrub ─────────────────────────────────────────────────────────────────────
const scrubHoverSeconds = computed(() => {
    if (scrubHoverX.value === null || !scrubRef.value) return 0;
    const rect = scrubRef.value.getBoundingClientRect();
    return Math.max(0, Math.min(duration.value, (scrubHoverX.value / rect.width) * duration.value));
});
function startScrubDrag(e: MouseEvent) { _seek(e); const move = (ev: MouseEvent) => _seek(ev); const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); }; window.addEventListener('mousemove', move); window.addEventListener('mouseup', up); }
function _seek(e: MouseEvent) { if (!scrubRef.value) return; const rect = scrubRef.value.getBoundingClientRect(); seekTo(((e.clientX - rect.left) / rect.width) * duration.value); }
function onScrubHover(e: MouseEvent) { if (!scrubRef.value) return; scrubHoverX.value = e.clientX - scrubRef.value.getBoundingClientRect().left; }

function getBubbleStyle(slot: number) {
    const pos = getPlayerPosAtTime(slot, currentSecond.value);
    if (!pos) return { display: 'none' };
    const gc = toGameCoords(pos.x, pos.y) as any, mp = mapPosition.value as any;
    return { left: (gc.x * mp.scale + mp.x) + 'px', top: (gc.y * mp.scale + mp.y - 55) + 'px' };
}

function fmt(s: number) { return Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0'); }

onMounted(() => { loadPlan(); });
onUnmounted(() => {
    pause();
    if (_raf !== null) cancelAnimationFrame(_raf);
    if (_loop !== null) cancelAnimationFrame(_loop);
});
</script>

<style scoped>
:root {
    --th-bg:        oklch(0.125 0.012 250);
    --th-surface:   oklch(0.175 0.014 250);
    --th-surface-2: oklch(0.215 0.016 250);
    --th-border:    oklch(1 0 0 / 0.09);
    --th-border-2:  oklch(1 0 0 / 0.14);
    --th-text:      oklch(1 0 0 / 0.96);
    --th-text-2:    oklch(1 0 0 / 0.70);
    --th-text-3:    oklch(1 0 0 / 0.62);
    --th-text-4:    oklch(1 0 0 / 0.50);
    --th-accent:    #ff8c00;
    --th-t:         oklch(0.76 0.13 75);
    --th-ct:        oklch(0.66 0.13 250);
}
@media (prefers-reduced-motion: reduce) {
    .animate-spin { animation-duration: 2s !important; }
    .transition-all { transition: none !important; }
}
</style>
