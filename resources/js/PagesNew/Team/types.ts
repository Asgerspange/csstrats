export type Side = 'CT' | 'T';

export type TacticType = string;

export type { TeamTactic } from '@/composables/useTeamTactics';

export interface TeamMember {
    id: number;
    name: string;
    steam_id: string | null;
    profile_picture: string;
    role: string;
    faceit_nickname: string | null;
}

export interface TeamOwner {
    id: number;
    name: string;
    steam_id: string | null;
    profile_picture: string;
    faceit_nickname: string | null;
}

export interface Team {
    id: number;
    name: string;
    owned_by: number;
    invite_token?: string;
    edit_users?: number[];
    admin_users?: number[];
    max_members: number;
    entitlement_mode?: 'legacy' | 'map_based';
    map_lock_expires_at?: string | null;
    settings?: {
        tags?: string[];
        types?: string[];
    };
    users: TeamMember[];
    owner: TeamOwner;
    stratbooks?: StratbookSummary[];
}

export interface StratbookSummary {
    id: number;
    stratbook_name: string;
    team_id: number;
    created_by: number;
    map_id?: number | null;
    tactics_count: number;
    updated_at: string;
}

export type TacticFilters = {
    map: string | null;
    side: Side | null;
    type: TacticType | null;
    q: string;
};

export type WidgetId = 'recent-changes' | 'coverage';

export type WidgetPrefs = WidgetId[];
