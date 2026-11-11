<script setup lang="ts">
defineProps<{
    message: string;
    hint?: string;
}>();

defineEmits<{
    (e: 'retry'): void;
}>();
</script>

<template>
    <div class="error-state" role="alert">
        <p class="error-state__message">{{ message }}</p>
        <p v-if="hint" class="error-state__hint">{{ hint }}</p>
        <button
            class="error-state__btn"
            @click="$emit('retry')"
        >
            Try again
        </button>
    </div>
</template>

<style scoped>
.error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 4rem 1rem;
    text-align: center;
}

.error-state__message {
    font-size: 0.875rem;
    margin: 0;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.error-state__hint {
    font-size: 0.875rem;
    margin: 0;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.error-state__btn {
    margin-top: 0.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    cursor: pointer;
    transition: background 150ms;
}

.error-state__btn:hover {
    background: var(--app-surface-3, oklch(0.285 0.018 250));
}

.error-state__btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}
</style>
