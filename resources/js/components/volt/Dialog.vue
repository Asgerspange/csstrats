<template>
    <Dialog
        unstyled
        :dismissableMask="dismissableMask"
        :pt="mergedPt"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template #closebutton="{ closeCallback }">
            <SecondaryButton variant="text" rounded @click="closeCallback" autofocus>
                <template #icon>
                    <TimesIcon />
                </template>
            </SecondaryButton>
        </template>

        <template #maximizebutton="{ maximized, maximizeCallback }">
            <SecondaryButton variant="text" rounded @click="maximizeCallback" autofocus>
                <template #icon>
                    <WindowMinimizeIcon v-if="maximized" />
                    <WindowMaximizeIcon v-else />
                </template>
            </SecondaryButton>
        </template>

        <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
            <slot :name="slotName" v-bind="slotProps ?? {}" />
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import TimesIcon from '@primevue/icons/times';
import WindowMaximizeIcon from '@primevue/icons/windowmaximize';
import WindowMinimizeIcon from '@primevue/icons/windowminimize';
import Dialog, { type DialogPassThroughOptions, type DialogProps } from 'primevue/dialog';
import { computed, ref } from 'vue';
import SecondaryButton from './SecondaryButton.vue';
import { ptViewMerge } from './utils';

// Define the component props
interface Props extends /* @vue-ignore */ DialogProps {
    dismissableMask?: boolean;
    pt?: DialogPassThroughOptions;
}

// Set the default to true
const props = withDefaults(defineProps<Props>(), {
    dismissableMask: true
});

// Mirrors the sidebar switcher / picker surface: --app-surface body, soft
// --app-border-2 hairline (not the old orange border), --app-text typography,
// the deep 0 20px 40px -8px shadow, and divided header/footer like the popover.
// Surface/text/border use --app-* tokens with --card / oklch fallbacks so the
// blue theme (which omits --app-*) still resolves.
const theme = ref<DialogPassThroughOptions>({
    root: `max-h-[90%] max-w-[screen] rounded-xl min-w-[200px] sm:min-w-[620px]
        border border-[var(--app-border-2,oklch(1_0_0_/_0.14))]
        bg-[var(--app-surface,var(--card-primary-bg))]
        text-[var(--app-text,var(--primary-text-color))]
        shadow-[0_20px_40px_-8px_rgba(0,0,0,0.5)]
        p-maximized:w-screen p-maximized:h-screen p-maximized:top-0 p-maximized:start-0 p-maximized:max-h-full p-maximized:rounded-none`,
    header: `flex items-center justify-between shrink-0 px-5 py-4 border-b border-[var(--app-border,oklch(1_0_0_/_0.07))]`,
    title: `font-semibold text-lg text-[var(--app-text,var(--primary-text-color))]`,
    headerActions: `flex items-center gap-2`,
    content: `overflow-y-auto p-5 p-maximized:grow`,
    footer: `shrink-0 px-5 py-4 flex justify-end gap-2 border-t border-[var(--app-border,oklch(1_0_0_/_0.07))]`,
    mask: `bg-[oklch(0.04_0.01_250)]/70 fixed top-0 start-0 w-full h-full pointer-events-auto`,
    transition: {
        enterFromClass: 'opacity-0 scale-[0.97]',
        enterActiveClass: 'transition-all duration-150 ease-[cubic-bezier(0,0,0.2,1)]',
        leaveActiveClass: 'transition-all duration-150 ease-[cubic-bezier(0.4,0,0.2,1)]',
        leaveToClass: 'opacity-0 scale-[0.97]'
    }
});

/* Merge any consumer-supplied `pt` onto the Volt theme. The vue-ignored
   `extends DialogProps` declaration otherwise drops `pt` into $attrs, where
   the template's explicit `:pt` binding shadows it, making a passed-in `:pt`
   silently a no-op. */
const classOf = (v: unknown): string => {
    if (typeof v === 'string') return v;
    if (v && typeof v === 'object' && typeof (v as any).class === 'string') return (v as any).class;
    return '';
};

const mergedPt = computed<DialogPassThroughOptions>(() => {
    const userPt = props.pt as Record<string, any> | undefined;
    if (!userPt) return theme.value;
    const base = theme.value as Record<string, any>;
    const out: Record<string, any> = { ...base };
    for (const section of Object.keys(userPt)) {
        const baseVal = base[section];
        const userVal = userPt[section];
        const baseIsObj = baseVal && typeof baseVal === 'object';
        const userIsObj = userVal && typeof userVal === 'object';
        const mergedClass = [classOf(baseVal), classOf(userVal)].filter(Boolean).join(' ');
        if (baseIsObj || userIsObj) {
            out[section] = {
                ...(baseIsObj ? baseVal : {}),
                ...(userIsObj ? userVal : {}),
                ...(mergedClass ? { class: mergedClass } : {})
            };
        } else {
            out[section] = mergedClass || userVal;
        }
    }
    return out;
});
</script>