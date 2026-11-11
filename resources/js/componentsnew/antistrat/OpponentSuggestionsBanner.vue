<template>
    <!-- Load failure — surface it instead of rendering nothing -->
    <div v-if="isError" class="osb-error">
        <Icon icon="lucide:alert-triangle" class="osb-error__icon" />
        <span>Could not load suggestions</span>
    </div>

    <div v-else-if="suggestions.length" class="osb">
        <div class="osb__head">
            <span class="osb__kicker">
                {{ suggestions.length }} untracked team{{ suggestions.length > 1 ? 's' : '' }} found in your demos
            </span>
        </div>

        <div v-for="s in suggestions" :key="s.id" class="osb-card">
            <div class="osb-card__icon">
                <Icon icon="lucide:shield-question" />
            </div>

            <div class="osb-card__body">
                <p class="osb-card__name">{{ s.clan_name }}</p>
                <p class="osb-card__meta">
                    {{ (s.steamids ?? []).length }} players ·
                    {{ s.map ?? 'unknown map' }} ·
                    from demo <span class="osb-card__demo">{{ s.demo?.name ?? '#' + s.demo_id }}</span>
                </p>
            </div>

            <div class="osb-card__actions">
                <Button
                    size="sm"
                    :disabled="creating === s.id"
                    @click="handleCreate(s)"
                >
                    <Icon icon="lucide:plus" class="osb-btn-icon" />
                    {{ creating === s.id ? 'Creating...' : 'Create profile' }}
                </Button>
                <Button
                    severity="secondary"
                    size="sm"
                    :disabled="dismissing === s.id"
                    @click="handleDismiss(s)"
                >
                    Dismiss
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { useRouter } from 'vue-router';
import { Icon } from '@iconify/vue';
import Button from '@/components/volt/Button.vue';

const router = useRouter();
const qc     = useQueryClient();

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const { data: suggestions, isError } = useQuery({
    queryKey: ['opponent-suggestions'],
    queryFn:  async () => {
        const res = await fetch('/api/opponent-suggestions', {
            headers: { 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) {
            console.error('[OpponentSuggestionsBanner] failed to load suggestions', res.status, res.statusText);
            throw new Error(`Failed to load opponent suggestions (${res.status})`);
        }
        return res.json();
    },
    initialData: [],
    retry: 1,
    staleTime: 1000 * 30,
});

const creating   = ref<number | null>(null);
const dismissing = ref<number | null>(null);

const handleCreate = async (s: any) => {
    creating.value = s.id;
    try {
        const res = await fetch(`/api/opponent-suggestions/${s.id}/create-profile`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
        });
        if (res.ok) {
            const opponent = await res.json();
            qc.invalidateQueries({ queryKey: ['opponent-suggestions'] });
            qc.invalidateQueries({ queryKey: ['opponent-teams'] });
            router.push({ name: 'OpponentTeamProfile', params: { id: opponent.id } });
        }
    } finally {
        creating.value = null;
    }
};

const handleDismiss = async (s: any) => {
    dismissing.value = s.id;
    try {
        await fetch(`/api/opponent-suggestions/${s.id}/dismiss`, {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': getCsrf() },
        });
        qc.invalidateQueries({ queryKey: ['opponent-suggestions'] });
    } finally {
        dismissing.value = null;
    }
};
</script>

<style scoped>
.osb {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Load error ── */
.osb-error {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    padding: 10px 14px;
    border-radius: 6px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.02em;
    color: var(--th-loss, oklch(0.62 0.20 22));
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 8%, var(--th-surface, oklch(0.175 0.014 250)));
    border: 1px solid color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 28%, transparent);
}

.osb-error__icon {
    font-size: 14px;
    flex-shrink: 0;
}

.osb__head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.osb__kicker {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-warn, oklch(0.78 0.15 80));
}

.osb-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 6px;
    background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 8%, var(--th-surface, oklch(0.175 0.014 250)));
    border: 1px solid color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 28%, transparent);
}

.osb-card__icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 14%, transparent);
    color: var(--th-warn, oklch(0.78 0.15 80));
    font-size: 16px;
}

.osb-card__body {
    flex: 1;
    min-width: 0;
}

.osb-card__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    margin: 0;
}

.osb-card__meta {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 4px 0 0;
}

.osb-card__demo { color: var(--th-text-2, oklch(1 0 0 / 0.70)); }

.osb-card__actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.osb-btn-icon { font-size: 14px; }

@media (max-width: 560px) {
    .osb-card { flex-wrap: wrap; }
    .osb-card__actions { width: 100%; }
}
</style>
