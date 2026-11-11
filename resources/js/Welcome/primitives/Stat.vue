<script setup lang="ts">
defineProps<{
    value: string
    label: string
    suffix?: string
}>()
</script>

<template>
    <div class="flex flex-col gap-1" data-reveal>
        <span class="font-mono text-3xl font-medium tracking-[-0.02em] text-chalk lg:text-4xl">
            {{ value }}<span v-if="suffix" class="text-bone">{{ suffix }}</span>
        </span>
        <span class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
            {{ label }}
        </span>
    </div>
</template>
