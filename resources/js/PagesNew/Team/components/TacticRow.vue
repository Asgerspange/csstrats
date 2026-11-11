<script setup lang="ts">
import { useRouter } from 'vue-router';
import type { TeamTactic } from '../../types';

const props = defineProps<{
    tactic: TeamTactic;
}>();

const router = useRouter();

function open() {
    router.push(`/stratbook/${props.tactic.stratbook_id}`);
}
</script>

<template>
    <button
        class="tactic-row"
        @click="open"
    >
        <span class="tactic-row__name">{{ tactic.name }}</span>
        <div class="tactic-row__meta">
            <span class="tactic-row__chip tactic-row__chip--map">{{ tactic.map }}</span>
            <span
                class="tactic-row__chip"
                :class="tactic.side === 'T' ? 'tactic-row__chip--t' : 'tactic-row__chip--ct'"
            >{{ tactic.side }}</span>
            <span v-if="tactic.type" class="tactic-row__chip tactic-row__chip--type">{{ tactic.type }}</span>
        </div>
    </button>
</template>

<style scoped>
.tactic-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: none;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.06));
    background: none;
    text-align: left;
    cursor: pointer;
    transition: background 150ms;
    min-height: 2.75rem;
}

.tactic-row:hover {
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.tactic-row:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: -2px;
}

.tactic-row__name {
    flex: 1;
    min-width: 0;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.tactic-row__meta {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
}

.tactic-row__chip {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.125rem 0.375rem;
    border-radius: 0.5rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.tactic-row__chip--map {
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.tactic-row:hover .tactic-row__chip--map {
    background: var(--app-surface-3, oklch(0.285 0.018 250));
}

.tactic-row__chip--t {
    color: oklch(0.72 0.12 145);
    background: oklch(0.72 0.12 145 / 0.12);
}

.tactic-row__chip--ct {
    color: oklch(0.60 0.045 240);
    background: oklch(0.60 0.045 240 / 0.12);
}

.tactic-row__chip--type {
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

@media (max-width: 640px) {
    .tactic-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.375rem;
        padding: 0.75rem;
    }

    .tactic-row__name {
        font-size: 0.9375rem;
    }
}
</style>
