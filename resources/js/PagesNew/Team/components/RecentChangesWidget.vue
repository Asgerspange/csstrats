<script setup lang="ts">
import { computed } from 'vue';
import { useTeamTactics, type TeamTactic } from '@/composables/useTeamTactics';
import { useRouter } from 'vue-router';

const { data: tactics, isLoading } = useTeamTactics();
const router = useRouter();

const recent = computed<TeamTactic[]>(() => {
    const list = tactics.value ?? [];
    return [...list]
        .sort((a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
        .slice(0, 8);
});

function open(t: TeamTactic) {
    router.push(`/stratbook/${t.stratbook_id}`);
}

function fmtDate(d: string): string {
    try {
        return new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    } catch {
        return '';
    }
}
</script>

<template>
    <div class="widget">
        <h2 class="widget__title">Recent changes</h2>
        <p v-if="isLoading" class="widget__loading">Loading...</p>
        <div v-else-if="recent.length === 0" class="widget__empty">No tactics yet.</div>
        <ul v-else class="recent-list">
            <li v-for="t in recent" :key="t.id">
                <button class="recent-row" @click="open(t)">
                    <span class="recent-row__name">{{ t.name }}</span>
                    <span class="recent-row__meta">
                        {{ t.map }} · {{ t.side }}
                        <span class="recent-row__date tabular-nums">{{ fmtDate(t.updated_at) }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.widget__title {
    font-size: 0.75rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.widget__empty {
    font-size: 0.75rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.recent-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.recent-row {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    width: 100%;
    padding: 0.375rem 0.5rem;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 150ms;
}

.recent-row:hover {
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.recent-row:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.recent-row__name {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recent-row__meta {
    font-size: 0.6875rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    display: flex;
    gap: 0.375rem;
}

.recent-row__date {
    margin-left: auto;
}
</style>
