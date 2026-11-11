<template>
    <Slider
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    />
</template>

<script setup lang="ts">
import Slider, { type SliderPassThroughOptions, type SliderProps } from 'primevue/slider';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ SliderProps {}
defineProps<Props>();

const handleCommon = `cursor-grab touch-none flex items-center justify-center h-[20px] w-[20px]
        bg-slider rounded-full
        border border-primary
        transition-colors duration-200
        focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-primary
        before:w-[16px] before:h-[16px] before:block before:rounded-full
        before:bg-slider
        before:shadow-[0px_0.5px_0px_0px_rgba(0,0,0,0.08),0px_1px_1px_0px_rgba(0,0,0,0.14)]
        before:transition-colors before:duration-200
        top-1/2 -mt-[10px] -ms-[10px]`;

const theme = ref<SliderPassThroughOptions>({
    root: `relative bg-slider rounded-xs
            h-[3px]`,
    range: `block bg-primary rounded-xs top-0 start-0 h-full`,
    handle: handleCommon,
    startHandler: handleCommon,
    endHandler: handleCommon
});
</script>
