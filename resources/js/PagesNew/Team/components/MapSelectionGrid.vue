<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import type { TeamMap } from '@/composables/useTeamMaps';

const props = defineProps<{
    maps: TeamMap[];
    maxMaps: number;
    selectedIds: number[];
}>();

const emit = defineEmits<{
    confirm: [mapIds: number[]];
    cancel: [];
}>();

const localSelected = ref<number[]>([...props.selectedIds]);
const confirmed = ref(false);

watch(() => props.selectedIds, (ids) => {
    localSelected.value = [...ids];
});

const isMaxed = computed(() => localSelected.value.length >= props.maxMaps);

function toggle(mapId: number) {
    const idx = localSelected.value.indexOf(mapId);
    if (idx >= 0) {
        localSelected.value.splice(idx, 1);
    } else if (!isMaxed.value) {
        localSelected.value.push(mapId);
    }
}

function isSelected(mapId: number) {
    return localSelected.value.includes(mapId);
}

function isDisabled(mapId: number) {
    return isMaxed.value && !isSelected(mapId);
}

function confirm() {
    if (!confirmed.value) return;
    emit('confirm', localSelected.value);
}

const countLabel = computed(() =>
    `${localSelected.value.length} of ${props.maxMaps} selected`,
);
</script>

<template>
    <div class="map-select">
        <div class="map-select__header">
            <h2 class="map-select__title">Change your maps</h2>
            <p class="map-select__body">
                Pick {{ maxMaps }} maps. Your selection locks for 30 days.
                The rest stay visible but not editable.
            </p>
        </div>

        <div class="map-select__grid">
            <button
                v-for="map in maps"
                :key="map.id"
                class="map-select__card"
                :class="{
                    'map-select__card--selected': isSelected(map.id),
                    'map-select__card--disabled': isDisabled(map.id),
                }"
                :title="isDisabled(map.id) ? `You've selected ${maxMaps} of ${maxMaps}. Deselect one to change.` : ''"
                @click="toggle(map.id)"
            >
                <div class="map-select__img">
                    <img :src="map.image_path" :alt="map.display_name" loading="lazy" />
                    <span v-if="isSelected(map.id)" class="map-select__check material-symbols">check_circle</span>
                </div>
                <span class="map-select__name">{{ map.display_name }}</span>
            </button>
        </div>

        <div class="map-select__footer">
            <div class="map-select__left">
                <span class="map-select__count tabular-nums">{{ countLabel }}</span>
                <label class="map-select__confirm">
                    <input v-model="confirmed" type="checkbox" />
                    <span>I understand my maps lock for 30 days</span>
                </label>
            </div>
            <div class="map-select__actions">
                <button class="map-select__btn map-select__btn--cancel" @click="$emit('cancel')">Cancel</button>
                <button
                    class="map-select__btn map-select__btn--confirm"
                    :disabled="!confirmed || localSelected.length === 0"
                    @click="confirm"
                >
                    Confirm maps
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.map-select {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.map-select__header { display: flex; flex-direction: column; gap: 0.375rem; }
.map-select__title { font-size: 1.125rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; color: var(--app-text, oklch(1 0 0 / 0.96)); }
.map-select__body { font-size: 0.8125rem; color: var(--app-text-2, oklch(1 0 0 / 0.70)); margin: 0; line-height: 1.5; }

.map-select__grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(2, 1fr);
}
@media (min-width: 560px) { .map-select__grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 768px) { .map-select__grid { grid-template-columns: repeat(4, 1fr); } }

.map-select__card {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0;
    border: 2px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.625rem;
    overflow: hidden;
    background: var(--app-surface, oklch(0.20 0.014 250));
    cursor: pointer;
    transition: border-color 150ms;
}
.map-select__card--selected {
    border-color: var(--main-color, #ff8c00);
}
.map-select__card--disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.map-select__card--selected:hover,
.map-select__card:hover:not(.map-select__card--disabled) {
    border-color: color-mix(in oklch, var(--main-color, #ff8c00) 45%, var(--app-border, oklch(1 0 0 / 0.09)));
}

.map-select__img {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
}
.map-select__img img { width: 100%; height: 100%; object-fit: cover; }
.map-select__check {
    position: absolute;
    top: 0.375rem;
    right: 0.375rem;
    font-size: 22px;
    color: var(--main-color, #ff8c00);
    background: var(--app-surface, oklch(0.20 0.014 250));
    border-radius: 50%;
}
.map-select__name {
    padding: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.map-select__footer {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 0.75rem;
    border-top: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}
.map-select__left { display: flex; flex-direction: column; gap: 0.5rem; }
.map-select__count { font-size: 0.875rem; font-weight: 700; color: var(--app-text, oklch(1 0 0 / 0.96)); }
.map-select__confirm { display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; color: var(--app-text-2, oklch(1 0 0 / 0.70)); cursor: pointer; }
.map-select__confirm input { accent-color: var(--main-color, #ff8c00); }

.map-select__actions { display: flex; gap: 0.5rem; }
.map-select__btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: filter 150ms, background 150ms;
}
.map-select__btn--cancel {
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
}
.map-select__btn--confirm {
    border: none;
    background: var(--main-color, #ff8c00);
    color: oklch(0.15 0.01 53);
}
.map-select__btn--confirm:hover:not(:disabled) { filter: brightness(1.08); }
.map-select__btn--confirm:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
