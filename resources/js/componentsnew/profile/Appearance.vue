<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import useUser from '@/composables/useUser';
import { useToast } from '@/composables/useToast';

interface Theme {
    name: string;
    displayName: string;
    variables: Record<string, string>;
}

const { User } = useUser();
const { data: user } = User.get();
const toast = useToast();

const themes = computed<Record<string, Theme>>(() => {
    return (window as any).themes ?? {};
});

const themeList = computed<Theme[]>(() => Object.values(themes.value));

const currentTheme = ref<string>(
    (typeof window !== 'undefined' ? (window as any).currentTheme : null) ?? 'original'
);

const saving = ref<string | null>(null);

onMounted(() => {
    const userTheme = user.value?.settings?.theme;
    if (userTheme && themes.value[userTheme]) {
        currentTheme.value = userTheme;
    }
});

function applyTheme(themeKey: string) {
    const theme = themes.value[themeKey];
    if (!theme) return;
    const root = document.documentElement;
    // Clear every property any theme defines first, so switching never leaves
    // a stale value behind (e.g. daylight's light --app-* lingering when you
    // switch back to a dark theme that relies on the CSS fallback).
    for (const t of Object.values(themes.value)) {
        for (const prop of Object.keys(t.variables)) root.style.removeProperty(prop);
    }
    for (const [property, value] of Object.entries(theme.variables)) {
        root.style.setProperty(property, value);
    }
    root.setAttribute('data-theme', themeKey);
}

async function selectTheme(themeKey: string) {
    if (themeKey === currentTheme.value) return;
    if (!themes.value[themeKey]) return;

    saving.value = themeKey;
    const previous = currentTheme.value;

    // Apply immediately for instant feedback
    currentTheme.value = themeKey;
    applyTheme(themeKey);

    try {
        await axios.post('/api/user/settings/theme', { theme: themeKey });
        toast.success('Theme updated', `Switched to ${themes.value[themeKey].displayName}.`);
    } catch (e: any) {
        // Roll back on failure
        currentTheme.value = previous;
        applyTheme(previous);
        toast.error('Theme not saved', e?.response?.data?.message ?? 'Could not save your theme.');
    } finally {
        saving.value = null;
    }
}

function previewColor(theme: Theme, key: string): string {
    return theme.variables[key] ?? 'transparent';
}
</script>

<template>
    <div class="appearance">

        <section class="acct-section">
            <header class="acct-section__head">
                <span class="acct-section__kicker">Theme</span>
                <p class="acct-section__desc">Affects accent colors and surfaces throughout the app.</p>
            </header>

            <div v-if="themeList.length" class="theme-grid">
                <button
                    v-for="theme in themeList"
                    :key="theme.name"
                    class="theme-card"
                    :class="{
                        'theme-card--active': currentTheme === theme.name,
                        'theme-card--saving': saving === theme.name,
                    }"
                    :disabled="saving !== null"
                    @click="selectTheme(theme.name)"
                >
                    <div class="theme-card__preview">
                        <div
                            class="theme-card__swatch theme-card__swatch--bg"
                            :style="{ background: previewColor(theme, '--main-bg') }"
                        />
                        <div
                            class="theme-card__swatch theme-card__swatch--card"
                            :style="{ background: previewColor(theme, '--card-primary-bg') }"
                        />
                        <div
                            class="theme-card__swatch theme-card__swatch--accent"
                            :style="{ background: previewColor(theme, '--main-color') }"
                        />
                    </div>
                    <div class="theme-card__meta">
                        <span class="theme-card__name">{{ theme.displayName }}</span>
                        <span class="theme-card__state">
                            <template v-if="saving === theme.name">Saving…</template>
                            <template v-else-if="currentTheme === theme.name">Current</template>
                            <template v-else>Apply</template>
                        </span>
                    </div>
                </button>
            </div>

            <div v-else class="empty">
                <p>No themes available.</p>
            </div>
        </section>

    </div>
</template>

<style scoped>
.appearance {
    --pf-surface:   var(--app-surface, oklch(0.175 0.014 250));
    --pf-surface-2: var(--app-surface-2, oklch(0.215 0.016 250));
    --pf-surface-3: var(--app-surface-3, oklch(0.26 0.018 250));
    --pf-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --pf-border-2:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --pf-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --pf-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --pf-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --pf-accent:    var(--app-accent, #ff8c00);
    --pf-sans:      'Schibsted Grotesk', sans-serif;
    --pf-mono:      'JetBrains Mono', monospace;
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

.acct-section__desc {
    font-family: var(--pf-sans);
    font-size: 12px;
    color: var(--pf-text-3);
    margin: 4px 0 0;
}

.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    padding: 16px;
}

.theme-card {
    background: var(--pf-surface-2);
    border: 1px solid var(--pf-border);
    border-radius: 5px;
    overflow: hidden;
    cursor: pointer;
    text-align: left;
    transition: border-color 150ms ease, background 150ms ease;
    padding: 0;
    color: inherit;
    font-family: inherit;
    display: flex;
    flex-direction: column;
}

.theme-card:hover:not(:disabled) {
    border-color: var(--pf-border-2);
    background: var(--pf-surface-3);
}

.theme-card--active {
    border-color: var(--pf-accent);
    background: color-mix(in oklch, var(--pf-accent) 6%, var(--pf-surface-2));
}

.theme-card--saving { opacity: 0.7; cursor: progress; }

.theme-card:disabled { cursor: not-allowed; }

.theme-card:focus-visible {
    outline: 2px solid var(--pf-accent);
    outline-offset: 2px;
}

.theme-card__preview {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    height: 64px;
    border-bottom: 1px solid var(--pf-border);
}

.theme-card__swatch {
    height: 100%;
}

.theme-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 12px;
}

.theme-card__name {
    font-family: var(--pf-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--pf-text);
}

.theme-card__state {
    font-family: var(--pf-mono);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--pf-text-3);
    padding: 3px 7px;
    background: var(--pf-surface-3);
    border-radius: 2px;
}

.theme-card--active .theme-card__state {
    color: var(--pf-accent);
    background: color-mix(in oklch, var(--pf-accent) 14%, transparent);
}

.empty {
    padding: 32px;
    text-align: center;
    color: var(--pf-text-3);
    font-family: var(--pf-sans);
    font-size: 13px;
}
</style>
