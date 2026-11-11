<template>
    <InputText
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    />
</template>

<script setup lang="ts">
import InputText, { type InputTextPassThroughOptions, type InputTextProps } from 'primevue/inputtext';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ InputTextProps {}
defineProps<Props>();

const theme = ref<InputTextPassThroughOptions>({
    root: `appearance-none rounded-md outline-hidden
        bg-[var(--card-bg)] 
        p-filled:bg-[var(--main-bg-hover)]
        text-white
        placeholder:text-[var(--main-color-dark-10)]
        border border-primary
        disabled:bg-[var(--main-bg-dark)] disabled:text-[var(--main-color-dark-15)]
        p-invalid:border-red-400
        p-invalid:placeholder:text-red-600
        px-3 py-2 p-fluid:w-full
        p-small:text-sm p-small:px-[0.625rem] p-small:py-[0.375rem]
        p-large:text-lg p-large:px-[0.875rem] p-large:py-[0.625rem]
        transition-colors duration-200 shadow-[var(--win-box)]`
});
</script>