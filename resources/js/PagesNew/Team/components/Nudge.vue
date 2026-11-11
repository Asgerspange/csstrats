<script setup lang="ts">
defineProps<{
    message: string;
    actionLabel?: string;
}>();

const emit = defineEmits<{
    (e: 'dismiss'): void;
    (e: 'action'): void;
}>();
</script>

<template>
    <div class="nudge" role="status" aria-live="polite">
        <p class="nudge__message">{{ message }}</p>
        <div class="nudge__actions">
            <button
                v-if="actionLabel"
                class="nudge__action"
                @click="emit('action')"
            >
                {{ actionLabel }}
            </button>
            <button
                class="nudge__dismiss"
                aria-label="Dismiss"
                @click="emit('dismiss')"
            >
                <span class="material-symbols text-base">close</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.nudge {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.625rem 1rem;
    background: var(--app-surface, oklch(0.20 0.014 250));
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
}

.nudge__message {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    margin: 0;
}

.nudge__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.nudge__action {
    padding: 0.25rem 0.75rem;
    border: 1px solid var(--main-color, #ff8c00);
    border-radius: 0.5rem;
    background: none;
    color: var(--main-color, #ff8c00);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms;
}

.nudge__action:hover {
    background: oklch(1 0 0 / 0.06);
}

.nudge__action:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.nudge__dismiss {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    border-radius: 0.25rem;
    cursor: pointer;
    transition: color 150ms, background 150ms;
}

.nudge__dismiss:hover {
    color: var(--app-text, oklch(1 0 0 / 0.96));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.nudge__dismiss:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}
</style>
