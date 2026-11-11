<script setup lang="ts">
defineProps<{
    width?: 'narrow' | 'default' | 'wide'
    as?: string
}>()
</script>

<template>
    <component
        :is="as ?? 'div'"
        class="mx-auto w-full px-6 sm:px-10 lg:px-16"
        :class="{
            'max-w-[64rem]': width === 'narrow',
            'max-w-[96rem]': !width || width === 'default',
            'max-w-[126rem]': width === 'wide',
        }"
    >
        <slot />
    </component>
</template>
