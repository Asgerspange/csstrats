<script setup lang="ts">
import type { TeamMap } from '@/composables/useTeamMaps';

const props = defineProps<{
    map: TeamMap;
}>();

defineEmits<{
    open: [stratbookId: number];
    upgrade: [];
}>();

const isAccessible = computed(() => props.map.access_state === 'accessible');
const isLocked = computed(() => props.map.access_state === 'locked');
const isViewOnly = computed(() => props.map.access_state === 'view-only');
</script>

<script lang="ts">
import { computed } from 'vue';
</script>

<template>
    <div
        class="map-card"
        :class="{
            'map-card--accessible': isAccessible,
            'map-card--locked': isLocked,
            'map-card--view-only': isViewOnly,
        }"
        role="button"
        tabindex="0"
        @click="isAccessible ? $emit('open', map.id) : null"
        @keydown.enter="isAccessible ? $emit('open', map.id) : null"
    >
        <div class="map-card__image">
            <img :src="map.image_path" :alt="map.display_name" loading="lazy" />
            <div v-if="isLocked || isViewOnly" class="map-card__overlay" />
        </div>

        <div class="map-card__body">
            <h3 class="map-card__name">{{ map.display_name }}</h3>
            <div class="map-card__meta">
                <span class="map-card__count tabular-nums">
                    {{ map.tactic_count }} tactic{{ map.tactic_count === 1 ? '' : 's' }}
                </span>
                <span v-if="isAccessible" class="map-card__action">Open &rarr;</span>
                <span v-else-if="isViewOnly" class="map-card__action map-card__action--muted">
                    View only &middot; <span class="map-card__upgrade" @click.stop="$emit('upgrade')">upgrade to edit</span>
                </span>
                <span v-else class="map-card__action map-card__action--muted">
                    Not in your plan &middot; <span class="map-card__upgrade" @click.stop="$emit('upgrade')">Upgrade</span>
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.map-card {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.75rem;
    overflow: hidden;
    background: var(--app-surface, oklch(0.20 0.014 250));
    cursor: pointer;
    text-align: left;
    transition: border-color 150ms, background 150ms, transform 150ms;
}
.map-card--accessible:hover {
    border-color: color-mix(in oklch, var(--main-color, #ff8c00) 45%, var(--app-border, oklch(1 0 0 / 0.09)));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}
.map-card--accessible:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}
.map-card--locked,
.map-card--view-only {
    cursor: default;
}

.map-card__image {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}
.map-card__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.map-card--locked .map-card__image img,
.map-card--view-only .map-card__image img {
    filter: grayscale(1) opacity(0.4);
}
.map-card__overlay {
    position: absolute;
    inset: 0;
}

.map-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0.75rem;
}
.map-card__name {
    font-size: 0.9375rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: -0.01em;
}
.map-card--locked .map-card__name,
.map-card--view-only .map-card__name {
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}
.map-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
}
.map-card__count {
    font-weight: 600;
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
}
.map-card--locked .map-card__count,
.map-card--view-only .map-card__count {
    color: var(--app-text-3, oklch(1 0 0 / 0.40));
}
.map-card__action {
    font-weight: 600;
    color: var(--main-color, #ff8c00);
}
.map-card__action--muted {
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}
.map-card__upgrade {
    color: var(--main-color, #ff8c00);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
    .map-card { transition: none; }
}
</style>
