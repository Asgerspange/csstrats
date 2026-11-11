// Transient handoff for the "Send to Strat Builder" flow.
// The 2D demo viewer stashes the currently-loaded columnar round + roster
// here, then routes to /strat-builder?import=1. The Strat Builder setup
// modal reads and consumes the payload, then clears it.
//
// Module-level shallowRef: survives the in-SPA route change, does not
// persist across reloads (acceptable — the action is a deliberate send,
// not a durable state). Held by reference to avoid copying ~2MB of
// typed arrays.

import { shallowRef, readonly } from 'vue';

export interface ImportRosterEntry {
    name:     string;
    steamid:  string | null;
}

export interface ImportPayload {
    demoId:     number | string;
    roundIndex: number;
    side:       'T' | 'CT';
    mapName:    string | null;
    round:      any;           // columnar round from useRoundData
    roster:    ImportRosterEntry[];
    capturedAt: number;        // Date.now() — for staleness checks
}

const _payload = shallowRef<ImportPayload | null>(null);

export function setImportPayload(p: ImportPayload): void {
    _payload.value = p;
}

export function consumeImportPayload(): ImportPayload | null {
    const p = _payload.value;
    _payload.value = null;
    return p;
}

export function peekImportPayload(): ImportPayload | null {
    return _payload.value;
}

export function useImportHandoff() {
    return {
        payload: readonly(_payload),
        set:     setImportPayload,
        consume: consumeImportPayload,
        peek:    peekImportPayload,
    };
}
