<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import useTeams from '@/composables/useTeams';
import useUser from '@/composables/useUser';
import TeamHeader from './components/TeamHeader.vue';
import SkeletonBlock from './components/SkeletonBlock.vue';
import ErrorState from './components/ErrorState.vue';
import JoinByLink from './components/JoinByLink.vue';
import { useTeamPermissions } from './composables/useTeamPermissions';

const { Teams } = useTeams();
const { User } = useUser();
const router = useRouter();
const store = useStore();
const { selectedTeamId } = storeToRefs(store);

const { data: user } = User.get();
const { data: team, isLoading, isError, refetch } = Teams.find();

const hasNoTeam = computed(() => !selectedTeamId.value);

const { canManage } = useTeamPermissions(team, user);

const primaryAction = computed(() => {
    if (!canManage.value) return undefined;
    if (!team.value) return undefined;
    if (team.value.entitlement_mode === 'map_based') {
        if (team.value.users.length <= 1) {
            return { label: 'Invite teammates', to: '/team/members' };
        }
        return undefined;
    }
    if (!team.value.stratbooks || team.value.stratbooks.length === 0) {
        return { label: 'Create stratbook', to: '/stratbooks' };
    }
    if (team.value.users.length <= 1) {
        return { label: 'Invite teammates', to: '/team/members' };
    }
    return { label: 'Create stratbook', to: '/stratbooks' };
});

function onPrimaryAction() {
    if (primaryAction.value?.to) {
        router.push(primaryAction.value.to);
    }
}

function createTeam() {
    const url = new URL(window.location.href);
    url.searchParams.set('create_team', '1');
    window.location.href = url.toString();
}
</script>

<template>
    <div class="team-page">
        <!-- State 6: Loading -->
        <template v-if="isLoading">
            <div class="team-page__header-skel">
                <SkeletonBlock width="2.5rem" height="2.5rem" rounded="0.5rem" />
                <SkeletonBlock width="12rem" height="1.25rem" />
                <SkeletonBlock width="4rem" height="0.75rem" />
            </div>
            <SkeletonBlock width="100%" height="2.5rem" rounded="0.5rem" />
            <div class="team-page__body-skel">
                <SkeletonBlock v-for="i in 5" :key="i" width="100%" height="2.75rem" rounded="0" />
            </div>
        </template>

        <!-- State 1: No team -->
        <div v-else-if="hasNoTeam" class="team-page__no-team">
            <h1 class="team-page__no-team-title">You're not in a team yet.</h1>
            <p class="team-page__no-team-body">Create one, or join with an invite link. A team can be just you for now.</p>
            <div class="team-page__no-team-actions">
                <button class="team-page__no-team-primary" @click="createTeam">
                    Create team
                </button>
            </div>
            <div class="team-page__no-team-join">
                <JoinByLink />
            </div>
        </div>

        <!-- State 7: Error -->
        <ErrorState
            v-else-if="isError || !team"
            message="Could not load this team."
            hint="Check your connection and try again."
            @retry="refetch"
        />

        <!-- Loaded -->
        <template v-else>
            <TeamHeader
                :team="team"
                :user="user"
                :can-manage="canManage"
                :primary-action="primaryAction"
                @primary-action="onPrimaryAction"
            />
            <RouterView />
        </template>
    </div>
</template>

<style scoped>
.team-page {
    height: 100%;
    overflow-y: auto;
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

@media (min-width: 768px) {
    .team-page {
        padding: 2rem 2.5rem;
    }
}

.team-page__header-skel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.team-page__body-skel {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    margin-top: 1rem;
}

.team-page__no-team {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 4rem 1rem;
    text-align: center;
}

.team-page__no-team-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.team-page__no-team-body {
    font-size: 0.9375rem;
    max-width: 28rem;
    margin: 0;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    line-height: 1.6;
}

.team-page__no-team-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
}

.team-page__no-team-primary {
    padding: 0.625rem 1.5rem;
    border-radius: 0.5rem;
    border: none;
    background: var(--main-color, #ff8c00);
    color: oklch(0.15 0.01 53);
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    transition: filter 150ms;
}

.team-page__no-team-primary:hover {
    filter: brightness(1.1);
}

.team-page__no-team-primary:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.team-page__no-team-join {
    max-width: 24rem;
    width: 100%;
}
</style>
