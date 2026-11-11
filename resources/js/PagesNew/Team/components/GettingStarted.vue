<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from '@/lib/axios';

const props = defineProps<{
    defaultOpen?: boolean;
}>();

const open = ref(props.defaultOpen ?? false);
let completionMarked = false;

watch(open, (isOpen) => {
    if (!isOpen && !completionMarked && props.defaultOpen) {
        completionMarked = true;
        axios.post('/api/onboarding/complete').catch(() => {});
    }
});

const steps = [
    { title: 'Create a stratbook', body: 'A stratbook is a folder for your tactics on one map or for one event.' },
    { title: 'Add tactics', body: 'Copy a tactic from the pro library or build your own. Tag it by map, side and type.' },
    { title: 'Invite your team', body: 'Share an invite link in your Discord. Everyone gets the same stratbook instantly.' },
];
</script>

<template>
    <div class="guide">
        <button
            class="guide__toggle"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="material-symbols text-base">{{ open ? 'expand_less' : 'expand_more' }}</span>
            How CsStrats works
        </button>

        <div v-if="open" class="guide__steps">
            <div
                v-for="(step, i) in steps"
                :key="i"
                class="guide__step"
            >
                <span class="guide__step-num tabular-nums">{{ i + 1 }}</span>
                <div>
                    <h2 class="guide__step-title">{{ step.title }}</h2>
                    <p class="guide__step-body">{{ step.body }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.guide {
    margin: 0.5rem 0;
}

.guide__toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: color 150ms;
}

.guide__toggle:hover {
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
}

.guide__toggle:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.guide__steps {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    margin-top: 0.5rem;
    background: var(--app-surface, oklch(0.20 0.014 250));
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
}

.guide__step {
    display: flex;
    gap: 0.75rem;
}

.guide__step-num {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--main-color, #ff8c00);
    flex-shrink: 0;
    width: 1.25rem;
}

.guide__step-title {
    font-size: 0.8125rem;
    font-weight: 600;
    margin: 0 0 0.125rem;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.guide__step-body {
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    margin: 0;
}
</style>
