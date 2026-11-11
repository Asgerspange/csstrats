<template>
    <ConfirmDialog
        unstyled
        :pt="theme"
        :ptOptions="{
            mergeProps: ptViewMerge
        }"
    >
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="flex items-center justify-between shrink-0 px-5 py-4 border-b border-[var(--app-border,oklch(1_0_0_/_0.07))]">
                <span class="font-semibold text-lg text-[var(--app-text,var(--primary-text-color))]">{{ message.header }}</span>
                <Button variant="text" rounded @click="rejectCallback" autofocus>
                    <template #icon>
                        <TimesIcon />
                    </template>
                </Button>
            </div>
            <div class="overflow-y-auto p-5 flex items-center gap-3 text-[var(--app-text-2,var(--primary-text-color))]">
                <ExclamationTriangeIcon class="size-6 shrink-0 text-[oklch(0.72_0.18_60)]" />
                <span>{{ message.message }}</span>
            </div>
            <div class="px-5 py-4 flex justify-end gap-2 border-t border-[var(--app-border,oklch(1_0_0_/_0.07))]">
                <Button @click="rejectCallback" :label="message.rejectProps?.label ?? message.rejectLabel ?? 'Cancel'" severity="secondary" />
                <Button @click="acceptCallback" :label="message.acceptProps?.label ?? message.acceptLabel ?? 'Confirm'" severity="primary" />
            </div>
        </template>
    </ConfirmDialog>
</template>

<script setup lang="ts">
import ExclamationTriangeIcon from '@primevue/icons/exclamationtriangle';
import TimesIcon from '@primevue/icons/times';
import ConfirmDialog, { type ConfirmDialogPassThroughOptions, type ConfirmDialogProps } from 'primevue/confirmdialog';
import { ref } from 'vue';
import Button from './Button.vue';
import SecondaryButton from './SecondaryButton.vue';
import { ptViewMerge } from './utils';

interface Props extends /* @vue-ignore */ ConfirmDialogProps {}
defineProps<Props>();

// Matches the Dialog wrapper / switcher surface.
const theme = ref<ConfirmDialogPassThroughOptions>({
    root: `max-h-[90%] max-w-[600px] rounded-xl z-[9999]
        border border-[var(--app-border-2,oklch(1_0_0_/_0.14))]
        bg-[var(--app-surface,var(--card-primary-bg))]
        text-[var(--app-text,var(--primary-text-color))]
        shadow-[0_20px_40px_-8px_rgba(0,0,0,0.5)]`,
    mask: `bg-[oklch(0.04_0.01_250)]/70 fixed top-0 start-0 w-full h-full z-[9998] flex items-center justify-center`,
    transition: {
        enterFromClass: 'opacity-0 scale-[0.97]',
        enterActiveClass: 'transition-all duration-150 ease-[cubic-bezier(0,0,0.2,1)]',
        leaveActiveClass: 'transition-all duration-150 ease-[cubic-bezier(0.4,0,0.2,1)]',
        leaveToClass: 'opacity-0 scale-[0.97]'
    }
});
</script>
