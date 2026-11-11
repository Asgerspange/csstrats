<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue';
import Dialog from '@/components/volt/Dialog.vue';
import Button from '@/components/volt/Button.vue';
import InputText from '@/components/volt/InputText.vue';
import { useToast } from '@/composables/useToast';
import { usePlan } from '@/composables/usePlan';
import api from '@/lib/axios';

interface TierPreset {
    tier: string;
    limits: {
        max_members: number;
        max_teams: number;
        max_stratbooks: number;
        max_demos: number;
        ai_antistrat: boolean;
    };
}

interface PlanResponse {
    tiers: TierPreset[];
    cadences: Record<string, { label: string; save: string | null; note: string }>;
}

const props = defineProps<{
    /** The user id whose plan is being switched. Defaults to the acting admin. */
    userId?: number;
}>();

const visible = defineModel<boolean>('visible', { default: false });
const toast = useToast();
const { user, refresh } = usePlan();

const targetId = computed(() => props.userId ?? user.value?.id ?? null);

const tiers = ref<TierPreset[]>([]);
const loading = ref(false);
const saving = ref(false);
const showCustom = ref(false);

// The custom-limit form. Seeded from the admin's current plan when expanded.
const custom = reactive({
    max_members: 8,
    max_teams: 1,
    max_stratbooks: 8,
    max_demos: 100000,
    ai_antistrat: false,
    round_planner: true,
});

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data } = await api.get<PlanResponse>('/api/admin/plans');
        tiers.value = data.tiers;
    } catch {
        // ignore — switcher just won't show presets
    } finally {
        loading.value = false;
    }
}

async function applyTier(tier: string) {
    if (!targetId.value) return;
    saving.value = true;
    try {
        await api.post(`/api/admin/users/${targetId.value}/plan`, { tier });
        await refresh();
        toast.success('Plan switched', `Active plan is now ${tier}.`);
        visible.value = false;
    } catch {
        toast.error('Could not switch plan', 'Please try again later.');
    } finally {
        saving.value = false;
    }
}

async function applyCustom() {
    if (!targetId.value) return;
    saving.value = true;
    try {
        await api.post(`/api/admin/users/${targetId.value}/plan`, {
            max_members: Number(custom.max_members),
            max_teams: Number(custom.max_teams),
            max_stratbooks: Number(custom.max_stratbooks),
            max_demos: Number(custom.max_demos),
            ai_antistrat: !!custom.ai_antistrat,
            round_planner: !!custom.round_planner,
        });
        await refresh();
        toast.success('Custom limits applied', 'Your plan limits have been updated.');
        visible.value = false;
    } catch {
        toast.error('Could not apply limits', 'Please try again later.');
    } finally {
        saving.value = false;
    }
}

async function clearOverride() {
    if (!targetId.value) return;
    saving.value = true;
    try {
        await api.delete(`/api/admin/users/${targetId.value}/plan`);
        await refresh();
        toast.success('Override cleared', 'Your real subscription now applies.');
        visible.value = false;
    } catch {
        toast.error('Could not clear override', 'Please try again later.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:visible="visible" header="Switch plan (admin)" :modal="true" appendTo="body" class="w-[460px]">
        <div class="flex flex-col gap-5 py-2">
            <p class="text-xs text-[var(--app-text-3,rgba(255,255,255,0.6))] leading-relaxed">
                Preview any tier's gating end to end. This writes an admin override to your
                <span class="font-mono">user_limits</span> row, so the in-app limits and the upgrade
                popups behave exactly as they would for a real subscriber on that plan. Clear the
                override to hand resolution back to your real subscription.
            </p>

            <div v-if="loading" class="text-sm text-[var(--app-text-3,rgba(255,255,255,0.6))] py-4">Loading tiers…</div>

            <div v-else class="flex flex-col gap-2">
                <button
                    v-for="t in tiers"
                    :key="t.tier"
                    type="button"
                    class="flex items-center justify-between rounded-lg border border-[var(--app-border,rgba(255,255,255,0.09))] bg-[var(--app-surface-2,rgba(255,255,255,0.04))] px-4 py-3 text-left transition-colors hover:border-[var(--main-color,#ff8c00)]/50 disabled:opacity-50"
                    :disabled="saving"
                    @click="applyTier(t.tier)"
                >
                    <div class="flex flex-col gap-0.5">
                        <span class="font-semibold text-sm">{{ t.tier }}</span>
                        <span class="text-[11px] text-[var(--app-text-3,rgba(255,255,255,0.6))] font-mono tabular-nums">
                            {{ t.limits.max_maps === null ? 'All maps' : t.limits.max_maps + ' maps' }} · {{ t.limits.max_demos >= 100000 ? '∞' : t.limits.max_demos }} demos · Builder {{ t.limits.round_planner ? 'on' : 'off' }} · AI {{ t.limits.ai_antistrat ? 'on' : 'off' }}
                        </span>
                    </div>
                    <span class="material-symbols text-[var(--app-text-3,rgba(255,255,255,0.5))] text-[18px]">chevron_right</span>
                </button>
            </div>

            <!-- Custom limits expander -->
            <div class="border-t border-[var(--app-border,rgba(255,255,255,0.09))] pt-4">
                <button
                    type="button"
                    class="flex items-center gap-1 text-xs font-semibold text-[var(--app-text-2,rgba(255,255,255,0.75))] hover:text-white"
                    @click="showCustom = !showCustom"
                >
                    <span class="material-symbols text-[15px] transition-transform" :class="showCustom ? 'rotate-90' : ''">chevron_right</span>
                    Custom limits
                </button>

                <div v-if="showCustom" class="flex flex-col gap-3 mt-3">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] uppercase tracking-wider text-[var(--app-text-3,rgba(255,255,255,0.5))]">Max members</span>
                            <InputText v-model="custom.max_members" type="number" min="0" class="w-full" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] uppercase tracking-wider text-[var(--app-text-3,rgba(255,255,255,0.5))]">Max teams</span>
                            <InputText v-model="custom.max_teams" type="number" min="0" class="w-full" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] uppercase tracking-wider text-[var(--app-text-3,rgba(255,255,255,0.5))]">Max stratbooks</span>
                            <InputText v-model="custom.max_stratbooks" type="number" min="0" class="w-full" />
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] uppercase tracking-wider text-[var(--app-text-3,rgba(255,255,255,0.5))]">Max demos</span>
                            <InputText v-model="custom.max_demos" type="number" min="0" class="w-full" />
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="custom.ai_antistrat" type="checkbox" class="accent-[var(--main-color,#ff8c00)]" />
                        AI Antistrat
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="custom.round_planner" type="checkbox" class="accent-[var(--main-color,#ff8c00)]" />
                        Round Planner (Strat Builder)
                    </label>
                    <Button label="Apply custom limits" :disabled="saving" @click="applyCustom" />
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Clear override" severity="secondary" :disabled="saving" @click="clearOverride" />
            <Button label="Done" @click="visible = false" />
        </template>
    </Dialog>
</template>
