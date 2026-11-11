<template>
    <Select
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template #dropdownicon>
            <ChevronDownIcon />
        </template>
        <template #loadingicon>
            <SpinnerIcon class="animate-spin" />
        </template>
        <template #filtericon>
            <SearchIcon class="text-surface-400" />
        </template>
        <template #clearicon="{ clearCallback }">
            <TimesIcon @click="clearCallback" class="text-[var(--primary-text-color)] absolute top-1/2 -mt-2 end-10" />
        </template>
        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Select>
</template>

<script setup lang="ts">
import ChevronDownIcon from '@primevue/icons/chevrondown';
import SearchIcon from '@primevue/icons/search';
import SpinnerIcon from '@primevue/icons/spinner';
import TimesIcon from '@primevue/icons/times';
import Select, { type SelectPassThroughOptions, type SelectProps } from 'primevue/select';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ SelectProps {}
defineProps<Props>();

const theme = ref<SelectPassThroughOptions>({

    /* ===============================
     * Root container
     * =============================== */
    root: `
        group
        inline-flex
        relative
        select-none
        cursor-pointer

        w-full
        max-w-[200px]
        max-h-[40px]

        rounded-[8px]
        border
        border-[var(--primary-btn-bg)]

        bg-[var(--select-bg)]

        transition-colors
        duration-200
    `,

    /* ===============================
     * Selected label / value
     * =============================== */
    label: `
        peer
        block
        flex-auto
        w-[1%]

        whitespace-nowrap
        overflow-hidden
        overflow-ellipsis

        px-3
        py-2

        bg-transparent
        border-none
        outline-none

        /* Actual selected value */
        text-[var(--primary-text-color)]

        /* Placeholder (no value selected) */
        p-placeholder:text-red-500
        p-empty:opacity-0

        /* Clearable padding */
        p-clearable:pe-7

        /* States */
        p-disabled:text-surface-500
        p-editable:cursor-default

        /* Size variants */
        p-small:text-sm
        p-small:px-[0.625rem]
        p-small:py-[0.375rem]

        p-large:text-lg
        p-large:px-[0.875rem]
        p-large:py-[0.625rem]
    `,

    /* ===============================
     * Dropdown icon container
     * =============================== */
    dropdown: `
        flex
        items-center
        justify-center
        shrink-0

        w-10
        rounded-[8px]

        bg-transparent
        text-[var(--primary-text-color)]
    `,

    /* ===============================
     * Dropdown panel
     * =============================== */
    overlay: `
        absolute
        top-0
        left-0

        rounded-[8px]
        min-w-full
        max-w-fit

        bg-[var(--select-bg)]
        text-[var(--primary-text-color)]

        shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-2px_rgba(0,0,0,0.1)]
    `,

    /* ===============================
     * Header (filter area)
     * =============================== */
    header: `
        px-4
        pt-2
        pb-1
    `,

    pcFilterContainer: {
        root: `
            relative
        max-w-[200px]

        `
    },

    /* ===============================
     * Filter input
     * =============================== */
    pcFilter: {
        root: `
            w-full
            appearance-none

            px-3
            py-2
            ps-3
            pe-10

            rounded-md
            outline-hidden

            bg-[var(--select-bg)]
            text-[var(--p-select-color)]

            border
            border-[var(--main-color-dark-15)]

            disabled:bg-surface-200
            disabled:text-surface-500

            transition-colors
            duration-200

            shadow-[var(--win-box)]
            max-w-fit
        `
    },

    pcFilterIconContainer: {
        root: `
            absolute
            top-1/2
            -mt-2
            end-3
            z-1

            leading-none
        `
    },

    /* ===============================
     * Options list
     * =============================== */
    listContainer: `
        overflow-auto
        rounded-[8px]
        max-w-[400px]
    `,

    list: `
        m-0
        p-1

        flex
        flex-col
        gap-[2px]

        list-none
    `,

    /* ===============================
     * Option groups
     * =============================== */
    optionGroup: `
        m-0
        px-3
        py-2

        bg-transparent
        text-secondary
    `,

    optionGroupLabel: ``,

    /* ===============================
     * Option item
     * =============================== */
    option: `
        relative
        flex
        items-center

        px-3
        py-2

        cursor-pointer
        whitespace-nowrap
        overflow-hidden

        rounded-sm
        border-none
        min-h-[40px]
        bg-transparent
        text-[var(--primary-text-color)]

        transition-colors
        duration-200

        p-selected:bg-[var(--primary-btn-bg-hover)]
        hover:text-[var(--primary-text-color)]
        hover:bg-primary/20
    `,

    optionLabel: ``,

    optionCheckIcon: `
        relative
        -ms-[0.375rem]
        me-[0.375rem]
    `,

    optionBlankIcon: ``,

    /* ===============================
     * Empty state
     * =============================== */
    emptyMessage: `
        px-3
        py-2
    `,

    virtualScroller: ``,

    /* ===============================
     * Overlay animation
     * =============================== */
    transition: {
        enterFromClass: 'opacity-0 scale-y-75',
        enterActiveClass: 'transition duration-120 ease-[cubic-bezier(0,0,0.2,1)]',
        leaveActiveClass: 'transition-opacity duration-100 ease-linear',
        leaveToClass: 'opacity-0'
    }
});

</script>