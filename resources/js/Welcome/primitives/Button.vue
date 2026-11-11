<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
    defineProps<{
        as?: string
        variant?: 'primary' | 'secondary' | 'ghost' | 'Team' | 'Pro' | any
        size?: 'sm' | 'md' | 'lg'
        href?: string
        type?: 'button' | 'submit' | 'reset'
        disabled?: boolean
    }>(),
    {
        as: 'button',
        variant: 'primary',
        size: 'md',
        type: 'button',
    },
)

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'px-4 py-2.5 text-[11px]'
        case 'lg':
            return 'px-7 py-4 text-[13px]'
        default:
            return 'px-6 py-3.5 text-xs'
    }
})

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'secondary':
            return 'bg-slate text-chalk border border-ash hover:bg-smoke'
        case 'ghost':
            return 'bg-transparent text-bone hover:text-chalk hover:bg-slate'
        case 'Team':
            return 'bg-[#3cb4c9] rounded-full text-black hover:bg-[#3090a0] font-inter'
        case 'Pro':
            return 'bg-[#5324ff] rounded-full text-white hover:bg-[#421ccc] font-inter'
        default:
            return 'bg-[#e4e4e6] rounded-full text-black hover:bg-[#cdcdcf] font-inter'
    }
})
</script>

<template>
    <component
        :is="href ? 'a' : as"
        :href="href"
        :type="!href && as === 'button' ? type : undefined"
        :disabled="!href ? disabled : undefined"
        class="group inline-flex hover:cursor-pointer items-center justify-center gap-2 rounded-[var(--radius-sm)] font-inter font-medium uppercase tracking-[0.08em] transition-[background,color,box-shadow] duration-[180ms] ease-[var(--ease-quart)] disabled:cursor-not-allowed disabled:opacity-50"
        :class="[sizeClasses, variantClasses]"
    >
        <slot />
    </component>
</template>
