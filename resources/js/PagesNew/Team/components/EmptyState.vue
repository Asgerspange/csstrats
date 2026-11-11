<script setup lang="ts">
defineProps<{
    headline: string;
    body?: string;
    actionLabel?: string;
}>();

defineEmits<{
    (e: 'action'): void;
}>();
</script>

<template>
    <div class="empty-state">
        <h2 class="empty-state__headline">{{ headline }}</h2>
        <p v-if="body" class="empty-state__body">{{ body }}</p>
        <button
            v-if="actionLabel"
            class="empty-state__btn"
            @click="$emit('action')"
        >
            {{ actionLabel }}
        </button>
    </div>
</template>

<style scoped>
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 4rem 1rem;
    text-align: center;
}

.empty-state__headline {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.empty-state__body {
    font-size: 0.875rem;
    max-width: 28rem;
    margin: 0;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    line-height: 1.6;
}

.empty-state__btn {
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--main-color, #ff8c00);
    background: var(--main-color, #ff8c00);
    color: oklch(0.18 0.02 250);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: filter 150ms;
}

.empty-state__btn:hover {
    filter: brightness(1.08);
}

.empty-state__btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}
</style>
