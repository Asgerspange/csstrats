<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import { useTeamTactics } from '@/composables/useTeamTactics';
import useTeams from '@/composables/useTeams';
import useUser from '@/composables/useUser';
import useStratbooks from '@/composables/useStratbooks';
import { useTacticFilters } from './composables/useTacticFilters';
import { useTeamNudges } from './composables/useTeamNudges';
import FilterBar from './components/FilterBar.vue';
import TacticOverview from './components/TacticOverview.vue';
import WelcomeMessage from './components/WelcomeMessage.vue';
import Nudge from './components/Nudge.vue';
import GettingStarted from './components/GettingStarted.vue';
import WidgetTray from './components/WidgetTray.vue';
import SkeletonBlock from './components/SkeletonBlock.vue';
import ErrorState from './components/ErrorState.vue';
import type { TacticFilters } from './types';

const { Teams } = useTeams();
const { User } = useUser();
const { Stratbooks } = useStratbooks();
const store = useStore();
const { user: storeUser } = storeToRefs(store);

const { data: user } = User.get();
const { data: team } = Teams.find();
const { data: stratbooks } = Stratbooks.get();
const { data: tactics, isLoading, isError, refetch } = useTeamTactics();

const { filters } = useTacticFilters();

const teamTypes = computed(() => team.value?.settings?.types ?? []);

const tacticsCount = computed(() => tactics.value?.length ?? 0);
const stratbooksList = computed(() => stratbooks.value ?? []);
const hasStratbooks = computed(() => stratbooksList.value.length > 0);

const { activeNudge, dismiss } = useTeamNudges({
    team,
    user,
    stratbooks: stratbooksList,
    tacticsCount,
});

// ── Welcome message ──────────────────────────────────────────────────────────
// Shows until the user dismisses it OR all three actions are complete:
// 1. Renamed the team (name is no longer the auto-generated default)
// 2. Invited teammates (more than 1 member)
// 3. Created a stratbook
const WELCOME_KEY = 'team-welcome-dismissed:';

const welcomeDismissed = ref(false);

function loadWelcomeDismissed() {
    const uid = storeUser.value?.id ?? 'anon';
    try {
        welcomeDismissed.value = localStorage.getItem(WELCOME_KEY + uid) === '1';
    } catch {
        welcomeDismissed.value = false;
    }
}

loadWelcomeDismissed();

function dismissWelcome() {
    welcomeDismissed.value = true;
    const uid = storeUser.value?.id ?? 'anon';
    try {
        localStorage.setItem(WELCOME_KEY + uid, '1');
    } catch {
        // localStorage unavailable
    }
}

const autoTeamName = computed(() => user.value ? `${user.value.name}'s team` : '');

const hasRenamed = computed(() =>
    !!team.value && team.value.name !== autoTeamName.value,
);

const hasInvited = computed(() =>
    !!team.value && team.value.users.length > 1,
);

const allWelcomeActionsDone = computed(() =>
    hasRenamed.value && hasInvited.value && hasStratbooks.value,
);

const showWelcome = computed(() =>
    !welcomeDismissed.value && !allWelcomeActionsDone.value && !!team.value,
);

const guideDefaultOpen = computed(() =>
    showWelcome.value && !hasStratbooks.value,
);

// ── Actions ──────────────────────────────────────────────────────────────────
const router = useRouter();

function createStratbook() {
    if (team.value?.entitlement_mode === 'map_based') {
        router.push('/tactics');
    } else {
        router.push('/stratbooks');
    }
}

function onNudgeAction() {
    if (!activeNudge.value) return;
    if (activeNudge.value.id === 'create-stratbook') createStratbook();
    else if (activeNudge.value.id === 'invite-teammates') router.push('/team/members');
    else if (activeNudge.value.id === 'rename-team') {
        document.getElementById('team-name-display')?.click();
    }
}

function updateFilters(value: TacticFilters) {
    Object.assign(filters, value);
}
</script>

<template>
    <div class="stratbooks-view">
        <WelcomeMessage
            v-if="showWelcome"
            :first-name="user?.name?.split(' ')[0] ?? ''"
            @dismiss="dismissWelcome"
        />

        <Nudge
            v-if="activeNudge && !showWelcome"
            :message="activeNudge.message"
            :action-label="activeNudge.actionLabel"
            @dismiss="dismiss(activeNudge.id)"
            @action="onNudgeAction"
        />

        <GettingStarted :default-open="guideDefaultOpen" />

        <template v-if="isLoading">
            <SkeletonBlock width="100%" height="2.5rem" rounded="0.5rem" />
            <div class="stratbooks-view__skel-group">
                <SkeletonBlock v-for="i in 4" :key="i" width="100%" height="2.75rem" rounded="0" />
            </div>
        </template>

        <ErrorState
            v-else-if="isError"
            message="Could not load the team's tactics."
            hint="Check your connection and try again."
            @retry="refetch"
        />

        <template v-else>
            <FilterBar
                v-if="hasStratbooks"
                :filters="filters"
                :team-types="teamTypes"
                @update:filters="updateFilters"
            />
            <TacticOverview
                :tactics="tactics ?? []"
                :filters="filters"
                :has-stratbooks="hasStratbooks"
                @create-stratbook="createStratbook"
            />
            <WidgetTray />
        </template>
    </div>
</template>

<style scoped>
.stratbooks-view {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding-top: 1.25rem;
}

.stratbooks-view__skel-group {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}
</style>
