import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export interface PlanLimits {
    max_members: number;
    max_teams: number;
    max_stratbooks: number;
    max_maps: number | null;
    max_demos: number;
    ai_antistrat: boolean;
    round_planner: boolean;
}

export interface Plan {
    tier: string | null;
    limits: PlanLimits;
    is_admin_override: boolean;
    needs_migration: boolean;
    paddle_price_id: string | null;
}

export const useStore = defineStore('main', () => {
    // --- State ---
    const user = ref<any>(null);
    const teams = ref<any[]>([]);
    const selectedTeamId = ref<number | null>(null);

    // --- Getters ---
    const selectedTeam = computed(() =>
        teams.value.find(t => t.id === selectedTeamId.value) || null
    );

    // The plan descriptor attached to /api/user by UserController. Falls back
    // to Starter-equivalent defaults when missing so gating code always has a
    // baseline before the user query resolves.
    const plan = computed<Plan>(() => {
        const p = user.value?.plan;
        if (!p) {
            return {
                tier: null,
                limits: { max_members: 8, max_teams: 1, max_stratbooks: 4, max_maps: 4, max_demos: 5, ai_antistrat: false, round_planner: false },
                is_admin_override: false,
                needs_migration: false,
                paddle_price_id: null,
            };
        }
        // Backend ships limits flat on the plan object; normalise.
        return {
            tier: p.tier ?? null,
            limits: {
                max_members:   p.max_members   ?? 8,
                max_teams:      p.max_teams     ?? 1,
                max_stratbooks: p.max_stratbooks ?? 4,
                max_maps:      p.max_maps      ?? 4,
                max_demos:      p.max_demos      ?? 5,
                ai_antistrat:   p.ai_antistrat ?? false,
                round_planner:  p.round_planner ?? false,
            },
            is_admin_override: p.is_admin_override ?? false,
            needs_migration: p.needs_migration ?? false,
            paddle_price_id: p.paddle_price_id ?? null,
        };
    });

    const tier = computed(() => plan.value.tier);
    const isAdmin = computed(() => !!user.value?.is_admin);

    // --- Actions ---
    function setTeams(newTeams: any[]) {
        teams.value = newTeams;

        // If there's no persisted ID yet, default to the first team
        if (newTeams.length > 0 && !selectedTeamId.value) {
            selectedTeamId.value = newTeams[0].id;
        }
    }

    function setUser(newUser: any) {
        user.value = newUser;
    }

    return {
        user,
        teams,
        selectedTeamId,
        selectedTeam,
        plan,
        tier,
        isAdmin,
        setTeams,
        setUser
    };
}, {
    persist: {
        // Only persist the selectedTeamId property
        paths: ['selectedTeamId'],
        // Optional: specify storage (defaults to localStorage)
        storage: localStorage,
    }
});