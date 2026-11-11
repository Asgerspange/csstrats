import { computed, type Ref } from 'vue';
import type { Team } from '../types';

export interface TeamPermissions {
    currentUserRole: ReturnType<typeof computed<string>>;
    canManage: ReturnType<typeof computed<boolean>>;
    canEditSettings: ReturnType<typeof computed<boolean>>;
    canDelete: ReturnType<typeof computed<boolean>>;
    canInvite: ReturnType<typeof computed<boolean>>;
}

/**
 * Centralised team permission checks. Replaces the duplicated canManage /
 * currentUserRole logic that was copy-pasted across Index, Members, and
 * Settings. Mirrors the backend isAdministrator($team, $iglCheck) rules:
 *   - canManage (iglCheck=false): owner-by-id, Owner, Coach
 *   - canEditSettings (iglCheck=true): + IGL
 *   - canInvite: owner-by-id, Owner, Coach, IGL
 *   - canDelete: owner-by-id, Owner
 */
export function useTeamPermissions(
    team: Ref<Team | undefined>,
    user: Ref<{ id: number; name: string } | undefined>,
): TeamPermissions {
    const currentUserRole = computed(() => {
        if (!team.value || !user.value) return '';
        return team.value.users?.find((u) => u.id === user.value.id)?.role ?? '';
    });

    const isOwner = computed(() => {
        return !!team.value && !!user.value && team.value.owned_by === user.value.id;
    });

    const canManage = computed(() =>
        isOwner.value || currentUserRole.value === 'Owner' || currentUserRole.value === 'Coach',
    );

    const canEditSettings = computed(() =>
        isOwner.value ||
        currentUserRole.value === 'Owner' ||
        currentUserRole.value === 'Coach' ||
        currentUserRole.value === 'IGL',
    );

    const canInvite = computed(() =>
        isOwner.value ||
        currentUserRole.value === 'Owner' ||
        currentUserRole.value === 'Coach' ||
        currentUserRole.value === 'IGL',
    );

    const canDelete = computed(() =>
        isOwner.value || currentUserRole.value === 'Owner',
    );

    return { currentUserRole, canManage, canEditSettings, canDelete, canInvite };
}
