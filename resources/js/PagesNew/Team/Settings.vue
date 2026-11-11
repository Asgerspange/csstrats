<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import useTeams from '@/composables/useTeams';
import useUser from '@/composables/useUser';
import { useApi } from '@/composables/useApi';
import { useToast } from '@/composables/useToast';
import Dialog from '@/components/volt/Dialog.vue';
import InputText from '@/components/volt/InputText.vue';
import Select from '@/components/volt/Select.vue';
import { useTeamPermissions } from './composables/useTeamPermissions';
import { BASE_TYPES, BASE_TAGS } from './constants';

const { Teams } = useTeams();
const { User } = useUser();
const { usePost } = useApi();
const toast = useToast();

const { data: user } = User.get();
const { data: team } = Teams.find();

const t = computed(() => team.value);

const { canManage, canEditSettings, canDelete } = useTeamPermissions(team, user);

// --- Tags & Types ---
const customTags = ref<string[]>([]);
const customTypes = ref<string[]>([]);
const newTag = ref('');
const newType = ref('');

// Repopulate when the team query resolves (handles deep-link to /team/settings
// on a cold cache where the team isn't loaded yet at mount time).
watch(t, (newTeam) => {
    if (newTeam?.settings?.tags) customTags.value = [...newTeam.settings.tags];
    if (newTeam?.settings?.types) customTypes.value = [...newTeam.settings.types];
}, { immediate: true });

const settingsMutation = usePost<unknown, Record<string, unknown>>(['team']);

async function saveTags() {
    if (!t.value) return;
    try {
        await settingsMutation.mutateAsync({
            url: `/api/team/${t.value.id}/settings/tags`,
            payload: { tags: customTags.value },
        });
        toast.success('Tags saved');
    } catch {
        toast.error('Could not save tags. Try again.');
    }
}

async function saveTypes() {
    if (!t.value) return;
    try {
        await settingsMutation.mutateAsync({
            url: `/api/team/${t.value.id}/settings/types`,
            payload: { types: customTypes.value },
        });
        toast.success('Types saved');
    } catch {
        toast.error('Could not save types. Try again.');
    }
}

function addTag() {
    const trimmed = newTag.value.trim();
    if (trimmed && !customTags.value.includes(trimmed)) {
        customTags.value = [...customTags.value, trimmed];
        newTag.value = '';
    }
}

function removeTag(tag: string) {
    customTags.value = customTags.value.filter((t) => t !== tag);
}

function addType() {
    const trimmed = newType.value.trim();
    if (trimmed && !customTypes.value.includes(trimmed)) {
        customTypes.value = [...customTypes.value, trimmed];
        newType.value = '';
    }
}

function removeType(type: string) {
    customTypes.value = customTypes.value.filter((t) => t !== type);
}

// --- Stratbook permissions ---
const permOptions = [
    { label: 'View only', value: 'View' },
    { label: 'Editor', value: 'Edit' },
    { label: 'Full access', value: 'Edit, Add, Delete' },
].map(o => ({ label: o.label, value: o.value }));

function getPerm(userId: number): string {
    const adminUsers = t.value?.admin_users ?? [];
    const editUsers = t.value?.edit_users ?? [];
    if (adminUsers.includes(userId)) return 'Edit, Add, Delete';
    if (editUsers.includes(userId)) return 'Edit';
    return 'View';
}

async function updatePermission(userId: number, perm: string) {
    if (!t.value) return;
    try {
        await settingsMutation.mutateAsync({
            url: `/api/team/${t.value.id}/settings/permissions`,
            payload: { user_id: userId, perm, team_id: t.value.id },
        });
        toast.success('Permission updated');
    } catch {
        toast.error('Could not update permission. Try again.');
    }
}

// --- Danger zone ---
const deleteDialogVisible = ref(false);
const deleteConfirmText = ref('');

function promptDelete() {
    deleteConfirmText.value = '';
    deleteDialogVisible.value = true;
}

async function confirmDelete() {
    if (!t.value) return;
    try {
        await Teams.deleteTeam(t.value.id);
        toast.success('Team deleted');
    } catch {
        toast.error('Could not delete team. Try again.');
    } finally {
        deleteDialogVisible.value = false;
        deleteConfirmText.value = '';
    }
}
</script>

<template>
    <div class="settings-view">
        <!-- Restricted state 8 -->
        <template v-if="!canEditSettings">
            <p class="settings-view__restricted">
                You need Owner, Coach or IGL permission to change team settings.
            </p>
        </template>

        <template v-else>
            <!-- Tags -->
            <section class="settings-section">
                <h2 class="settings-section__title">Tags</h2>
                <p class="settings-section__desc">Custom tags your team uses to label tactics. Base tags are always available.</p>
                <div class="settings-section__chips">
                    <span v-for="tag in BASE_TAGS" :key="tag" class="settings-chip settings-chip--base">{{ tag }}</span>
                    <span v-for="tag in customTags" :key="tag" class="settings-chip settings-chip--custom">
                        {{ tag }}
                        <button class="settings-chip__remove" aria-label="Remove tag" @click="removeTag(tag)">&times;</button>
                    </span>
                </div>
                <div class="settings-section__add">
                    <input
                        v-model="newTag"
                        class="settings-input"
                        placeholder="Add a custom tag"
                        aria-label="Add a custom tag"
                        @keyup.enter="addTag"
                    />
                    <button class="settings-btn" @click="addTag">Add</button>
                    <button class="settings-btn settings-btn--primary" :disabled="settingsMutation.isPending.value" @click="saveTags">Save tags</button>
                </div>
            </section>

            <!-- Types -->
            <section class="settings-section">
                <h2 class="settings-section__title">Types</h2>
                <p class="settings-section__desc">Custom tactic types for your team. Base types are always available.</p>
                <div class="settings-section__chips">
                    <span v-for="type in BASE_TYPES" :key="type" class="settings-chip settings-chip--base">{{ type }}</span>
                    <span v-for="type in customTypes" :key="type" class="settings-chip settings-chip--custom">
                        {{ type }}
                        <button class="settings-chip__remove" aria-label="Remove type" @click="removeType(type)">&times;</button>
                    </span>
                </div>
                <div class="settings-section__add">
                    <input
                        v-model="newType"
                        class="settings-input"
                        placeholder="Add a custom type"
                        aria-label="Add a custom type"
                        @keyup.enter="addType"
                    />
                    <button class="settings-btn" @click="addType">Add</button>
                    <button class="settings-btn settings-btn--primary" :disabled="settingsMutation.isPending.value" @click="saveTypes">Save types</button>
                </div>
            </section>

            <!-- Stratbook permissions -->
            <section v-if="canManage" class="settings-section">
                <h2 class="settings-section__title">Stratbook permissions</h2>
                <p class="settings-section__desc">Control who can edit your team's stratbooks.</p>
                <div class="perm-list">
                    <div
                        v-for="member in t?.users ?? []"
                        :key="member.id"
                        class="perm-row"
                    >
                        <div class="perm-row__info">
                            <span class="perm-row__name">{{ member.name }}</span>
                            <span v-if="member.id === t?.owned_by" class="perm-row__owner">Owner</span>
                        </div>
                        <Select
                            :model-value="getPerm(member.id)"
                            :options="permOptions"
                            option-label="label"
                            option-value="value"
                            class="perm-row__select"
                            :aria-label="`Stratbook access for ${member.name}`"
                            @update:model-value="updatePermission(member.id, $event)"
                        />
                    </div>
                </div>
            </section>

            <!-- Danger zone -->
            <section v-if="canDelete" class="settings-section settings-section--danger">
                <h2 class="settings-section__title settings-section__title--danger">Delete team</h2>
                <p class="settings-section__desc">Permanently deletes the team and all its stratbooks. This cannot be undone.</p>
                <button class="settings-btn settings-btn--danger" @click="promptDelete">Delete team</button>
            </section>
        </template>

        <!-- Delete confirm dialog (step 2: type team name) -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            :modal="true"
            :dismissable-mask="false"
            header="Delete team"
        >
            <div class="delete-dialog">
                <p class="delete-dialog__warning">
                    Type the team name <strong>{{ t?.name }}</strong> to confirm. This cannot be undone.
                </p>
                <InputText
                    v-model="deleteConfirmText"
                    class="delete-dialog__input"
                    :placeholder="t?.name"
                />
            </div>
            <template #footer>
                <button class="settings-btn" @click="deleteDialogVisible = false">Cancel</button>
                <button
                    class="settings-btn settings-btn--danger"
                    :disabled="deleteConfirmText !== t?.name"
                    @click="confirmDelete"
                >
                    Delete permanently
                </button>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.settings-view {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    padding-top: 1.25rem;
    max-width: 48rem;
}

.settings-view__restricted {
    font-size: 0.875rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.settings-section {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.settings-section__title {
    font-size: 0.875rem;
    font-weight: 700;
    margin: 0;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.settings-section__title--danger {
    color: oklch(0.7 0.19 22);
}

.settings-section__desc {
    font-size: 0.75rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    margin: 0;
}

.settings-section__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.settings-chip {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.settings-chip--base {
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    background: var(--app-surface, oklch(0.20 0.014 250));
    border: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.settings-chip--custom {
    color: var(--app-text, oklch(1 0 0 / 0.96));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
}

.settings-chip__remove {
    border: none;
    background: none;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    cursor: pointer;
    font-size: 0.875rem;
    line-height: 1;
    padding: 0;
}

.settings-chip__remove:hover {
    color: oklch(0.7 0.19 22);
}

.settings-section__add {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.settings-input {
    flex: 1;
    min-width: 10rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface, oklch(0.20 0.014 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.8125rem;
    outline: none;
}

.settings-input:focus {
    border-color: var(--main-color, #ff8c00);
}

.settings-input:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.settings-btn {
    padding: 0.5rem 0.875rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 150ms, filter 150ms;
}

.settings-btn:hover:not(:disabled) {
    background: var(--app-surface-3, oklch(0.285 0.018 250));
}

.settings-btn:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.settings-btn--primary {
    background: var(--main-color, #ff8c00);
    border-color: var(--main-color, #ff8c00);
    color: oklch(0.15 0.01 53);
}

.settings-btn--primary:hover:not(:disabled) {
    filter: brightness(1.1);
    background: var(--main-color, #ff8c00);
}

.settings-btn--danger {
    border-color: oklch(0.58 0.18 22 / 0.4);
    background: none;
    color: oklch(0.7 0.19 22);
}

.settings-btn--danger:hover:not(:disabled) {
    background: oklch(0.58 0.18 22 / 0.08);
}

.settings-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.perm-list {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.perm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 0.625rem;
    border-radius: 0.5rem;
    transition: background 150ms;
}

.perm-row:hover {
    background: var(--app-surface, oklch(0.20 0.014 250));
}

.perm-row__info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.perm-row__name {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.perm-row__owner {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--main-color, #ff8c00);
}

.perm-row__select {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid var(--app-border-2, oklch(1 0 0 / 0.14));
    background: var(--app-surface-2, oklch(0.24 0.016 250));
    color: var(--app-text, oklch(1 0 0 / 0.96));
    cursor: pointer;
    width: 8rem;
    flex-shrink: 0;
}

.perm-row__select:focus-visible {
    outline: 2px solid var(--main-color, #ff8c00);
    outline-offset: 2px;
}

.settings-section--danger {
    border-top: 1px solid oklch(0.58 0.18 22 / 0.15);
    padding-top: 1.5rem;
}

.delete-dialog {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.5rem 0;
}

.delete-dialog__warning {
    font-size: 0.8125rem;
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
    margin: 0;
}

.delete-dialog__input {
    width: 100%;
}
</style>
