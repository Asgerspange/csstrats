<script setup lang="ts">
import { ref, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import Dialog from '@/components/volt/Dialog.vue';
import ProfilePersonal from './profile/Personal.vue';
import ProfileSettings from './profile/Settings.vue';
import ProfileAppearance from './profile/Appearance.vue';

const tabs = [
    { key: 'personal',   label: 'Personal' },
    { key: 'settings',   label: 'Account' },
    { key: 'appearance', label: 'Appearance' },
] as const;
type TabKey = typeof tabs[number]['key'];

const selectedTab = ref<TabKey>('personal');

const store = useStore();
const { user } = storeToRefs(store);

const visible = defineModel<boolean>('visible', { default: false });

function focusTab(direction: 1 | -1) {
    const idx = tabs.findIndex(t => t.key === selectedTab.value);
    const next = (idx + direction + tabs.length) % tabs.length;
    selectedTab.value = tabs[next].key;
}

const memberSince = computed(() => {
    if (!user.value?.created_at) return null;
    return new Date(user.value.created_at).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric'
    });
});
</script>

<template>
    <Dialog
        v-model:visible="visible"
        :closable="true"
        :draggable="false"
        :resizable="false"
        :modal="true"
        header=" "
        appendTo="body"
        :pt="{ root: { class: 'profile-dialog' } }"
    >
        <div class="profile">

            <header class="profile__head">
                <div class="profile__identity">
                    <img
                        v-if="user?.profile_picture?.base64"
                        :src="user.profile_picture.base64"
                        :alt="user.name"
                        class="profile__avatar"
                    />
                    <div v-else class="profile__avatar profile__avatar--fallback" aria-hidden="true">
                        <span class="material-symbols">person</span>
                    </div>

                    <div class="profile__identity-text">
                        <span class="profile__kicker">Profile</span>
                        <h2 class="profile__name">{{ user?.name }}</h2>
                        <span class="profile__email">{{ user?.email }}</span>
                    </div>
                </div>

                <dl class="profile__meta">
                    <div class="profile__meta-row">
                        <dt>Member since</dt>
                        <dd>{{ memberSince ?? '—' }}</dd>
                    </div>
                    <div class="profile__meta-row" v-if="user?.user_info?.steam_id">
                        <dt>Steam</dt>
                        <dd class="profile__meta-mono">{{ user.user_info.steam_id }}</dd>
                    </div>
                    <div class="profile__meta-row" v-if="user?.faceit_nickname">
                        <dt>FACEIT</dt>
                        <dd>{{ user.faceit_nickname }}</dd>
                    </div>
                </dl>
            </header>

            <nav class="profile__tabs" role="tablist" aria-label="Profile sections">
                <button
                    v-for="(tab, i) in tabs"
                    :key="tab.key"
                    role="tab"
                    :id="`prof-tab-${tab.key}`"
                    :aria-selected="selectedTab === tab.key"
                    :aria-controls="`prof-panel-${tab.key}`"
                    :tabindex="selectedTab === tab.key ? 0 : -1"
                    class="profile__tab"
                    :class="{ 'profile__tab--active': selectedTab === tab.key }"
                    @click="selectedTab = tab.key"
                    @keydown.right.prevent="focusTab(1)"
                    @keydown.left.prevent="focusTab(-1)"
                >
                    <span class="profile__tab-idx">0{{ i + 1 }}</span>
                    <span class="profile__tab-label">{{ tab.label }}</span>
                </button>
            </nav>

            <section class="profile__panel" :id="`prof-panel-${selectedTab}`" role="tabpanel" :aria-labelledby="`prof-tab-${selectedTab}`">
                <ProfilePersonal   v-if="selectedTab === 'personal'" />
                <ProfileSettings   v-else-if="selectedTab === 'settings'" />
                <ProfileAppearance v-else-if="selectedTab === 'appearance'" />
            </section>

        </div>
    </Dialog>
</template>

<style>
.profile-dialog {
    width: min(960px, calc(100vw - 32px));
    max-height: calc(100dvh - 64px);
    /* Volt root is rounded-xl; clip the square .profile content to it. */
    overflow: hidden;
}
.profile-dialog .p-dialog-content {
    padding: 0 !important;
    background: var(--pf-bg, oklch(0.125 0.012 250)) !important;
}
.profile-dialog .p-dialog-header {
    padding: 12px 18px !important;
    background: var(--pf-bg, oklch(0.125 0.012 250)) !important;
    border-bottom: none !important;
}
</style>

<style scoped>
.profile {
    --pf-bg:        var(--app-canvas, oklch(0.125 0.012 250));
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
    --pf-sans:      'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
    --pf-mono:      'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

    background: var(--pf-bg);
    color: var(--pf-text);
    font-family: var(--pf-sans);
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* ── Header ────────────────────────────────────────────────────────────── */
.profile__head {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 24px;
    padding: 8px 24px 24px;
    border-bottom: 1px solid var(--pf-border);
}

.profile__identity {
    display: flex;
    align-items: center;
    gap: 18px;
    min-width: 0;
}

.profile__avatar {
    width: 72px;
    height: 72px;
    border-radius: 6px;
    object-fit: cover;
    background: var(--pf-surface-2);
    border: 1px solid var(--pf-border-2);
    flex-shrink: 0;
}

.profile__avatar--fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pf-text-3);
}
.profile__avatar--fallback .material-symbols { font-size: 40px; }

.profile__identity-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.profile__kicker {
    font-family: var(--pf-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pf-text-3);
}

.profile__name {
    font-family: var(--pf-sans);
    font-size: clamp(1.5rem, 3vw, 1.875rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.02em;
    color: var(--pf-text);
    margin: 0;
}

.profile__email {
    font-family: var(--pf-mono);
    font-size: 12px;
    color: var(--pf-text-3);
    margin-top: 2px;
}

/* ── Header meta ───────────────────────────────────────────────────────── */
.profile__meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
}

.profile__meta-row {
    display: grid;
    grid-template-columns: auto auto;
    gap: 14px;
    align-items: baseline;
    justify-content: flex-end;
}

.profile__meta-row dt {
    font-family: var(--pf-mono);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--pf-text-3);
    text-align: right;
}

.profile__meta-row dd {
    font-family: var(--pf-sans);
    font-size: 13px;
    font-weight: 500;
    color: var(--pf-text);
    margin: 0;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.profile__meta-mono {
    font-family: var(--pf-mono) !important;
    font-size: 12px !important;
}

/* ── Tabs ──────────────────────────────────────────────────────────────── */
.profile__tabs {
    display: flex;
    gap: 0;
    padding: 0 24px;
    border-bottom: 1px solid var(--pf-border);
    background: var(--pf-bg);
    flex-shrink: 0;
    overflow-x: auto;
    scrollbar-width: none;
}
.profile__tabs::-webkit-scrollbar { display: none; }

.profile__tab {
    position: relative;
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    padding: 13px 0;
    margin-right: 28px;
    font-family: var(--pf-sans);
    font-size: 14px;
    font-weight: 500;
    color: var(--pf-text-3);
    background: none;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: color 150ms ease;
}

.profile__tab:last-child { margin-right: 0; }
.profile__tab:hover { color: var(--pf-text-2); }
.profile__tab--active { color: var(--pf-text); }

.profile__tab--active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--pf-accent);
}

.profile__tab:focus-visible {
    outline: 2px solid var(--pf-accent);
    outline-offset: 4px;
    border-radius: 2px;
}

.profile__tab-idx {
    font-family: var(--pf-mono);
    font-size: 10px;
    color: var(--pf-text-4);
    letter-spacing: 0.04em;
}

.profile__tab--active .profile__tab-idx { color: var(--pf-accent); }

/* ── Panel ─────────────────────────────────────────────────────────────── */
.profile__panel {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}

.profile__panel::-webkit-scrollbar { width: 6px; }
.profile__panel::-webkit-scrollbar-thumb {
    background: var(--pf-border-2);
    border-radius: 3px;
}

/* ── Responsive ────────────────────────────────────────────────────────── */
@media (max-width: 760px) {
    .profile__head { grid-template-columns: 1fr; padding: 8px 18px 18px; }
    .profile__meta { align-self: stretch; }
    .profile__meta-row { justify-content: space-between; }
    .profile__tabs { padding: 0 18px; }
    .profile__panel { padding: 18px; }
}

@media (max-width: 480px) {
    .profile__identity { gap: 14px; }
    .profile__avatar { width: 56px; height: 56px; }
}
</style>
