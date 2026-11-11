// ─── Domain types ─────────────────────────────────────────────────────────────

export type UtilType   = 'smoke' | 'flash' | 'he' | 'molotov';
export type SpeedType  = 'run' | 'walk';
export type SideType   = 'T' | 'CT';
export type RoleKey    = 'none' | 'igl' | 'entry' | 'awp' | 'support' | 'lurk';
export type ActiveMode = 'path' | 'util_land' | 'none';

export interface Vec2 {
    x: number;
    y: number;
}

export interface Waypoint extends Vec2 {
    t:         number;
    speed:     SpeedType;
    waitAfter: number;   // seconds the player waits at this point before moving on (0 = no wait)
}

export interface Utility {
    id:                number;
    type:              UtilType;
    throwPos:          Vec2;
    landPos:           Vec2;
    throwAt:           number;
    fromWaypointIndex: number | null;
}

export interface Instruction {
    id:   number;
    t:    number;
    text: string;
}

export interface Player {
    slot:         number;
    name:         string;
    steamid:      string | null;
    color:        string;
    role:         RoleKey;
    path:         Waypoint[];
    utilities:    Utility[];
    instructions: Instruction[];
}

// ─── Variation ────────────────────────────────────────────────────────────────

export interface Variation {
    id:                    number;
    name:                  string;
    players:               Player[];
    branchedFromVariationId?: number | null;
    branchedAtTime?:       number | null;
}

export interface RoundPlanData {
    name:               string;
    notes:              string;
    side:               SideType;
    duration:           number;
    // Variations supersede the flat players array.
    // For backward compat, players is kept on the root for old saves.
    players?:           Player[];
    variations:         Variation[];
    activeVariationId:  number;
}

export interface MapMetadata {
    scale: number;
    pos_x: number;
    pos_y: number;
}

export interface MapPosition {
    x:     number;
    y:     number;
    scale: number;
}

export interface DragState {
    slot:  number;
    index: number;
}

// ─── Utility type definition ───────────────────────────────────────────────────

export interface UtilTypeDef {
    key:   UtilType;
    label: string;
    icon:  string;
    color: string;
}

export interface RoleDef {
    key:   RoleKey;
    label: string;
    icon:  string;
}

// ─── Renderer params ───────────────────────────────────────────────────────────

export interface RenderParams {
    players:              Player[];
    selectedSlot:         number;
    currentSecond:        number;
    getPlayerPosAtTime:   (slot: number, t: number) => Vec2 | null;
    getActiveUtilsAtTime: (slot: number, t: number) => Utility[];
    activeMode:           ActiveMode;
    pendingUtilThrow:     Vec2 | null;
    pendingCursorPos:     Vec2 | null;
    pendingUtilThrowAt:   number | null;
    isPlayback:           boolean;
    draggingWaypoint:     DragState | null;
    hoveredWaypoint:      DragState | null;
    planName:             string;
    side:                 SideType;
    mapMetadata:          MapMetadata | null;
    captureForSnapshot?:  boolean;
}

// ─── Context menu ──────────────────────────────────────────────────────────────

export interface ContextMenuItem {
    label:  string;
    icon:   string;
    action: () => void;
    danger?: boolean;
}

export interface ContextMenu {
    x:     number;
    y:     number;
    items: ContextMenuItem[];
}