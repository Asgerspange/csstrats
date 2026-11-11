<template>
    <Toast
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template #closeicon>
            <TimesIcon />
        </template>
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Toast>
</template>

<script setup lang="ts">
import TimesIcon from '@primevue/icons/times';
import Toast, { type ToastPassThroughOptions, type ToastProps } from 'primevue/toast';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ ToastProps {}
defineProps<Props>();

const theme = ref<ToastPassThroughOptions>({
    root: ({ props }) => ({
        class: [
            'w-96 rounded-md whitespace-pre-line break-words',
            {
                '-translate-x-1/2': props.position === 'top-center' || props.position === 'bottom-center' || props.position === 'center',
                'min-w-[20vw] -translate-y-1/2': props.position === 'center'
            }
        ]
    }),
    message: ({ props }) => ({
        class: [
            'mb-4 border backdrop-blur-sm dark:backdrop-blur-md rounded-md',
            {
                'bg-blue-50/95 border-blue-200 text-blue-600 dark:bg-blue-500/15 dark:border-blue-700/35 dark:text-blue-500': props.message.severity === 'info' || props.message.severity === undefined,
                'bg-green-50/95 border-green-200 text-green-600 dark:bg-green-500/15 dark:border-green-700/35 dark:text-green-500': props.message.severity === 'success',
                'bg-yellow-50/95 border-yellow-200 text-yellow-600 dark:bg-yellow-500/15 dark:border-yellow-700/35 dark:text-yellow-500': props.message.severity === 'warn',
                'bg-red-50/95 border-red-200 text-red-600 dark:bg-red-500/15 dark:border-red-700/35 dark:text-red-500': props.message.severity === 'error',
                'bg-surface-100 border-surface-200 text-surface-600 dark:bg-surface-800 dark:border-surface-700 dark:text-surface-300': props.message.severity === 'secondary',
                'bg-surface-900 border-surface-950 text-surface-50 dark:bg-surface-0 dark:border-surface-100 dark:text-surface-950': props.message.severity === 'contrast'
            }
        ]
    }),
    messageContent: { class: 'flex items-start p-3 gap-2' },
    messageIcon: { class: 'flex-shrink-0 text-lg w-[1.125rem] h-[1.125rem] mt-1' },
    messageText: { class: 'flex-auto flex flex-col gap-2' },
    summary: { class: 'font-medium text-base' },
    detail: ({ props }) => ({
        class: [
            'font-medium text-sm text-surface-700 dark:text-surface-0',
            {
                'text-surface-0 dark:text-surface-950': props.message.severity === 'contrast'
            }
        ]
    }),
    buttonContainer: { class: '' },
    closeButton: ({ props }) => ({
        class: [
            'flex items-center justify-center overflow-hidden relative cursor-pointer bg-transparent select-none',
            'transition-colors duration-200 text-inherit w-7 h-7 rounded-full -mt-[25%] -end-1/4 p-0 border-none',
            'focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-2',
            {
                'hover:bg-blue-100 focus-visible:outline-blue-600 dark:hover:bg-white/5 dark:focus-visible:outline-blue-500': props.message.severity === 'info' || props.message.severity === undefined,
                'hover:bg-green-100 focus-visible:outline-green-600 dark:hover:bg-white/5 dark:focus-visible:outline-green-500': props.message.severity === 'success',
                'hover:bg-yellow-100 focus-visible:outline-yellow-600 dark:hover:bg-white/5 dark:focus-visible:outline-yellow-500': props.message.severity === 'warn',
                'hover:bg-red-100 focus-visible:outline-red-600 dark:hover:bg-white/5 dark:focus-visible:outline-red-500': props.message.severity === 'error',
                'hover:bg-surface-200 focus-visible:outline-surface-600 dark:hover:bg-surface-700 dark:focus-visible:outline-surface-300': props.message.severity === 'secondary',
                'hover:bg-surface-800 focus-visible:outline-surface-50 dark:hover:bg-surface-100 dark:focus-visible:outline-surface-950': props.message.severity === 'contrast'
            }
        ]
    }),
    closeIcon: { class: 'text-base w-4 h-4' },
    transition: {
        enterFromClass: 'opacity-0 translate-y-1/2',
        enterActiveClass: 'transition-all duration-500',
        leaveFromClass: 'max-h-[1000px]',
        leaveActiveClass: 'transition-all duration-500',
        leaveToClass: 'max-h-0 opacity-0 mb-0 overflow-hidden'
    }
});
</script>
