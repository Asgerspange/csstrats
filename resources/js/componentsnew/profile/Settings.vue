<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import axios from 'axios';
import useUser from '@/composables/useUser';
import Faceit from './Faceit.vue';
import { useToast } from '@/composables/useToast';

const { User } = useUser();
const { data: user } = User.get();
const toast = useToast();

const form = reactive({
    name: '',
    email: '',
    faceit_nickname: '',
});

const saving = ref(false);
const resettingOnboarding = ref(false);

onMounted(() => {
    if (user.value) {
        form.name = user.value.name ?? '';
        form.email = user.value.email ?? '';
        form.faceit_nickname = user.value.faceit_nickname ?? '';
    }
});

async function saveProfile() {
    saving.value = true;
    try {
        await axios.post('/api/user', { name: form.name, email: form.email });
        toast.success('Profile saved', 'Your changes have been saved.');
    } catch {
        toast.error('Save failed', 'Could not save your profile.');
    } finally {
        saving.value = false;
    }
}

async function restartOnboarding() {
    resettingOnboarding.value = true;
    try {
        await axios.post('/api/onboarding/reset');
        window.location.reload();
    } catch {
        toast.error('Could not reset onboarding', 'Please try again.');
        resettingOnboarding.value = false;
    }
}

function openStripePortal() {
    axios.get('/stripe-portal')
        .then(r => window.location.href = r.data.url)
        .catch(() => toast.error('Portal unavailable', 'Could not open the Stripe portal.'));
}

function openPaddlePortal() {
    window.location.href = 'https://customer-portal.paddle.com/cpl_01knkqhjksmdbw75rtdgw0cpvb';
}
</script>

<template>
    <div class="acct-settings">

        <!-- Identity -->
        <section class="acct-section">
            <header class="acct-section__head">
                <span class="acct-section__kicker">Identity</span>
            </header>
            <div class="acct-section__body">
                <div class="field-grid">
                    <div class="field">
                        <label class="field__label" for="acct-name">Display name</label>
                        <input id="acct-name" v-model="form.name" type="text" class="field__input" />
                    </div>
                    <div class="field">
                        <label class="field__label" for="acct-email">Email</label>
                        <input id="acct-email" v-model="form.email" type="email" class="field__input" />
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn--primary" :disabled="saving" @click="saveProfile">
                        {{ saving ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Connections -->
        <section class="acct-section">
            <header class="acct-section__head">
                <span class="acct-section__kicker">Connections</span>
            </header>
            <div class="conn-list">
                <div class="conn-row">
                    <div class="conn-row__icon conn-row__icon--steam" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M11.55 9.6c0 1.85 1.5 3.35 3.35 3.35 1.85 0 3.35-1.5 3.35-3.35S16.75 6.25 14.9 6.25c-1.85 0-3.35 1.5-3.35 3.35zm-3.31 6.85 1.78-.74-1.38-2.06c-.36-.06-.7-.15-1.04-.27l1.45-3.34c.04 0 .07-.01.11-.01.07 0 .14.01.21.02l1.83 4.21c.94.65 1.6 1.73 1.6 2.97 0 1.97-1.6 3.57-3.57 3.57s-3.57-1.6-3.57-3.57c0-.27.03-.54.09-.79l1.49 2.01zM12 2C6.48 2 2 6.48 2 12c0 4.21 2.61 7.81 6.3 9.27l-.69-1.62c-2.46-1.4-4.11-4.04-4.11-7.06 0-4.49 3.64-8.13 8.13-8.13.79 0 1.55.11 2.27.32-1.04.96-1.69 2.34-1.69 3.87 0 1.04.31 2.01.84 2.83l-1.36 3.13c-.04 0-.08-.01-.13-.01-.5 0-.96.18-1.31.49l-3.79-1.58c-.04.7.1 1.4.39 2.02l3.32 1.39c.07 1.69 1.46 3.04 3.17 3.04 1.7 0 3.08-1.33 3.17-3.01 1.42-.66 2.4-2.1 2.4-3.78 0-1.29-.58-2.45-1.49-3.23.39-.07.79-.11 1.21-.11 4.49 0 8.13 3.64 8.13 8.13S16.49 20.13 12 20.13z"/></svg>
                    </div>
                    <div class="conn-row__main">
                        <span class="conn-row__name">Steam</span>
                        <span class="conn-row__value">{{ user?.user_info?.steam_id || 'Not linked' }}</span>
                    </div>
                    <a v-if="user?.user_info?.steam_id" class="conn-row__status">Connected</a>
                    <a v-else href="/steam" class="btn btn--ghost">Connect</a>
                </div>

                <div class="conn-row">
                    <div class="conn-row__icon conn-row__icon--faceit" aria-hidden="true">
                        <span class="conn-row__letter">F</span>
                    </div>
                    <div class="conn-row__main">
                        <span class="conn-row__name">FACEIT</span>
                        <span class="conn-row__value">{{ user?.faceit_nickname || 'Not linked' }}</span>
                    </div>
                    <span v-if="user?.faceit_nickname" class="conn-row__status">Connected</span>
                    <Faceit v-else />
                </div>
            </div>
        </section>

        <!-- Onboarding + Subscription -->
        <section class="acct-section">
            <header class="acct-section__head">
                <span class="acct-section__kicker">Tour &amp; billing</span>
            </header>
            <ul class="util-list">
                <li class="util-row">
                    <div class="util-row__text">
                        <span class="util-row__title">Replay onboarding tour</span>
                        <span class="util-row__hint">Walk through the platform from the beginning.</span>
                    </div>
                    <button
                        class="btn btn--ghost"
                        :disabled="resettingOnboarding"
                        @click="restartOnboarding"
                    >
                        {{ resettingOnboarding ? 'Resetting…' : 'Restart tour' }}
                    </button>
                </li>
                <li class="util-row">
                    <div class="util-row__text">
                        <span class="util-row__title">Manage subscription</span>
                        <span class="util-row__hint">Open the billing portal to update payment, cancel, or change plan.</span>
                    </div>
                    <div class="util-row__buttons">
                        <button class="btn btn--ghost" @click="openStripePortal">Stripe portal</button>
                        <button class="btn btn--ghost" @click="openPaddlePortal">Paddle portal</button>
                    </div>
                </li>
            </ul>
        </section>

    </div>
</template>

<style scoped>
.acct-settings {
    --pf-surface:   var(--app-surface, oklch(0.175 0.014 250));
    --pf-surface-2: var(--app-surface-2, oklch(0.215 0.016 250));
    --pf-surface-3: var(--app-surface-3, oklch(0.26 0.018 250));
    --pf-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --pf-border-2:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --pf-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --pf-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --pf-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --pf-text-4:    var(--app-text-4, oklch(1 0 0 / 0.50));
    --pf-accent:    var(--app-accent, #ff8c00);
    --pf-sans:      'Schibsted Grotesk', sans-serif;
    --pf-mono:      'JetBrains Mono', monospace;

    display: flex;
    flex-direction: column;
    gap: 16px;
}

.acct-section {
    background: var(--pf-surface);
    border: 1px solid var(--pf-border);
    border-radius: 6px;
    overflow: hidden;
}

.acct-section__head {
    padding: 12px 16px;
    background: var(--pf-surface-2);
    border-bottom: 1px solid var(--pf-border);
}

.acct-section__kicker {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pf-text-3);
}

.acct-section__body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ── Fields ─── */
.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

@media (max-width: 640px) {
    .field-grid { grid-template-columns: 1fr; }
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.field__label {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pf-text-3);
}

.field__input {
    background: var(--pf-surface-3);
    border: 1px solid var(--pf-border);
    border-radius: 4px;
    padding: 9px 12px;
    font-family: var(--pf-sans);
    font-size: 13px;
    color: var(--pf-text);
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}

.field__input:focus-visible {
    border-color: var(--pf-accent);
    box-shadow: 0 0 0 2px color-mix(in oklch, var(--pf-accent) 20%, transparent);
}

.actions { display: flex; justify-content: flex-end; }

/* ── Buttons ─── */
.btn {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 8px 14px;
    border-radius: 3px;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn:disabled { opacity: 0.4; cursor: not-allowed; }

.btn--primary {
    background: var(--pf-accent);
    color: oklch(0.125 0.012 250);
    border-color: var(--pf-accent);
}

.btn--primary:hover:not(:disabled) {
    background: oklch(0.62 0.20 50);
    color: var(--pf-text);
    border-color: oklch(0.62 0.20 50);
}

.btn--ghost {
    background: transparent;
    color: var(--pf-text-2);
    border-color: var(--pf-border-2);
}

.btn--ghost:hover:not(:disabled) {
    background: var(--pf-surface-3);
    color: var(--pf-text);
}

.btn:focus-visible {
    outline: 2px solid var(--pf-accent);
    outline-offset: 2px;
}

/* ── Connections ─── */
.conn-list {
    display: flex;
    flex-direction: column;
}

.conn-row {
    display: grid;
    grid-template-columns: 36px 1fr auto;
    gap: 14px;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--pf-border);
}

.conn-row:last-child { border-bottom: none; }

.conn-row__icon {
    width: 36px;
    height: 36px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--pf-text);
}

.conn-row__icon--steam  { background: oklch(0.45 0.04 235); }
.conn-row__icon--faceit { background: oklch(0.55 0.18 35); }

.conn-row__letter {
    font-family: var(--pf-sans);
    font-size: 16px;
    font-weight: 900;
}

.conn-row__main {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.conn-row__name {
    font-family: var(--pf-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--pf-text);
}

.conn-row__value {
    font-family: var(--pf-mono);
    font-size: 11px;
    color: var(--pf-text-3);
    font-variant-numeric: tabular-nums;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conn-row__status {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--pf-text-3);
    padding: 4px 10px;
    background: var(--pf-surface-3);
    border-radius: 2px;
}

/* ── Util rows ─── */
.util-list { margin: 0; padding: 0; list-style: none; }

.util-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--pf-border);
}

.util-row:last-child { border-bottom: none; }

.util-row__text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.util-row__title {
    font-family: var(--pf-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--pf-text);
}

.util-row__hint {
    font-family: var(--pf-sans);
    font-size: 12px;
    color: var(--pf-text-3);
    line-height: 1.4;
}

.util-row__buttons {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

@media (max-width: 640px) {
    .util-row { flex-direction: column; align-items: stretch; }
    .util-row__buttons { flex-direction: column; }
}
</style>
