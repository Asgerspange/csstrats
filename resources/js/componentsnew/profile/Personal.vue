<script setup lang="ts">
import { computed } from 'vue';
import useUser from '@/composables/useUser';

const { User } = useUser();
const { data: user } = User.get();

const formatDate = (s: string | null | undefined) => {
    if (!s) return '—';
    return new Date(s).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatDateTime = (s: string | null | undefined) => {
    if (!s) return '—';
    return new Date(s).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const uniqueRoles = computed(() => {
    if (!user.value?.role) return [];
    return [...new Set(user.value.role.map((r: any) => r.role))] as string[];
});
</script>

<template>
    <div class="personal">

        <div class="info-grid">
            <section class="info-section">
                <header class="info-section__head">
                    <span class="info-section__kicker">Account</span>
                </header>
                <dl class="info-list">
                    <div class="info-row">
                        <dt>Display name</dt>
                        <dd>{{ user?.name ?? '—' }}</dd>
                    </div>
                    <div class="info-row">
                        <dt>Email</dt>
                        <dd class="info-row__mono">{{ user?.email ?? '—' }}</dd>
                    </div>
                    <div class="info-row">
                        <dt>Member since</dt>
                        <dd>{{ formatDate(user?.created_at) }}</dd>
                    </div>
                    <div class="info-row">
                        <dt>Country</dt>
                        <dd>{{ user?.user_info?.country_code?.toUpperCase() || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="info-section">
                <header class="info-section__head">
                    <span class="info-section__kicker">Linked accounts</span>
                </header>
                <dl class="info-list">
                    <div class="info-row">
                        <dt>Steam ID</dt>
                        <dd v-if="user?.user_info?.steam_id" class="info-row__mono">{{ user.user_info.steam_id }}</dd>
                        <dd v-else class="info-row__empty">
                            Not linked
                            <a href="/steam" class="info-link">Connect</a>
                        </dd>
                    </div>
                    <div class="info-row">
                        <dt>FACEIT</dt>
                        <dd v-if="user?.faceit_nickname">{{ user.faceit_nickname }}</dd>
                        <dd v-else class="info-row__empty">Not linked</dd>
                    </div>
                    <div class="info-row">
                        <dt>Last active</dt>
                        <dd class="info-row__mono">{{ formatDateTime(user?.user_info?.last_online) }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section v-if="uniqueRoles.length" class="info-section info-section--wide">
            <header class="info-section__head">
                <span class="info-section__kicker">Team roles</span>
            </header>
            <div class="role-chips">
                <span v-for="role in uniqueRoles" :key="role" class="role-chip">{{ role }}</span>
            </div>
        </section>

        <section v-if="user?.product" class="info-section info-section--wide">
            <header class="info-section__head">
                <span class="info-section__kicker">Subscription</span>
            </header>
            <div class="sub-card">
                <div class="sub-card__main">
                    <span class="sub-card__plan">{{ user.product?.name ?? 'Active plan' }}</span>
                    <span v-if="user.subscription_ends_at" class="sub-card__renews">
                        Ends {{ formatDate(user.subscription_ends_at) }}
                    </span>
                    <span v-else-if="user.recurs_at" class="sub-card__renews">
                        Renews {{ formatDate(user.recurs_at) }}
                    </span>
                </div>
            </div>
        </section>

    </div>
</template>

<style scoped>
.personal {
    --pf-surface:   var(--app-surface, oklch(0.175 0.014 250));
    --pf-surface-2: var(--app-surface-2, oklch(0.215 0.016 250));
    --pf-surface-3: var(--app-surface-3, oklch(0.26 0.018 250));
    --pf-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --pf-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --pf-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --pf-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --pf-text-4:    var(--app-text-4, oklch(1 0 0 / 0.50));
    --pf-accent:    var(--app-accent, #ff8c00);
    --pf-sans:      'Schibsted Grotesk', sans-serif;
    --pf-mono:      'JetBrains Mono', monospace;

    display: flex;
    flex-direction: column;
    gap: 20px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 640px) {
    .info-grid { grid-template-columns: 1fr; }
}

.info-section {
    background: var(--pf-surface);
    border: 1px solid var(--pf-border);
    border-radius: 6px;
    overflow: hidden;
}

.info-section--wide { grid-column: span 2; }

.info-section__head {
    padding: 12px 16px;
    background: var(--pf-surface-2);
    border-bottom: 1px solid var(--pf-border);
}

.info-section__kicker {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pf-text-3);
}

.info-list {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
}

.info-row {
    display: grid;
    grid-template-columns: 110px 1fr;
    gap: 12px;
    align-items: baseline;
    padding: 10px 16px;
    border-bottom: 1px solid var(--pf-border);
}

.info-row:last-child { border-bottom: none; }

.info-row dt {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pf-text-3);
    margin: 0;
}

.info-row dd {
    font-family: var(--pf-sans);
    font-size: 13px;
    font-weight: 500;
    color: var(--pf-text);
    margin: 0;
    display: inline-flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}

.info-row__mono {
    font-family: var(--pf-mono) !important;
    font-size: 12px !important;
    font-variant-numeric: tabular-nums;
}

.info-row__empty {
    color: var(--pf-text-3);
    font-style: italic;
}

.info-link {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--pf-accent);
    text-decoration: none;
    padding: 2px 7px;
    border-radius: 2px;
    background: color-mix(in oklch, var(--pf-accent) 10%, transparent);
    transition: background 150ms ease;
}

.info-link:hover {
    background: color-mix(in oklch, var(--pf-accent) 16%, transparent);
}

/* ── Role chips ─── */
.role-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 14px 16px;
}

.role-chip {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 4px 10px;
    background: color-mix(in oklch, var(--pf-accent) 10%, transparent);
    color: var(--pf-accent);
    border: 1px solid color-mix(in oklch, var(--pf-accent) 30%, transparent);
    border-radius: 3px;
}

/* ── Subscription card ─── */
.sub-card {
    padding: 14px 16px;
    display: flex;
    align-items: center;
}

.sub-card__main {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sub-card__plan {
    font-family: var(--pf-sans);
    font-size: 16px;
    font-weight: 600;
    color: var(--pf-text);
}

.sub-card__renews {
    font-family: var(--pf-mono);
    font-size: 11px;
    color: var(--pf-text-3);
    letter-spacing: 0.04em;
}
</style>
