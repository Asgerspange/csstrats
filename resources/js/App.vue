<template>
    <div class="main-layout flex flex-col">
        <SteamConnectBanner />
        <div class="main-layout__body flex flex-col lg:flex-row">
            <Sidebar />
            <div class="flex-1 h-full overflow-hidden pt-[65px] lg:pt-0">
                <RouterView />
            </div>
        </div>
    </div>

    <ConfirmDialog />
    <Toast />
    <UpgradeModal />

    <!-- Global upload dialog — mounted here so uploads survive navigation -->
    <DemoUpload v-model:visible="showUploadDialog" :folder-id="currentUploadFolderId" />

    <!-- Floating upload indicator — shown when dialog is closed but uploads are active -->
    <Transition name="slide-up">
        <button
            v-if="uploadStore.hasActive && !showUploadDialog"
            class="fixed bottom-4 right-4 z-50 flex items-center gap-3 bg-zinc-900 border border-zinc-700 rounded-2xl px-4 py-3 shadow-2xl hover:border-primary/50 transition-colors"
            @click="showUploadDialog = true"
        >
            <div class="relative w-8 h-8 shrink-0">
                <svg class="absolute inset-0 -rotate-90" width="32" height="32" viewBox="0 0 32 32">
                    <circle cx="16" cy="16" r="12" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3" />
                    <circle cx="16" cy="16" r="12" fill="none" stroke="rgb(16,185,129)" stroke-width="3"
                        stroke-linecap="round"
                        :stroke-dasharray="75.4"
                        :stroke-dashoffset="75.4 * (1 - uploadStore.overallProgress / 100)"
                        style="transition: stroke-dashoffset 0.4s ease" />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <Icon icon="lucide:upload" class="text-primary text-xs" />
                </div>
            </div>
            <div class="text-left">
                <p class="text-xs font-semibold text-white leading-tight">
                    Uploading {{ uploadStore.active.length }} demo{{ uploadStore.active.length > 1 ? 's' : '' }}
                </p>
                <p class="text-[10px] text-zinc-500 mt-0.5">{{ uploadStore.overallProgress }}% · click to manage</p>
            </div>
        </button>
    </Transition>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router';
import Sidebar from '@/componentsnew/Sidebar.vue';
import SteamConnectBanner from '@/componentsnew/SteamConnectBanner.vue';
import { watch, computed, ref, onMounted, onUnmounted } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useStore } from '@/store';
import useTeams from '@/composables/useTeams';
import useUser from '@/composables/useUser';
import ConfirmDialog from '@/components/volt/ConfirmDialog.vue';
import Bus from '@/eventbus';
import Toast from '@/components/volt/Toast.vue';
import DemoUpload from '@/componentsnew/demo/DemoUpload.vue';
import UpgradeModal from '@/componentsnew/UpgradeModal.vue';
import { useUpgradeModal } from '@/composables/useUpgradeModal';
import { usePlan } from '@/composables/usePlan';
import { useUploadQueueStore } from '@/stores/uploadQueueStore';
import { useUXWizz } from '@/composables/useUXWizz';
import { Icon } from '@iconify/vue';

const { track } = useUXWizz();
const store       = useStore();
const uploadStore = useUploadQueueStore();
const queryClient = useQueryClient();
const { Teams }   = useTeams();
const { User }    = useUser();
const { open: openUpgrade } = useUpgradeModal();
const { can }     = usePlan();

const { data: user }  = User.get();
const { data: teams } = Teams.get();

watch(teams, (v) => { if (v) store.setTeams(v); }, { immediate: true });
watch(user,  (v) => { if (v) store.setUser(v);  }, { immediate: true });

// ── Upload dialog ─────────────────────────────────────────────────────────────
const showUploadDialog      = ref(false);
const currentUploadFolderId = ref<number | null>(null);

/**
 * Best-effort count of the user's current demos (parsed + queued) from the
 * TanStack cache, so the demo-upload gate can pre-empt the backend 403
 * without an extra network round-trip. Returns null when the demos query
 * hasn't been loaded yet, in which case we let the dialog open and rely on
 * the backend PLAN_LIMIT_EXCEEDED gate (the axios interceptor pops the
 * upgrade modal). No wrong-key query is created.
 */
const cachedDemoCount = computed<number | null>(() => {
    const caches = queryClient.getQueriesData<any[]>({ queryKey: ['demos'] });
    let total = 0;
    let found = false;
    for (const [, data] of caches) {
        if (Array.isArray(data)) { total = Math.max(total, data.length); found = true; }
    }
    return found ? total : null;
});

const handleOpenUpload = (payload?: { folderId?: number | null }) => {
    // Pre-emptive demo-cap gate. The backend re-checks on requestUpload, so a
    // stale cache here only means we open the dialog and let the real 403 fire.
    const usage = cachedDemoCount.value;
    if (usage !== null && !can('demos', usage)) {
        openUpgrade({ feature: 'demos', requiredTier: 'Team' });
        return;
    }
    currentUploadFolderId.value = payload?.folderId ?? null;
    showUploadDialog.value = true;
};

onMounted(() => {
    Bus.on('openUploadDialog', handleOpenUpload);
});
onUnmounted(() => {
    Bus.remove('openUploadDialog', handleOpenUpload);
});
</script>

<style lang="scss">
/* Unify auth-app bg with the team / demo page palette so short pages
   don't reveal the old body bg through gaps */
html, body {
    background: var(--app-canvas, oklch(0.125 0.012 250));
}

.main-layout {
    width: 100%;
    height: 100%;
    background: var(--app-canvas, oklch(0.125 0.012 250));
}

.main-layout__body {
    flex: 1;
    min-height: 0;
}

.slide-up-enter-active, .slide-up-leave-active {
    transition: all 0.3s ease;
}
.slide-up-enter-from, .slide-up-leave-to {
    opacity: 0;
    transform: translateY(16px);
}
</style>