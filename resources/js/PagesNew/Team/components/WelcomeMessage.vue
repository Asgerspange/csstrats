<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import JoinByLink from './JoinByLink.vue';

defineProps<{
    firstName: string;
}>();

const emit = defineEmits<{
    (e: 'dismiss'): void;
}>();

const router = useRouter();
const showJoin = ref(false);

function focusRename() {
    const el = document.getElementById('team-name-display');
    el?.click();
}

function goToInvite() {
    router.push('/team/members');
}

function toggleJoin() {
    showJoin.value = !showJoin.value;
}
</script>

<template>
    <div class="welcome-message" role="status" aria-live="polite">
        <button
            class="welcome-message__dismiss"
            aria-label="Dismiss welcome message"
            @click="emit('dismiss')"
        >
            <span class="material-symbols text-base">close</span>
        </button>
        <p class="welcome-message__text">
            Hello {{ firstName }}, welcome to CsStrats. Start by
            <button class="welcome-message__action" @click="focusRename">renaming this team</button>
            and
            <button class="welcome-message__action" @click="goToInvite">inviting your teammates</button>,
            or
            <button class="welcome-message__action" @click="toggleJoin">join an existing team with an invite link</button>.
        </p>
        <div v-if="showJoin" class="welcome-message__join">
            <JoinByLink />
        </div>
    </div>
</template>

<style scoped>
.welcome-message {
    position: relative;
    font-size: 0.9375rem;
    line-height: 1.6;
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    margin: 0;
    padding: 1rem 2.5rem 1rem 1.25rem;
    background: var(--app-surface, oklch(0.20 0.014 250));
    border-radius: 0.5rem;
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.welcome-message__text {
    margin: 0;
}

.welcome-message__action {
    display: inline;
    border: none;
    background: none;
    padding: 0;
    color: var(--main-color, #ff8c00);
    font: inherit;
    text-decoration: underline;
    text-underline-offset: 0.125rem;
    cursor: pointer;
}

.welcome-message__action:hover {
    filter: brightness(1.15);
}

.welcome-message__action:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
    border-radius: 0.125rem;
}

.welcome-message__dismiss {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    border-radius: 0.25rem;
    cursor: pointer;
    transition: color 150ms, background 150ms;
}

.welcome-message__dismiss:hover {
    color: var(--app-text, oklch(1 0 0 / 0.96));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.welcome-message__dismiss:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.welcome-message__join {
    margin-top: 0.75rem;
    max-width: 24rem;
}
</style>
