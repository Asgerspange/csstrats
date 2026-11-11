<script setup lang="ts">
import { ref, computed } from 'vue';
import { useTeamWidgets } from '../composables/useTeamWidgets';
import type { WidgetId } from '../types';
import RecentChangesWidget from './RecentChangesWidget.vue';
import CoverageWidget from './CoverageWidget.vue';

const { widgets, add, remove, has } = useTeamWidgets();

const pickerOpen = ref(false);

const ALL_WIDGETS: { id: WidgetId; label: string; description: string }[] = [
    { id: 'recent-changes', label: 'Recent changes', description: 'The last tactics your team updated.' },
    { id: 'coverage', label: 'Tactic coverage by map', description: 'See which maps and sides have gaps.' },
];

const availableWidgets = computed(() => ALL_WIDGETS.filter((w) => !has(w.id)));
</script>

<template>
    <div class="widget-tray">
        <!-- Active widgets -->
        <div v-if="widgets.length > 0" class="widget-tray__grid">
            <div
                v-for="id in widgets"
                :key="id"
                class="widget-tray__card"
            >
                <div class="widget-tray__card-header">
                    <button
                        class="widget-tray__remove"
                        :aria-label="`Remove ${ALL_WIDGETS.find(w => w.id === id)?.label ?? 'widget'}`"
                        @click="remove(id)"
                    >
                        <span class="material-symbols text-sm">close</span>
                    </button>
                </div>
                <RecentChangesWidget v-if="id === 'recent-changes'" />
                <CoverageWidget v-else-if="id === 'coverage'" />
            </div>
        </div>

        <!-- Add widgets button + picker -->
        <div class="widget-tray__add">
            <button
                v-if="availableWidgets.length > 0"
                class="widget-tray__add-btn"
                @click="pickerOpen = !pickerOpen"
            >
                <span class="material-symbols text-sm">add</span>
                Add widgets
            </button>

            <div v-if="pickerOpen" class="widget-tray__picker">
                <button
                    v-for="w in availableWidgets"
                    :key="w.id"
                    class="widget-tray__picker-item"
                    @click="add(w.id); pickerOpen = false"
                >
                    <span class="widget-tray__picker-label">{{ w.label }}</span>
                    <span class="widget-tray__picker-desc">{{ w.description }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.widget-tray {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.widget-tray__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
}

@media (min-width: 640px) {
    .widget-tray__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.widget-tray__card {
    padding: 0.875rem;
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
    background: var(--app-surface, oklch(0.20 0.014 250));
}

.widget-tray__card-header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.25rem;
}

.widget-tray__remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    border-radius: 0.5rem;
    cursor: pointer;
    transition: color 150ms, background 150ms;
}

.widget-tray__remove:hover {
    color: var(--app-text, oklch(1 0 0 / 0.96));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.widget-tray__remove:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.widget-tray__add {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.widget-tray__add-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border: 1px dashed var(--app-border-2, oklch(1 0 0 / 0.14));
    border-radius: 0.5rem;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    align-self: flex-start;
    transition: color 150ms, border-color 150ms;
}

.widget-tray__add-btn:hover {
    color: var(--main-color, #ff8c00);
    border-color: var(--main-color, #ff8c00);
}

.widget-tray__add-btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.widget-tray__picker {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 24rem;
}

.widget-tray__picker-item {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
    background: var(--app-surface, oklch(0.20 0.014 250));
    cursor: pointer;
    text-align: left;
    transition: border-color 150ms;
}

.widget-tray__picker-item:hover {
    border-color: var(--main-color, #ff8c00);
}

.widget-tray__picker-item:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.widget-tray__picker-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.widget-tray__picker-desc {
    font-size: 0.75rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}
</style>
