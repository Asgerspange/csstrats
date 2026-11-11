<script setup lang="ts">
import { computed, ref } from 'vue';
import useTeams from '@/composables/useTeams';
import useUser from '@/composables/useUser';
import useTeamInvitations, { type PendingInvitation } from '@/composables/useTeamInvitations';
import { useInviteLink } from '@/composables/useInviteLink';
import { useToast } from '@/composables/useToast';
import { useConfirm } from 'primevue/useconfirm';
import Select from '@/components/volt/Select.vue';
import SkeletonBlock from './components/SkeletonBlock.vue';
import { useTeamPermissions } from './composables/useTeamPermissions';

const { Teams } = useTeams();
const { User } = useUser();
const invitations = useTeamInvitations();
const { inviteUrl, regenerate, isRegenerating } = useInviteLink();
const toast = useToast();
const confirm = useConfirm();

const { data: user } = User.get();
const { data: team } = Teams.find();
const { data: invitesData } = invitations.list();

const { canManage, canInvite } = useTeamPermissions(team, user);

const t = computed(() => team.value);

const maxMembers = computed(() => t.value?.max_members ?? 0);
const memberCount = computed(() => t.value?.users.length ?? 0);

const pendingInvites = computed<PendingInvitation[]>(
    () => invitesData.value?.invitations ?? [],
);

const ROLES = ['Owner', 'Coach', 'IGL', 'Entry', 'AWP', 'Support', 'Lurker', 'Player', 'Stand-in'].map(r => ({ label: r, value: r }));

function canChangeRole(memberId: number): boolean {
    if (!canManage.value || !t.value) return false;
    if (memberId === t.value.owned_by) return false;
    return true;
}

function canKick(memberId: number, memberRole: string): boolean {
    if (!canManage.value || !t.value) return false;
    if (memberId === t.value.owned_by) return false;
    if (memberId === user.value?.id) return false;
    if (t.value.owned_by === user.value?.id) return true;
    if (memberRole === 'Owner' || memberRole === 'Coach') return false;
    return true;
}

const canLeave = computed(() => {
    if (!t.value || !user.value) return false;
    return user.value.id !== t.value.owned_by;
});

const inviteEmail = ref('');

async function sendInvite() {
    if (!t.value || !inviteEmail.value.trim()) return;
    try {
        const res = await invitations.send(t.value.id, { email: inviteEmail.value.trim(), role: 'Player' });
        const accountCreated = (res as { account_created?: boolean })?.account_created;
        toast.success(accountCreated
            ? 'Invitation sent. A new account was created for them.'
            : 'Invitation sent.');
        inviteEmail.value = '';
    } catch {
        toast.error('Could not send invitation. Check the email and try again.');
    }
}

async function changeRole(memberId: number, role: string) {
    if (!t.value) return;
    try {
        await Teams.updateRole(t.value.id, { user_id: memberId, role });
        toast.success('Role updated');
    } catch {
        toast.error('Could not update role. Try again.');
    }
}

function kickMember(memberId: number, memberName: string) {
    if (!t.value) return;
    confirm.require({
        message: `Remove ${memberName} from the team?`,
        header: 'Remove member',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Remove', severity: 'danger' },
        accept: async () => {
            try {
                await Teams.removeMember(t.value!.id, memberId);
                toast.success('Member removed');
            } catch {
                toast.error('Could not remove member. Try again.');
            }
        },
    });
}

function leaveTeam() {
    if (!t.value || !user.value) return;
    confirm.require({
        message: 'Leave this team? You will lose access to its stratbooks.',
        header: 'Leave team',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Leave', severity: 'danger' },
        accept: async () => {
            try {
                await Teams.removeMember(t.value!.id, user.value!.id);
                toast.success('You have left the team');
            } catch {
                toast.error('Could not leave the team. Try again.');
            }
        },
    });
}

function revokeInvite(inviteId: number) {
    if (!t.value) return;
    confirm.require({
        message: 'Revoke this invitation?',
        header: 'Revoke invitation',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Revoke', severity: 'danger' },
        accept: async () => {
            try {
                await invitations.revoke(t.value!.id, inviteId);
                toast.success('Invitation revoked');
            } catch {
                toast.error('Could not revoke invitation.');
            }
        },
    });
}

function regenerateLink() {
    confirm.require({
        message: 'This will invalidate the current invite link. Anyone with the old link will no longer be able to join. Continue?',
        header: 'Regenerate invite link',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Regenerate', severity: 'danger' },
        accept: async () => {
            try {
                await regenerate();
                toast.success('New invite link generated');
            } catch {
                toast.error('Could not regenerate link.');
            }
        },
    });
}

async function copyLink() {
    if (!inviteUrl.value) return;
    try {
        await navigator.clipboard.writeText(inviteUrl.value);
        toast.success('Link copied');
    } catch {
        toast.error('Could not copy. Select the link and copy manually.');
    }
}
</script>

<template>
    <div class="members-view">
        <!-- Seat count -->
        <p class="members-view__seats tabular-nums">
            {{ memberCount }} of {{ maxMembers }} seats used
        </p>

        <!-- Roster -->
        <section class="members-view__section">
            <h2 class="members-view__section-title">Members</h2>
            <div v-if="!t" class="members-view__roster">
                <div v-for="i in 3" :key="i" class="member-row member-row--skel">
                    <SkeletonBlock width="2rem" height="2rem" rounded="0.5rem" />
                    <SkeletonBlock width="8rem" height="0.875rem" />
                    <SkeletonBlock width="5rem" height="1.75rem" rounded="0.5rem" />
                </div>
            </div>
            <div v-else class="members-view__roster">
                <div
                    v-for="member in t.users"
                    :key="member.id"
                    class="member-row"
                >
                    <div class="member-row__avatar">
                        {{ member.name?.charAt(0)?.toUpperCase() ?? '?' }}
                    </div>
                    <div class="member-row__info">
                        <span class="member-row__name">
                            {{ member.name }}
                            <span v-if="member.id === user?.id" class="member-row__you">(you)</span>
                        </span>
                        <span v-if="member.id === t?.owned_by" class="member-row__owner-badge">Owner</span>
                    </div>

                    <!-- Role dropdown (admins) or role label (others) -->
                    <Select
                        v-if="canChangeRole(member.id)"
                        :model-value="member.role"
                        :options="ROLES"
                        option-label="label"
                        option-value="value"
                        class="member-row__role-select"
                        :aria-label="`Role for ${member.name}`"
                        @update:model-value="changeRole(member.id, $event)"
                    />
                    <span v-else class="member-row__role">{{ member.role }}</span>

                    <!-- Actions -->
                    <div class="member-row__actions">
                        <button
                            v-if="canKick(member.id, member.role)"
                            class="member-row__action member-row__action--danger"
                            aria-label="Remove member"
                            @click="kickMember(member.id, member.name)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Leave team (self, non-owner) -->
            <button
                v-if="canLeave"
                class="members-view__leave"
                @click="leaveTeam"
            >
                Leave team
            </button>
        </section>

        <!-- Invite (gated) -->
        <section v-if="canInvite" class="members-view__section">
            <h2 class="members-view__section-title">Invite teammates</h2>

            <!-- Email invite -->
            <div class="invite-form">
                <input
                    v-model="inviteEmail"
                    type="email"
                    class="invite-form__input"
                    placeholder="teammate@email.com"
                    aria-label="Email to invite"
                    @keyup.enter="sendInvite"
                />
                <button
                    class="invite-form__btn"
                    :disabled="!inviteEmail.trim()"
                    @click="sendInvite"
                >
                    Send invite
                </button>
            </div>

            <!-- Invite link -->
            <div class="invite-link">
                <label class="invite-link__label">Invite link</label>
                <div class="invite-link__row">
                    <input
                        :value="inviteUrl || 'Loading...'"
                        readonly
                        class="invite-link__url"
                        aria-label="Invite link"
                        @click="($event.target as HTMLInputElement).select()"
                    />
                    <button class="invite-link__copy" @click="copyLink">Copy</button>
                </div>
                <button
                    class="invite-link__regenerate"
                    :disabled="isRegenerating"
                    @click="regenerateLink"
                >
                    {{ isRegenerating ? 'Regenerating...' : 'Regenerate link (invalidates old one)' }}
                </button>
            </div>

            <!-- Pending invites -->
            <div v-if="pendingInvites.length > 0" class="pending-invites">
                <h3 class="pending-invites__title">Pending ({{ pendingInvites.length }})</h3>
                <div
                    v-for="invite in pendingInvites"
                    :key="invite.id"
                    class="pending-invite-row"
                >
                    <div class="pending-invite-row__info">
                        <span class="pending-invite-row__email">{{ invite.email }}</span>
                        <span class="pending-invite-row__meta">
                            {{ invite.role }}
                            <span v-if="invite.expired" class="pending-invite-row__expired">expired</span>
                        </span>
                    </div>
                    <button
                        class="pending-invite-row__revoke"
                        @click="revokeInvite(invite.id)"
                    >
                        Revoke
                    </button>
                </div>
            </div>
        </section>

        <!-- Restricted state 8 -->
        <p
            v-if="!canInvite"
            class="members-view__restricted"
        >
            You need Owner, Coach or IGL permission to invite teammates.
        </p>
    </div>
</template>

<style scoped>
.members-view {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding-top: 1.25rem;
}

.members-view__seats {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    margin: 0;
}

.members-view__section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.members-view__section-title {
    font-size: 0.8125rem;
    font-weight: 700;
    margin: 0;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.members-view__roster {
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
    overflow: hidden;
}

.member-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.06));
    min-height: 3rem;
}

.member-row:last-child {
    border-bottom: none;
}

.member-row--skel {
    gap: 0.75rem;
}

.member-row__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    flex-shrink: 0;
}

.member-row__info {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.member-row__name {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.member-row__you {
    font-size: 0.75rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    font-weight: 400;
}

.member-row__owner-badge {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--main-color, #ff8c00);
    padding: 0.125rem 0.375rem;
    border-radius: 0.125rem;
    background: oklch(1 0 0 / 0.06);
}

.member-row__role-select,
.member-row__role {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    flex-shrink: 0;
    min-width: 0;
}

.member-row__role-select {
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    cursor: pointer;
}

.member-row__role-select:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.member-row__role {
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.member-row__actions {
    flex-shrink: 0;
}

.member-row__action {
    padding: 0.25rem 0.625rem;
    border: none;
    background: none;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: color 150ms;
}

.member-row__action--danger {
    color: oklch(0.58 0.18 22);
}

.member-row__action--danger:hover {
    color: oklch(0.7 0.19 22);
}

.member-row__action:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.members-view__leave {
    align-self: flex-start;
    padding: 0.375rem 0.75rem;
    border: 1px solid oklch(0.58 0.18 22 / 0.3);
    border-radius: 0.5rem;
    background: none;
    color: oklch(0.7 0.19 22);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms;
}

.members-view__leave:hover {
    background: oklch(0.58 0.18 22 / 0.08);
}

.members-view__leave:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.invite-form {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.invite-form__input {
    flex: 1;
    min-width: 12rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface, oklch(0.20 0.014 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    outline: none;
}

.invite-form__input:focus {
    border-color: var(--main-color, #ff8c00);
}

.invite-form__input:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.invite-form__btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: none;
    background: var(--main-color, #ff8c00);
    color: oklch(0.15 0.01 53);
    font-size: 0.8125rem;
    font-weight: 700;
    cursor: pointer;
    transition: filter 150ms;
}

.invite-form__btn:hover:not(:disabled) {
    filter: brightness(1.1);
}

.invite-form__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.invite-form__btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.invite-link {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.invite-link__label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.invite-link__row {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.invite-link__url {
    flex: 1;
    min-width: 12rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    background: var(--app-surface, oklch(0.20 0.014 250));
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    font-size: 0.75rem;
    cursor: text;
}

.invite-link__copy {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms;
}

.invite-link__copy:hover {
    background: var(--app-surface-3, oklch(0.285 0.018 250));
}

.invite-link__copy:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.invite-link__regenerate {
    align-self: flex-start;
    padding: 0.25rem 0;
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: color 150ms;
}

.invite-link__regenerate:hover:not(:disabled) {
    color: oklch(0.7 0.19 22);
}

.invite-link__regenerate:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pending-invites {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.pending-invites__title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    margin: 0;
}

.pending-invite-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
    border-radius: 0.5rem;
}

.pending-invite-row__info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    min-width: 0;
}

.pending-invite-row__email {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--app-text, oklch(1 0 0 / 0.96));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pending-invite-row__meta {
    font-size: 0.6875rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    display: flex;
    gap: 0.375rem;
}

.pending-invite-row__expired {
    color: oklch(0.7 0.19 22);
}

.pending-invite-row__revoke {
    padding: 0.25rem 0.625rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    border-radius: 0.5rem;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: color 150ms;
    flex-shrink: 0;
}

.pending-invite-row__revoke:hover {
    color: oklch(0.7 0.19 22);
}

.pending-invite-row__revoke:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.members-view__restricted {
    font-size: 0.8125rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    margin: 0;
}

@media (max-width: 640px) {
    .member-row {
        flex-wrap: wrap;
    }
    .member-row__actions {
        width: 100%;
        text-align: right;
    }
}
</style>
