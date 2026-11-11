<template>
    <aside
        class="context-panel"
        :class="[`context-panel--${side}`, { 'context-panel--collapsed': collapsed, 'context-panel--flow': !overlay, 'context-panel--mobile-sheet': mobileSheet }]"
        :style="{ '--cp-width': width, '--cp-rail': rail }"
        :aria-label="title || 'Panel'"
    >
        <!-- Drag handle — only shown in the mobile bottom-sheet mode -->
        <div v-if="mobileSheet" class="context-panel__handle" aria-hidden="true"></div>

        <!-- Header: title + toggle. Stays mounted when collapsed so the rail
             keeps its toggle affordance and (optionally) a vertical label. -->
        <div class="context-panel__head">
            <button
                type="button"
                class="context-panel__toggle"
                :aria-expanded="!collapsed"
                :title="collapsed ? `Expand ${title || 'panel'}` : `Collapse ${title || 'panel'}`"
                @click="toggle"
            >
                <span class="material-symbols">{{ toggleIcon }}</span>
            </button>

            <template v-if="!collapsed">
                <!-- Custom title content (e.g. an inline rename input) falls back
                     to the plain title label. -->
                <slot name="title">
                    <span class="context-panel__title">{{ title }}</span>
                </slot>

                <div class="context-panel__actions">
                    <slot name="actions" />
                </div>
            </template>
        </div>

        <!-- Collapsed rail: vertical label / icon stack -->
        <div v-if="collapsed" class="context-panel__rail">
            <slot name="rail">
                <span v-if="icon" class="material-symbols context-panel__rail-icon">{{ icon }}</span>
                <span v-if="title" class="context-panel__rail-label">{{ title }}</span>
            </slot>
        </div>

        <!-- Body: only rendered when open, so collapsed panels cost nothing.
             bodyScroll=false hands scroll control to the host content (which
             manages its own internal scroll regions). -->
        <div
            v-show="!collapsed"
            class="context-panel__body"
            :class="bodyScroll ? 'context-panel__scroll' : 'context-panel__body--fill'"
        >
            <slot />
        </div>

        <!-- Backdrop for the mobile bottom sheet. Teleported to body so it
             covers the full viewport without inheriting the panel's transform.
             Hidden on desktop and when collapsed (CSS-driven). -->
        <Teleport to="body">
            <div
                v-if="mobileSheet"
                class="context-panel__backdrop"
                :class="{ 'context-panel__backdrop--visible': !collapsed }"
                @click="collapse"
            />
        </Teleport>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';

/**
 * ContextPanel — a reusable, edge-docked, collapsible side panel.
 *
 * The canonical "page tools on the side" surface: filters, layers, a library,
 * round annotations. Docks to the left or right edge of its positioned parent,
 * collapses to a thin rail, and themes itself from the shared `--app-*` tokens
 * (with dark fallbacks) so it matches both dark themes and the daylight theme.
 *
 * Control collapse with `v-model:collapsed` so the host can keep the state as
 * the source of truth (e.g. to offset sibling panels off this one's width).
 */
const props = withDefaults(defineProps<{
    /** Which edge to dock against. */
    side?: 'left' | 'right';
    /** Collapsed (rail) state — use with v-model:collapsed. */
    collapsed?: boolean;
    /** Header title, also used as the collapsed rail label. */
    title?: string;
    /** Open width (any CSS length). */
    width?: string;
    /** Collapsed rail width. */
    rail?: string;
    /** Material Symbols icon shown on the collapsed rail. */
    icon?: string;
    /** Absolutely position against the parent edge (canvas overlay). When
     *  false the panel sits in normal flow as a flex column. */
    overlay?: boolean;
    /** When true (default) the body is a vertical scroll container. Set false
     *  when the host content manages its own internal scroll regions. */
    bodyScroll?: boolean;
    /** On viewports below lg, render as a bottom sheet (fixed, bottom-anchored,
     *  full width, tap-backdrop-to-dismiss) instead of an in-flow side panel.
     *  Desktop behavior is unaffected. */
    mobileSheet?: boolean;
}>(), {
    side: 'right',
    collapsed: false,
    title: '',
    width: '20rem',
    rail: '3rem',
    icon: '',
    overlay: true,
    bodyScroll: true,
    mobileSheet: false,
});

const emit = defineEmits<{ 'update:collapsed': [value: boolean] }>();

const toggle = () => emit('update:collapsed', !props.collapsed);
const collapse = () => emit('update:collapsed', true);

// Chevron points "outward" to expand, "inward" to collapse — mirrored by side.
// In mobile-sheet mode the toggle becomes a "slide down to dismiss" affordance.
const toggleIcon = computed(() => {
    if (props.mobileSheet) return 'expand_more';
    if (props.side === 'right') return props.collapsed ? 'chevron_left' : 'chevron_right';
    return props.collapsed ? 'chevron_right' : 'chevron_left';
});
</script>

<style scoped>
.context-panel {
    /* Themeable surface — mirrors the bridge used across the app's canvas
       surfaces so this matches Board / Demoviewer without extra wiring. */
    --cp-surface: var(--app-surface, oklch(0.175 0.014 250));
    --cp-border:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --cp-text:    var(--app-text, oklch(1 0 0 / 0.96));
    --cp-text-2:  var(--app-text-2, oklch(1 0 0 / 0.70));
    --cp-text-3:  var(--app-text-3, oklch(1 0 0 / 0.62));
    --cp-accent:  var(--app-accent, #ff8c00);

    position: absolute;
    top: 0;
    bottom: 0;
    z-index: 30;
    width: var(--cp-width);
    display: flex;
    flex-direction: column;
    background: var(--cp-surface);
    box-shadow: 0 20px 40px -8px oklch(0 0 0 / 0.5);
    transition: width 300ms cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}
.context-panel--collapsed { width: var(--cp-rail); }

.context-panel--right { right: 0; border-left: 1px solid var(--cp-border); }
.context-panel--left  { left: 0;  border-right: 1px solid var(--cp-border); }

/* In-flow variant: no absolute positioning, fills its grid/flex cell height,
   and holds its width in a flex row. The side-edge border (--left/--right)
   provides the divider; no drop shadow since it is not an overlay. */
.context-panel--flow {
    position: relative;
    top: auto;
    bottom: auto;
    height: 100%;
    flex-shrink: 0;
    box-shadow: none;
}

/* Right-docked in-flow panel (Tactics / Stratbook filters) reads as a rounded
   card to match the rest of the UI instead of a flush, sharp-edged slab. The
   left-docked flow variant (RoundPlanner sidebar) intentionally stays flush. */
.context-panel--flow.context-panel--right {
    border: 1px solid var(--cp-border);
    border-radius: 1rem;
}

/* ── Header ─────────────────────────────────────────────────────────────── */
.context-panel__head {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    min-height: 3rem;
}
.context-panel--collapsed .context-panel__head {
    padding: 0.875rem 0;
    justify-content: center;
}

.context-panel__toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
    border: none;
    background: none;
    border-radius: 0.375rem;
    color: var(--cp-text-3);
    cursor: pointer;
    transition: color 150ms, background 150ms;
}
.context-panel__toggle:hover { color: var(--cp-text); background: oklch(1 0 0 / 0.05); }

.context-panel__title {
    flex: 1;
    min-width: 0;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--cp-text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.context-panel__actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

/* ── Collapsed rail ─────────────────────────────────────────────────────── */
.context-panel__rail {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding-top: 0.5rem;
    color: var(--cp-text-3);
}
.context-panel__rail-icon { font-size: 1.25rem; }
.context-panel__rail-label {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    white-space: nowrap;
}

/* ── Body ───────────────────────────────────────────────────────────────── */
.context-panel__body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
}
/* Host-managed scroll: body is a plain flex column, inner regions scroll. */
.context-panel__body--fill {
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.context-panel__scroll::-webkit-scrollbar { width: 4px; }
.context-panel__scroll::-webkit-scrollbar-track { background: transparent; }
.context-panel__scroll::-webkit-scrollbar-thumb {
    background: var(--app-surface-3, oklch(0.26 0.018 250));
    border-radius: 10px;
}
.context-panel__scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--app-surface-3, oklch(0.26 0.018 250)) transparent;
}

.material-symbols {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (prefers-reduced-motion: reduce) {
    .context-panel { transition: none; }
}

/* ── Mobile bottom sheet ──────────────────────────────────────────────────────
   On viewports below lg, a mobileSheet panel stops claiming layout width and
   slides up from the bottom as a full-width sheet. The collapsed rail is
   hidden; the host toolbar provides the trigger. Tap the backdrop to dismiss. */
.context-panel__handle {
    display: none;
}
.context-panel__backdrop {
    position: fixed;
    inset: 0;
    background: oklch(0 0 0 / 0.55);
    z-index: 49;
    opacity: 0;
    pointer-events: none;
    transition: opacity 200ms ease;
}

@media (max-width: 1023px) {
    .context-panel--mobile-sheet {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        width: auto;
        max-height: 85vh;
        border-left: none;
        border-right: none;
        border-top: 1px solid var(--cp-border);
        border-radius: 16px 16px 0 0;
        box-shadow: 0 -20px 40px -8px oklch(0 0 0 / 0.5);
        transition: transform 320ms cubic-bezier(0.4, 0, 0.2, 1);
        transform: translateY(0);
        z-index: 50;
    }
    .context-panel--mobile-sheet.context-panel--collapsed {
        transform: translateY(100%);
        box-shadow: none;
        pointer-events: none;
    }
    /* No rail on mobile sheet — the trigger lives in the host toolbar. */
    .context-panel--mobile-sheet .context-panel__rail { display: none; }
    /* Drag handle visible only in the sheet. */
    .context-panel--mobile-sheet .context-panel__handle {
        display: block;
        flex-shrink: 0;
        width: 2.25rem;
        height: 0.25rem;
        margin: 0.5rem auto 0;
        border-radius: 9999px;
        background: oklch(var(--app-sidebar-ink, 1 0 0) / 0.18);
    }
    .context-panel--mobile-sheet.context-panel--collapsed .context-panel__handle {
        /* Keep mounted but hidden with the sheet. */
        opacity: 0;
    }
    /* Backdrop shows only when the sheet is expanded. */
    .context-panel__backdrop--visible {
        opacity: 1;
        pointer-events: auto;
    }
}

</style>
