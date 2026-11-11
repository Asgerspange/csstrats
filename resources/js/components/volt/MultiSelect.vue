<template>
    <MultiSelect
        unstyled
        :pt="theme"
        :maxSelectedLabels="2"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template #dropdownicon>
            <span class="material-symbols text-[18px] leading-none">unfold_more</span>
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
    </MultiSelect>
</template>

<script setup lang="ts">
import SearchIcon from '@primevue/icons/search';
import SpinnerIcon from '@primevue/icons/spinner';
import TimesIcon from '@primevue/icons/times';
import MultiSelect, { type MultiSelectPassThroughOptions, type MultiSelectProps } from 'primevue/multiselect';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ MultiSelectProps {}
defineProps<Props>();

// Mirrors the sidebar team / stratbook switcher popovers (and the Select
// wrapper): soft-bordered rounded surface panel with a deep shadow, comfortable
// option rows, primary-tinted selected state + chips. Surface/text/border use
// the --app-* tokens with --select-bg / oklch fallbacks so the blue theme
// (which omits --app-*) still resolves.
const theme = ref<MultiSelectPassThroughOptions>({

    /* ===============================
     * Root container (trigger)
     * =============================== */
    root: `
        inline-flex
        items-center
        relative
        select-none
        cursor-pointer

        w-full
        max-w-[240px]
        max-h-[40px]

        rounded-lg
        border
        border-[var(--app-border-2,oklch(1_0_0_/_0.14))]

        bg-[var(--app-surface,var(--select-bg))]

        hover:border-[var(--app-border-3,oklch(1_0_0_/_0.24))]
        p-focus:border-primary/60

        transition-colors
        duration-150
    `,

    /* ===============================
     * Label container
     * =============================== */
    labelContainer: `
        flex-auto
        overflow-hidden
    `,

    /* ===============================
     * Selected values / chips
     * =============================== */
    label: `
        flex
        items-center
        gap-1

        w-[80%]
        flex-auto

        whitespace-nowrap
        overflow-hidden
        overflow-ellipsis

        px-3
        py-2

        bg-transparent
        border-none
        outline-none

        text-sm
        font-medium
        text-[var(--app-text,var(--primary-text-color))]

        /* Placeholder */
        p-placeholder:font-normal
        p-placeholder:text-[var(--app-text-3,oklch(1_0_0_/_0.5))]
        p-empty:opacity-0

        /* Clearable padding */
        p-clearable:pe-7

        /* Size variants */
        p-small:text-sm
        p-small:px-[0.625rem]
        p-small:py-[0.375rem]

        p-large:text-base
        p-large:px-[0.875rem]
        p-large:py-[0.625rem]
    `,

    /* ===============================
     * Chips — primary tint, like an active switcher row
     * =============================== */
    pcChip: {
        root: `
            inline-flex
            items-center
            gap-1.5

            px-2
            py-0.5

            rounded-md
            text-xs
            font-medium

            bg-primary/15
            text-[var(--app-text,var(--primary-text-color))]

            p-removable:pe-1.5
        `,
        removeIcon: `
            cursor-pointer
            w-3.5
            h-3.5
            text-[var(--app-text-3,oklch(1_0_0_/_0.55))]
            hover:text-[var(--app-text,var(--primary-text-color))]
        `
    },

    /* ===============================
     * Dropdown icon
     * =============================== */
    dropdown: `
        flex
        items-center
        justify-center
        shrink-0

        w-9

        bg-transparent
        text-[var(--app-text-3,oklch(1_0_0_/_0.55))]
    `,

    /* ===============================
     * Overlay panel — the switcher popover look
     * =============================== */
    overlay: `
        absolute
        top-0
        left-0
        !mt-1.5
        rounded-lg
        overflow-hidden
        min-w-full
        max-w-[min(22rem,90vw)]
        custom-track

        border
        border-[var(--app-border-2,oklch(1_0_0_/_0.14))]

        bg-[var(--app-surface,var(--select-bg))]
        text-[var(--app-text,var(--primary-text-color))]

        shadow-[0_20px_40px_-8px_rgba(0,0,0,0.5)]
    `,

    /* ===============================
     * Header (filter + select-all)
     * =============================== */
    header: `
        !p-2
        flex
        flex-col-reverse
        gap-2
        border-b
        border-[var(--app-border,oklch(1_0_0_/_0.07))]
    `,

    pcFilterContainer: {
        root: `
            relative
            w-full
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
            pe-9

            rounded-md
            outline-none

            text-sm
            bg-[var(--app-surface-2,var(--select-bg))]
            text-[var(--app-text,var(--primary-text-color))]

            border
            border-[var(--app-border-2,oklch(1_0_0_/_0.14))]

            focus:border-primary/50

            transition-colors
            duration-150
        `
    },

    /* ===============================
    * Header Checkbox (Select All)
    * =============================== */
    pcHeaderCheckbox: {
        root: `
            relative
            inline-flex
            items-center
            select-none
            w-5 h-5
            cursor-pointer
            /* The Label Text */
            after:content-['Select_All']
            after:absolute
            after:left-7
            after:text-sm
            after:text-[var(--app-text-2,var(--primary-text-color))]
            after:whitespace-nowrap
        `,
        input: `
            peer
            appearance-none
            absolute
            inset-0
            w-full h-full
            opacity-0
            z-10
            cursor-pointer
        `,
        box: ({ context }: any) => ({
            class: [
                'flex items-center justify-center',
                'w-5 h-5 rounded-[5px] border transition-all duration-150',
                // Default State
                'border-[var(--app-border-2,oklch(1_0_0_/_0.18))] bg-[var(--app-surface-2,var(--select-bg))]',
                // Checked State
                { 'bg-primary border-primary': context.checked }
            ]
        }),
        icon: 'w-3.5 h-3.5 text-[var(--color-primary-foreground,#fff)]'
    },

    pcFilterIconContainer: {
        root: `
            absolute
            top-1/2
            -mt-2
            end-3
            z-1

            leading-none
            text-[var(--app-text-3,oklch(1_0_0_/_0.5))]
        `
    },

    /* ===============================
     * Options list
     * =============================== */
    listContainer: `
        overflow-auto
        custom-track
        max-w-[min(22rem,90vw)]
    `,

    list: `
        !m-1.5
        !p-0

        flex
        flex-col
        gap-px

        list-none
    `,

    /* ===============================
     * Option groups
     * =============================== */
    optionGroup: `
        m-0
        px-3
        pt-3
        pb-1

        bg-transparent
        text-[10px]
        font-bold
        uppercase
        tracking-[0.12em]
        text-[var(--app-text-3,oklch(1_0_0_/_0.45))]
    `,

    optionGroupLabel: ``,

    /* ===============================
     * Option item
     * =============================== */
    option: `
        relative
        flex
        items-center
        gap-2

        px-3
        py-2

        cursor-pointer
        whitespace-nowrap
        overflow-hidden

        rounded-md
        border-none
        min-h-[38px]

        bg-transparent

        text-sm
        text-[var(--app-text-2,var(--primary-text-color))]

        transition-colors
        duration-150

        /* Hover / focus */
        hover:bg-[var(--app-surface-2,var(--card-btn-bg))]
        hover:text-[var(--app-text,var(--primary-text-color))]
        p-focus:bg-[var(--app-surface-2,var(--card-btn-bg))]
        p-focus:text-[var(--app-text,var(--primary-text-color))]

        /* Selected — primary tint */
        p-selected:bg-primary/12
        p-selected:font-semibold
        p-selected:text-[var(--app-text,var(--primary-text-color))]
        p-focus:p-selected:bg-primary/15
    `,

    /* ===============================
     * Option checkbox
     * =============================== */
    pcOptionCheckbox: {
        root: `
            relative
            inline-flex
            select-none
            w-5
            h-5
        `,
        input: `
            peer
            cursor-pointer
            appearance-none
            absolute
            inset-0
            opacity-0
            z-10
        `,
        box: `
            flex
            items-center
            justify-center

            w-5
            h-5

            rounded-[5px]
            border
            border-[var(--app-border-2,oklch(1_0_0_/_0.18))]

            bg-[var(--app-surface-2,var(--select-bg))]
            text-[var(--color-primary-foreground,#fff)]

            transition-colors
            duration-150

            p-checked:bg-primary
            p-checked:border-primary
        `,
        icon: `
            w-3.5
            h-3.5
        `
    },

    /* ===============================
     * Empty state
     * =============================== */
    emptyMessage: `
        px-3
        py-3
        text-sm
        text-center
        text-[var(--app-text-3,oklch(1_0_0_/_0.5))]
    `,

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
