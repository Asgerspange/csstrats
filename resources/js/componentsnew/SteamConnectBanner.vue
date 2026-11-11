<script setup lang="ts">
import { ref, computed } from 'vue';
import useUser from '@/composables/useUser';

const STORAGE_KEY = 'cs:steam-banner:dismissed-until';

const { User } = useUser();
const { data: user } = User.get();

const dismissedUntil = ref<number>(
    Number(typeof window !== 'undefined' ? localStorage.getItem(STORAGE_KEY) ?? 0 : 0)
);

const steamMissing = computed(() => {
    if (!user.value) return false;
    const sid = user.value?.user_info?.steam_id;
    return !sid || String(sid).trim().length === 0;
});

const visible = computed(() => steamMissing.value && Date.now() > dismissedUntil.value);

function dismiss() {
    // Snooze for 24 hours.
    const until = Date.now() + 24 * 60 * 60 * 1000;
    dismissedUntil.value = until;
    try { localStorage.setItem(STORAGE_KEY, String(until)); } catch {}
}
</script>

<template>
    <a v-if="visible" href="/steam" class="steam-banner" role="status">
        <span class="steam-banner__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </span>
        <span class="steam-banner__text">
            <span class="steam-banner__title">Connect your Steam account</span>
            <span class="steam-banner__body">Link Steam to attach your demos and stats to your profile.</span>
        </span>
        <span class="steam-banner__cta">Connect Steam</span>
        <button
            class="steam-banner__close"
            aria-label="Dismiss for 24 hours"
            @click.prevent.stop="dismiss"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </a>
</template>

<style scoped>
.steam-banner {
    --sb-accent: var(--main-color, #ff8c00);
    --sb-bg: oklch(0.14 0.005 250);
    --sb-text: var(--app-text, oklch(1 0 0 / 0.95));
    --sb-text-2: var(--app-text-2, oklch(1 0 0 / 0.65));
    --sb-border: var(--app-border, oklch(1 0 0 / 0.08));

    position: relative;
    z-index: 30;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 20px;
    background: color-mix(in oklch, var(--sb-accent) 9%, var(--sb-bg));
    border-bottom: 1px solid color-mix(in oklch, var(--sb-accent) 22%, var(--sb-border));
    color: var(--sb-text);
    text-decoration: none;
    font-family: 'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
    transition: background 150ms ease;
}

.steam-banner:hover {
    background: color-mix(in oklch, var(--sb-accent) 14%, var(--sb-bg));
}

.steam-banner:focus-visible {
    outline: 2px solid var(--sb-accent);
    outline-offset: -2px;
}

.steam-banner__icon {
    color: var(--sb-accent);
    display: inline-flex;
    flex-shrink: 0;
}

.steam-banner__text {
    flex: 1;
    display: flex;
    align-items: baseline;
    gap: 10px;
    min-width: 0;
    flex-wrap: wrap;
}

.steam-banner__title {
    font-size: 13px;
    font-weight: 600;
    color: var(--sb-text);
}

.steam-banner__body {
    font-size: 12px;
    color: var(--sb-text-2);
}

.steam-banner__cta {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 6px 12px;
    background: var(--sb-accent);
    color: oklch(0.10 0.005 250);
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 150ms ease, color 150ms ease;
}

.steam-banner:hover .steam-banner__cta {
    background: oklch(0.62 0.20 50);
    color: var(--sb-text);
}

.steam-banner__close {
    background: none;
    border: none;
    color: oklch(1 0 0 / 0.45);
    cursor: pointer;
    padding: 6px;
    margin-left: 4px;
    border-radius: 3px;
    display: inline-flex;
    flex-shrink: 0;
    transition: color 150ms ease, background 150ms ease;
}

.steam-banner__close:hover {
    color: var(--sb-text);
    background: oklch(1 0 0 / 0.06);
}

.steam-banner__close:focus-visible {
    outline: 2px solid var(--sb-accent);
    outline-offset: 2px;
}

@media (max-width: 640px) {
    .steam-banner { padding: 8px 14px; gap: 10px; }
    .steam-banner__body { display: none; }
    .steam-banner__title { font-size: 12px; }
    .steam-banner__cta { font-size: 9px; padding: 5px 10px; }
}
</style>
