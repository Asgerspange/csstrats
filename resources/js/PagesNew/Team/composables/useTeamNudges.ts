import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import type { Team, StratbookSummary } from '../types';

const DISMISS_PREFIX = 'team-nudge-dismissed:';

export type NudgeId =
    | 'rename-team'
    | 'create-stratbook'
    | 'add-first-tactic'
    | 'invite-teammates';

export interface Nudge {
    id: NudgeId;
    message: string;
    actionLabel?: string;
}

interface UseTeamNudgesArgs {
    team: Ref<Team | undefined>;
    user: Ref<{ id: number; name: string } | undefined>;
    stratbooks: Ref<StratbookSummary[] | undefined>;
    tacticsCount: Ref<number> | ComputedRef<number>;
}

/**
 * State-driven nudges: at most one on screen at a time, selected by priority.
 * Dismissal is permanent (localStorage keyed by userId + nudgeId).
 * Once a nudge's condition is satisfied it stops being a candidate.
 */
export function useTeamNudges(args: UseTeamNudgesArgs) {
    const store = useStore();
    const { user: storeUser } = storeToRefs(store);
    const dismissed = ref<Set<NudgeId>>(new Set());

    function loadDismissed() {
        const uid = storeUser.value?.id ?? 'anon';
        try {
            const raw = localStorage.getItem(DISMISS_PREFIX + uid);
            dismissed.value = new Set(raw ? JSON.parse(raw) : []);
        } catch {
            dismissed.value = new Set();
        }
    }

    loadDismissed();

    function isDismissed(id: NudgeId): boolean {
        return dismissed.value.has(id);
    }

    function dismiss(id: NudgeId) {
        const next = new Set(dismissed.value);
        next.add(id);
        dismissed.value = next;
        const uid = storeUser.value?.id ?? 'anon';
        try {
            localStorage.setItem(DISMISS_PREFIX + uid, JSON.stringify([...next]));
        } catch {
            // localStorage unavailable
        }
    }

    const activeNudge = computed<Nudge | null>(() => {
        const t = args.team.value;
        const sbs = args.stratbooks.value ?? [];
        const tc = args.tacticsCount.value;

        if (!t) return null;

        // Check if the team still has its auto-generated name (backfilled or
        // auto-created as "{username}'s team"). We can't import the user here
        // without circular deps, so we check if the name ends with "'s team".
        const isAutoName = t.name.endsWith("'s team") || t.name === 'New team';
        if (isAutoName && !isDismissed('rename-team')) {
            return { id: 'rename-team', message: 'Give your team a name' };
        }

        if (sbs.length === 0 && !isDismissed('create-stratbook')) {
            return { id: 'create-stratbook', message: 'Create your first stratbook', actionLabel: 'Create stratbook' };
        }

        if (sbs.length > 0 && tc === 0 && !isDismissed('add-first-tactic')) {
            return { id: 'add-first-tactic', message: `Add your first tactic to ${sbs[0].stratbook_name}` };
        }

        if (t.users.length <= 1 && !isDismissed('invite-teammates')) {
            return { id: 'invite-teammates', message: 'Invite your teammates', actionLabel: 'Invite' };
        }

        return null;
    });

    return { activeNudge, dismiss };
}
