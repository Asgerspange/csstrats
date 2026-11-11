<template>
    <Popover
        ref="el"
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Popover>
</template>

<script setup lang="ts">
import Popover, { type PopoverPassThroughOptions, type PopoverProps } from 'primevue/popover';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ PopoverProps {}
defineProps<Props>();

const theme = ref<PopoverPassThroughOptions>({
    root: `mt-[10px] p-flipped:-mt-[10px] p-flipped:mb-[10px]
        bg-card text-surface-700 rounded-lg
        border border-primary shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-2px_rgba(0,0,0,0.1)]
        before:bottom-full before:left-5 before:h-0 before:w-0 before:absolute before:pointer-events-none
        before:border-[10px] before:-ms-[10px] before:border-transparent before:border-b-primary
        after:bottom-full after:left-5 after:h-0 after:w-0 after:absolute after:pointer-events-none
        after:border-[8px] after:-ms-[8px] after:border-transparent after:border-b-primary
        p-flipped:before:bottom-auto p-flipped:before:top-full p-flipped:after:bottom-auto p-flipped:after:top-full
        p-flipped:after:border-b-transparent p-flipped:after:border-t-primary
        p-flipped:before:border-b-transparent p-flipped:before:border-t-primary`,
    content: `p-3`,
    transition: {
        enterFromClass: 'opacity-0 scale-y-75',
        enterActiveClass: 'transition duration-120 ease-[cubic-bezier(0,0,0.2,1)]',
        leaveActiveClass: 'transition-opacity duration-100 ease-linear',
        leaveToClass: 'opacity-0'
    }
});

const el = ref();
defineExpose({
    toggle: (event, target) => el.value.toggle(event, target),
    show: (event, target) => el.value.show(event, target),
    hide: () => el.value.toggle()
});
</script>
