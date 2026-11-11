import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import useUser from '@/composables/useUser';

/**
 * Plan + feature-gate access for the authenticated app. Mirrors the backend
 * PlanService::can() decisions so the UI can pre-empt a 403 by opening the
 * upgrade modal before the request even fires (and the axios interceptor
 * catches the backend 403 as a backstop).
 *
 * Usage:
 *   const { tier, isAdmin, can } = usePlan();
 *   if (!can('ai_antistrat')) return openUpgrade();
 */
export type PlanFeature = 'stratbooks' | 'demos' | 'ai_antistrat' | 'round_planner' | 'maps';

export function usePlan() {
    const store = useStore();
    const { plan, tier, isAdmin, user } = storeToRefs(store);
    const { User } = useUser();

    const limits = computed(() => plan.value.limits);

    function can(feature: PlanFeature, usage?: number): boolean {
        const l = plan.value.limits;
        switch (feature) {
            case 'ai_antistrat':
                return l.ai_antistrat === true;
            case 'round_planner':
                return l.round_planner === true;
            case 'demos':
                return usage === undefined ? true : usage < l.max_demos;
            case 'stratbooks':
                return usage === undefined ? true : usage < l.max_stratbooks;
            case 'maps':
                return l.max_maps === null || (usage === undefined ? true : usage < l.max_maps);
            default:
                return false;
        }
    }

    function maxMaps(): number | null {
        return plan.value.limits.max_maps;
    }

    function isAllMaps(): boolean {
        return plan.value.limits.max_maps === null;
    }

    function refresh() {
        return User.refresh();
    }

    return { plan, tier, limits, isAdmin, user, can, refresh, maxMaps, isAllMaps };
}
