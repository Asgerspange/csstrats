<template>
    <span class="callout-renderer">
        <template v-for="(part, i) in parts" :key="i">
            <span v-if="part.type === 'text'">{{ part.text }}</span>
            <button
                v-else-if="part.type === 'callout'"
                @click="openZone(part.zone)"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold transition-all hover:brightness-110 cursor-pointer mx-0.5"
                :style="{
                    background: (part.zone?.color ?? '#f97316') + '30',
                    border: '1px solid ' + (part.zone?.color ?? '#f97316') + '60',
                    color: part.zone?.color ?? '#f97316',
                }"
                :title="`Click to view ${part.label} on map`"
            >
                <span class="w-2 h-2 rounded-sm" :style="{ background: part.zone?.color ?? '#f97316' }"></span>
                {{ part.label }}
            </button>
        </template>
    </span>

    <!-- Zone map viewer modal -->
    <Teleport to="body">
        <div
            v-if="activeZone"
            class="fixed inset-0 z-[9999] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
            @click.self="activeZone = null"
        >
            <div class="bg-neutral-900 border border-neutral-700 rounded-2xl overflow-hidden shadow-2xl max-w-2xl w-full">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-800">
                    <div class="flex items-center gap-3">
                        <span class="w-3 h-3 rounded-sm" :style="{ background: activeZone.color }"></span>
                        <h3 class="text-white font-bold text-base">{{ activeZone.display_name ?? activeZone.canonical_name }}</h3>
                        <span class="text-neutral-500 text-xs">{{ activeZone.map_name }}</span>
                    </div>
                    <button @click="activeZone = null" class="text-neutral-500 hover:text-white transition-colors text-xl leading-none">×</button>
                </div>

                <!-- Map with zone highlighted -->
                <div class="relative bg-neutral-950" ref="viewerContainer">
                    <img
                        v-if="activeMapImagePath"
                        :src="activeMapImagePath"
                        ref="viewerImg"
                        @load="onViewerLoad"
                        class="w-full block opacity-60"
                    />
                    <svg
                        v-if="viewerSize.w"
                        :width="viewerSize.w"
                        :height="viewerSize.h"
                        :style="{ position: 'absolute', top: viewerOffset.y + 'px', left: viewerOffset.x + 'px' }"
                    >
                        <polygon
                            v-for="zone in siblingZones"
                            :key="zone.id"
                            :points="worldPolyToSvg(zone.polygon)"
                            :fill="zone.color + '15'"
                            :stroke="zone.color + '30'"
                            stroke-width="1"
                        />
                        <polygon
                            :points="worldPolyToSvg(activeZone.polygon)"
                            :fill="activeZone.color + '60'"
                            :stroke="activeZone.color"
                            stroke-width="3"
                        />
                        <text
                            :x="worldPolyCentroid(activeZone.polygon).x"
                            :y="worldPolyCentroid(activeZone.polygon).y"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            fill="white"
                            font-size="14"
                            font-weight="800"
                            style="text-shadow:0 2px 6px #000;"
                        >
                            {{ activeZone.display_name ?? activeZone.canonical_name }}
                        </text>
                    </svg>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, inject, isRef, nextTick } from 'vue'
import getScale from '@/scales.js'

const props = defineProps<{
    text: string
    mapImagePath?: string // image path of current tactic's map
}>()

const _injected = inject<any>('callout-zones', [])
const allZones = computed(() => {
    const v = isRef(_injected) ? _injected.value : _injected
    return Array.isArray(v) ? v : []
})

// Parse [callout:id:name] tokens in the text
const CALLOUT_RE = /\[callout:(\d+):([^\]]+)\]/g

const parts = computed(() => {
    const result: any[] = []
    let lastIndex = 0
    let match: RegExpExecArray | null

    CALLOUT_RE.lastIndex = 0
    while ((match = CALLOUT_RE.exec(props.text)) !== null) {
        if (match.index > lastIndex) {
            result.push({ type: 'text', text: props.text.slice(lastIndex, match.index) })
        }
        const zoneId = parseInt(match[1])
        const label = match[2]
        const zone = allZones.value.find((z: any) => z.id === zoneId) ?? null
        result.push({ type: 'callout', zoneId, label, zone })
        lastIndex = match.index + match[0].length
    }

    if (lastIndex < props.text.length) {
        result.push({ type: 'text', text: props.text.slice(lastIndex) })
    }

    return result
})

const activeZone = ref<any | null>(null)
const viewerImg = ref<HTMLImageElement | null>(null)
const viewerContainer = ref<HTMLElement | null>(null)
const viewerSize = ref({ w: 0, h: 0 })
const viewerOffset = ref({ x: 0, y: 0 })

const activeMapImagePath = computed(() => {
    if (!activeZone.value) return null
    return activeZone.value.map_image_path ?? props.mapImagePath ?? null
})

const activeMapScale = computed(() => {
    if (!activeZone.value) return getScale('')
    // map_key is the internal name e.g. "de_mirage" — returned directly from API
    return getScale(activeZone.value.map_key ?? '')
})

const siblingZones = computed(() => {
    if (!activeZone.value) return []
    return allZones.value.filter((z: any) => z.map_id === activeZone.value?.map_id && z.id !== activeZone.value?.id)
})

function openZone(zone: any) {
    if (!zone) return
    activeZone.value = zone
    viewerSize.value = { w: 0, h: 0 }
    nextTick(() => { if (viewerImg.value?.complete) onViewerLoad() })
}

function onViewerLoad() {
    const img = viewerImg.value
    const container = viewerContainer.value
    if (!img || !container) return
    requestAnimationFrame(() => {
        const cw = container.clientWidth
        const ch = container.clientHeight || img.clientHeight
        const natW = img.naturalWidth || 1024
        const natH = img.naturalHeight || 1024
        const ratio = natW / natH
        const cRatio = cw / (ch || cw)
        let w: number, h: number
        if (ratio > cRatio) { w = cw; h = cw / ratio } else { h = ch; w = ch * ratio }
        viewerSize.value = { w: Math.round(w), h: Math.round(h) }
        viewerOffset.value = { x: Math.round((cw - w) / 2), y: 0 }
    })
}

// World coordinate → SVG pixel (same transforms as Callouts.vue editor)
function worldToSvgX(wx: number): number {
    const s = activeMapScale.value
    const imgPx = (wx - s.pos_x) / s.scale
    return (imgPx / 1024) * viewerSize.value.w
}
function worldToSvgY(wy: number): number {
    const s = activeMapScale.value
    const imgPy = (s.pos_y - wy) / s.scale
    return (imgPy / 1024) * viewerSize.value.h
}

function worldPolyToSvg(polygon: any[]): string {
    if (!polygon?.length || !viewerSize.value.w) return ''
    return polygon.map((p: any) => {
        const wx = Array.isArray(p) ? p[0] : p.x
        const wy = Array.isArray(p) ? p[1] : p.y
        return `${worldToSvgX(wx)},${worldToSvgY(wy)}`
    }).join(' ')
}

function worldPolyCentroid(polygon: any[]): { x: number; y: number } {
    if (!polygon?.length) return { x: 0, y: 0 }
    const pts = polygon.map((p: any) => {
        const wp = Array.isArray(p) ? { x: p[0], y: p[1] } : p
        return { x: worldToSvgX(wp.x), y: worldToSvgY(wp.y) }
    })
    const n = pts.length
    if (n === 1) return pts[0]

    // Signed-area centroid — stays inside irregular/concave polygons
    let area = 0, cx = 0, cy = 0
    for (let i = 0; i < n; i++) {
        const j = (i + 1) % n
        const cross = pts[i].x * pts[j].y - pts[j].x * pts[i].y
        area += cross
        cx += (pts[i].x + pts[j].x) * cross
        cy += (pts[i].y + pts[j].y) * cross
    }
    area /= 2
    if (Math.abs(area) < 0.001) {
        // Degenerate — fall back to vertex average
        return {
            x: pts.reduce((s, p) => s + p.x, 0) / n,
            y: pts.reduce((s, p) => s + p.y, 0) / n,
        }
    }
    const f = 1 / (6 * area)
    return { x: cx * f, y: cy * f }
}
</script>