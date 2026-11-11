<script setup lang="ts">
defineProps<{
    id?: string
    pad?: 'tight' | 'default' | 'wide'
}>()
</script>

<template>
    <section
        :id="id"
        class="relative"
        :class="{
            'py-20 sm:py-24': pad === 'tight',
            'py-28 sm:py-36 lg:py-44': !pad || pad === 'default',
            'py-36 sm:py-48 lg:py-56': pad === 'wide',
        }"
        data-reveal-group
    >
        <slot />
    </section>
</template>
