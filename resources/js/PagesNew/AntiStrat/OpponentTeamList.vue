<template>
    <div class="antistrat-list">

        <!-- Header strip -->
        <div class="al-header">
            <div class="al-header__top">
                <div class="al-header__title">
                    <span class="al-header__kicker">AntiStrat</span>
                    <h1 class="al-header__name">Opponent scouting</h1>
                    <p class="al-header__desc">Scouting profiles for opposing teams.</p>
                </div>
                <Button size="sm" @click="showCreate = true">
                    <Icon icon="lucide:plus" class="al-btn-icon" />
                    New opponent
                </Button>
            </div>
        </div>

        <!-- Content -->
        <div class="al-content">
            <div class="al-content__inner">

                <OpponentSuggestionsBanner />

                <!-- Error -->
                <div v-if="isError" class="state-error">
                    <p class="state-error__headline">Could not load opponent profiles</p>
                    <p class="state-error__body">Check your connection and refresh the page.</p>
                </div>

                <!-- List -->
                <div v-else-if="!isPending" class="al-stack">
                    <router-link
                        v-for="opp in opponents"
                        :key="opp.id"
                        :to="{ name: 'OpponentTeamProfile', params: { id: opp.id } }"
                        class="opp-card"
                    >
                        <div class="opp-card__main">
                            <div class="opp-card__avatar">
                                <Icon icon="lucide:shield" />
                            </div>
                            <div class="opp-card__identity">
                                <div class="opp-card__name-row">
                                    <span v-if="opp.tag" class="opp-card__tag">{{ opp.tag }}</span>
                                    <p class="opp-card__name">{{ opp.name }}</p>
                                    <span
                                        v-if="opp.ai_insights?.threat_level"
                                        class="threat-chip"
                                        :class="`threat-chip--${threatTone(opp.ai_insights.threat_level)}`"
                                    >{{ opp.ai_insights.threat_level.replace('_', ' ') }}</span>
                                </div>
                                <p class="opp-card__meta">
                                    {{ opp.demos_count ?? 0 }} demos · {{ opp.players?.length ?? 0 }} known players
                                </p>
                                <div v-if="opp.shared_teams?.length" class="opp-card__shared">
                                    <Icon icon="lucide:users" class="opp-card__shared-icon" />
                                    Shared with {{ opp.shared_teams.length }} team{{ opp.shared_teams.length > 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>

                        <div class="opp-card__right">
                            <div v-if="opp.ai_insights?.danger_score" class="opp-card__danger">
                                <span class="opp-card__danger-value">{{ opp.ai_insights.danger_score }}</span>
                                <span class="opp-card__danger-label">Danger</span>
                            </div>
                            <Icon icon="lucide:chevron-right" class="opp-card__chevron" />
                        </div>

                        <p v-if="opp.notes" class="opp-card__notes">{{ opp.notes }}</p>
                    </router-link>

                    <div v-if="opponents.length === 0" class="al-empty">
                        <Icon icon="lucide:shield-off" class="al-empty__icon" />
                        <p class="al-empty__headline">No opponent profiles yet</p>
                        <p class="al-empty__body">Create one to start scouting.</p>
                    </div>
                </div>

                <!-- Loading -->
                <div v-else class="al-stack">
                    <div v-for="i in 3" :key="i" class="opp-skel" />
                </div>

            </div>
        </div>

        <!-- Create modal -->
        <Dialog v-model:visible="showCreate" modal header="New opponent profile" :style="{ width: '420px' }">
            <div class="al-form">
                <div class="al-field">
                    <label class="al-label">Team name *</label>
                    <InputText v-model="form.name" class="al-input" placeholder="e.g. noobteam123" autofocus />
                </div>
                <div class="al-field">
                    <label class="al-label">Tag / abbreviation</label>
                    <InputText v-model="form.tag" class="al-input" placeholder="NB123" maxlength="32" />
                </div>
                <div class="al-field">
                    <label class="al-label">Notes</label>
                    <Textarea v-model="form.notes" class="al-input" rows="2" placeholder="Any initial scouting notes..." />
                </div>
                <div class="al-field">
                    <label class="al-label">Platform URL</label>
                    <InputText v-model="form.platform_url" class="al-input" placeholder="https://faceit.com/..." />
                </div>
            </div>
            <template #footer>
                <Button severity="secondary" size="sm" @click="showCreate = false">Cancel</Button>
                <Button
                    size="sm"
                    :disabled="!form.name || create.isPending.value"
                    @click="handleCreate"
                >
                    {{ create.isPending.value ? 'Creating...' : 'Create' }}
                </Button>
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { Icon } from '@iconify/vue';
import useOpponentTeams from '@/composables/useOpponentTeams';
import Dialog from '@/components/volt/Dialog.vue';
import InputText from '@/components/volt/InputText.vue';
import Button from '@/components/volt/Button.vue';
import Textarea from 'primevue/textarea';
import OpponentSuggestionsBanner from '@/componentsnew/antistrat/OpponentSuggestionsBanner.vue';

const router = useRouter();

const { OpponentTeams, create } = useOpponentTeams();

const { data: opponents, isPending, isError } = OpponentTeams.list();

const showCreate = ref(false);
const form       = ref({
    name:         '',
    tag:          '',
    notes:        '',
    platform_url: '',
});

const handleCreate = () => {
    if (!form.value.name) return;
    create.mutate(form.value, {
        onSuccess: (data: any) => {
            showCreate.value = false;
            form.value = { name: '', tag: '', notes: '', platform_url: '' };
            router.push({ name: 'OpponentTeamProfile', params: { id: data.id } });
        },
    });
};

const threatTone = (level: string): string => {
    const map: Record<string, string> = {
        low:       'win',
        medium:    'warn',
        high:      'accent',
        very_high: 'loss',
    };
    return map[level] ?? 'default';
};
</script>

<style scoped>
.antistrat-list {
    --th-bg:        var(--app-canvas, oklch(0.125 0.012 250));
    --th-surface:   var(--app-surface, oklch(0.175 0.014 250));
    --th-surface-2: var(--app-surface-2, oklch(0.215 0.016 250));
    --th-surface-3: var(--app-surface-3, oklch(0.26 0.018 250));
    --th-surface-4: var(--app-surface-4, oklch(0.31 0.02 250));

    --th-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --th-border-2:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --th-border-3:  var(--app-border-3, oklch(1 0 0 / 0.22));

    --th-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --th-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --th-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --th-text-4:    var(--app-text-4, oklch(1 0 0 / 0.50));

    --th-accent:    var(--app-accent, #ff8c00);
    --th-accent-2:  oklch(0.62 0.20 50);

    --th-win:       oklch(0.72 0.14 145);
    --th-loss:      oklch(0.62 0.20 22);
    --th-warn:      oklch(0.78 0.15 80);

    --th-sans:      'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
    --th-mono:      'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--th-bg);
    font-family: var(--th-sans);
    color: var(--th-text);
}

/* ── Header ── */
.al-header {
    flex-shrink: 0;
    background: var(--th-surface);
    border-bottom: 1px solid var(--th-border);
}

.al-header__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 20px 28px;
    flex-wrap: wrap;
}

.al-header__title {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.al-header__kicker {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--th-text-3);
}

.al-header__name {
    font-family: var(--th-sans);
    font-size: clamp(1.25rem, 2.2vw, 1.75rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.02em;
    color: var(--th-text);
    margin: 0;
}

.al-header__desc {
    font-size: 13px;
    color: var(--th-text-3);
    margin: 2px 0 0;
}

.al-btn-icon { font-size: 15px; }

/* ── Content ── */
.al-content {
    flex: 1;
    overflow-y: auto;
    background: var(--th-bg);
    scrollbar-width: thin;
    scrollbar-color: var(--th-border-2) transparent;
}

.al-content::-webkit-scrollbar { width: 6px; }
.al-content::-webkit-scrollbar-thumb {
    background: var(--th-border-2);
    border-radius: 3px;
}
.al-content::-webkit-scrollbar-thumb:hover { background: var(--th-border-3); }

.al-content__inner {
    max-width: 1024px;
    margin: 0 auto;
    padding: 24px 28px 48px;
}

.al-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* ── Opponent card ── */
.opp-card {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px 16px;
    padding: 16px;
    background: var(--th-surface);
    border: 1px solid var(--th-border);
    border-radius: 6px;
    text-decoration: none;
    transition: border-color 150ms ease, background 150ms ease;
}

.opp-card:hover {
    border-color: var(--th-border-3);
    background: var(--th-surface-2);
}

.opp-card:focus-visible {
    outline: 2px solid var(--th-accent);
    outline-offset: 2px;
}

.opp-card__main {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.opp-card__avatar {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    background: var(--th-surface-3);
    border: 1px solid var(--th-border-2);
    color: var(--th-text-3);
    font-size: 16px;
}

.opp-card__identity { min-width: 0; }

.opp-card__name-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.opp-card__tag {
    font-family: var(--th-mono);
    font-size: 10px;
    color: var(--th-text-3);
}

.opp-card__name {
    font-family: var(--th-sans);
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.opp-card__meta {
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-3);
    margin: 4px 0 0;
    font-variant-numeric: tabular-nums;
}

.opp-card__shared {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
    padding: 3px 7px;
    border-radius: 3px;
    background: var(--th-surface-3);
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.04em;
    color: var(--th-text-2);
}

.opp-card__shared-icon { font-size: 11px; }

.opp-card__right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.opp-card__danger {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1px;
}

.opp-card__danger-value {
    font-family: var(--th-mono);
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    color: var(--th-accent);
    font-variant-numeric: tabular-nums;
}

.opp-card__danger-label {
    font-family: var(--th-mono);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--th-text-4);
}

.opp-card__chevron {
    font-size: 18px;
    color: var(--th-text-4);
}

.opp-card__notes {
    grid-column: 1 / -1;
    font-size: 12px;
    color: var(--th-text-3);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Threat chips ── */
.threat-chip {
    display: inline-flex;
    padding: 2px 7px;
    border-radius: 3px;
    font-family: var(--th-mono);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    flex-shrink: 0;
}

.threat-chip--win    { color: var(--th-win);    background: color-mix(in oklch, var(--th-win) 16%, transparent); }
.threat-chip--warn   { color: var(--th-warn);   background: color-mix(in oklch, var(--th-warn) 16%, transparent); }
.threat-chip--accent { color: var(--th-accent); background: color-mix(in oklch, var(--th-accent) 16%, transparent); }
.threat-chip--loss   { color: var(--th-loss);   background: color-mix(in oklch, var(--th-loss) 18%, transparent); }
.threat-chip--default { color: var(--th-text-3); background: var(--th-surface-3); }

/* ── Empty + skeleton ── */
.al-empty {
    padding: 56px 32px;
    text-align: center;
    border: 1px dashed var(--th-border-2);
    border-radius: 6px;
    color: var(--th-text-3);
}

.al-empty__icon {
    font-size: 30px;
    color: var(--th-text-4);
    margin-bottom: 8px;
}

.al-empty__headline {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text-2);
    margin: 0;
}

.al-empty__body {
    font-size: 12px;
    color: var(--th-text-4);
    margin: 4px 0 0;
}

/* ── Error state ── */
.state-error {
    padding: 56px 32px;
    text-align: center;
    border: 1px solid color-mix(in oklch, var(--th-loss) 28%, transparent);
    background: color-mix(in oklch, var(--th-loss) 7%, var(--th-surface));
    border-radius: 6px;
}

.state-error__headline {
    font-size: 15px;
    font-weight: 600;
    color: var(--th-loss);
    margin: 0 0 4px;
}

.state-error__body {
    font-size: 13px;
    color: var(--th-text-3);
    margin: 0;
}

.opp-skel {
    height: 86px;
    border-radius: 6px;
    background: var(--th-surface);
    border: 1px solid var(--th-border);
    animation: al-pulse 1.6s ease-in-out infinite;
}

@keyframes al-pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 0.8; }
}

@media (prefers-reduced-motion: reduce) {
    .opp-skel { animation: none; opacity: 0.6; }
}

/* ── Create form ── */
.al-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 4px;
}

.al-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.al-label {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--th-text-3);
}

.al-input { width: 100%; }
.al-input :deep(textarea) { width: 100%; }

/* ── Responsive ── */
@media (max-width: 760px) {
    .al-header__top { padding: 16px 20px; }
    .al-content__inner { padding: 18px 20px 32px; }
}

@media (max-width: 560px) {
    .al-header__top { padding: 14px 16px; }
    .al-content__inner { padding: 14px 16px 24px; }
    .opp-card { padding: 14px; }
}
</style>
