<script setup lang="ts">
// index is accepted for backwards compatibility but no longer rendered:
// numbered section markers read as scaffolding, not voice.
defineProps<{
    index?: string
    label: string
    tone?: 'saffron' | 'bone'
}>()
</script>

<template>
    <p
        class="font-mono text-xs font-medium uppercase tracking-[0.08em]"
        :class="tone === 'bone' ? 'text-bone' : 'text-saffron'"
        data-reveal
    >
        {{ label }}
    </p>
</template>
