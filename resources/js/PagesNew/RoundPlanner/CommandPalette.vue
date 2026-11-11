<template>
    <transition enter-active-class="transition-all duration-150 ease-out" enter-from-class="opacity-0 scale-[0.97]" leave-active-class="transition-all duration-100 ease-in" leave-to-class="opacity-0 scale-[0.98]">
        <div v-if="open" class="fixed inset-0 z-[1400] flex items-start justify-center pt-[12vh] px-4" @mousedown.self="$emit('close')">
            <!-- Backdrop: light, non-blocking. Premium palettes dim, not mask. -->
            <div class="absolute inset-0 bg-[oklch(0.04_0.01_250_/_0.55)]" />

            <div
                ref="panelRef"
                class="relative w-full max-w-[520px] bg-[var(--th-surface)] border border-[var(--th-border-2)] rounded-xl shadow-2xl overflow-hidden flex flex-col"
                role="dialog" aria-modal="false" aria-label="Command palette"
                @keydown.esc.stop="$emit('close')"
            >
                <!-- Input row -->
                <div class="flex items-center gap-3 px-4 py-3 border-b border-[var(--th-border)]">
                    <span class="material-symbols text-[var(--th-text-4)]" style="font-size:18px">search</span>
                    <input
                        ref="inputRef"
                        v-model="query"
                        type="text"
                        autocomplete="off" spellcheck="false"
                        class="flex-1 bg-transparent border-none outline-none text-[var(--th-text)] text-sm placeholder-[var(--th-text-4)]"
                        placeholder="Type a command or search…"
                        @keydown.down.prevent="moveSel(1)"
                        @keydown.up.prevent="moveSel(-1)"
                        @keydown.enter.prevent="runSelected"
                    />
                    <kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1.5 py-0.5 text-[10px] font-mono text-[var(--th-text-3)]">esc</kbd>
                </div>

                <!-- Results -->
                <div class="max-h-[min(50vh,360px)] overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--th-surface-3)] py-1.5">
                    <div v-for="(group, gi) in grouped" :key="group.label" class="flex flex-col">
                        <div v-if="group.label" class="px-4 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-[var(--th-text-4)]">{{ group.label }}</div>
                        <button
                            v-for="(item, i) in group.items"
                            :key="item.id"
                            :ref="(el) => { if (flatIndex(group, i) === sel) itemRef = el as HTMLElement; }"
                            type="button"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors"
                            :class="flatIndex(group, i) === sel
                                ? 'bg-[var(--th-accent)]/12 text-[var(--th-text)]'
                                : 'text-[var(--th-text-2)] hover:bg-[var(--th-surface-2)]'"
                            @click="run(item)"
                            @mousemove="sel = flatIndex(group, i)"
                        >
                            <span class="material-symbols text-[var(--th-text-3)] flex-shrink-0" :class="flatIndex(group, i) === sel ? '!text-[var(--th-accent)]' : ''" style="font-size:16px">{{ item.icon }}</span>
                            <span class="flex-1 text-sm font-medium min-w-0 truncate">{{ item.label }}</span>
                            <span v-if="item.hint" class="text-[10px] text-[var(--th-text-4)] font-mono tabular-nums flex-shrink-0">{{ item.hint }}</span>
                        </button>
                        <div v-if="gi < grouped.length - 1" class="h-px mx-3 my-1 bg-[var(--th-border)]/50" />
                    </div>

                    <!-- Empty -->
                    <div v-if="!grouped.length" class="px-4 py-8 text-center text-sm text-[var(--th-text-3)]">
                        No commands match "{{ query }}".
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center gap-3 px-4 py-2 border-t border-[var(--th-border)] text-[10px] text-[var(--th-text-4)]">
                    <span class="flex items-center gap-1"><kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1 font-mono">↑↓</kbd> navigate</span>
                    <span class="flex items-center gap-1"><kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1 font-mono">↵</kbd> run</span>
                    <span class="flex items-center gap-1"><kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1 font-mono">esc</kbd> close</span>
                    <span class="ml-auto tabular-nums">{{ flatCount }} commands</span>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onUnmounted } from 'vue';

export interface CommandItem {
    id:       string;
    label:    string;
    icon:     string;
    hint?:    string;
    group?:   string;
    run:     () => void;
    // Optional: hide this item from the palette (e.g. "Save" when nothing is dirty)
    hidden?:  boolean;
    // Optional: only show when this predicate passes (e.g. "Undo" only when canUndo)
    show?:   () => boolean;
}

const props = defineProps<{ open: boolean; commands: CommandItem[] }>();
const emit  = defineEmits<{ close: [] }>();

const query = ref('');
const sel   = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const itemRef: any = ref<HTMLElement | null>(null);

// ── Fuzzy match: subsequence with proximity scoring ────────────────────────
// Higher score = better. Exact prefix > word-boundary > subsequence.
// Returns 0 for non-matches.
function fuzzyScore(query: string, target: string): number {
    if (!query) return 1;
    const q = query.toLowerCase();
    const t = target.toLowerCase();
    if (t.startsWith(q)) return 100 - q.length;          // prefix match
    const ti = t.indexOf(q);
    if (ti !== -1) return 80 - ti - q.length;             // substring match
    // Subsequence match with consecutive-run bonus
    let qi = 0, run = 0, maxRun = 0, lastIdx = -2;
    for (let ti2 = 0; ti2 < t.length && qi < q.length; ti2++) {
        if (t[ti2] === q[qi]) {
            run = (ti2 === lastIdx + 1) ? run + 1 : 1;
            if (run > maxRun) maxRun = run;
            lastIdx = ti2;
            qi++;
        }
    }
    if (qi < q.length) return 0;                         // not a subsequence
    return 20 + maxRun * 3 - (t.length - q.length);
}

const filtered = computed<CommandItem[]>(() => {
    const visible = props.commands.filter(c => !c.hidden && (!c.show || c.show()));
    if (!query.value.trim()) return visible;
    const q = query.value.trim();
    return visible
        .map(c => ({ c, s: fuzzyScore(q, c.label) }))
        .filter(x => x.s > 0)
        .sort((a, b) => b.s - a.s)
        .map(x => x.c);
});

const grouped = computed(() => {
    const map = new Map<string, CommandItem[]>();
    for (const c of filtered.value) {
        const g = c.group ?? 'Commands';
        if (!map.has(g)) map.set(g, []);
        map.get(g)!.push(c);
    }
    return Array.from(map, ([label, items]) => ({ label, items }));
});

const flatCount = computed(() => filtered.value.length);

function flatIndex(group: { items: CommandItem[] }, i: number): number {
    let n = 0;
    for (const g of grouped.value) {
        if (g === group) return n + i;
        n += g.items.length;
    }
    return 0;
}

function moveSel(delta: number) {
    if (!flatCount.value) return;
    sel.value = (sel.value + delta + flatCount.value) % flatCount.value;
    nextTick(() => itemRef.value?.scrollIntoView({ block: 'nearest' }));
}

function runSelected() {
    const item = filtered.value[sel.value];
    if (item) run(item);
}

function run(item: CommandItem) {
    item.run();
    emit('close');
}

// Reset state when opening; focus the input.
watch(() => props.open, (open) => {
    if (open) {
        query.value = '';
        sel.value = 0;
        nextTick(() => inputRef.value?.focus());
    }
});

// Keep sel in range as the query changes.
watch(filtered, () => { if (sel.value >= flatCount.value) sel.value = Math.max(0, flatCount.value - 1); });

// Global keydown: if palette is open, trap the chord. Prevents Ctrl+K from
// toggling twice and keeps Arrow/Page keys from scrolling the page.
function onKey(e: KeyboardEvent) {
    if (!props.open) return;
    if (e.key === 'Escape') { emit('close'); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') return; // handled on input
    if (e.key === 'Enter') return;
}
onUnmounted(() => {
    // nothing to clean — the global listener is owned by the parent
});
</script>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    .transition-all { transition: opacity 0.1s linear !important; }
    .scale-\[0\.97\], .scale-\[0\.98\] { transform: none !important; }
}
</style>
