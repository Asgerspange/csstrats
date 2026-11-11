<template>
    <div class="counters">

        <!-- Header -->
        <div class="counters__head">
            <p class="counters__count">{{ strats.length }} counter strats</p>
            <div class="counters__actions">
                <Button
                    severity="secondary"
                    size="sm"
                    :disabled="isGenerating"
                    @click="handleGenerate"
                >
                    <Icon v-if="isGenerating" icon="lucide:loader-2" class="counters__btn-icon counters__btn-icon--spin" />
                    <Icon v-else icon="lucide:sparkles" class="counters__btn-icon" />
                    {{ isGenerating ? 'Generating...' : 'Generate with AI' }}
                </Button>
                <Button size="sm" @click="$emit('add')">
                    <Icon icon="lucide:plus" class="counters__btn-icon" />
                    Add strat
                </Button>
            </div>
        </div>

        <!-- Generating banner -->
        <div v-if="isGenerating" class="counters-banner">
            <div class="counters-banner__spinner" />
            <div>
                <p class="counters-banner__title">Generating counter strats...</p>
                <p class="counters-banner__body">The AI is analysing opponent patterns. This page will update when done.</p>
            </div>
        </div>

        <!-- Group by map -->
        <template v-if="strats.length">
            <div v-for="(mapStrats, mapName) in stratsByMap" :key="String(mapName)" class="counters-group">
                <!-- Map header -->
                <div class="counters-group__head">
                    <div class="counters-group__badge">
                        {{ String(mapName).replace('de_', '').slice(0, 3) }}
                    </div>
                    <span class="counters-group__name">{{ getMapDisplayName(String(mapName)) }}</span>
                    <span class="counters-group__count">{{ mapStrats.length }} strat{{ mapStrats.length > 1 ? 's' : '' }}</span>
                </div>

                <!-- Strat cards -->
                <div v-for="strat in mapStrats" :key="strat.id" class="strat-card">

                    <div class="strat-card__top">
                        <div class="strat-card__main">
                            <div class="strat-card__chips">
                                <span class="side-chip" :class="strat.side === 'T' ? 'side-chip--t' : 'side-chip--ct'">
                                    {{ strat.side }}-side
                                </span>
                                <span v-if="strat.ai_generated" class="ai-chip">
                                    <Icon icon="lucide:sparkles" class="ai-chip__icon" /> AI
                                </span>
                                <span v-for="tag in (strat.tags ?? [])" :key="tag" class="tag-chip">
                                    {{ tag }}
                                </span>
                            </div>
                            <p class="strat-card__name">{{ strat.name }}</p>
                            <p v-if="strat.description" class="strat-card__desc">
                                <CalloutRenderer :text="strat.description" />
                            </p>
                        </div>
                        <button class="strat-card__delete" aria-label="Delete strat" @click="requestDelete(strat)">
                            <Icon icon="lucide:trash-2" />
                        </button>
                    </div>

                    <!-- Zone chips -->
                    <div v-if="strat.zone_ids?.length" class="strat-card__zones">
                        <div
                            v-for="zoneId in strat.zone_ids"
                            :key="zoneId"
                            class="zone-chip"
                            :style="{
                                borderColor: (getZone(zoneId)?.color ?? 'oklch(1 0 0 / 0.50)') + '60',
                                background:  (getZone(zoneId)?.color ?? 'transparent') + '18',
                                color:       getZone(zoneId)?.color ?? 'var(--th-text-3, oklch(1 0 0 / 0.62))',
                            }"
                        >
                            <span class="zone-chip__dot" :style="{ background: getZone(zoneId)?.color ?? 'var(--th-text-3, oklch(1 0 0 / 0.62))' }" />
                            {{ getZone(zoneId)?.display_name ?? getZone(zoneId)?.canonical_name ?? 'Zone ' + zoneId }}
                        </div>
                    </div>

                    <p class="strat-card__date">Added {{ formatDate(strat.created_at) }}</p>
                </div>
            </div>
        </template>

        <!-- Empty state -->
        <div v-else-if="!isGenerating" class="counters-empty">
            <Icon icon="lucide:shield" class="counters-empty__icon" />
            <p class="counters-empty__headline">No counter strats yet</p>
            <p class="counters-empty__body">Generate AI-powered strats based on opponent patterns, or add your own.</p>
            <div class="counters-empty__actions">
                <Button severity="secondary" size="sm" @click="handleGenerate">
                    <Icon icon="lucide:sparkles" class="counters__btn-icon" />
                    Generate with AI
                </Button>
                <Button size="sm" @click="$emit('add')">
                    <Icon icon="lucide:plus" class="counters__btn-icon" />
                    Add manually
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { Icon } from '@iconify/vue';
import { AppErrorCode } from '@/constants/errorCodes';
import { usePlan } from '@/composables/usePlan';
import { useUpgradeModal } from '@/composables/useUpgradeModal';
import CalloutRenderer from '@/componentsnew/callouts/CalloutRenderer.vue';
import Button from '@/components/volt/Button.vue';

const props = defineProps<{
    opponentId: number;
    strats: any[];
    maps: any[];
    zones: any[];
    generatedAt: string | null;
}>();

const emit = defineEmits(['add', 'delete', 'generated']);

const qc      = useQueryClient();
const confirm = useConfirm();
const toast   = useToast();
const { can } = usePlan();
const { open: openUpgrade } = useUpgradeModal();

// ── Zone lookup ────────────────────────────────────────────────────────────────
const zoneById = computed(() => {
    const m: Record<number, any> = {};
    for (const z of props.zones) m[z.id] = z;
    return m;
});
const getZone = (id: number) => zoneById.value[id] ?? null;

// ── Map display names ──────────────────────────────────────────────────────────
const getMapDisplayName = (mapKey: string) => {
    const MAP_NAMES: Record<string, string> = {
        de_overpass: 'Overpass', de_mirage: 'Mirage', de_dust2: 'Dust 2',
        de_inferno: 'Inferno', de_nuke: 'Nuke', de_ancient: 'Ancient',
        de_anubis: 'Anubis', de_vertigo: 'Vertigo',
    };
    return MAP_NAMES[mapKey] ?? mapKey.replace('de_', '');
};

// ── Group strats by map ────────────────────────────────────────────────────────
const stratsByMap = computed(() => {
    const grouped: Record<string, any[]> = {};
    for (const strat of props.strats) {
        const key = strat.map?.name ?? String(strat.map_id);
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(strat);
    }
    // Sort T before CT within each map
    for (const key of Object.keys(grouped)) {
        grouped[key].sort((a, b) => {
            if (a.side !== b.side) return a.side === 'T' ? -1 : 1;
            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        });
    }
    return grouped;
});

// ── AI generation ──────────────────────────────────────────────────────────────
const isGenerating = ref(false);
const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const handleGenerate = async () => {
    // Pro-only feature. Pre-empt the 403 before dispatching the AI job.
    if (!can('ai_antistrat')) {
        openUpgrade({ feature: 'ai_antistrat', requiredTier: 'Pro' });
        return;
    }
    isGenerating.value = true;
    try {
        const res = await fetch(`/api/opponent-teams/${props.opponentId}/counter-strats/generate`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
        });
        if (!res.ok) {
            let detail = 'Failed to start generation';
            let body: any = null;
            try { body = await res.json(); } catch { /* non-JSON */ }
            // Backend tier gate → upgrade modal (raw fetch; interceptor misses it).
            if (body?.app_code === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
                openUpgrade({
                    feature: body.feature ?? 'ai_antistrat',
                    requiredTier: body.required_tier ?? 'Pro',
                });
                isGenerating.value = false;
                return;
            }
            detail = body?.error ?? detail;
            toast.add({ severity: 'error', summary: 'Generation failed', detail, life: 5000 });
            isGenerating.value = false;
            return;
        }
        // Poll for completion by watching opponent query
        pollForStrats();
    } catch (e) {
        console.error('[CountersTab] counter-strat generation request failed', e);
        toast.add({
            severity: 'error',
            summary:  'Generation failed',
            detail:   'Could not reach the server. Check your connection and try again.',
            life:     5000,
        });
        isGenerating.value = false;
    }
};

let pollInterval: ReturnType<typeof setInterval> | null = null;

const pollForStrats = () => {
    pollInterval = setInterval(async () => {
        await qc.invalidateQueries({ queryKey: ['opponent-teams', props.opponentId] });
        // If new strats appeared, stop polling
        if (props.strats.some(s => s.ai_generated)) {
            stopPoll();
        }
    }, 3000);

    // Stop after 3 minutes regardless
    setTimeout(stopPoll, 180_000);
};

const stopPoll = () => {
    isGenerating.value = false;
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
    emit('generated');
};

// ── Delete (with confirmation) ─────────────────────────────────────────────────
const requestDelete = (strat: any) => {
    confirm.require({
        message:     `Delete this counter strat?${strat?.name ? ` "${strat.name}" will be removed.` : ''}`,
        header:      'Delete counter strat',
        icon:        'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Delete', severity: 'danger' },
        accept:      () => emit('delete', strat.id),
    });
};

// ── Helpers ────────────────────────────────────────────────────────────────────
const formatDate = (iso: string) =>
    iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '';
</script>

<style scoped>
.counters {
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Header ── */
.counters__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.counters__count {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 0;
}

.counters__actions {
    display: flex;
    gap: 8px;
}

.counters__btn-icon { font-size: 14px; }
.counters__btn-icon--spin { animation: counters-spin 1s linear infinite; }

@keyframes counters-spin {
    to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .counters__btn-icon--spin { animation: none; }
}

/* ── Generating banner ── */
.counters-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 6px;
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 7%, var(--th-surface, oklch(0.175 0.014 250)));
    border: 1px solid color-mix(in oklch, var(--th-accent, #ff8c00) 22%, transparent);
}

.counters-banner__spinner {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    border-radius: 50%;
    border: 2px solid color-mix(in oklch, var(--th-accent, #ff8c00) 30%, transparent);
    border-top-color: var(--th-accent, #ff8c00);
    animation: counters-spin 0.8s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .counters-banner__spinner { animation: none; }
}

.counters-banner__title {
    font-size: 13px;
    font-weight: 600;
    color: var(--th-accent, #ff8c00);
    margin: 0;
}

.counters-banner__body {
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 3px 0 0;
}

/* ── Map groups ── */
.counters-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.counters-group__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-top: 4px;
}

.counters-group__badge {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.counters-group__name {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.counters-group__count {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

/* ── Strat card ── */
.strat-card {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border-radius: 6px;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
}

.strat-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.strat-card__main {
    flex: 1;
    min-width: 0;
}

.strat-card__chips {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.strat-card__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    margin: 0;
}

.strat-card__desc {
    font-size: 12px;
    line-height: 1.5;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    margin: 6px 0 0;
}

.strat-card__delete {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: none;
    background: none;
    border-radius: 4px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    cursor: pointer;
    font-size: 14px;
    transition: color 150ms ease, background 150ms ease;
}

.strat-card__delete:hover {
    color: var(--th-loss, oklch(0.62 0.20 22));
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 14%, transparent);
}

.strat-card__delete:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: -2px;
}

.strat-card__zones {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.strat-card__date {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 0;
}

/* ── Chips ── */
.side-chip {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 2px 7px;
    border-radius: 3px;
}

.side-chip--t  { color: var(--th-warn, oklch(0.78 0.15 80)); background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 16%, transparent); }
.side-chip--ct { color: var(--th-accent-2, oklch(0.62 0.20 50)); background: color-mix(in oklch, var(--th-accent-2, oklch(0.62 0.20 50)) 20%, transparent); }

.ai-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 3px;
    color: var(--th-accent, #ff8c00);
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 16%, transparent);
}

.ai-chip__icon { font-size: 9px; }

.tag-chip {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
}

.zone-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 4px;
    border: 1px solid;
}

.zone-chip__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Empty state ── */
.counters-empty {
    padding: 56px 32px;
    text-align: center;
    border: 1px dashed var(--th-border-2, oklch(1 0 0 / 0.14));
    border-radius: 6px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.counters-empty__icon {
    font-size: 30px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin-bottom: 8px;
}

.counters-empty__headline {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    margin: 0;
}

.counters-empty__body {
    font-size: 12px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 4px 0 16px;
}

.counters-empty__actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}
</style>
