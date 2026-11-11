<template>
    <div class="h-full bg-neutral-950 text-white p-4 flex flex-col overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <router-link to="/tactics" class="text-neutral-500 hover:text-white text-sm transition-colors">← Back</router-link>
                    <span class="text-neutral-700">/</span>
                    <span class="text-neutral-400 text-sm">Admin</span>
                    <span class="text-neutral-700">/</span>
                    <span class="text-white text-sm font-bold">Callout Zones</span>
                </div>
                <h1 class="text-2xl font-black">Callout Zone Editor</h1>
                <p class="text-neutral-500 text-sm mt-0.5">World coords · Layers control render order · Zone type drives auto-tag priority</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="resetView" class="px-3 py-2 bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-bold rounded-lg border border-neutral-700">Reset View</button>
                <select v-model="selectedMapId" @change="onMapChange"
                    class="bg-neutral-800 border border-neutral-700 text-white px-3 py-2 rounded-lg text-sm outline-none focus:border-orange-500">
                    <option v-for="map in maps" :key="map.id" :value="map.id">{{ map.display_name }}</option>
                </select>
                <button @click="saveAll" :disabled="saving"
                    class="px-5 py-2 bg-orange-500 hover:bg-orange-400 disabled:opacity-50 text-white text-sm font-black uppercase tracking-wider rounded-lg">
                    {{ saving ? 'Saving...' : 'Save All' }}
                </button>
            </div>
        </div>

        <div v-if="loadingMaps" class="flex-1 flex items-center justify-center text-neutral-500">Loading maps...</div>

        <div v-else class="flex gap-4 flex-1 overflow-hidden min-w-0">

            <!-- ── Map canvas (grows) ── -->
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden flex flex-col flex-1 min-w-0">

                <!-- Toolbar -->
                <div class="px-4 py-3 border-b border-neutral-800 flex items-center gap-3 flex-shrink-0">
                    <span v-if="drawingMode" class="px-2 py-0.5 bg-orange-500/20 border border-orange-500/40 rounded text-orange-400 text-[10px] font-black uppercase tracking-wider">
                        ✏️ Drawing — {{ currentPoints.length }} pts
                    </span>
                    <span v-else class="text-xs text-neutral-500 font-bold uppercase tracking-widest">{{ zoom.toFixed(1) }}× zoom</span>
                    <span class="text-xs text-neutral-600">
                        {{ drawingMode ? 'Click to add · Double-click to finish · Clicks go through existing zones' : 'Scroll to zoom · Drag to pan · Click zone to select' }}
                    </span>
                    <div class="ml-auto flex gap-2">
                        <button v-if="drawingMode && currentPoints.length >= 3" @click="finishZone"
                            class="px-3 py-1 bg-green-500 hover:bg-green-400 text-white text-xs font-bold rounded-lg">Finish</button>
                        <button v-if="drawingMode" @click="cancelDraw"
                            class="px-3 py-1 bg-red-500 hover:bg-red-400 text-white text-xs font-bold rounded-lg">Cancel</button>
                    </div>
                </div>

                <!-- Viewport -->
                <div class="relative flex-1 overflow-hidden select-none" ref="mapContainer"
                    @wheel.prevent="onWheel"
                    @mousedown="onMouseDown"
                    @mousemove="onMouseMoveContainer"
                    @mouseup="onMouseUp"
                    @mouseleave="onMouseUp"
                    @dblclick.prevent="onDblClick"
                    :style="{ cursor: drawingMode ? 'crosshair' : (isPanning ? 'grabbing' : 'grab') }"
                >
                    <div :style="{
                        position:'absolute', transformOrigin:'0 0',
                        transform:`translate(${panX}px,${panY}px) scale(${zoom})`,
                        width: baseW + 'px', height: baseH + 'px',
                    }">
                        <img v-if="selectedMap" :src="selectedMap.image_path" ref="mapImg" @load="onImageLoad"
                            :style="{ position:'absolute', top:0, left:0, width:baseW+'px', height:baseH+'px', opacity:0.65, userSelect:'none' }"
                            draggable="false" />

                        <svg v-if="baseW > 0" :width="baseW" :height="baseH" style="position:absolute;top:0;left:0;">
                            <!-- Render layers lowest first, then by sort_order within each layer -->
                            <g v-for="zone in renderSortedZones" :key="zone.id ?? zone._tempId">
                                <polygon
                                    :points="worldPolyToSvg(zone.polygon)"
                                    :fill="zone.color + (selectedZone === zone ? '70' : '28')"
                                    :stroke="zone.color"
                                    :stroke-width="(selectedZone === zone ? 3 : 1.5) / zoom"
                                    :style="drawingMode ? 'pointer-events:none' : 'cursor:pointer'"
                                    :opacity="selectedZone && selectedZone !== zone ? 0.6 : 1"
                                    @click.stop="!drawingMode && selectZone(zone)"
                                />
                                <text
                                    :x="polygonVisualCenter(zone.polygon).x"
                                    :y="polygonVisualCenter(zone.polygon).y"
                                    text-anchor="middle" dominant-baseline="middle"
                                    fill="white" :font-size="11/zoom" font-weight="700"
                                    style="pointer-events:none;text-shadow:0 1px 4px #000;"
                                >{{ zone.canonical_name }}</text>
                                <!-- Draggable points — only on selected zone, not in drawing mode -->
                                <g v-if="selectedZone === zone && !drawingMode">
                                    <circle
                                        v-for="(pt, i) in zone.polygon"
                                        :key="i"
                                        :cx="worldToSvgX(pt.x)"
                                        :cy="worldToSvgY(pt.y)"
                                        :r="6 / zoom"
                                        fill="white"
                                        :stroke="zone.color"
                                        :stroke-width="2 / zoom"
                                        style="cursor:grab;"
                                        @mousedown.stop="startPointDrag($event, zone, i)"
                                    />
                                </g>
                            </g>

                            <!-- Draggable handles on selected zone -->
                            <g v-if="selectedZone && !drawingMode"
                               v-for="(pt, ptIdx) in selectedZone.polygon" :key="'handle-' + ptIdx">
                                <circle
                                    :cx="worldToSvgX(pt.x)"
                                    :cy="worldToSvgY(pt.y)"
                                    :r="6 / zoom"
                                    fill="white"
                                    :stroke="selectedZone.color"
                                    :stroke-width="2 / zoom"
                                    style="cursor:move;"
                                    @mousedown.stop="startDragPoint($event, selectedZone, ptIdx)"
                                />
                            </g>

                            <!-- In-progress polygon -->
                            <g v-if="drawingMode && currentPoints.length" style="pointer-events:none;">
                                <polyline
                                    :points="[...currentPoints, mouseWorldPos].map(p=>`${worldToSvgX(p.x)},${worldToSvgY(p.y)}`).join(' ')"
                                    fill="none" stroke="#f97316" :stroke-width="2/zoom" stroke-dasharray="6 3" />
                                <circle v-for="(pt,i) in currentPoints" :key="i"
                                    :cx="worldToSvgX(pt.x)" :cy="worldToSvgY(pt.y)"
                                    :r="5/zoom" fill="#f97316" stroke="white" :stroke-width="1.5/zoom" />
                            </g>
                        </svg>
                    </div>

                    <div v-if="!selectedMap" class="absolute inset-0 flex items-center justify-center text-neutral-600">Select a map</div>

                    <!-- Zoom buttons -->
                    <div class="absolute bottom-3 right-3 flex flex-col gap-1">
                        <button @click="zoomIn" class="w-8 h-8 bg-neutral-800/90 hover:bg-neutral-700 border border-neutral-700 rounded-lg text-white text-sm font-black flex items-center justify-center">+</button>
                        <button @click="zoomOut" class="w-8 h-8 bg-neutral-800/90 hover:bg-neutral-700 border border-neutral-700 rounded-lg text-white text-sm font-black flex items-center justify-center">−</button>
                    </div>

                    <!-- Coord readout -->
                    <div v-if="drawingMode" class="absolute bottom-3 left-3 px-2 py-1 bg-neutral-900/90 border border-neutral-700 rounded text-[10px] font-mono text-neutral-400">
                        {{ mouseWorldPos.x }}, {{ mouseWorldPos.y }}
                    </div>
                </div>
            </div>

            <!-- ── Right panel ── -->
            <div class="flex flex-col gap-3 overflow-y-auto flex-shrink-0" style="width:300px;">

                <!-- New zone button -->
                <button @click="drawingMode ? cancelDraw() : startDraw()"
                    class="w-full py-2.5 text-white text-sm font-black uppercase tracking-wider rounded-xl transition-all"
                    :class="drawingMode ? 'bg-red-500 hover:bg-red-400' : 'bg-orange-500 hover:bg-orange-400'">
                    {{ drawingMode ? '× Cancel Drawing' : '+ New Zone' }}
                </button>

                <!-- ── LAYERS panel ── -->
                <div class="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-neutral-800 flex items-center justify-between">
                        <span class="text-[10px] text-neutral-500 font-bold uppercase tracking-widest">Layers</span>
                        <button @click="addLayer" class="text-orange-500 hover:text-orange-400 text-xs font-bold transition-colors">+ Add</button>
                    </div>
                    <div class="p-2 space-y-1">
                        <div v-if="!layers.length" class="px-2 py-4 text-center text-neutral-600 text-xs">No layers yet</div>
                        <div v-for="layer in layersSorted" :key="layer.id ?? layer._tempId"
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border transition-all cursor-pointer"
                            :class="selectedLayerForNew === (layer.id ?? layer._tempId)
                                ? 'border-orange-500/50 bg-orange-500/5'
                                : 'border-transparent hover:border-neutral-700'"
                            @click="selectedLayerForNew = (layer.id ?? layer._tempId)"
                        >
                            <!-- Layer order badge -->
                            <span class="text-[10px] font-black text-neutral-600 w-5 text-center">{{ layer.order }}</span>

                            <!-- Name -->
                            <input v-model="layer.name" @click.stop
                                class="flex-1 bg-transparent text-xs text-white outline-none min-w-0 border-b border-transparent focus:border-neutral-600"
                                placeholder="Layer name" />

                            <!-- Up / Down -->
                            <div class="flex flex-col gap-0">
                                <button @click.stop="moveLayerUp(layer)" class="text-neutral-600 hover:text-white text-[9px] leading-none">▲</button>
                                <button @click.stop="moveLayerDown(layer)" class="text-neutral-600 hover:text-white text-[9px] leading-none">▼</button>
                            </div>

                            <!-- Delete -->
                            <button @click.stop="deleteLayer(layer)" class="text-neutral-700 hover:text-red-400 text-sm leading-none transition-colors">×</button>
                        </div>
                        <p class="text-[10px] text-neutral-600 px-2 pt-1">Click a layer to assign new zones to it. Higher order = renders on top.</p>
                    </div>
                </div>

                <!-- ── ZONES list ── -->
                <div class="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-neutral-800">
                        <span class="text-[10px] text-neutral-500 font-bold uppercase tracking-widest">Zones ({{ zones.length }})</span>
                    </div>
                    <div class="overflow-y-auto" style="max-height:220px;">
                        <div v-if="!zones.length" class="px-4 py-6 text-center text-neutral-600 text-sm">No zones yet</div>
                        <div v-for="zone in renderSortedZones" :key="zone.id ?? zone._tempId"
                            @click="selectZone(zone)"
                            class="flex items-center gap-2 px-3 py-2.5 cursor-pointer border-b border-neutral-800/50 transition-colors"
                            :class="selectedZone === zone ? 'bg-orange-500/10' : 'hover:bg-neutral-800/50'">
                            <div class="w-2.5 h-2.5 rounded-sm flex-shrink-0" :style="{ background: zone.color }"></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-white truncate">{{ zone.canonical_name }}</div>
                                <div class="text-[10px] text-neutral-600">
                                    {{ layerNameForZone(zone) }} · {{ zone.zone_type }} · {{ zone.polygon?.length ?? 0 }}pts
                                </div>
                            </div>
                            <button @click.stop="deleteZone(zone)" class="text-neutral-700 hover:text-red-400 text-sm leading-none transition-colors flex-shrink-0">×</button>
                        </div>
                    </div>
                </div>

                <!-- ── Edit selected zone ── -->
                <div v-if="selectedZone" class="bg-neutral-900 border border-neutral-800 rounded-2xl p-4 space-y-3">
                    <h4 class="text-[10px] text-orange-500 font-bold uppercase tracking-widest">Edit Zone</h4>

                    <div class="space-y-1">
                        <label class="text-[10px] text-neutral-500 font-bold uppercase block">Canonical Name</label>
                        <input v-model="selectedZone.canonical_name"
                            class="w-full bg-neutral-800 border border-neutral-700 focus:border-orange-500 text-white text-sm rounded-lg px-3 py-2 outline-none transition-colors" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] text-neutral-500 font-bold uppercase block">Short Code</label>
                        <input v-model="selectedZone.short_code" placeholder="e.g. JUNGLE"
                            class="w-full bg-neutral-800 border border-neutral-700 focus:border-orange-500 text-white text-sm rounded-lg px-3 py-2 outline-none transition-colors" />
                    </div>

                    <!-- Layer assignment -->
                    <div class="space-y-1">
                        <label class="text-[10px] text-neutral-500 font-bold uppercase block">Layer</label>
                        <select v-model="selectedZone.layer_id"
                            class="w-full bg-neutral-800 border border-neutral-700 focus:border-orange-500 text-white text-sm rounded-lg px-3 py-2 outline-none transition-colors">
                            <option :value="null">— Unassigned —</option>
                            <option v-for="layer in layersSorted" :key="layer.id ?? layer._tempId" :value="layer.id ?? layer._tempId">
                                {{ layer.order }} · {{ layer.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Zone type -->
                    <div class="space-y-1">
                        <label class="text-[10px] text-neutral-500 font-bold uppercase block">Zone Type <span class="text-neutral-600">(auto-tag priority)</span></label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button v-for="t in zoneTypes" :key="t.value"
                                @click="selectedZone.zone_type = t.value"
                                class="py-2 rounded-lg border text-[10px] font-bold uppercase transition-all"
                                :class="selectedZone.zone_type === t.value
                                    ? 'border-orange-500 bg-orange-500/10 text-orange-400'
                                    : 'border-neutral-700 bg-neutral-800 text-neutral-500 hover:border-neutral-600'">
                                {{ t.label }}
                            </button>
                        </div>
                        <p class="text-[10px] text-neutral-600 mt-1">{{ zoneTypeDesc[selectedZone.zone_type] }}</p>
                    </div>

                    <div class="flex gap-2">
                        <div class="flex-1 space-y-1">
                            <label class="text-[10px] text-neutral-500 font-bold uppercase block">Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color" v-model="selectedZone.color" class="w-9 h-9 rounded border-0 cursor-pointer bg-transparent" />
                                <span class="text-neutral-500 text-[10px] font-mono">{{ selectedZone.color }}</span>
                            </div>
                        </div>
                        <div class="flex-1 space-y-1">
                            <label class="text-[10px] text-neutral-500 font-bold uppercase block">Sort (in layer)</label>
                            <input v-model.number="selectedZone.sort_order" type="number"
                                class="w-full bg-neutral-800 border border-neutral-700 focus:border-orange-500 text-white text-sm rounded-lg px-3 py-2 outline-none transition-colors" />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useStore } from '@/store'
import { storeToRefs } from 'pinia'
import getScale from '@/scales.js'

const router = useRouter()
const store = useStore()
const { user } = storeToRefs(store)

onMounted(() => {
    if (user.value && !user.value.is_admin) { router.push('/tactics'); return }
    loadMaps()
    window.addEventListener('resize', onResize)
})
onUnmounted(() => window.removeEventListener('resize', onResize))

// ── Types ──────────────────────────────────────────────────────────────
interface MapItem { id: number; name: string; display_name: string; image_path: string }
interface WorldPoint { x: number; y: number }
interface Layer {
    id: number | null; _tempId?: number
    name: string; order: number; color: string | null; map_id: number
}
interface Zone {
    id: number | null; _tempId?: number
    canonical_name: string; short_code: string
    layer_id: number | null
    zone_type: 'area' | 'zone' | 'point'
    polygon: WorldPoint[]
    color: string; sort_order: number; map_id: number
}

const zoneTypes = [
    { value: 'area',  label: 'Area' },
    { value: 'zone',  label: 'Zone' },
    { value: 'point', label: 'Point' },
]
const zoneTypeDesc: Record<string, string> = {
    area:  'Large region (CT Spawn, T Spawn). Lowest auto-tag priority.',
    zone:  'Named sub-area (Jungle, Short, Ticket). Medium priority.',
    point: 'Pixel-precise spot (corner, boost). Highest priority — always wins.',
}

// ── State ──────────────────────────────────────────────────────────────
const maps = ref<MapItem[]>([])
const selectedMapId = ref<number | null>(null)
const zones = ref<Zone[]>([])
const layers = ref<Layer[]>([])
const selectedZone = ref<Zone | null>(null)
const selectedLayerForNew = ref<number | null>(null) // layer to assign when creating new zone
const drawingMode = ref(false)
const currentPoints = ref<WorldPoint[]>([])
const mouseWorldPos = ref<WorldPoint>({ x: 0, y: 0 })
const mapImg = ref<HTMLImageElement | null>(null)
const mapContainer = ref<HTMLElement | null>(null)
const loadingMaps = ref(true)
const saving = ref(false)
let tempId = 0

const baseW = ref(0)
const baseH = ref(0)
const zoom = ref(1)
const panX = ref(0)
const panY = ref(0)
const isPanning = ref(false)
let panStart = { x: 0, y: 0, px: 0, py: 0 }
let clickMoved = false
let dragState: {
    zone: any
    pointIndex: number
    startWorld: { x: number; y: number }
} | null = null
const MIN_ZOOM = 1
const MAX_ZOOM = 16

// Point dragging state
const draggingPoint = ref<{ zone: Zone; index: number } | null>(null)

// ── Computed ───────────────────────────────────────────────────────────
const selectedMap = computed<MapItem | null>(() =>
    Array.isArray(maps.value) ? (maps.value.find(m => m.id === selectedMapId.value) ?? null) : null
)
const mapScale = computed(() => getScale(selectedMap.value?.name ?? ''))

const layersSorted = computed(() => [...layers.value].sort((a, b) => a.order - b.order))

// Layer order map for priority lookup
const layerOrderById = computed(() => {
    const m: Record<number, number> = {}
    layers.value.forEach(l => { if (l.id) m[l.id] = l.order })
    return m
})

const TYPE_PRIORITY: Record<string, number> = { area: 0, zone: 1, point: 2 }

// Render order: layer order asc → type priority asc → sort_order asc
// This means higher layer = rendered on top (painted last)
const renderSortedZones = computed(() =>
    [...zones.value].sort((a, b) => {
        const la = layerOrderById.value[a.layer_id ?? -1] ?? -1
        const lb = layerOrderById.value[b.layer_id ?? -1] ?? -1
        if (la !== lb) return la - lb
        const ta = TYPE_PRIORITY[a.zone_type] ?? 0
        const tb = TYPE_PRIORITY[b.zone_type] ?? 0
        if (ta !== tb) return ta - tb
        return (a.sort_order ?? 0) - (b.sort_order ?? 0)
    })
)

function layerNameForZone(zone: Zone): string {
    if (!zone.layer_id) return 'No layer'
    return layers.value.find(l => l.id === zone.layer_id)?.name ?? 'Unknown'
}

// ── Coordinate transforms ──────────────────────────────────────────────
function worldToImgX(wx: number) { return (wx - mapScale.value.pos_x) / mapScale.value.scale }
function worldToImgY(wy: number) { return (mapScale.value.pos_y - wy) / mapScale.value.scale }
function imgToWorldX(px: number) { return px * mapScale.value.scale + mapScale.value.pos_x }
function imgToWorldY(py: number) { return mapScale.value.pos_y - py * mapScale.value.scale }
function worldToSvgX(wx: number) { return (worldToImgX(wx) / 1024) * baseW.value }
function worldToSvgY(wy: number) { return (worldToImgY(wy) / 1024) * baseH.value }

function containerToWorld(cx: number, cy: number): WorldPoint {
    const sx = (cx - panX.value) / zoom.value
    const sy = (cy - panY.value) / zoom.value
    return { x: Math.round(imgToWorldX((sx / baseW.value) * 1024)), y: Math.round(imgToWorldY((sy / baseH.value) * 1024)) }
}

function worldPolyToSvg(polygon: WorldPoint[]): string {
    if (!polygon?.length || baseW.value === 0) return ''
    return polygon.map(p => `${worldToSvgX(p.x)},${worldToSvgY(p.y)}`).join(' ')
}

function worldPolyCentroid(polygon: WorldPoint[]): { x: number; y: number } {
    if (!polygon?.length) return { x: 0, y: 0 }
    if (polygon.length === 1) return { x: worldToSvgX(polygon[0].x), y: worldToSvgY(polygon[0].y) }

    // Convert to SVG space first
    const pts = polygon.map(p => ({ x: worldToSvgX(p.x), y: worldToSvgY(p.y) }))
    const n = pts.length

    // Shoelace centroid formula — gives the true area-weighted center
    let area = 0
    let cx = 0
    let cy = 0
    for (let i = 0; i < n; i++) {
        const j = (i + 1) % n
        const cross = pts[i].x * pts[j].y - pts[j].x * pts[i].y
        area += cross
        cx += (pts[i].x + pts[j].x) * cross
        cy += (pts[i].y + pts[j].y) * cross
    }
    area /= 2
    if (Math.abs(area) < 0.001) {
        // Degenerate polygon — fall back to mean
        return { x: pts.reduce((s, p) => s + p.x, 0) / n, y: pts.reduce((s, p) => s + p.y, 0) / n }
    }
    cx /= (6 * area)
    cy /= (6 * area)
    return { x: cx, y: cy }
}
// Alias used in template
const polygonVisualCenter = worldPolyCentroid

// ── Image sizing ────────────────────────────────────────────────────────
function computeBaseSize() {
    const c = mapContainer.value; const img = mapImg.value
    if (!c || !img) return
    const cw = c.clientWidth; const ch = c.clientHeight
    const natW = img.naturalWidth || 1024; const natH = img.naturalHeight || 1024
    const ratio = natW / natH; const cRatio = cw / ch
    let w: number, h: number
    if (ratio > cRatio) { w = cw; h = cw / ratio } else { h = ch; w = ch * ratio }
    baseW.value = Math.round(w); baseH.value = Math.round(h)
    panX.value = Math.round((cw - w) / 2); panY.value = Math.round((ch - h) / 2)
    zoom.value = 1
}
function onImageLoad() { computeBaseSize() }
function onResize() { if (mapImg.value?.complete) computeBaseSize() }

// ── Pan + Zoom ──────────────────────────────────────────────────────────
function onWheel(e: WheelEvent) {
    const c = mapContainer.value; if (!c) return
    const rect = c.getBoundingClientRect()
    const mx = e.clientX - rect.left; const my = e.clientY - rect.top
    const delta = e.deltaY < 0 ? 1.12 : 1 / 1.12
    const newZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, zoom.value * delta))
    const r = newZoom / zoom.value
    panX.value = mx - r * (mx - panX.value)
    panY.value = my - r * (my - panY.value)
    zoom.value = newZoom
}
function zoomIn() {
    const cx = (mapContainer.value?.clientWidth ?? 600) / 2
    const cy = (mapContainer.value?.clientHeight ?? 400) / 2
    const nz = Math.min(MAX_ZOOM, zoom.value * 1.25)
    const r = nz / zoom.value
    panX.value = cx - r * (cx - panX.value); panY.value = cy - r * (cy - panY.value); zoom.value = nz
}
function zoomOut() {
    const cx = (mapContainer.value?.clientWidth ?? 600) / 2
    const cy = (mapContainer.value?.clientHeight ?? 400) / 2
    const nz = Math.max(MIN_ZOOM, zoom.value / 1.25)
    const r = nz / zoom.value
    panX.value = cx - r * (cx - panX.value); panY.value = cy - r * (cy - panY.value); zoom.value = nz
}
function resetView() { computeBaseSize() }

function onMouseDown(e: MouseEvent) {
    if (e.button !== 0) return
    clickMoved = false
    panStart = { x: e.clientX, y: e.clientY, px: panX.value, py: panY.value }
    isPanning.value = true
}
function onMouseMoveContainer(e: MouseEvent) {
    const c = mapContainer.value; if (!c) return
    const rect = c.getBoundingClientRect()
    const world = containerToWorld(e.clientX - rect.left, e.clientY - rect.top)
    mouseWorldPos.value = world

    // Handle point dragging
    if (draggingPoint.value) {
        draggingPoint.value.zone.polygon[draggingPoint.value.index] = { ...world }
        return
    }

    if (!isPanning.value) return
    const dx = e.clientX - panStart.x; const dy = e.clientY - panStart.y
    if (Math.abs(dx) > 2 || Math.abs(dy) > 2) clickMoved = true
    if (!drawingMode.value) { panX.value = panStart.px + dx; panY.value = panStart.py + dy }
}
function onMouseUp(e: MouseEvent) {
    // Stop point drag
    if (draggingPoint.value) {
        draggingPoint.value = null
        return
    }

    if (isPanning.value && !clickMoved && drawingMode.value) {
        const c = mapContainer.value
        if (c) {
            const rect = c.getBoundingClientRect()
            currentPoints.value.push(containerToWorld(e.clientX - rect.left, e.clientY - rect.top))
        }
    }
    isPanning.value = false
}
function onDblClick(e: MouseEvent) {
    if (!drawingMode.value) return
    e.preventDefault()
    if (currentPoints.value.length > 3) currentPoints.value.pop()
    finishZone()
}

// ── Drawing ────────────────────────────────────────────────────────────
function startDraw() { drawingMode.value = true; currentPoints.value = []; selectedZone.value = null }
function cancelDraw() { drawingMode.value = false; currentPoints.value = [] }

function finishZone() {
    if (currentPoints.value.length < 3) return
    const zone: Zone = {
        _tempId: ++tempId, id: null,
        canonical_name: 'New Zone', short_code: '',
        layer_id: typeof selectedLayerForNew.value === 'number' ? selectedLayerForNew.value : null,
        zone_type: 'zone',
        polygon: [...currentPoints.value],
        color: '#f97316', sort_order: zones.value.length, map_id: selectedMapId.value!,
    }
    zones.value.push(zone); selectedZone.value = zone
    drawingMode.value = false; currentPoints.value = []
}

function selectZone(zone: Zone) { if (!drawingMode.value) selectedZone.value = zone }

function startDragPoint(e: MouseEvent, zone: Zone, index: number) {
    if (drawingMode.value) return
    e.preventDefault()
    e.stopPropagation()
    selectedZone.value = zone
    draggingPoint.value = { zone, index }
    // Prevent pan from starting
    isPanning.value = false
    clickMoved = true
}

async function deleteZone(zone: Zone) {
    if (!confirm(`Delete zone "${zone.canonical_name}"?`)) return
    if (zone.id) await axios.delete(`/api/admin/callouts/zones/${zone.id}`)
    zones.value = zones.value.filter(z => z !== zone)
    if (selectedZone.value === zone) selectedZone.value = null
}

// ── Point dragging ────────────────────────────────────────────────────
function startPointDrag(e: MouseEvent, zone: any, pointIndex: number) {
    if (drawingMode.value) return
    e.preventDefault()
    e.stopPropagation()
    const c = mapContainer.value
    if (!c) return
    const rect = c.getBoundingClientRect()
    dragState = {
        zone,
        pointIndex,
        startWorld: containerToWorld(e.clientX - rect.left, e.clientY - rect.top),
    }
    // Override the container mousemove/mouseup temporarily
    window.addEventListener('mousemove', onPointDragMove)
    window.addEventListener('mouseup', onPointDragEnd)
}

function onPointDragMove(e: MouseEvent) {
    if (!dragState) return
    const c = mapContainer.value
    if (!c) return
    const rect = c.getBoundingClientRect()
    const world = containerToWorld(e.clientX - rect.left, e.clientY - rect.top)
    dragState.zone.polygon[dragState.pointIndex] = world
}

function onPointDragEnd() {
    dragState = null
    window.removeEventListener('mousemove', onPointDragMove)
    window.removeEventListener('mouseup', onPointDragEnd)
}

// ── Layer management ───────────────────────────────────────────────────
function addLayer() {
    const maxOrder = layers.value.reduce((m, l) => Math.max(m, l.order), -1)
    const layer: Layer = {
        _tempId: ++tempId, id: null,
        name: 'New Layer', order: maxOrder + 1, color: null, map_id: selectedMapId.value!,
    }
    layers.value.push(layer)
    selectedLayerForNew.value = layer._tempId!
}

function moveLayerUp(layer: Layer) {
    const idx = layersSorted.value.findIndex(l => l === layer)
    if (idx < layersSorted.value.length - 1) {
        const next = layersSorted.value[idx + 1]
        const tmp = layer.order; layer.order = next.order; next.order = tmp
    }
}
function moveLayerDown(layer: Layer) {
    const idx = layersSorted.value.findIndex(l => l === layer)
    if (idx > 0) {
        const prev = layersSorted.value[idx - 1]
        const tmp = layer.order; layer.order = prev.order; prev.order = tmp
    }
}

async function deleteLayer(layer: Layer) {
    if (!confirm(`Delete layer "${layer.name}"? Zones in this layer will become unassigned.`)) return
    if (layer.id) {
        await axios.delete(`/api/admin/callouts/layers/${layer.id}`)
        zones.value.forEach(z => { if (z.layer_id === layer.id) z.layer_id = null })
    }
    layers.value = layers.value.filter(l => l !== layer)
    if (selectedLayerForNew.value === (layer.id ?? layer._tempId)) selectedLayerForNew.value = null
}

// ── Load ───────────────────────────────────────────────────────────────
async function loadMaps() {
    try {
        const res = await axios.get('/api/admin/callouts/maps')
        maps.value = Array.isArray(res.data) ? res.data : (res.data?.data ?? [])
        if (maps.value.length) { selectedMapId.value = maps.value[0].id; await loadMapData() }
    } finally { loadingMaps.value = false }
}

async function loadMapData() {
    if (!selectedMapId.value) return
    zones.value = []; layers.value = []; selectedZone.value = null; baseW.value = 0
    const [zonesRes, layersRes] = await Promise.all([
        axios.get(`/api/admin/callouts/${selectedMapId.value}`),
        axios.get(`/api/admin/callouts/${selectedMapId.value}/layers`),
    ])
    layers.value = layersRes.data
    zones.value = (zonesRes.data as any[]).map(z => ({
        ...z,
        polygon: (z.polygon || []).map((p: any) => Array.isArray(p) ? { x: p[0], y: p[1] } : p)
    }))
    if (layers.value.length) selectedLayerForNew.value = layers.value[0].id
}

function onMapChange() { loadMapData() }

// ── Save ───────────────────────────────────────────────────────────────
async function saveAll() {
    saving.value = true
    try {
        // Save layers first (new ones get IDs we need for zones)
        for (const layer of layers.value) {
            const payload = { name: layer.name, order: layer.order, color: layer.color }
            if (layer.id) {
                await axios.put(`/api/admin/callouts/layers/${layer.id}`, payload)
            } else {
                const res = await axios.post(`/api/admin/callouts/${selectedMapId.value}/layers`, payload)
                const oldTempId = layer._tempId
                layer.id = res.data.id; delete layer._tempId
                // Update any zones that referenced this temp id
                zones.value.forEach(z => { if (z.layer_id === oldTempId) z.layer_id = layer.id })
            }
        }

        // Save zones
        for (const zone of zones.value) {
            const payload = {
                canonical_name: zone.canonical_name, short_code: zone.short_code,
                layer_id: zone.layer_id, zone_type: zone.zone_type,
                polygon: zone.polygon.map(p => [p.x, p.y]),
                color: zone.color, sort_order: zone.sort_order,
            }
            if (zone.id) {
                await axios.put(`/api/admin/callouts/zones/${zone.id}`, payload)
            } else {
                const res = await axios.post(`/api/admin/callouts/${selectedMapId.value}/zones`, payload)
                zone.id = res.data.id; delete zone._tempId
            }
        }

        alert('Saved successfully')
    } catch (e: any) {
        alert('Save failed: ' + (e.message ?? 'unknown'))
    } finally { saving.value = false }
}
</script>