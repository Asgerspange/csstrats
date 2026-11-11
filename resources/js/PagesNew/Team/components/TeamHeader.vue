<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import useTeams from '@/composables/useTeams';
import { useToast } from '@/composables/useToast';
import type { Team } from '../types';

const props = defineProps<{
    team: Team | null;
    user: { id: number; name: string } | null;
    canManage: boolean;
    primaryAction?: { label: string; to?: string };
}>();

const { Teams } = useTeams();
const toast = useToast();
const route = useRoute();
const router = useRouter();

const editing = ref(false);
const draftName = ref('');
const saving = ref(false);
const savedFlash = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

const memberCount = computed(() => props.team?.users?.length ?? 0);

function startRename() {
    if (!props.canManage || !props.team) return;
    draftName.value = props.team.name;
    editing.value = true;
    nextTick(() => inputRef.value?.focus());
}

function cancelRename() {
    editing.value = false;
    draftName.value = '';
    nextTick(() => {
        const el = document.getElementById('team-name-display');
        el?.focus();
    });
}

async function saveRename() {
    if (!props.team) return;
    const trimmed = draftName.value.trim();
    if (!trimmed || trimmed === props.team.name) {
        cancelRename();
        return;
    }
    saving.value = true;
    try {
        await Teams.updateName(props.team.id, { team_name: trimmed });
        savedFlash.value = true;
        setTimeout(() => { savedFlash.value = false; }, 2000);
    } catch {
        toast.error('Could not rename team. Try again.');
    } finally {
        saving.value = false;
        editing.value = false;
        draftName.value = '';
    }
}

function onNameKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') { e.preventDefault(); saveRename(); }
    else if (e.key === 'Escape') { e.preventDefault(); cancelRename(); }
}

const isMapBased = computed(() => props.team?.entitlement_mode === 'map_based');

const navItems = computed(() => [
    { label: isMapBased.value ? 'Tactics' : 'Stratbooks', to: '/team' },
    { label: 'Members', to: '/team/members' },
    { label: 'Settings', to: '/team/settings' },
]);

function isActive(to: string): boolean {
    if (to === '/team') return route.path === '/team';
    return route.path.startsWith(to);
}

const emit = defineEmits<{
    (e: 'primary-action'): void;
}>();

function onPrimaryAction() {
    if (props.primaryAction?.to) {
        router.push(props.primaryAction.to);
    }
    emit('primary-action');
}
</script>

<template>
    <header class="team-header">
        <div class="team-header__identity">
            <!-- Avatar -->
            <div class="team-header__avatar" aria-hidden="true">
                {{ team?.name?.charAt(0)?.toUpperCase() ?? '?' }}
            </div>

            <!-- Name (inline rename) -->
            <div class="team-header__name-wrap">
                <h1
                    v-if="!editing"
                    id="team-name-display"
                    class="team-header__name"
                    :class="{ 'team-header__name--editable': props.canManage }"
                    :tabindex="props.canManage ? 0 : -1"
                    :aria-label="props.canManage ? 'Team name, click to rename' : 'Team name'"
                    @click="startRename"
                    @keydown.enter="startRename"
                    @keydown.space.prevent="startRename"
                >
                    {{ team?.name ?? '' }}
                    <span v-if="props.canManage" class="team-header__name-edit-hint" aria-hidden="true">
                        <span class="material-symbols text-sm">edit</span>
                    </span>
                </h1>
                <div v-else class="team-header__rename">
                    <input
                        ref="inputRef"
                        v-model="draftName"
                        class="team-header__rename-input"
                        maxlength="60"
                        aria-label="Team name"
                        @keydown="onNameKeydown"
                        @blur="saveRename"
                    />
                    <span v-if="saving" class="team-header__saved" aria-live="polite">Saving...</span>
                </div>
                <span v-if="savedFlash" class="team-header__saved" aria-live="polite">Saved</span>
            </div>

            <!-- Member count -->
            <span class="team-header__count tabular-nums">
                {{ memberCount }} member{{ memberCount === 1 ? '' : 's' }}
            </span>
        </div>

        <!-- Primary action -->
        <div class="team-header__action">
            <button
                v-if="primaryAction"
                class="team-header__btn"
                @click="onPrimaryAction"
            >
                {{ primaryAction.label }}
            </button>
        </div>

        <!-- Sub-nav -->
        <nav class="team-header__nav" aria-label="Team sections">
            <RouterLink
                v-for="item in navItems"
                :key="item.to"
                :to="item.to"
                class="team-header__nav-link"
                :class="{ 'team-header__nav-link--active': isActive(item.to) }"
                :aria-current="isActive(item.to) ? 'page' : undefined"
            >
                {{ item.label }}
            </RouterLink>
        </nav>
    </header>
</template>

<style scoped>
.team-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.team-header__identity {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}

.team-header__avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 800;
    text-transform: uppercase;
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    flex-shrink: 0;
}

.team-header__name-wrap {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    min-width: 0;
}

.team-header__name {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: -0.01em;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    cursor: default;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.team-header__name--editable {
    cursor: pointer;
}

.team-header__name--editable:hover {
    color: var(--main-color, #ff8c00);
}

.team-header__name-edit-hint {
    opacity: 0;
    transition: opacity 150ms;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.team-header__name--editable:hover .team-header__name-edit-hint {
    opacity: 1;
}

.team-header__rename {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.team-header__rename-input {
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    border: 1px solid var(--main-color, #ff8c00);
    border-radius: 0.5rem;
    padding: 0.125rem 0.5rem;
    outline: none;
    max-width: 20rem;
}

.team-header__saved {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--main-color, #ff8c00);
}

.team-header__count {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    flex-shrink: 0;
}

.team-header__action {
    flex-shrink: 0;
}

.team-header__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    border-radius: 0.5rem;
    border: 1px solid var(--main-color, #ff8c00);
    background: var(--main-color, #ff8c00);
    color: oklch(0.18 0.02 250);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: filter 150ms;
}

.team-header__btn:hover {
    filter: brightness(1.08);
}

.team-header__btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.team-header__nav {
    display: flex;
    gap: 0.125rem;
    width: 100%;
    margin-top: 0.5rem;
}

.team-header__nav-link {
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    text-decoration: none;
    transition: color 150ms, background 150ms;
}

.team-header__nav-link:hover {
    color: var(--app-text, oklch(1 0 0 / 0.96));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
}

.team-header__nav-link--active {
    color: var(--main-color, #ff8c00);
}

.team-header__nav-link:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

@media (max-width: 640px) {
    .team-header__action {
        order: 2;
    }
}
</style>
