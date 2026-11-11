<template>
    <Select
        unstyled
        :pt="theme"
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
    </Select>
</template>

<script setup lang="ts">
import SearchIcon from '@primevue/icons/search';
import SpinnerIcon from '@primevue/icons/spinner';
import TimesIcon from '@primevue/icons/times';
import Select, { type SelectPassThroughOptions, type SelectProps } from 'primevue/select';
import { ref } from 'vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ SelectProps {}
defineProps<Props>();

// Mirrors the sidebar team / stratbook switcher popovers: a soft-bordered,
// rounded surface panel with a deep shadow, comfortable option rows that
// highlight on hover, and a primary-tinted selected state with the check
// pushed to the right. Surface/text/border use the --app-* tokens (with
// --select-bg / oklch fallbacks so the blue theme, which omits --app-*, still
// resolves).
const theme = ref<SelectPassThroughOptions>({

    /* ===============================
     * Root container (trigger)
     * =============================== */
    root: `
        group
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

        text-sm
        font-medium
        text-[var(--app-text,var(--primary-text-color))]

        /* Placeholder (no value selected) */
        p-placeholder:font-normal
        p-placeholder:text-[var(--app-text-3,oklch(1_0_0_/_0.5))]
        p-empty:opacity-0

        /* Clearable padding */
        p-clearable:pe-7

        /* States */
        p-disabled:opacity-50
        p-editable:cursor-default

        /* Size variants */
        p-small:text-sm
        p-small:px-[0.625rem]
        p-small:py-[0.375rem]

        p-large:text-base
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

        w-9

        bg-transparent
        text-[var(--app-text-3,oklch(1_0_0_/_0.55))]
    `,

    /* ===============================
     * Dropdown panel — the switcher popover look
     * =============================== */
    overlay: `
        absolute
        top-0
        left-0
        !mt-1.5
        custom-track
        rounded-lg
        overflow-hidden
        min-w-full
        max-w-[min(22rem,90vw)]

        border
        border-[var(--app-border-2,oklch(1_0_0_/_0.14))]

        bg-[var(--app-surface,var(--select-bg))]
        text-[var(--app-text,var(--primary-text-color))]

        shadow-[0_20px_40px_-8px_rgba(0,0,0,0.5)]
    `,

    /* ===============================
     * Header (filter area)
     * =============================== */
    header: `
        !p-2
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
        max-w-[min(22rem,90vw)]
    `,

    list: `
        !m-1.5

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

        /* Selected — primary tint, like the active switcher row */
        p-selected:bg-primary/12
        p-selected:font-semibold
        p-selected:text-[var(--app-text,var(--primary-text-color))]
        p-focus:p-selected:bg-primary/15
    `,

    optionLabel: `flex-auto truncate`,

    optionCheckIcon: `
        order-last
        ms-auto
        shrink-0
        text-primary
    `,

    optionBlankIcon: `hidden`,

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