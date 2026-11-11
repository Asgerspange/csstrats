<script setup lang="ts">
withDefaults(defineProps<{
    width?: string;
    height?: string;
    rounded?: string;
}>(), {
    width: '100%',
    height: '1rem',
    rounded: '4px',
});
</script>

<template>
    <div
        class="skeleton-block"
        :style="{ width, height, borderRadius: rounded }"
    />
</template>

<style scoped>
.skeleton-block {
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    animation: skeleton-pulse 1.5s ease-in-out infinite;
}

@keyframes skeleton-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-block {
        animation: none;
        opacity: 0.6;
    }
}
</style>
