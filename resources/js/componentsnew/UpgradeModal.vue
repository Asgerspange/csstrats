<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Dialog from '@/components/volt/Dialog.vue';
import Button from '@/components/volt/Button.vue';
import { useUpgradeModal } from '@/composables/useUpgradeModal';
import { usePlan } from '@/composables/usePlan';
import { usePaddle } from '@/composables/usePaddle';
import { useToast } from '@/composables/useToast';
import api from '@/lib/axios';
import type { PlanFeature } from '@/composables/usePlan';

const { state, close } = useUpgradeModal();
const { plan, refresh } = usePlan();
const { checkout } = usePaddle();
const toast = useToast();

// ── Per-feature copy ───────────────────────────────────────────────────────
// `title`/`body` describe what the user tried to do and why it's gated;
// `unlockTiers` is the set of tiers the modal pre-highlights as the upgrade
// path for that feature. The full tier list still renders so the user can pick
// any tier; the highlight just steers them.
interface FeatureCopy {
    title: string;
    body: string;
    migrationBody: string;
    icon: string;
    unlockTiers: string[];
}

const COPY: Record<PlanFeature, FeatureCopy> = {
    stratbooks: {
        title: 'You have reached your map limit',
        body: 'Your current plan caps the number of maps per team. Upgrade to Team or Pro to unlock more maps and keep building out your playbook.',
        migrationBody: 'We are moving CsStrats to Paddle billing. Migrate to a Paddle plan to keep creating tactics. Your current subscription stays active until the end of its period.',
        icon: 'map',
        unlockTiers: ['Team', 'Pro'],
    },
    demos: {
        title: 'You have reached your demo upload limit',
        body: 'Your current plan includes a small demo allowance for trying the viewer. Upgrade to Team or Pro for unlimited uploads and the full 2D/3D playback.',
        migrationBody: 'We are moving CsStrats to Paddle billing. Migrate to a Paddle plan to keep uploading demos. Your current subscription stays active until the end of its period.',
        icon: 'videocam',
        unlockTiers: ['Team', 'Pro'],
    },
    ai_antistrat: {
        title: 'AI Antistrat is a Pro feature',
        body: 'Chat with your opponent\'s demos, auto-generate counter-strats, and refresh AI insights. Upgrade to Pro to unlock the full antistrat workbench.',
        migrationBody: 'We are moving CsStrats to Paddle billing. Migrate to the Pro plan on Paddle to keep using AI Antistrat. Your current subscription stays active until the end of its period.',
        icon: 'shield',
        unlockTiers: ['Pro'],
    },
    round_planner: {
        title: 'The Strat Builder is a Team feature',
        body: 'Draw timed player paths, schedule utility throws, and play rounds back from start to finish. Upgrade to Team or Pro to unlock the Strat Builder.',
        migrationBody: 'We are moving CsStrats to Paddle billing. Migrate to a Paddle plan to keep using the Strat Builder. Your current subscription stays active until the end of its period.',
        icon: 'build',
        unlockTiers: ['Team', 'Pro'],
    },
};

const copy = computed<FeatureCopy>(() => {
    const f = state.feature ?? 'stratbooks';
    return COPY[f] ?? COPY.stratbooks;
});

const visible = computed({
    get: () => state.open,
    set: (v: boolean) => { if (!v) close(); },
});

// ── Plan catalogue ─────────────────────────────────────────────────────────
interface TierPreset {
    tier: string;
    price_ids: Record<string, string>;
    limits: { max_members: number; max_teams: number; max_stratbooks: number; max_maps: number | null; max_demos: number; ai_antistrat: boolean; round_planner: boolean };
    features: string[];
}
type Cadence = 'monthly' | 'semi' | 'yearly';

const tiers = ref<TierPreset[]>([]);
const cadences = ref<Record<Cadence, { label: string; save: string | null; note: string }>>({
    monthly: { label: 'Monthly', save: null, note: 'No commitment' },
    semi: { label: 'Every 6 months', save: 'Save 20%', note: '' },
    yearly: { label: 'Yearly', save: 'Save 36.6%', note: '' },
});
const loading = ref(false);
const activeCadence = ref<Cadence>('yearly');
const checkingOut = ref<string | null>(null); // tier name currently opening checkout
const alreadySubscribed = ref(false);

const cadenceList = computed<Cadence[]>(() => Object.keys(cadences.value) as Cadence[]);

async function loadCatalogue() {
    loading.value = true;
    try {
        const { data } = await api.get<{ tiers: TierPreset[]; cadences: typeof cadences.value }>('/api/plans');
        tiers.value = data.tiers;
        if (data.cadences) cadences.value = data.cadences;
    } catch {
        // Catalogue is best-effort; the See plans link is the fallback.
    } finally {
        loading.value = false;
    }
}

// Load the catalogue the first time the modal opens.
watch(() => state.open, (open) => {
    if (open && !tiers.value.length && !loading.value) loadCatalogue();
    if (open) alreadySubscribed.value = false;
});

const needsMigration = computed(() => plan.value.needs_migration);
const currentTier = computed(() => plan.value.tier);

function isUnlock(tier: string): boolean {
    return copy.value.unlockTiers.includes(tier);
}

function priceIdFor(tier: TierPreset): string | undefined {
    return tier.price_ids[activeCadence.value];
}

async function choose(tier: TierPreset) {
    const priceId = priceIdFor(tier);
    if (!priceId || checkingOut.value) return;
    checkingOut.value = tier.tier;
    try {
        const result = await checkout(priceId);
        if (result.status === 'subscribed') {
            alreadySubscribed.value = true;
            toast.info('You already have a subscription', 'Manage it from your profile.');
        } else if (result.status === 'error') {
            toast.error('Checkout unavailable', result.message ?? 'Please try again later.');
        }
        // 'opened' — Paddle overlay is up. Refetch the user once it's likely
        // closed; we can't reliably listen for the overlay close across Paddle
        // SDK versions, so a short delay + invalidate is the pragmatic path.
        if (result.status === 'opened') {
            setTimeout(() => { refresh(); }, 4000);
        }
    } finally {
        checkingOut.value = null;
    }
}

const subtitle = computed(() => needsMigration.value ? copy.value.migrationBody : copy.value.body);
</script>

<template>
    <Dialog v-model:visible="visible" :modal="true" :closable="true" appendTo="body" class="w-[520px]">
        <template #header>
            <div class="flex items-center gap-3">
                <span class="material-symbols text-[var(--main-color,#ff8c00)]">{{ copy.icon }}</span>
                <div class="flex flex-col gap-0.5">
                    <span class="font-semibold text-base">{{ copy.title }}</span>
                    <span v-if="needsMigration" class="text-[11px] font-mono uppercase tracking-[0.08em] text-[var(--main-color,#ff8c00)]">
                        Migration to Paddle required
                    </span>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-5 py-1">
            <p class="text-sm text-[var(--app-text-2,rgba(255,255,255,0.7))] leading-relaxed">
                {{ subtitle }}
            </p>

            <div v-if="alreadySubscribed" class="rounded-lg border border-[var(--app-border,rgba(255,255,255,0.09))] bg-[var(--app-surface-2,rgba(255,255,255,0.04))] px-4 py-3 text-sm text-[var(--app-text-2,rgba(255,255,255,0.75))]">
                You already have an active subscription. Manage it in
                <a href="/profile" class="text-[var(--main-color,#ff8c00)] underline-offset-2 hover:underline">your profile</a>.
            </div>

            <div v-else-if="loading" class="text-sm text-[var(--app-text-3,rgba(255,255,255,0.6))] py-4">Loading plans…</div>

            <template v-else>
                <!-- Billing cadence -->
                <div class="flex justify-center">
                    <div
                        class="inline-flex rounded-md border border-[var(--app-border,rgba(255,255,255,0.09))] bg-[var(--app-surface,rgba(255,255,255,0.03))] p-1"
                        role="tablist"
                        aria-label="Billing cadence"
                    >
                        <button
                            v-for="c in cadenceList"
                            :key="c"
                            type="button"
                            role="tab"
                            :aria-selected="activeCadence === c"
                            class="flex flex-col items-center gap-0.5 rounded-md px-4 py-2 transition-colors duration-150"
                            :class="activeCadence === c
                                ? 'bg-[var(--main-color,#ff8c00)] text-black'
                                : 'text-[var(--app-text-2,rgba(255,255,255,0.7))] hover:bg-white/5'"
                            @click="activeCadence = c"
                        >
                            <span class="text-xs font-semibold">{{ cadences[c].label }}</span>
                            <span
                                v-if="cadences[c].save"
                                class="text-[10px] font-mono uppercase tracking-wide"
                                :class="activeCadence === c ? 'text-black/70' : 'text-[var(--main-color,#ff8c00)]'"
                            >{{ cadences[c].save }}</span>
                            <span
                                v-else
                                class="text-[10px] font-mono uppercase tracking-wide"
                                :class="activeCadence === c ? 'text-black/70' : 'text-[var(--app-text-3,rgba(255,255,255,0.5))]'"
                            >{{ cadences[c].note }}</span>
                        </button>
                    </div>
                </div>

                <!-- Tier list -->
                <div class="flex flex-col gap-2">
                    <button
                        v-for="t in tiers"
                        :key="t.tier"
                        type="button"
                        class="flex items-center justify-between rounded-lg border px-4 py-3 text-left transition-colors disabled:opacity-50"
                        :class="isUnlock(t.tier)
                            ? 'border-[var(--main-color,#ff8c00)]/60 bg-[var(--main-color,#ff8c00)]/5 hover:border-[var(--main-color,#ff8c00)]'
                            : 'border-[var(--app-border,rgba(255,255,255,0.09))] bg-[var(--app-surface-2,rgba(255,255,255,0.04))] hover:border-[var(--app-text-3,rgba(255,255,255,0.3))]'"
                        :disabled="checkingOut !== null"
                        @click="choose(t)"
                    >
                        <div class="flex flex-col gap-0.5 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm">{{ t.tier }}</span>
                                <span v-if="isUnlock(t.tier)" class="text-[10px] font-mono uppercase tracking-wide text-[var(--main-color,#ff8c00)]">Unlocks this</span>
                                <span v-else-if="t.tier === currentTier" class="text-[10px] font-mono uppercase tracking-wide text-[var(--app-text-3,rgba(255,255,255,0.5))]">Current</span>
                            </div>
                            <span class="text-[11px] text-[var(--app-text-3,rgba(255,255,255,0.6))] font-mono tabular-nums truncate">
                                {{ t.limits.max_maps === null ? 'All maps' : t.limits.max_maps + ' maps' }} · {{ t.limits.max_demos >= 100000 ? '∞' : t.limits.max_demos }} demos · Builder {{ t.limits.round_planner ? 'on' : 'off' }} · AI {{ t.limits.ai_antistrat ? 'on' : 'off' }}
                            </span>
                        </div>
                        <span class="flex items-center gap-2 shrink-0">
                            <span v-if="checkingOut === t.tier" class="material-symbols text-[16px] animate-spin text-[var(--app-text-3,rgba(255,255,255,0.6))]">progress_activity</span>
                            <span v-else class="material-symbols text-[18px] text-[var(--app-text-3,rgba(255,255,255,0.5))]">chevron_right</span>
                        </span>
                    </button>
                </div>
            </template>
        </div>

        <template #footer>
            <Button label="Maybe later" severity="secondary" @click="close" />
            <Button as="a" href="/#pricing" label="See full pricing" variant="text" />
        </template>
    </Dialog>
</template>
