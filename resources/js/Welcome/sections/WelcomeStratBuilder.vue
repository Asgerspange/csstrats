<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useLandingData } from '../useLandingData'
import Container from '../primitives/Container.vue'
import Eyebrow from '../primitives/Eyebrow.vue'
import Button from '../primitives/Button.vue'

// Mirror of resources/js/PagesNew/RoundPlanner/composables/useRoundPlanner.ts
const PLAYER_COLORS = ['#FF8C00', '#4FC3F7', '#81C784', '#F06292', '#CE93D8'] as const

interface RoleDef { key: RoleKey; label: string; icon: string }
type RoleKey = 'none' | 'igl' | 'entry' | 'awp' | 'support' | 'lurk'

const PLAYER_ROLES: readonly RoleDef[] = [
    { key: 'none',    label: '—',       icon: 'person'    },
    { key: 'igl',     label: 'IGL',     icon: 'psychology'},
    { key: 'entry',   label: 'Entry',   icon: 'bolt'      },
    { key: 'awp',     label: 'AWP',     icon: 'visibility'},
    { key: 'support', label: 'Support', icon: 'favorite'  },
    { key: 'lurk',    label: 'Lurk',    icon: 'mystery'   },
]

type UtilType = 'smoke' | 'flash' | 'he' | 'molotov'
interface UtilTypeDef { key: UtilType; label: string; icon: string; color: string }

const UTILITY_TYPES: readonly UtilTypeDef[] = [
    { key: 'smoke',   label: 'Smoke',   icon: 'blur_on',               color: '#90CAF9' },
    { key: 'flash',   label: 'Flash',   icon: 'flash_on',              color: '#FFF176' },
    { key: 'he',      label: 'HE',      icon: 'crisis_alert',          color: '#EF9A9A' },
    { key: 'molotov', label: 'Molotov', icon: 'local_fire_department', color: '#FFAB91' },
]

const UTIL_COLORS: Record<UtilType, string> = {
    smoke: '#90CAF9', flash: '#FFF176', he: '#EF9A9A', molotov: '#FFAB91',
}

type ActiveMode = 'none' | 'path' | 'util_land'
type SpeedType = 'run' | 'walk'
type SideType = 'T' | 'CT'

interface Waypoint { x: number; y: number; t: number; speed: SpeedType }
interface Utility { id: number; type: UtilType; landPos: { x: number; y: number }; throwAt: number }
interface Player {
    slot: number
    name: string
    color: string
    role: RoleKey
    path: Waypoint[]
    utilities: Utility[]
}

const buildPlayers = (): Player[] =>
    PLAYER_COLORS.map((color, slot) => ({
        slot,
        name: `Player ${slot + 1}`,
        color,
        role: 'none',
        path: [],
        utilities: [],
    }))

// Real product: RUN 250, WALK 130 in game-units/sec (CS2 player speeds).
// de_mirage radar (1024x1024) has scale=5 game-units-per-radar-pixel, so 1 radar
// pixel = 5 game units. Our 0-100 SVG is the radar / 10.24, so 1 SVG unit
// = 10.24 radar px = 51.2 game units. Therefore run = 250/51.2 ≈ 4.88, walk
// = 130/51.2 ≈ 2.54 in SVG-units/sec. Rounded to keep the CS2 walk/run ratio.
const RUN_SPEED = 4.88
const WALK_SPEED = 2.54

const { user } = useLandingData()

const players = ref<Player[]>(buildPlayers())
const selectedSlot = ref<number>(0)
const side = ref<SideType>('T')

// Path mode is preselected by default so a fresh visitor can click immediately.
const activeMode = ref<ActiveMode>('path')
const pendingUtilType = ref<UtilType | null>(null)
const lastSpeed = ref<SpeedType>('run')

// Utility placement is a paid feature; clicking a util button opens a teaser
// instead of letting them place utilities on the landing.
const utilityTeaser = ref<UtilTypeDef | null>(null)
const dismissTeaser = () => { utilityTeaser.value = null }

const currentSecond = ref(0)
const isPlaying = ref(false)
const playbackSpeed = ref<0.5 | 1 | 2>(1)
let rafId: number | null = null
let lastFrame = 0

let uid = 1
const nextId = () => uid++

const selectedPlayer = computed<Player>(() => players.value[selectedSlot.value])

const duration = computed(() => {
    let max = 10
    for (const p of players.value) {
        for (const w of p.path) if (w.t > max) max = w.t
        for (const u of p.utilities) if (u.throwAt > max) max = u.throwAt
    }
    return Math.ceil(max + 1)
})

const isEmpty = computed(() =>
    players.value.every((p) => p.path.length === 0 && p.utilities.length === 0),
)

const getRoleLabel = (key: RoleKey) =>
    PLAYER_ROLES.find((r) => r.key === key)?.label ?? '—'

const selectPlayer = (slot: number) => {
    if (selectedSlot.value === slot) {
        selectedSlot.value = -1 as unknown as number
        activeMode.value = 'none'
        return
    }
    selectedSlot.value = slot
    // Preselect path mode so the user can immediately drop waypoints.
    activeMode.value = 'path'
}

const setPlayerRole = (slot: number, role: RoleKey) => {
    players.value[slot].role = role
}

const togglePathMode = () => {
    if (activeMode.value === 'path') {
        activeMode.value = 'none'
    } else {
        activeMode.value = 'path'
        pendingUtilType.value = null
    }
}

const startAddingUtil = (slot: number, type: UtilType) => {
    selectedSlot.value = slot
    const def = UTILITY_TYPES.find((u) => u.key === type) ?? null
    utilityTeaser.value = def
}

const lastWaypointSpeed = (slot: number): SpeedType => {
    const p = players.value[slot]
    if (p.path.length === 0) return lastSpeed.value
    return p.path[p.path.length - 1].speed
}
const toggleLastWaypointSpeed = (slot: number) => {
    const p = players.value[slot]
    const newSpeed: SpeedType = lastWaypointSpeed(slot) === 'walk' ? 'run' : 'walk'
    if (p.path.length > 0) p.path[p.path.length - 1].speed = newSpeed
    lastSpeed.value = newSpeed
    // Re-stamp t values to reflect new speed
    recomputePathTimes(slot)
}

const removeLastWaypoint = (slot: number) => {
    const p = players.value[slot]
    p.path.pop()
    recomputePathTimes(slot)
}

const clearPath = (slot: number) => {
    players.value[slot].path = []
}

const removeUtil = (slot: number, id: number) => {
    players.value[slot].utilities = players.value[slot].utilities.filter((u) => u.id !== id)
}

const updateUtilThrowAt = (slot: number, id: number, value: number) => {
    const u = players.value[slot].utilities.find((u) => u.id === id)
    if (u) u.throwAt = Math.max(0, Math.min(300, value))
}

const recomputePathTimes = (slot: number) => {
    const p = players.value[slot]
    if (p.path.length === 0) return
    p.path[0].t = 0
    for (let i = 1; i < p.path.length; i++) {
        const a = p.path[i - 1]
        const b = p.path[i]
        const dist = Math.hypot(b.x - a.x, b.y - a.y)
        const speed = b.speed === 'walk' ? WALK_SPEED : RUN_SPEED
        b.t = a.t + dist / speed
    }
}

const stage = ref<SVGSVGElement | null>(null)

const toStageCoords = (e: PointerEvent) => {
    const svg = stage.value
    if (!svg) return { x: 50, y: 50 }
    // Use SVG's own coordinate transform so clicks map to viewBox space
    // correctly under preserveAspectRatio="xMidYMid meet" (the letterbox).
    const pt = svg.createSVGPoint()
    pt.x = e.clientX
    pt.y = e.clientY
    const ctm = svg.getScreenCTM()
    if (!ctm) return { x: 50, y: 50 }
    const p = pt.matrixTransform(ctm.inverse())
    return { x: Math.max(0, Math.min(100, p.x)), y: Math.max(0, Math.min(100, p.y)) }
}

const onCanvasClick = (e: PointerEvent) => {
    if (utilityTeaser.value) return
    const p = toStageCoords(e)
    if (activeMode.value === 'path') {
        const player = players.value[selectedSlot.value]
        const speed = lastSpeed.value
        let t = 0
        if (player.path.length > 0) {
            const prev = player.path[player.path.length - 1]
            const dist = Math.hypot(p.x - prev.x, p.y - prev.y)
            t = prev.t + dist / (speed === 'walk' ? WALK_SPEED : RUN_SPEED)
        }
        player.path.push({ x: p.x, y: p.y, t, speed })
        currentSecond.value = t
    }
}

const onCanvasRightClick = (e: MouseEvent) => {
    e.preventDefault()
    if (activeMode.value === 'path') {
        removeLastWaypoint(selectedSlot.value)
    }
}

// Playback
const togglePlayback = () => {
    if (isEmpty.value) return
    isPlaying.value = !isPlaying.value
    if (isPlaying.value) {
        if (currentSecond.value >= duration.value - 0.05) currentSecond.value = 0
        lastFrame = performance.now()
        rafId = requestAnimationFrame(tick)
    } else if (rafId !== null) {
        cancelAnimationFrame(rafId)
        rafId = null
    }
}

const tick = (now: number) => {
    const dt = (now - lastFrame) / 1000
    lastFrame = now
    currentSecond.value = Math.min(duration.value, currentSecond.value + dt * playbackSpeed.value)
    if (currentSecond.value >= duration.value) {
        isPlaying.value = false
        rafId = null
        return
    }
    rafId = requestAnimationFrame(tick)
}

const togglePlaybackSpeed = () => {
    const order: Array<0.5 | 1 | 2> = [1, 2, 0.5]
    const i = order.indexOf(playbackSpeed.value)
    playbackSpeed.value = order[(i + 1) % order.length]
}

const fmt = (s: number) => {
    const m = Math.floor(s / 60)
    const sec = Math.floor(s % 60)
    return `${m}:${sec.toString().padStart(2, '0')}`
}

// Scrub interactions
const scrubRef = ref<HTMLDivElement | null>(null)
const scrubHoverX = ref<number | null>(null)
const scrubHoverSeconds = computed(() => {
    if (!scrubRef.value || scrubHoverX.value === null) return 0
    const rect = scrubRef.value.getBoundingClientRect()
    return (scrubHoverX.value / rect.width) * duration.value
})

const startScrubDrag = (e: MouseEvent) => {
    if (!scrubRef.value) return
    const rect = scrubRef.value.getBoundingClientRect()
    const setTime = (clientX: number) => {
        const x = Math.max(0, Math.min(rect.width, clientX - rect.left))
        currentSecond.value = (x / rect.width) * duration.value
    }
    setTime(e.clientX)
    const move = (ev: MouseEvent) => setTime(ev.clientX)
    const up = () => {
        window.removeEventListener('mousemove', move)
        window.removeEventListener('mouseup', up)
    }
    window.addEventListener('mousemove', move)
    window.addEventListener('mouseup', up)
}

const onScrubHover = (e: MouseEvent) => {
    if (!scrubRef.value) return
    const rect = scrubRef.value.getBoundingClientRect()
    scrubHoverX.value = e.clientX - rect.left
}

// Position helpers
// Centripetal Catmull-Rom spline. Player position interpolates through
// waypoints with smooth curves through corners (matching how the real
// product simulates movement through the path). The DRAWN polyline stays
// straight so the user can see the planned waypoints exactly.
interface Vec2 { x: number; y: number }
const catmullRom = (P0: Vec2, P1: Vec2, P2: Vec2, P3: Vec2, u: number): Vec2 => {
    const u2 = u * u
    const u3 = u2 * u
    return {
        x: 0.5 * (
            (2 * P1.x) +
            (-P0.x + P2.x) * u +
            (2 * P0.x - 5 * P1.x + 4 * P2.x - P3.x) * u2 +
            (-P0.x + 3 * P1.x - 3 * P2.x + P3.x) * u3
        ),
        y: 0.5 * (
            (2 * P1.y) +
            (-P0.y + P2.y) * u +
            (2 * P0.y - 5 * P1.y + 4 * P2.y - P3.y) * u2 +
            (-P0.y + 3 * P1.y - 3 * P2.y + P3.y) * u3
        ),
    }
}

// Arc-length reparametrization. Linear u over a Catmull-Rom segment makes
// the player accelerate through corners (the curve covers more distance per
// unit u in the middle than at the ends). We sample the curve, build a
// cumulative-length table, and invert it so the player moves at constant
// speed along the curve. timeFrac ∈ [0,1] is the fraction of the segment's
// time span already elapsed; the returned u is the curve parameter that
// places the player at that fraction of the curve's arc length.
const ARC_SAMPLES = 24
const uForArcFraction = (P0: Vec2, P1: Vec2, P2: Vec2, P3: Vec2, timeFrac: number) => {
    if (timeFrac <= 0) return 0
    if (timeFrac >= 1) return 1

    let cum = 0
    let prev = catmullRom(P0, P1, P2, P3, 0)
    const lengths = new Array<number>(ARC_SAMPLES + 1)
    lengths[0] = 0
    for (let i = 1; i <= ARC_SAMPLES; i++) {
        const u = i / ARC_SAMPLES
        const pt = catmullRom(P0, P1, P2, P3, u)
        cum += Math.hypot(pt.x - prev.x, pt.y - prev.y)
        lengths[i] = cum
        prev = pt
    }

    const target = cum * timeFrac
    for (let i = 1; i <= ARC_SAMPLES; i++) {
        if (lengths[i] >= target) {
            const segLen = lengths[i] - lengths[i - 1]
            const k = segLen === 0 ? 0 : (target - lengths[i - 1]) / segLen
            return ((i - 1) + k) / ARC_SAMPLES
        }
    }
    return 1
}

const playerPosAtTime = (p: Player, t: number): Vec2 | null => {
    if (p.path.length === 0) return null
    if (p.path.length === 1) return { x: p.path[0].x, y: p.path[0].y }
    if (t <= p.path[0].t) return { x: p.path[0].x, y: p.path[0].y }

    for (let i = 0; i < p.path.length - 1; i++) {
        const A = p.path[i]
        const B = p.path[i + 1]
        if (t >= A.t && t <= B.t) {
            const span = B.t - A.t
            const timeFrac = span === 0 ? 1 : (t - A.t) / span
            const P0 = p.path[i - 1] ?? A
            const P1 = A
            const P2 = B
            const P3 = p.path[i + 2] ?? B
            const u = uForArcFraction(P0, P1, P2, P3, timeFrac)
            return catmullRom(P0, P1, P2, P3, u)
        }
    }
    const last = p.path[p.path.length - 1]
    return { x: last.x, y: last.y }
}

// Drawn path: straight polyline through every waypoint (mirrors the real
// product's ctx.lineTo). The smooth curve only applies to player motion.
const pathFor = (path: Waypoint[]) => {
    if (path.length < 2) return ''
    const [head, ...rest] = path
    return `M ${head.x} ${head.y} ` + rest.map((p) => `L ${p.x} ${p.y}`).join(' ')
}

// Yaw is the tangent of the smoothed (Catmull-Rom) motion curve at t,
// sampled with a small forward/back epsilon.
const playerYawAtTime = (p: Player, t: number) => {
    if (p.path.length < 2) return 0
    const eps = 0.05
    const a = playerPosAtTime(p, Math.max(0, t - eps))
    const b = playerPosAtTime(p, t + eps)
    if (!a || !b) return 0
    const dx = b.x - a.x
    const dy = b.y - a.y
    if (Math.hypot(dx, dy) < 1e-4) return 0
    return Math.atan2(dy, dx)
}

// Scaled-down version of the real product's ARROW_PATH (game-units → 0-100 SVG).
// Original: 'M 6 -7.44 a 9.6 9.6 180 0 0 -6 -2.16 a 9.6 9.6 180 0 0 0 19.2 a 9.6 9.6 180 0 0 6 -2.16 l 9.84 -7.44 z'
const ARROW_PATH = 'M 1.2 -1.49 a 1.92 1.92 180 0 0 -1.2 -0.43 a 1.92 1.92 180 0 0 0 3.84 a 1.92 1.92 180 0 0 1.2 -0.43 l 1.97 -1.49 z'

const isUtilActive = (u: Utility, t: number) => {
    if (u.type === 'smoke') return t >= u.throwAt && t <= u.throwAt + 18
    return t >= u.throwAt && t <= u.throwAt + 1.5
}

const canvasCursorClass = computed(() => {
    if (utilityTeaser.value) return 'cursor-default'
    if (activeMode.value === 'path') return 'cursor-crosshair'
    return 'cursor-default'
})

const openInProductCta = computed(() => {
    if (user.value) return { label: 'Open the full builder', href: '/strat-builder' }
    return { label: 'Open the full builder', href: '/register' }
})

onUnmounted(() => {
    if (rafId !== null) cancelAnimationFrame(rafId)
})
</script>

<template>
    <section id="builder" class="relative py-16 sm:py-20 lg:py-24" data-reveal-group>
        <Container>
            <div class="grid gap-10 lg:grid-cols-[4fr_8fr] lg:items-center lg:gap-16" data-reveal-group>
            <header class="flex flex-col gap-6">
                <Eyebrow index="03" label="Strat Builder" />
                <h2
                    class="font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                    data-reveal
                >
                    Draw the call. Right here.
                </h2>
                <p class="text-bone" data-reveal>
                    The Strat Builder from inside the product, embedded on this page. Pick a player, draw their path, schedule utility, hit play. Nothing is saved.
                </p>
            </header>

            <!-- This block mirrors the real RoundPlanner editor (resources/js/PagesNew/RoundPlanner/RoundPlanner.vue) -->
            <div
                class="relative flex h-[500px] w-full overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950 text-white shadow-2xl"
                data-reveal
            >
                <!-- ═══════════ SIDEBAR ═══════════════════════════════════════ -->
                <aside class="flex w-[268px] min-w-[268px] flex-col overflow-hidden border-r border-zinc-800 bg-zinc-900">
                    <!-- Plan header -->
                    <div class="flex flex-shrink-0 items-center gap-2 border-b border-zinc-800 px-4 py-3">
                        <span class="flex-1 truncate text-sm font-bold text-white">B Split · de_mirage</span>
                        <button
                            type="button"
                            class="flex-shrink-0 rounded border px-2 py-0.5 text-[10px] font-black uppercase tracking-widest"
                            :class="side === 'T'
                                ? 'border-[#d79b33]/30 bg-[#d79b33]/10 text-[#d79b33]'
                                : 'border-[#3579bc]/30 bg-[#3579bc]/10 text-[#3579bc]'"
                            @click="side = side === 'T' ? 'CT' : 'T'"
                            :aria-label="`Side: ${side}, click to toggle`"
                        >{{ side }}</button>
                    </div>

                    <!-- Player list -->
                    <div class="flex flex-1 flex-col gap-1 overflow-y-auto p-1.5">
                        <div
                            v-for="(player, slot) in players"
                            :key="slot"
                            class="cursor-pointer overflow-hidden rounded-xl border transition-all select-none"
                            :class="selectedSlot === slot
                                ? 'border-zinc-700 bg-zinc-800/90'
                                : 'border-transparent bg-zinc-800/20 hover:border-zinc-800 hover:bg-zinc-800/50'"
                            @click="selectPlayer(slot)"
                        >
                            <div class="relative flex items-center gap-0">
                                <div class="w-[3px] self-stretch rounded-l-xl flex-shrink-0" :style="{ background: player.color }" />
                                <div class="flex min-w-0 flex-1 items-center gap-2 px-2.5 py-2">
                                    <div
                                        class="h-5 w-5 flex-shrink-0 rounded-full border-2"
                                        :style="{ background: player.color, borderColor: player.color + '60' }"
                                        aria-hidden="true"
                                    />
                                    <span class="flex-1 truncate text-xs font-bold text-zinc-200">{{ player.name }}</span>
                                    <span
                                        v-if="player.role !== 'none'"
                                        class="flex-shrink-0 rounded-full px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider"
                                        :style="{ background: player.color + '20', color: player.color, border: '1px solid ' + player.color + '40' }"
                                    >{{ getRoleLabel(player.role) }}</span>
                                    <div class="flex flex-shrink-0 items-center gap-1.5">
                                        <span class="flex items-center gap-0.5 text-[10px] font-bold text-zinc-500">
                                            <span class="material-symbols" style="font-size:11px">route</span>{{ player.path.length }}
                                        </span>
                                        <span class="flex items-center gap-0.5 text-[10px] font-bold text-zinc-500">
                                            <span class="material-symbols" style="font-size:11px">blur_on</span>{{ player.utilities.length }}
                                        </span>
                                    </div>
                                    <span
                                        class="material-symbols flex-shrink-0 text-zinc-600 transition-transform duration-200"
                                        style="font-size:14px"
                                        :style="{ transform: selectedSlot === slot ? 'rotate(180deg)' : 'rotate(0deg)' }"
                                        aria-hidden="true"
                                    >expand_more</span>
                                </div>
                            </div>

                            <div
                                v-if="selectedSlot === slot"
                                class="flex flex-col border-t border-zinc-700/40 bg-black/25"
                                @click.stop
                            >
                                <!-- Role -->
                                <div class="px-3 pt-2.5 pb-2">
                                    <p class="mb-1.5 text-[8px] font-black uppercase tracking-widest text-zinc-600">Role</p>
                                    <div class="flex flex-wrap gap-1">
                                        <button
                                            v-for="r in PLAYER_ROLES"
                                            :key="r.key"
                                            type="button"
                                            class="rounded-md border px-2 py-0.5 text-[9px] font-black uppercase tracking-widest transition-all"
                                            :class="player.role === r.key
                                                ? 'border-primary/60 bg-primary/10 text-primary'
                                                : 'border-zinc-700 bg-zinc-800/50 text-zinc-500 hover:border-zinc-600 hover:text-zinc-300'"
                                            @click="setPlayerRole(slot, r.key)"
                                        >{{ r.label }}</button>
                                    </div>
                                </div>
                                <div class="h-px w-full bg-zinc-700/30" />

                                <!-- Path -->
                                <div class="px-3 pt-2.5 pb-2">
                                    <p class="mb-1.5 text-[8px] font-black uppercase tracking-widest text-zinc-600">Path</p>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md border text-xs transition-all"
                                            :class="activeMode === 'path'
                                                ? 'border-primary/60 bg-primary/15 text-primary'
                                                : 'border-zinc-700 bg-zinc-800/60 text-zinc-400 hover:border-zinc-600 hover:text-white'"
                                            :aria-pressed="activeMode === 'path'"
                                            title="Draw path"
                                            @click="togglePathMode"
                                        >
                                            <span class="material-symbols" style="font-size:14px">route</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-7 flex-shrink-0 items-center gap-1 rounded-md border px-2 transition-all"
                                            :class="lastWaypointSpeed(slot) === 'walk'
                                                ? 'border-[#4FC3F7]/50 bg-[#4FC3F7]/10 text-[#4FC3F7]'
                                                : 'border-zinc-700 bg-zinc-800/60 text-zinc-400 hover:border-zinc-600 hover:text-white'"
                                            @click="toggleLastWaypointSpeed(slot)"
                                        >
                                            <span class="material-symbols" style="font-size:13px">{{ lastWaypointSpeed(slot) === 'walk' ? 'directions_walk' : 'directions_run' }}</span>
                                            <span class="text-[9px] font-black uppercase">{{ lastWaypointSpeed(slot) }}</span>
                                        </button>
                                        <div class="flex-1" />
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md border border-zinc-700 bg-zinc-800/60 text-zinc-400 transition-all hover:border-zinc-600 hover:text-white disabled:opacity-40"
                                            :disabled="player.path.length === 0"
                                            @click="removeLastWaypoint(slot)"
                                            title="Undo waypoint"
                                        >
                                            <span class="material-symbols" style="font-size:14px">undo</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md border border-zinc-700 bg-zinc-800/60 text-zinc-400 transition-all hover:border-red-500/40 hover:text-red-400 disabled:opacity-40"
                                            :disabled="player.path.length === 0"
                                            @click="clearPath(slot)"
                                            title="Clear path"
                                        >
                                            <span class="material-symbols" style="font-size:14px">delete_sweep</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="h-px w-full bg-zinc-700/30" />

                                <!-- Utility -->
                                <div class="px-3 pt-2.5 pb-3">
                                    <p class="mb-1.5 text-[8px] font-black uppercase tracking-widest text-zinc-600">Utility</p>
                                    <div class="mb-2 flex flex-wrap gap-1">
                                        <button
                                            v-for="ut in UTILITY_TYPES"
                                            :key="ut.key"
                                            type="button"
                                            class="flex items-center gap-1 rounded-md border px-2 py-1 text-[9px] font-bold transition-all"
                                            :class="activeMode === 'util_land' && pendingUtilType === ut.key
                                                ? ''
                                                : 'border-zinc-700 bg-zinc-800/60 text-zinc-400 hover:border-zinc-600 hover:text-white'"
                                            :style="activeMode === 'util_land' && pendingUtilType === ut.key
                                                ? { borderColor: ut.color, color: ut.color, background: ut.color + '18' }
                                                : {}"
                                            @click="startAddingUtil(slot, ut.key)"
                                        >
                                            <div class="h-1.5 w-1.5 flex-shrink-0 rounded-full" :style="{ background: ut.color }" aria-hidden="true" />
                                            {{ ut.label }}
                                        </button>
                                    </div>
                                    <div v-if="player.utilities.length" class="flex flex-col gap-1">
                                        <div
                                            v-for="util in player.utilities"
                                            :key="util.id"
                                            class="flex items-center gap-1.5 rounded-lg bg-zinc-950/60 px-2 py-1.5"
                                        >
                                            <div class="h-2 w-2 flex-shrink-0 rounded-full" :style="{ background: UTIL_COLORS[util.type] }" />
                                            <span class="min-w-0 flex-1 truncate text-[10px] font-bold capitalize" :style="{ color: UTIL_COLORS[util.type] }">{{ util.type }}</span>
                                            <label class="flex flex-shrink-0 items-center gap-0.5 rounded border border-primary/25 bg-primary/10 px-1.5 py-0.5">
                                                <input
                                                    type="number"
                                                    class="w-8 border-none bg-transparent text-right text-[10px] font-black text-primary outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                                                    :value="util.throwAt"
                                                    min="0"
                                                    max="300"
                                                    step="0.5"
                                                    @change="(e) => updateUtilThrowAt(slot, util.id, +(e.target as HTMLInputElement).value)"
                                                />
                                                <span class="text-[9px] font-bold text-primary/60">s</span>
                                            </label>
                                            <button
                                                type="button"
                                                class="flex-shrink-0 text-zinc-600 transition-colors hover:text-red-400"
                                                @click="removeUtil(slot, util.id)"
                                                :aria-label="`Remove ${util.type}`"
                                            >
                                                <span class="material-symbols" style="font-size:12px">close</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- ═══════════ CANVAS COLUMN ════════════════════════════════ -->
                <div class="relative flex flex-1 flex-col overflow-hidden">
                    <!-- Status bar -->
                    <div class="flex h-8 flex-shrink-0 items-center gap-2 border-b border-zinc-800 bg-zinc-900 px-3">
                        <template v-if="activeMode === 'path' && selectedPlayer">
                            <span class="material-symbols text-primary" style="font-size:12px">route</span>
                            <span class="text-[11px] text-zinc-500">
                                Placing <b class="text-zinc-200">{{ selectedPlayer.name }}</b>
                                <span class="ml-1.5 text-zinc-600">
                                    <kbd class="rounded border border-zinc-700 bg-zinc-800 px-1 text-[9px]">RMB</kbd> undo
                                </span>
                            </span>
                        </template>
                        <span v-else class="text-[11px] text-zinc-600">Select a player to start</span>
                    </div>

                    <!-- Canvas -->
                    <div
                        class="relative flex-1 overflow-hidden bg-zinc-950 touch-none"
                        :class="canvasCursorClass"
                        @contextmenu.prevent="onCanvasRightClick"
                    >
                        <img
                            src="/images/demo/de_mirage.svg"
                            alt="de_mirage map"
                            loading="lazy"
                            decoding="async"
                            class="pointer-events-none absolute inset-0 h-full w-full object-contain opacity-80"
                        />
                        <svg
                            ref="stage"
                            viewBox="0 0 100 100"
                            preserveAspectRatio="xMidYMid meet"
                            class="absolute inset-0 h-full w-full"
                            role="application"
                            aria-label="Strat builder canvas"
                            @pointerdown="onCanvasClick"
                        >
                            <!-- Paths -->
                            <g v-for="(p, idx) in players" :key="`paths-${p.slot}`">
                                <path
                                    v-if="p.path.length >= 2"
                                    :d="pathFor(p.path)"
                                    fill="none"
                                    :stroke="p.color"
                                    :stroke-opacity="selectedSlot === idx ? 1 : 0.55"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :style="{ strokeWidth: selectedSlot === idx ? '2.2' : '1.6' }"
                                    vector-effect="non-scaling-stroke"
                                />
                                <g v-for="(w, wi) in p.path" :key="`${p.slot}-w-${wi}`">
                                    <circle
                                        :cx="w.x"
                                        :cy="w.y"
                                        r="0.9"
                                        :fill="p.color"
                                        :fill-opacity="selectedSlot === idx ? 1 : 0.55"
                                    />
                                </g>
                            </g>

                            <!-- Utilities -->
                            <g v-for="p in players" :key="`utils-${p.slot}`">
                                <g v-for="u in p.utilities" :key="u.id">
                                    <circle
                                        :cx="u.landPos.x"
                                        :cy="u.landPos.y"
                                        :r="u.type === 'smoke' ? 4 : 2.2"
                                        :fill="UTIL_COLORS[u.type]"
                                        :fill-opacity="isUtilActive(u, currentSecond) ? 0.32 : 0.08"
                                        :stroke="UTIL_COLORS[u.type]"
                                        :stroke-opacity="isUtilActive(u, currentSecond) ? 0.9 : 0.4"
                                        stroke-width="0.4"
                                        vector-effect="non-scaling-stroke"
                                    />
                                    <text
                                        :x="u.landPos.x"
                                        :y="u.landPos.y + 0.6"
                                        text-anchor="middle"
                                        font-family="JetBrains Mono"
                                        font-size="1.6"
                                        font-weight="500"
                                        :fill="UTIL_COLORS[u.type]"
                                        fill-opacity="0.9"
                                        style="text-transform: uppercase;"
                                    >{{ u.type === 'molotov' ? 'M' : u.type === 'flash' ? 'F' : u.type === 'smoke' ? 'S' : 'HE' }}</text>
                                </g>
                            </g>

                            <!-- Live player arrows. Same shape as the product's ARROW_PATH, rotated by yaw. -->
                            <g v-for="(p, idx) in players" :key="`live-${p.slot}`">
                                <template v-if="p.path.length > 0">
                                    <g
                                        v-if="playerPosAtTime(p, currentSecond)"
                                        :transform="`translate(${(playerPosAtTime(p, currentSecond) as { x: number; y: number }).x} ${(playerPosAtTime(p, currentSecond) as { x: number; y: number }).y}) rotate(${(playerYawAtTime(p, currentSecond) * 180) / Math.PI})`"
                                        :style="{ filter: selectedSlot === idx ? `drop-shadow(0 0 1.2px ${p.color})` : 'none' }"
                                    >
                                        <path
                                            :d="ARROW_PATH"
                                            :fill="p.color"
                                            :fill-opacity="selectedSlot === idx ? 1 : 0.65"
                                        />
                                    </g>
                                    <!-- Player name + role labels above the arrow, screen-aligned (counter-rotated) -->
                                    <g
                                        v-if="playerPosAtTime(p, currentSecond)"
                                        :transform="`translate(${(playerPosAtTime(p, currentSecond) as { x: number; y: number }).x} ${(playerPosAtTime(p, currentSecond) as { x: number; y: number }).y - 3.4})`"
                                        :style="{ opacity: selectedSlot === idx ? 1 : 0.6 }"
                                    >
                                        <text
                                            text-anchor="middle"
                                            font-family="JetBrains Mono"
                                            :font-size="selectedSlot === idx ? 2 : 1.6"
                                            font-weight="700"
                                            :fill="p.color"
                                            style="paint-order: stroke; stroke: #18130e; stroke-width: 0.4;"
                                        >{{ p.name }}</text>
                                        <text
                                            v-if="p.role !== 'none'"
                                            y="1.8"
                                            text-anchor="middle"
                                            font-family="JetBrains Mono"
                                            :font-size="selectedSlot === idx ? 1.4 : 1.2"
                                            font-weight="500"
                                            :fill="p.color"
                                            :fill-opacity="selectedSlot === idx ? 0.8 : 0.5"
                                            style="paint-order: stroke; stroke: #18130e; stroke-width: 0.4; text-transform: uppercase;"
                                        >{{ getRoleLabel(p.role) }}</text>
                                    </g>
                                </template>
                            </g>

                            <!-- Empty state -->
                            <g v-if="isEmpty" pointer-events="none">
                                <text x="50" y="50" text-anchor="middle" font-family="JetBrains Mono" font-size="2" font-weight="500" fill="#a1a1aa" letter-spacing="0.15" style="text-transform: uppercase; paint-order: stroke; stroke: #09090b; stroke-width: 0.6;">Click the map to drop waypoints for {{ selectedPlayer?.name }}.</text>
                            </g>
                        </svg>

                        <!-- Utility teaser. Replaces in-canvas util placement on the landing. -->
                        <transition
                            enter-active-class="transition duration-200 ease-[var(--ease-quart)]"
                            enter-from-class="opacity-0 scale-[0.96]"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-150 ease-[var(--ease-quart)]"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="utilityTeaser"
                                class="absolute inset-0 z-30 flex items-center justify-center bg-zinc-950/85 p-6 backdrop-blur-sm"
                                @click="dismissTeaser"
                            >
                                <div
                                    class="relative w-full max-w-md overflow-hidden rounded-xl border bg-zinc-900 p-6"
                                    :style="{ borderColor: utilityTeaser.color + '60' }"
                                    role="dialog"
                                    aria-labelledby="util-teaser-title"
                                    @click.stop
                                >
                                    <button
                                        type="button"
                                        class="absolute right-3 top-3 flex h-7 w-7 items-center justify-center rounded text-zinc-500 transition-colors hover:bg-zinc-800 hover:text-white"
                                        aria-label="Close"
                                        @click="dismissTeaser"
                                    >
                                        <span class="material-symbols" style="font-size: 16px;">close</span>
                                    </button>
                                    <div class="flex flex-col gap-4">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex h-9 w-9 items-center justify-center rounded-md"
                                                :style="{ background: utilityTeaser.color + '20', color: utilityTeaser.color, border: '1px solid ' + utilityTeaser.color + '50' }"
                                                aria-hidden="true"
                                            >
                                                <span class="material-symbols" style="font-size: 18px;">{{ utilityTeaser.icon }}</span>
                                            </span>
                                            <div class="flex flex-col">
                                                <p class="font-mono text-[10px] font-black uppercase tracking-widest text-zinc-500">
                                                    {{ utilityTeaser.label }} placement
                                                </p>
                                                <h3
                                                    id="util-teaser-title"
                                                    class="text-base font-bold text-white"
                                                >
                                                    Throw arcs live in the full builder.
                                                </h3>
                                            </div>
                                        </div>
                                        <p class="text-sm text-zinc-400">
                                            Inside the product, you set a throw position and a land position. The Strat Builder simulates the arc, times the pop, and lingers the smoke for 18 seconds on the timeline. Open it to use {{ utilityTeaser.label.toLowerCase() }}s on this map.
                                        </p>
                                        <div class="flex gap-2">
                                            <a
                                                :href="openInProductCta.href"
                                                class="flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-xs font-black uppercase tracking-widest text-black transition-colors hover:bg-primary/90"
                                            >
                                                {{ user ? 'Open the builder' : 'Sign up to use' }}
                                            </a>
                                            <button
                                                type="button"
                                                class="rounded-md border border-zinc-700 bg-zinc-800 px-4 py-2 text-xs font-bold text-zinc-300 transition-colors hover:border-zinc-600 hover:text-white"
                                                @click="dismissTeaser"
                                            >
                                                Keep sketching
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- Timeline -->
                    <div class="flex h-14 flex-shrink-0 items-center gap-2 border-t border-zinc-800 bg-zinc-900 px-3">
                        <button
                            type="button"
                            class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border border-zinc-700 bg-zinc-800 text-primary transition-colors hover:bg-zinc-700 disabled:opacity-40"
                            :disabled="isEmpty"
                            :aria-label="isPlaying ? 'Pause' : 'Play'"
                            :aria-pressed="isPlaying"
                            @click="togglePlayback"
                        >
                            <span class="material-symbols text-xl">{{ isPlaying ? 'pause' : 'play_arrow' }}</span>
                        </button>
                        <button
                            type="button"
                            class="w-7 flex-shrink-0 cursor-pointer border-none bg-transparent text-[11px] font-black text-zinc-500 transition-colors hover:text-white"
                            @click="togglePlaybackSpeed"
                        >{{ playbackSpeed }}x</button>
                        <div
                            ref="scrubRef"
                            class="relative h-10 flex-1 cursor-pointer overflow-visible rounded-lg border border-zinc-700 bg-zinc-800"
                            @mousedown="startScrubDrag"
                            @mousemove="onScrubHover"
                            @mouseleave="scrubHoverX = null"
                        >
                            <div class="absolute inset-0 overflow-hidden rounded-lg">
                                <div class="absolute inset-0 flex flex-col justify-evenly py-0.5">
                                    <div v-for="(player, slot) in players" :key="slot" class="relative h-[5px]">
                                        <div
                                            v-if="player.path.length >= 2"
                                            class="absolute top-0 h-full min-w-[2px] rounded-sm border"
                                            :style="{
                                                left: `${(player.path[0].t / duration) * 100}%`,
                                                width: `${((player.path[player.path.length - 1].t - player.path[0].t) / duration) * 100}%`,
                                                background: player.color + '44',
                                                borderColor: player.color,
                                            }"
                                        />
                                        <span
                                            v-for="util in player.utilities"
                                            :key="util.id"
                                            class="absolute -top-0.5 select-none text-[7px] leading-none pointer-events-none"
                                            style="transform: translateX(-50%)"
                                            :style="{ left: `${(util.throwAt / duration) * 100}%`, color: UTIL_COLORS[util.type] }"
                                            aria-hidden="true"
                                        >▼</span>
                                    </div>
                                </div>
                                <div
                                    class="pointer-events-none absolute top-0 h-full w-0.5 bg-primary"
                                    style="transform: translateX(-50%); z-index: 5; box-shadow: 0 0 6px #ff8c00;"
                                    :style="{ left: `${(currentSecond / duration) * 100}%` }"
                                    aria-hidden="true"
                                />
                            </div>
                            <div
                                v-if="scrubHoverX !== null"
                                class="pointer-events-none absolute -top-7 z-20 whitespace-nowrap rounded border border-zinc-700 bg-zinc-800 px-1.5 py-0.5 text-[10px] font-black text-white"
                                style="transform: translateX(-50%)"
                                :style="{ left: scrubHoverX + 'px' }"
                            >{{ fmt(scrubHoverSeconds) }}</div>
                        </div>
                        <span class="min-w-[36px] flex-shrink-0 text-[11px] font-black tabular-nums text-zinc-500">{{ fmt(currentSecond) }}</span>
                    </div>
                </div>
            </div>
            </div>

            <div class="mt-12 flex flex-col items-start gap-4 sm:flex-row sm:items-center" data-reveal>
                <p class="max-w-[58ch] text-sm text-bone">
                    Inside the product: walk vs run speeds with auto-timed waypoints, util throw arcs, variations, scripted instructions, save into a stratbook tactic, export.
                </p>
                <Button as="a" :href="openInProductCta.href" variant="primary" size="md">
                    {{ openInProductCta.label }}
                </Button>
            </div>
        </Container>
    </section>
</template>
