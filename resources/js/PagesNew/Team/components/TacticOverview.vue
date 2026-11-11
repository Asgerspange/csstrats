<script setup lang="ts">
import { computed } from 'vue';
import type { TeamTactic, TacticFilters } from '../../types';
import TacticRow from './TacticRow.vue';
import EmptyState from './EmptyState.vue';

const props = defineProps<{
    tactics: TeamTactic[];
    filters: TacticFilters;
    hasStratbooks: boolean;
}>();

const emit = defineEmits<{
    (e: 'create-stratbook'): void;
}>();

const filtered = computed<TeamTactic[]>(() => {
    const f = props.filters;
    let list = props.tactics;

    if (f.map) list = list.filter((t) => t.map === f.map);
    if (f.side) list = list.filter((t) => t.side === f.side);
    if (f.type) list = list.filter((t) => t.type === f.type);
    if (f.q) {
        const q = f.q.toLowerCase();
        list = list.filter((t) => t.name.toLowerCase().includes(q));
    }

    return list;
});

const grouped = computed(() => {
    const groups: Record<string, TeamTactic[]> = {};
    for (const t of filtered.value) {
        const key = t.stratbook_name || 'Unsorted';
        if (!groups[key]) groups[key] = [];
        groups[key].push(t);
    }
    return Object.entries(groups);
});
</script>

<template>
    <div>
        <!-- State 3: No stratbooks at all -->
        <EmptyState
            v-if="!hasStratbooks"
            headline="No stratbooks yet."
            body="Create your first stratbook to start collecting tactics."
            action-label="Create stratbook"
            @action="emit('create-stratbook')"
        />

        <!-- State 4: Stratbooks exist but no tactics -->
        <div v-else-if="props.tactics.length === 0" class="tactic-overview__empty-stratbooks">
            <p class="tactic-overview__empty-text">No tactics yet. Browse the tactics library to add your first one.</p>
            <button class="tactic-overview__empty-btn" @click="emit('create-stratbook')">
                Browse tactics
            </button>
        </div>

        <!-- No tactics match the filters -->
        <EmptyState
            v-else-if="filtered.length === 0"
            headline="No tactics match these filters."
            body="Try clearing a filter to see more."
        />

        <!-- Grouped tactic list -->
        <div v-else>
            <section
                v-for="[stratbookName, tactics] in grouped"
                :key="stratbookName"
                class="tactic-overview__group"
            >
                <div class="tactic-overview__group-header">
                    <h2 class="tactic-overview__group-title">{{ stratbookName }}</h2>
                    <span class="tactic-overview__group-count tabular-nums">{{ tactics.length }}</span>
                </div>
                <div class="tactic-overview__rows">
                    <TacticRow
                        v-for="tactic in tactics"
                        :key="tactic.id"
                        :tactic="tactic"
                    />
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.tactic-overview__group {
    margin-bottom: 1.5rem;
}

.tactic-overview__group-header {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.75rem 0;
}

.tactic-overview__group-title {
    font-size: 0.8125rem;
    font-weight: 700;
    margin: 0;
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    text-transform: none;
    letter-spacing: 0;
}

.tactic-overview__group-count {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.tactic-overview__rows {
    border-top: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
    overflow: hidden;
}

.tactic-overview__empty-stratbooks {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 2.5rem 1rem;
    text-align: center;
}

.tactic-overview__empty-text {
    font-size: 0.875rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    margin: 0;
}

.tactic-overview__empty-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms;
}

.tactic-overview__empty-btn:hover {
    background: var(--app-surface-3, oklch(0.285 0.018 250));
}

.tactic-overview__empty-btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}
</style>
