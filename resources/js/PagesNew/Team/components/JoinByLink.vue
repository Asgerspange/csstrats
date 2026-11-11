<script setup lang="ts">
import { ref } from 'vue';

const inviteLink = ref('');

function join() {
    const trimmed = inviteLink.value.trim();
    if (!trimmed) return;

    // Accept either a full URL (https://csstrats.dk/invite/abc123)
    // or just the token itself.
    const match = trimmed.match(/\/invite\/([a-zA-Z0-9]+)/);
    const token = match ? match[1] : trimmed;

    // The invite page is a separate Blade entry (invite.js), not part of
    // the SPA router, so we need a full document navigation.
    window.location.href = `/invite/${token}`;
}
</script>

<template>
    <div class="join-link">
        <input
            v-model="inviteLink"
            type="text"
            class="join-link__input"
            placeholder="Paste an invite link"
            aria-label="Invite link"
            @keyup.enter="join"
        />
        <button
            class="join-link__btn"
            :disabled="!inviteLink.trim()"
            @click="join"
        >
            Join
        </button>
    </div>
</template>

<style scoped>
.join-link {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.join-link__input {
    flex: 1;
    min-width: 14rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface, oklch(0.20 0.014 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    outline: none;
    transition: border-color 150ms;
}

.join-link__input:focus {
    border-color: var(--main-color, #ff8c00);
}

.join-link__input:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.join-link__btn {
    padding: 0.5rem 1.25rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms;
}

.join-link__btn:hover:not(:disabled) {
    background: var(--app-surface-3, oklch(0.285 0.018 250));
}

.join-link__btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.join-link__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
