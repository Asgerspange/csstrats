import { reactive, readonly } from 'vue';
import type { PlanFeature } from '@/composables/usePlan';

/**
 * Global upgrade-modal state. One reactive singleton shared by every call
 * site: the axios 403 interceptor, the pre-emptive gate buttons, and the
 * <UpgradeModal> mounted once in App.vue.
 *
 *   open({ feature: 'ai_antistrat', requiredTier: 'Pro' })
 *   open({ feature: 'stratbooks', requiredTier: 'Team', limit: 3 })
 */

export interface UpgradeModalState {
    open: boolean;
    feature: PlanFeature | null;
    requiredTier: string | null;
    limit: number | null;
}

const state = reactive<UpgradeModalState>({
    open: false,
    feature: null,
    requiredTier: null,
    limit: null,
});

export function useUpgradeModal() {
    function open(opts: { feature: PlanFeature; requiredTier?: string | null; limit?: number | null } = { feature: 'stratbooks' }) {
        state.feature = opts.feature;
        state.requiredTier = opts.requiredTier ?? null;
        state.limit = opts.limit ?? null;
        state.open = true;
    }

    function close() {
        state.open = false;
        // Keep the last payload around for any late transitions; cleared on
        // next open() so a stale feature never leaks into a new session.
    }

    return { state: readonly(state), open, close };
}
