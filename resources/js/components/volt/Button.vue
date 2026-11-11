<template>
    <Button
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Button>
</template>

<script setup lang="ts">
import Button, { type ButtonPassThroughOptions, type ButtonProps } from 'primevue/button';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ ButtonProps {
    severity?: 'primary' | 'secondary';
    size?: 'sm' | 'lg' | 'xl';
    variant?: 'text' | 'outlined' | 'flat';
}

const props = defineProps<Props>();

function getComputedBgClasses() {
    return props.severity === 'secondary'
        ? 'bg-[var(--secondary-button-bg)] hover:bg-[var(--secondary-button-bg-hover)] text-[var(--secondary-text-color)] border-none'
        : 'bg-[var(--primary-btn-bg)] hover:bg-[var(--primary-btn-bg-hover)] text-[var(--primary-text-color)] border border-[var(--primary-btn-bg)]';
}

function getComputedSizeClasses() {
    switch (props.size) {
        case 'sm':
            return 'h-[30px] text-sm';
        case 'lg':
            return 'h-[40px] text-base';
        case 'xl':
            return 'h-[50px] text-lg';
        default:
            return 'h-[40px] text-base';
    }
}

const theme = ref<ButtonPassThroughOptions>({
    root: `inline-flex cursor-pointer select-none items-center justify-center overflow-hidden relative
        p-2 gap-2 rounded-[8px] disabled:pointer-events-none disabled:opacity-60 transition-colors duration-200
        w-fit
    ` + getComputedBgClasses() + ' ' + getComputedSizeClasses() + (props.variant === 'text' ? `
        p-text p-outlined:p-0 p-outlined:border p-outlined:border-current
        p-flat border-none bg-transparent hover:bg-transparent
    ` : ''),
    loadingIcon: `animate-spin`,
    icon: `p-right:order-1 p-bottom:order-2`,
    label: `font-medium p-icon-only:invisible p-icon-only:w-0
        p-small:text-sm p-large:text-[1.125rem]`,
    pcBadge: {
        root: `min-w-4 h-4 leading-4 bg-primary-contrast rounded-full text-primary text-xs font-bold`
    }
});
</script>
