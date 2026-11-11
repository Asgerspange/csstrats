import { ref, computed, type Ref } from 'vue';
import type {
    Player, Waypoint, Utility, Instruction, RoundPlanData, Variation,
    Vec2, MapMetadata, ActiveMode, SpeedType, UtilType, RoleKey,
    UtilTypeDef, RoleDef, DragState,
} from './types';

// ─── Constants ────────────────────────────────────────────────────────────────

export const PLAYER_COLORS: readonly string[] = [
    '#FF8C00', '#4FC3F7', '#81C784', '#F06292', '#CE93D8',
];

export const PLAYER_ROLES: readonly RoleDef[] = [
    { key: 'none',    label: '—',       icon: 'person'    },
    { key: 'igl',     label: 'IGL',     icon: 'psychology'},
    { key: 'entry',   label: 'Entry',   icon: 'bolt'      },
    { key: 'awp',     label: 'AWP',     icon: 'visibility'},
    { key: 'support', label: 'Support', icon: 'favorite'  },
    { key: 'lurk',    label: 'Lurk',    icon: 'mystery'   },
];

export const UTILITY_TYPES: readonly UtilTypeDef[] = [
    { key: 'smoke',   label: 'Smoke',   icon: 'blur_on',               color: '#90CAF9' },
    { key: 'flash',   label: 'Flash',   icon: 'flash_on',              color: '#FFF176' },
    { key: 'he',      label: 'HE',      icon: 'crisis_alert',          color: '#EF9A9A' },
    { key: 'molotov', label: 'Molotov', icon: 'local_fire_department', color: '#FFAB91' },
];

export const SMOKE_WORLD_RADIUS = 72;

const WALK_SPEED        = 130;
const RUN_SPEED         = 250;
const MAX_IMPORT_POINTS = 60;
const SERVER_TICK_RATE  = 64;

// ─── Unique ID ────────────────────────────────────────────────────────────────

let _uid = 1;
const uid = (): number => _uid++;

// ─── RDP ─────────────────────────────────────────────────────────────────────

function _perpDistance(pt: Vec2, a: Vec2, b: Vec2): number {
    const dx = b.x - a.x, dy = b.y - a.y;
    const len = Math.hypot(dx, dy);
    if (len === 0) return Math.hypot(pt.x - a.x, pt.y - a.y);
    return Math.abs(dx * (a.y - pt.y) - (a.x - pt.x) * dy) / len;
}

function _rdp(points: Vec2[], epsilon: number): Vec2[] {
    if (points.length <= 2) return points.slice();
    let maxDist = 0, maxIdx = 0;
    for (let i = 1; i < points.length - 1; i++) {
        const d = _perpDistance(points[i], points[0], points[points.length - 1]);
        if (d > maxDist) { maxDist = d; maxIdx = i; }
    }
    if (maxDist > epsilon) {
        return [..._rdp(points.slice(0, maxIdx + 1), epsilon).slice(0, -1), ..._rdp(points.slice(maxIdx), epsilon)];
    }
    return [points[0], points[points.length - 1]];
}

function decimatePath(points: Vec2[], maxPoints: number): Vec2[] {
    if (points.length <= maxPoints) return points.slice();
    let eps = 1, result: Vec2[] = points;
    while (result.length > maxPoints && eps < 100_000) { result = _rdp(points, eps); eps *= 1.5; }
    return result;
}

// ─── Deep clone helpers ───────────────────────────────────────────────────────

function clonePlayers(players: Player[]): Player[] {
    return JSON.parse(JSON.stringify(players));
}

/** Clone players and trim each path to waypoints at or before time t.
 *  Utilities and instructions after t are also trimmed. */
function clonePlayersUpToTime(players: Player[], t: number): Player[] {
    return players.map(p => ({
        ...p,
        path:         JSON.parse(JSON.stringify(p.path.filter(w => w.t <= t))),
        utilities:    JSON.parse(JSON.stringify(p.utilities.filter(u => u.throwAt <= t))),
        instructions: JSON.parse(JSON.stringify(p.instructions.filter(i => i.t <= t))),
    }));
}

// ─── Player factory ───────────────────────────────────────────────────────────

function makePlayer(slot: number, name?: string): Player {
    return {
        slot,
        name:         name ?? `Player ${slot + 1}`,
        steamid:      null,
        color:        PLAYER_COLORS[slot] ?? '#FFFFFF',
        role:         'none',
        path:         [],
        utilities:    [],
        instructions: [],
    };
}

function makeVariation(name: string, players?: Player[]): Variation {
    return {
        id:      uid(),
        name,
        players: players ?? Array.from({ length: 5 }, (_, i) => makePlayer(i)),
        branchedFromVariationId: null,
        branchedAtTime:          null,
    };
}

// ─── Composable ───────────────────────────────────────────────────────────────

export interface UseRoundPlannerOptions {
    mapMetadata: Ref<MapMetadata | null>;
}

export interface ImportDemoOptions {
    getTickView: (round: any, tickIndex: number) => any;
    startTick?:  number;
    side?:       'T' | 'CT';
}

export function useRoundPlanner({ mapMetadata }: UseRoundPlannerOptions) {

    // ── Plan meta ─────────────────────────────────────────────────────────────
    const planName    = ref('New Round Plan');
    const planNotes   = ref('');
    const side        = ref<'T' | 'CT'>('T');
    const duration    = ref(115);
    const isDirty     = ref(false);
    const savedPlanId = ref<number | null>(null);

    // ── Variations ────────────────────────────────────────────────────────────
    const variations        = ref<Variation[]>([makeVariation('Default')]);
    const activeVariationId = ref<number>(variations.value[0].id);

    const activeVariation = computed(() =>
        variations.value.find(v => v.id === activeVariationId.value) ?? variations.value[0]
    );

    // Players proxy — always points to active variation's players
    const players = computed({
        get: () => activeVariation.value.players,
        set: (val: Player[]) => { activeVariation.value.players = val; },
    });

    // ── Add / rename / delete variations ──────────────────────────────────────
    function addVariation(name = 'Variation ' + (variations.value.length + 1)): void {
        const v = makeVariation(name, clonePlayers(players.value));
        variations.value.push(v);
        activeVariationId.value = v.id;
        isDirty.value = true;
    }

    function renameVariation(id: number, name: string): void {
        const v = variations.value.find(v => v.id === id);
        if (v) { v.name = name; isDirty.value = true; }
    }

    function deleteVariation(id: number): void {
        if (variations.value.length <= 1) return; // always keep at least one
        variations.value = variations.value.filter(v => v.id !== id);
        if (activeVariationId.value === id) {
            activeVariationId.value = variations.value[0].id;
        }
        isDirty.value = true;
    }

    function switchVariation(id: number): void {
        activeVariationId.value = id;
        // Reset playhead to start when switching
        currentSecond.value = 0;
    }

    /**
     * Branch — create a new variation that copies all players' paths up to
     * `branchTime`, then lets you continue drawing from that point.
     */
    function branchVariationFromTime(branchTime: number, name?: string): void {
        const trimmed = clonePlayersUpToTime(players.value, branchTime);
        const v: Variation = {
            id:                    uid(),
            name:                  name ?? `Branch @${branchTime.toFixed(1)}s`,
            players:               trimmed,
            branchedFromVariationId: activeVariationId.value,
            branchedAtTime:        branchTime,
        };
        variations.value.push(v);
        activeVariationId.value = v.id;
        currentSecond.value     = branchTime;
        isDirty.value           = true;
        saveHistory();
    }

    // ── Editor state ──────────────────────────────────────────────────────────
    const selectedPlayerSlot = ref(0);
    const activeMode         = ref<ActiveMode>('path');
    const pendingUtilType    = ref<UtilType | null>(null);
    const pendingUtilThrow   = ref<Vec2 | null>(null);
    const pendingUtilThrowAt = ref<number | null>(null);  // locked throw time from waypoint
    const draggingWaypoint   = ref<DragState | null>(null);

    const selectedPlayer = computed(() => players.value[selectedPlayerSlot.value]);

    // ── Playback ──────────────────────────────────────────────────────────────
    const currentSecond = ref(0);
    const isPlaying     = ref(false);
    const playbackSpeed = ref(1);

    let _rafHandle: number | null = null, _lastTimestamp: number | null = null;

    function _rafLoop(timestamp: number): void {
        if (!isPlaying.value) return;
        if (_lastTimestamp !== null) {
            const delta = (timestamp - _lastTimestamp) / 1000;
            currentSecond.value = Math.min(duration.value, currentSecond.value + delta * playbackSpeed.value);
            if (currentSecond.value >= duration.value) {
                currentSecond.value = duration.value;
                isPlaying.value = false; _lastTimestamp = null; _rafHandle = null; return;
            }
        }
        _lastTimestamp = timestamp;
        _rafHandle = requestAnimationFrame(_rafLoop);
    }

    function play(): void {
        if (isPlaying.value) return;
        if (currentSecond.value >= duration.value) currentSecond.value = 0;
        isPlaying.value = true; _lastTimestamp = null;
        _rafHandle = requestAnimationFrame(_rafLoop);
    }

    function pause(): void {
        isPlaying.value = false; _lastTimestamp = null;
        if (_rafHandle !== null) { cancelAnimationFrame(_rafHandle); _rafHandle = null; }
    }

    function togglePlayback():      void { isPlaying.value ? pause() : play(); }
    function togglePlaybackSpeed(): void {
        const speeds = [1, 2, 0.5] as const;
        const idx = speeds.indexOf(playbackSpeed.value as 1 | 2 | 0.5);
        playbackSpeed.value = speeds[(idx + 1) % speeds.length];
    }
    function seekTo(seconds: number): void {
        currentSecond.value = Math.max(0, Math.min(duration.value, seconds));
    }

    // ── History ───────────────────────────────────────────────────────────────
    const history = ref<string[]>([]);
    const historyIndex = ref(-1);

    // Snapshot includes ALL variations, not just the active one
    function _snapshot(): string { return JSON.stringify(variations.value); }

    function saveHistory(): void {
        if (historyIndex.value < history.value.length - 1) {
            history.value = history.value.slice(0, historyIndex.value + 1);
        }
        history.value.push(_snapshot());
        historyIndex.value++;
        isDirty.value = true;
    }

    function undo(): void {
        if (historyIndex.value > 0) {
            historyIndex.value--;
            variations.value = JSON.parse(history.value[historyIndex.value]);
        }
    }

    function redo(): void {
        if (historyIndex.value < history.value.length - 1) {
            historyIndex.value++;
            variations.value = JSON.parse(history.value[historyIndex.value]);
        }
    }

    saveHistory();

    // ── Player management ──────────────────────────────────────────────────────
    function setPlayerName(slot: number, name: string):   void { players.value[slot].name = name; isDirty.value = true; }
    function setPlayerColor(slot: number, color: string): void { players.value[slot].color = color; isDirty.value = true; }
    function setPlayerRole(slot: number, role: RoleKey):  void { players.value[slot].role = role; isDirty.value = true; }
    function setPlayerFromTeam(slot: number, p: { name: string; steamid?: string }): void {
        players.value[slot].name    = p.name;
        players.value[slot].steamid = p.steamid ?? null;
        isDirty.value = true;
    }
    function resetPlayer(slot: number): void {
        players.value[slot] = makePlayer(slot, players.value[slot].name);
        saveHistory();
    }

    // ── Path ──────────────────────────────────────────────────────────────────
    function _recalcTimings(path: Waypoint[]): void {
        for (let i = 1; i < path.length; i++) {
            const prev = path[i - 1], curr = path[i];
            const dist = Math.hypot(curr.x - prev.x, curr.y - prev.y);
            curr.t = prev.t + (prev.waitAfter ?? 0) + dist / (curr.speed === 'walk' ? WALK_SPEED : RUN_SPEED);
        }
    }

    /**
     * After path timings change (wait added, waypoint dragged, speed toggled),
     * resync every utility's throwAt and every instruction's t to match the
     * current departure time of the waypoint they're linked to.
     *
     * Departure time = path[waypointIndex].t + path[waypointIndex].waitAfter
     *
     * Utilities linked via fromWaypointIndex are moved to that departure time.
     * Utilities with no fromWaypointIndex are left alone (manually placed).
     *
     * Instructions don't have a waypointIndex — we find the closest waypoint
     * whose departure time is <= the instruction's current time, and shift it
     * by the same delta the path shifted.
     */
    function _resyncUtilsAndInstructions(slot: number): void {
        const player = players.value[slot];
        const path   = player.path;

        // Resync utilities
        player.utilities.forEach(util => {
            const idx = util.fromWaypointIndex;
            if (idx === null || idx === undefined) return;
            const wp = path[idx];
            if (!wp) return;
            const departureT = wp.t + (wp.waitAfter ?? 0);
            util.throwAt  = Math.round(departureT * 10) / 10;
            util.throwPos = { x: wp.x, y: wp.y };
        });

        // Resync instructions — find the nearest waypoint departure at or before
        // the instruction's time, then pin the instruction to that departure.
        player.instructions.forEach(instr => {
            // Find the waypoint the player was at (or waiting at) when this note fires
            let bestWp: Waypoint | null = null;
            for (const wp of path) {
                const departureT = wp.t + (wp.waitAfter ?? 0);
                if (departureT <= instr.t + 0.001) bestWp = wp;
            }
            if (bestWp) {
                const departureT = bestWp.t + (bestWp.waitAfter ?? 0);
                instr.t = Math.round(departureT * 10) / 10;
            }
        });
        player.instructions.sort((a, b) => a.t - b.t);
    }

    function setWaypointWait(slot: number, index: number, seconds: number): void {
        const path = players.value[slot].path;
        if (!path[index]) return;
        path[index].waitAfter = Math.max(0, Math.round(seconds * 10) / 10);
        _recalcTimings(path);
        _resyncUtilsAndInstructions(slot);
        saveHistory();
    }

    function addWaypoint(slot: number, worldX: number, worldY: number, speed: SpeedType = 'run'): void {
        const path  = players.value[slot].path;
        const point: Waypoint = { x: worldX, y: worldY, speed, t: 0, waitAfter: 0 };
        if (path.length > 0) {
            const prev = path[path.length - 1];
            // Travel starts after the previous waypoint's wait time
            point.t = prev.t + (prev.waitAfter ?? 0) + Math.hypot(worldX - prev.x, worldY - prev.y) / (speed === 'walk' ? WALK_SPEED : RUN_SPEED);
        }
        path.push(point);
        const newEnd = point.t + 2;
        if (newEnd > duration.value) duration.value = Math.min(Math.ceil(newEnd), 300);
        currentSecond.value = point.t;
        saveHistory();
    }

    function removeLastWaypoint(slot: number): void {
        if (players.value[slot].path.length > 0) { players.value[slot].path.pop(); saveHistory(); }
    }

    function clearPath(slot: number): void { players.value[slot].path = []; saveHistory(); }

    function toggleLastWaypointSpeed(slot: number): void {
        const path = players.value[slot].path;
        if (!path.length) return;
        path[path.length - 1].speed = path[path.length - 1].speed === 'run' ? 'walk' : 'run';
        _recalcTimings(path);
        _resyncUtilsAndInstructions(slot);
        saveHistory();
    }

    // ── Bulk operations (multi-select) ─────────────────────────────────────────
    // Apply a transform to multiple slots at once, saving a single history
    // entry. Each function takes the list of slots to affect.
    function setSpeedForSlots(slots: number[], speed: SpeedType): void {
        for (const slot of slots) {
            const path = players.value[slot]?.path;
            if (!path?.length) continue;
            for (const wp of path) wp.speed = speed;
            _recalcTimings(path);
            _resyncUtilsAndInstructions(slot);
        }
        saveHistory();
    }

    function clearPathsForSlots(slots: number[]): void {
        for (const slot of slots) {
            const p = players.value[slot];
            if (!p) continue;
            p.path = [];
            p.utilities = [];
            p.instructions = [];
        }
        saveHistory();
    }

    // Nudge every waypoint of every selected player by a game-space delta.
    // Useful for "shift the whole execute 2 units left" without redrawing.
    function shiftPathsForSlots(slots: number[], dx: number, dy: number): void {
        for (const slot of slots) {
            const p = players.value[slot];
            if (!p) continue;
            for (const wp of p.path) { wp.x += dx; wp.y += dy; }
            for (const u of p.utilities) {
                u.throwPos.x += dx; u.throwPos.y += dy;
                u.landPos.x  += dx; u.landPos.y  += dy;
            }
        }
        saveHistory();
    }

    // ── Waypoint drag ─────────────────────────────────────────────────────────
    function startDragWaypoint(slot: number, index: number): void { draggingWaypoint.value = { slot, index }; }

    function moveDragWaypoint(worldX: number, worldY: number): void {
        if (!draggingWaypoint.value) return;
        const { slot, index } = draggingWaypoint.value;
        const path = players.value[slot].path;
        if (!path[index]) return;
        path[index].x = worldX; path[index].y = worldY;
        if (index > 0) {
            const prev = path[index - 1], curr = path[index];
            curr.t = prev.t + (prev.waitAfter ?? 0) + Math.hypot(curr.x - prev.x, curr.y - prev.y) / (curr.speed === 'walk' ? WALK_SPEED : RUN_SPEED);
        }
        for (let i = index + 1; i < path.length; i++) {
            const prev = path[i - 1], curr = path[i];
            curr.t = prev.t + (prev.waitAfter ?? 0) + Math.hypot(curr.x - prev.x, curr.y - prev.y) / (curr.speed === 'walk' ? WALK_SPEED : RUN_SPEED);
        }
        players.value[slot].utilities.forEach(util => {
            if (util.fromWaypointIndex === index) {
                util.throwPos = { x: worldX, y: worldY };
                util.throwAt  = Math.round(path[index].t * 10) / 10;
            }
        });
    }

    function endDragWaypoint(): void {
        if (!draggingWaypoint.value) return;
        const slot = draggingWaypoint.value.slot;
        draggingWaypoint.value = null;
        _resyncUtilsAndInstructions(slot);
        saveHistory();
    }

    // ── Utility ───────────────────────────────────────────────────────────────
    function startAddingUtil(slot: number, type: UtilType, lockedThrowAt?: number): void {
        selectedPlayerSlot.value  = slot;
        pendingUtilType.value     = type;
        pendingUtilThrowAt.value  = lockedThrowAt ?? currentSecond.value;
        activeMode.value          = 'util_land';
    }

    function setUtilThrowPos(worldX: number, worldY: number): void {
        pendingUtilThrow.value = { x: worldX, y: worldY }; activeMode.value = 'util_land';
    }

    function setUtilLandPos(worldX: number, worldY: number): void {
        const slot      = selectedPlayerSlot.value;
        const player    = players.value[slot];
        // Use the locked throw time (set when util mode was activated).
        const rawThrowTime = pendingUtilThrowAt.value ?? currentSecond.value;

        // If rawThrowTime falls inside a waypoint's wait window, snap the
        // throwAt to departure time so the utility fires when the player leaves.
        const path = player.path;
        let nearestIdx  = 0, nearestDist = Infinity;
        let throwTime   = rawThrowTime;
        for (let i = 0; i < path.length; i++) {
            const wp         = path[i];
            const departureT = wp.t + (wp.waitAfter ?? 0);
            // Snap throwAt to departure if we're placing during the wait window
            if (rawThrowTime >= wp.t && rawThrowTime < departureT) {
                throwTime  = departureT;
                nearestIdx = i;
                nearestDist = 0;
                break;
            }
            const d = Math.hypot(wp.x - (getPlayerPosAtTime(slot, rawThrowTime)?.x ?? 0),
                                  wp.y - (getPlayerPosAtTime(slot, rawThrowTime)?.y ?? 0));
            if (d < nearestDist) { nearestDist = d; nearestIdx = i; }
        }

        const throwPos = getPlayerPosAtTime(slot, throwTime);
        if (!throwPos) { activeMode.value = 'path'; return; }

        player.utilities.push({
            id:                uid(),
            type:              pendingUtilType.value!,
            throwPos:          { x: throwPos.x, y: throwPos.y },
            landPos:           { x: worldX, y: worldY },
            throwAt:           Math.round(throwTime * 10) / 10,
            fromWaypointIndex: nearestIdx,
        });
        pendingUtilType.value    = null;
        pendingUtilThrowAt.value = null;
        activeMode.value         = 'path';
        saveHistory();
    }

    function removeUtil(slot: number, utilId: number): void {
        players.value[slot].utilities = players.value[slot].utilities.filter(u => u.id !== utilId);
        saveHistory();
    }
    function updateUtilThrowAt(slot: number, utilId: number, seconds: number): void {
        const util = players.value[slot].utilities.find(u => u.id === utilId);
        if (util) { util.throwAt = seconds; isDirty.value = true; }
    }
    function cancelUtil(): void { pendingUtilType.value = null; pendingUtilThrowAt.value = null; activeMode.value = 'path'; }

    // ── Instructions ──────────────────────────────────────────────────────────
    function addInstruction(slot: number, text: string, t: number | null = null): void {
        let time = t ?? currentSecond.value;
        // Snap instruction time to departure if placed during a wait window,
        // so the note appears when the player actually moves.
        const path = players.value[slot]?.path ?? [];
        for (const wp of path) {
            const departureT = wp.t + (wp.waitAfter ?? 0);
            if (time >= wp.t && time < departureT) { time = departureT; break; }
        }
        players.value[slot].instructions.push({ id: uid(), t: Math.round(time * 10) / 10, text });
        players.value[slot].instructions.sort((a, b) => a.t - b.t);
        saveHistory();
    }
    function removeInstruction(slot: number, instrId: number): void {
        players.value[slot].instructions = players.value[slot].instructions.filter(i => i.id !== instrId);
        saveHistory();
    }
    function updateInstruction(slot: number, instrId: number, patch: Partial<Instruction>): void {
        const instr = players.value[slot].instructions.find(i => i.id === instrId);
        if (instr) { Object.assign(instr, patch); players.value[slot].instructions.sort((a, b) => a.t - b.t); isDirty.value = true; }
    }

    // ── Queries ───────────────────────────────────────────────────────────────
    function getPlayerPosAtTime(slot: number, t: number): Vec2 | null {
        const path = players.value[slot]?.path;
        if (!path?.length) return null;
        if (path.length === 1) return { x: path[0].x, y: path[0].y };
        if (t <= path[0].t) return { x: path[0].x, y: path[0].y };

        const lastPt = path[path.length - 1];
        if (t >= lastPt.t + (lastPt.waitAfter ?? 0)) return { x: lastPt.x, y: lastPt.y };

        for (let i = 0; i < path.length; i++) {
            const curr = path[i];
            const departureT = curr.t + (curr.waitAfter ?? 0);

            // Player is waiting at this waypoint
            if (t >= curr.t && t < departureT) {
                return { x: curr.x, y: curr.y };
            }

            // Player is moving toward next waypoint
            if (i + 1 < path.length && t >= departureT && t < path[i + 1].t) {
                const p1   = curr;
                const p2   = path[i + 1];
                const span = p2.t - departureT;
                if (span <= 0) return { x: p2.x, y: p2.y };
                const frac = (t - departureT) / span;
                const p0   = i > 0 ? path[i - 1] : p1;
                const p3   = i + 2 < path.length ? path[i + 2] : p2;
                const t2   = frac * frac, t3 = t2 * frac;
                return {
                    x: 0.5 * ((2 * p1.x) + (-p0.x + p2.x) * frac + (2 * p0.x - 5 * p1.x + 4 * p2.x - p3.x) * t2 + (-p0.x + 3 * p1.x - 3 * p2.x + p3.x) * t3),
                    y: 0.5 * ((2 * p1.y) + (-p0.y + p2.y) * frac + (2 * p0.y - 5 * p1.y + 4 * p2.y - p3.y) * t2 + (-p0.y + 3 * p1.y - 3 * p2.y + p3.y) * t3),
                };
            }
        }
        return { x: lastPt.x, y: lastPt.y };
    }

    function getPlayerInstructionAtTime(slot: number, t: number): Instruction | null {
        const instrs = players.value[slot]?.instructions;
        if (!instrs?.length) return null;
        let active: Instruction | null = null;
        for (const instr of instrs) { if (instr.t <= t) active = instr; else break; }
        return active;
    }

    function getActiveUtilsAtTime(slot: number, t: number): Utility[] {
        return (players.value[slot]?.utilities ?? []).filter(u => { const e = t - u.throwAt; return e >= 0 && e <= 3; });
    }

    // ── Import ────────────────────────────────────────────────────────────────
    // Imports a parsed demo round (the columnar format produced by
    // useRoundData / RoundLoader.worker) into the active variation: builds one
    // waypoint path per player on the chosen side, plus thrown utilities.
    //
    // `getTickView` is the columnar→view assembler from the 2D demo viewer; we
    // accept it as a dependency so this composable stays decoupled from the
    // demo pipeline's internals.
    function importFromDemoRound(
        round: any,
        teamRoster: { name: string; steamid: string | null }[] = [],
        opts: ImportDemoOptions = {} as ImportDemoOptions,
    ): void {
        if (!round?.tickNumbers?.length || !round?.players?.count) return;
        const tickNumbers = round.tickNumbers as Int32Array;
        const firstTick = opts.startTick ?? tickNumbers[0];
        const sideTeam  = (opts.side ?? side.value) === 'T' ? 't' : 'ct';
        const view      = opts.getTickView;
        if (typeof view !== 'function') return;

        // Sample every Nth tick — 64Hz ticks at SAMPLE_EVERY=4 ≈ 16Hz, plenty
        // for a tactic path while keeping decimation input small.
        const SAMPLE_EVERY = 4;
        const playerDense: Record<string, { name: string; team: string; points: (Vec2 & { t: number })[] }> = {};
        for (let i = 0; i < tickNumbers.length; i += SAMPLE_EVERY) {
            const tv = view(round, i);
            if (!tv?.players?.length) continue;
            const t = (tickNumbers[i] - firstTick) / SERVER_TICK_RATE;
            for (const p of tv.players) {
                if (!p?.steamid) continue;
                if (!playerDense[p.steamid]) playerDense[p.steamid] = { name: p.name ?? 'Player', team: p.team_name ?? '', points: [] };
                playerDense[p.steamid].points.push({ x: p.X, y: p.Y, t });
            }
        }
        const teamPlayers = Object.entries(playerDense)
            .map(([steamid, data]) => ({ steamid, ...data }))
            .filter(p => p.team === sideTeam)
            .slice(0, 5);
        if (!teamPlayers.length) return;

        // Reset the active variation's players, then fill the side's 5 slots.
        for (let i = 0; i < 5; i++) players.value[i] = makePlayer(i);
        teamPlayers.forEach((tp, slot) => {
            const rosterMatch = teamRoster.find(r => r.steamid === tp.steamid);
            const decimated = decimatePath(tp.points, MAX_IMPORT_POINTS);
            const path: Waypoint[] = decimated.map((p: any) => ({
                x: p.x, y: p.y,
                t: p.t ?? 0,
                speed: 'run' as SpeedType,
                waitAfter: 0,
            }));
            // Recompute timing from geometry so the imported path plays back
            // at the planner's run/walk speeds (imported ticks are real time).
            _recalcTimings(path);
            players.value[slot] = {
                slot,
                name:     rosterMatch?.name ?? tp.name,
                steamid:  tp.steamid,
                color:    PLAYER_COLORS[slot] ?? '#FFFFFF',
                role:     'none',
                path,
                utilities: [],
                instructions: [],
            };
        });

        // Utilities — one entry per thrown grenade (skip per-frame flash
        // effect artifacts). Throw pos = grenade origin; land pos = final
        // trajectory sample.
        const grenadeList: any[] = round.grenades ? (Array.isArray(round.grenades) ? round.grenades : Object.values(round.grenades)) : [];
        const typeMap: Record<string, UtilType> = {
            smoke_grenade: 'smoke', smoke: 'smoke',
            flashbang: 'flash', he_grenade: 'he',
            molotov: 'molotov', incendiary_grenade: 'molotov',
        };
        for (const g of grenadeList) {
            if (g?.isEffectOnly) continue;
            const slotIdx = teamPlayers.findIndex(tp => tp.steamid === g.thrower_steamid);
            if (slotIdx < 0) continue;
            const throwAt = Math.max(0, ((g.throwTick ?? g.tick - 10) - firstTick) / SERVER_TICK_RATE);
            const lastTraj = g.trajectory?.[g.trajectory.length - 1];
            players.value[slotIdx].utilities.push({
                id:                uid(),
                type:              (typeMap[g.grenade_type] ?? 'he') as UtilType,
                throwAt:           Math.round(throwAt * 10) / 10,
                throwPos:           { x: g.X, y: g.Y },
                landPos:            lastTraj ? { x: lastTraj.X, y: lastTraj.Y } : { x: g.X, y: g.Y },
                fromWaypointIndex:  null,
            });
        }

        // Grow duration to cover the imported timeline.
        const lastT = teamPlayers.reduce((m, tp) => {
            const last = players.value[teamPlayers.indexOf(tp)]?.path.at(-1)?.t ?? 0;
            return Math.max(m, last);
        }, 0);
        const lastUtil = grenadeList.reduce((m: number, g: any) => {
            if (g?.isEffectOnly) return m;
            const slotIdx = teamPlayers.findIndex((tp: any) => tp.steamid === g.thrower_steamid);
            return slotIdx >= 0 ? Math.max(m, ((g.throwTick ?? g.tick - 10) - firstTick) / SERVER_TICK_RATE) : m;
        }, 0);
        const needed = Math.ceil(Math.max(lastT, lastUtil) + 2);
        if (needed > duration.value) duration.value = Math.min(needed, 300);

        selectedPlayerSlot.value = 0;
        currentSecond.value = 0;
        saveHistory();
    }

    // ── Serialization ─────────────────────────────────────────────────────────
    function serialize(): RoundPlanData {
        return {
            name:              planName.value,
            notes:             planNotes.value,
            side:              side.value,
            duration:          duration.value,
            variations:        variations.value,
            activeVariationId: activeVariationId.value,
        };
    }

    function deserialize(data: Partial<RoundPlanData> | null): void {
        if (!data) return;
        planName.value  = data.name     ?? 'Round Plan';
        planNotes.value = data.notes    ?? '';
        side.value      = (data.side    ?? 'T') as 'T' | 'CT';
        duration.value  = data.duration ?? 115;

        // hydratePlayers adds waitAfter default for backward compat
        const hydratePlayers = (raw: any[]): Player[] =>
            raw.map((p, i) => ({
                slot:         p.slot         ?? i,
                name:         p.name         ?? `Player ${i + 1}`,
                steamid:      p.steamid      ?? null,
                color:        p.color        ?? PLAYER_COLORS[i],
                role:         (p.role        ?? 'none') as RoleKey,
                path:         (p.path ?? []).map((w: any) => ({ ...w, waitAfter: w.waitAfter ?? 0 })),
                utilities:    (p.utilities   ?? []).map((u: any) => ({ ...u, id: u.id ?? uid(), fromWaypointIndex: u.fromWaypointIndex ?? null })),
                instructions: (p.instructions ?? []).map((ins: any) => ({ ...ins, id: ins.id ?? uid() })),
            }));

        if (data.variations?.length) {
            // New format with variations
            variations.value = data.variations.map(v => ({
                id:                    v.id ?? uid(),
                name:                  v.name ?? 'Variation',
                players:               hydratePlayers(v.players ?? []),
                branchedFromVariationId: v.branchedFromVariationId ?? null,
                branchedAtTime:        v.branchedAtTime ?? null,
            }));
            activeVariationId.value = data.activeVariationId ?? variations.value[0].id;
        } else if (data.players?.length) {
            // Legacy format — wrap players in a default variation
            variations.value = [{ id: uid(), name: 'Default', players: hydratePlayers(data.players), branchedFromVariationId: null, branchedAtTime: null }];
            activeVariationId.value = variations.value[0].id;
        } else {
            variations.value = [makeVariation('Default')];
            activeVariationId.value = variations.value[0].id;
        }
        saveHistory();
    }

    // ── Expose ────────────────────────────────────────────────────────────────
    return {
        planName, planNotes, side, duration, isDirty, savedPlanId,
        players, selectedPlayerSlot, selectedPlayer,
        activeMode, pendingUtilType, pendingUtilThrow, draggingWaypoint,
        currentSecond, isPlaying, playbackSpeed,
        // Variations
        variations, activeVariationId, activeVariation,
        addVariation, renameVariation, deleteVariation, switchVariation, branchVariationFromTime,
        // Player
        setPlayerName, setPlayerColor, setPlayerRole, setPlayerFromTeam, resetPlayer,
        // Path
        addWaypoint, removeLastWaypoint, clearPath, toggleLastWaypointSpeed, setWaypointWait,
        startDragWaypoint, moveDragWaypoint, endDragWaypoint,
        // Bulk (multi-select)
        setSpeedForSlots, clearPathsForSlots, shiftPathsForSlots,
        // Utility
        startAddingUtil, setUtilThrowPos, setUtilLandPos, removeUtil, updateUtilThrowAt, cancelUtil, pendingUtilThrowAt,
        // Instructions
        addInstruction, removeInstruction, updateInstruction,
        // Playback
        play, pause, togglePlayback, togglePlaybackSpeed, seekTo,
        // Queries
        getPlayerPosAtTime, getPlayerInstructionAtTime, getActiveUtilsAtTime,
        // Import
        importFromDemoRound,
        // History
        undo, redo,
        // Serialization
        serialize, deserialize,
    };
}