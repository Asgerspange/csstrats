<template>
    <!-- ── Mobile header ────────────────────────────────────────────────────── -->
    <header class="lg:hidden fixed top-0 left-0 w-full bg-[var(--app-sidebar)] border-b border-white/[0.06] px-5 py-3.5 z-40 flex items-center justify-between backdrop-blur-xl">
        <a href="/" class="flex items-center gap-2" aria-label="CsStrats home">
            <img src="https://csstrats.b-cdn.net/icons/isdigital.svg" alt="" width="24" height="24" class="h-6 w-6 shrink-0" />
            <span class="text-xl font-medium leading-none tracking-[-0.02em] text-white">cs<span class="text-primary">strats</span></span>
        </a>
        <button @click="openSidebar" aria-label="Open navigation menu" class="p-2 text-white/60 hover:text-white hover:bg-white/5 rounded-lg transition-colors">
            <span class="material-symbols" aria-hidden="true">menu</span>
        </button>
    </header>

    <!-- ── Mobile backdrop ──────────────────────────────────────────────────── -->
    <transition name="fade">
        <div v-if="isSidebarOpen" @click="closeSidebar" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden" aria-hidden="true" />
    </transition>

    <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
    <aside
        class="fixed inset-y-0 left-0 z-50 h-[100dvh] flex flex-col lg:relative lg:translate-x-0 sidebar-chrome"
        :class="[
            isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
            isCollapsed ? 'sidebar--collapsed' : 'sidebar--expanded',
            isHoverExpanded && 'sidebar--peek'
        ]"
        aria-label="Sidebar Navigation"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <!-- Mobile close -->
        <button @click="closeSidebar" aria-label="Close" class="lg:hidden absolute top-3.5 right-3.5 p-1.5 text-white/30 hover:text-white rounded-lg hover:bg-white/5 transition-colors z-10">
            <span class="material-symbols text-lg" aria-hidden="true">close</span>
        </button>

        <!-- ── Top: Toggle + Logo + Team ────────────────────────────────────── -->
        <div class="shrink-0 sidebar-top">
            <!-- Toggle + Logo row -->
            <div class="sidebar-logo-row">
                <button
                    @click="toggleCollapse"
                    class="sidebar-toggle hidden lg:flex"
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                >
                    <span class="material-symbols text-[18px] transition-transform duration-200" :class="isCollapsed ? 'rotate-180' : ''">left_panel_close</span>
                </button>
                <a href="/" @click="closeSidebar" class="sidebar-logo hidden lg:flex">
                    <img src="https://csstrats.b-cdn.net/icons/isdigital.svg" alt="" width="22" height="22" class="sidebar-logo-mark" />
                    <span class="sidebar-logo-word">cs<span class="text-primary">strats</span></span>
                </a>
            </div>

            <!-- Team badge -->
            <button
                v-if="teams?.length"
                @click="isTeamPopoverOpen = !isTeamPopoverOpen"
                class="sidebar-team-badge"
                :class="isTeamPopoverOpen && 'sidebar-team-badge--open'"
            >
                <span class="sidebar-team-initials">{{ teamInitials }}</span>
                <span class="sidebar-fade text-xs font-semibold text-white/70 truncate text-left flex-1">{{ currentTeam?.name || 'Select Team' }}</span>
                <span class="material-symbols text-[14px] text-white/20 sidebar-fade">unfold_more</span>
            </button>

            <!-- Seat occupancy: empty seats are the earliest churn signal.
                 Always mounted (when a team exists) and revealed via height +
                 opacity synced to the width animation, so it doesn't hard-pop
                 and shove the nav down as the rail expands. -->
            <div v-if="teams?.length" class="seat-reveal">
                <SeatOccupancy />
            </div>

            <!-- Create team (shown when the user has no team) -->
            <button
                v-if="!teams?.length"
                @click="handleCreateTeam"
                class="sidebar-team-badge"
                :title="isCollapsed ? 'Create team' : undefined"
            >
                <span class="sidebar-team-initials"><span class="material-symbols text-[16px]">add</span></span>
                <span class="sidebar-fade text-xs font-semibold text-white/70 truncate text-left flex-1">Create team</span>
            </button>

            <!-- Team popover -->
            <transition name="popover">
                <div v-if="isTeamPopoverOpen" class="sidebar-popover" :style="{ top: isCollapsed ? '80px' : '104px' }">
                    <div class="px-3 py-2 border-b border-white/[0.04]">
                        <span class="text-[9px] font-black uppercase tracking-[0.16em] text-white/25">Switch Team</span>
                    </div>
                    <div class="py-1 max-h-[200px] overflow-y-auto sidebar-scroll">
                        <button
                            v-for="team in teams"
                            :key="team.id"
                            @click="switchTeam(team.id)"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-left transition-colors"
                            :class="team.id === selectedTeamId ? 'bg-primary/10 text-white' : 'text-white/50 hover:bg-white/[0.04] hover:text-white/80'"
                        >
                            <span class="w-5 h-5 rounded flex items-center justify-center text-[9px] font-black uppercase shrink-0" :class="team.id === selectedTeamId ? 'bg-primary/20 text-primary' : 'bg-white/[0.06] text-white/40'">{{ getInitials(team.name) }}</span>
                            <span class="text-xs font-medium truncate">{{ team.name }}</span>
                            <span v-if="team.id === selectedTeamId" class="material-symbols text-primary text-[14px] ml-auto shrink-0">check</span>
                        </button>
                    </div>
                    <div class="px-2 py-2 border-t border-white/[0.04]">
                        <button @click="handleCreateTeam(); isTeamPopoverOpen = false" class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-white/30 hover:text-primary rounded-md border border-dashed border-white/[0.06] hover:border-primary/40 transition-colors">
                            <span class="material-symbols text-[14px]">add</span>
                            New Team
                        </button>
                    </div>
                </div>
            </transition>
        </div>

        <!-- ── Scrollable navigation ────────────────────────────────────────── -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden sidebar-scroll sidebar-nav" @click="isTeamPopoverOpen = false; isStratbookPopoverOpen = false">

            <!-- Primary nav -->
            <template v-for="link in primaryRoutes" :key="link.path">
                <!-- AntiStrat (AI Antistrat) is Pro-only. On Starter/Team,
                     open the upgrade modal instead of navigating to the route. -->
                <button
                    v-if="link.gated && !can(link.gated)"
                    class="nav-item"
                    :title="isCollapsed ? link.name : undefined"
                    @click="openUpgrade({ feature: link.gated, requiredTier: link.gated === 'ai_antistrat' ? 'Pro' : 'Team' }); closeSidebar()"
                >
                    <span class="material-symbols nav-icon" aria-hidden="true">{{ link.icon }}</span>
                    <span class="sidebar-fade">{{ link.name }}</span>
                    <span class="material-symbols sidebar-fade text-[14px] ml-auto opacity-30" aria-hidden="true">lock</span>
                </button>
                <RouterLink
                    v-else
                    :to="link.path"
                    @click="closeSidebar"
                    class="nav-item"
                    :class="isCurrentRoute(link.path) && 'nav-item--active'"
                    :title="isCollapsed ? link.name : undefined"
                >
                    <span class="material-symbols nav-icon" aria-hidden="true">{{ link.icon }}</span>
                    <span class="sidebar-fade">{{ link.name }}</span>
                </RouterLink>
            </template>

            <!-- Stratbooks: one row → the page, caret → quick-switch popover -->
            <div class="stratbook-switch">
                <div class="stratbook-row" :class="isStratbookPopoverOpen && 'stratbook-row--open'">
                    <RouterLink
                        :to="isMapBased ? '/maps' : '/stratbooks'"
                        @click="closeSidebar"
                        class="nav-item flex-1 min-w-0"
                        :class="(route.path.startsWith('/stratbook') || route.path === '/maps') && 'nav-item--active'"
                        :title="isCollapsed ? stratbookLabel : undefined"
                    >
                        <span class="material-symbols nav-icon" aria-hidden="true">{{ isMapBased ? 'map' : 'menu_book' }}</span>
                        <span class="sidebar-fade">{{ stratbookLabel }}</span>
                    </RouterLink>
                    <button
                        class="stratbook-caret sidebar-fade"
                        :class="isStratbookPopoverOpen && 'stratbook-caret--open'"
                        @click.stop="toggleStratbookPopover"
                        :title="isMapBased ? 'Switch map' : 'Switch stratbook'"
                        :aria-label="isMapBased ? 'Switch map' : 'Switch stratbook'"
                    >
                        <span class="material-symbols text-[16px]">unfold_more</span>
                    </button>
                </div>

                <transition name="popover">
                    <div v-if="isStratbookPopoverOpen" class="sidebar-popover stratbook-popover" @click.stop>
                        <div class="stratbook-popover__search">
                            <span class="material-symbols text-[15px] text-white/25">search</span>
                            <input v-model="stratbookSearch" v-focus type="text" :placeholder="isMapBased ? 'Search maps…' : 'Search stratbooks…'" class="flex-1 bg-transparent border-none outline-none text-xs text-white placeholder-white/25 py-1" />
                        </div>
                        <div class="py-1 max-h-[260px] overflow-y-auto sidebar-scroll">
                            <template v-if="filteredStratbooks.length">
                                <div
                                    v-for="sb in filteredStratbooks"
                                    :key="sb.id"
                                    class="stratbook-popover__row"
                                    :class="route.path === `/stratbook/${sb.id}` && 'stratbook-popover__row--active'"
                                >
                                    <div v-if="editingId === sb.id" class="flex items-center gap-1.5 px-2 py-1 w-full" @click.stop>
                                        <InputText v-model="editName" v-focus class="!py-1 !px-2 !text-xs flex-1 !bg-white/[0.04] !border-primary/30 !min-w-0" @keyup.enter="saveRename(sb.id)" @keyup.esc="editingId = null" />
                                        <button @click.stop="saveRename(sb.id)" class="material-symbols text-sm text-primary shrink-0">check</button>
                                        <button @click.stop="editingId = null" class="material-symbols text-sm text-white/30 shrink-0">close</button>
                                    </div>
                                    <template v-else>
                                        <div class="stratbook-popover__item" role="button" tabindex="0" @click="selectStratbook(sb.id)" @keydown.enter="selectStratbook(sb.id)">
                                            <span class="material-symbols text-[15px] shrink-0" :class="route.path === `/stratbook/${sb.id}` ? 'text-primary' : 'text-white/30'">{{ isMapBased ? 'map' : 'menu_book' }}</span>
                                            <span class="flex-1 truncate text-left">{{ sb.stratbook_name }}</span>
                                        </div>
                                        <span v-if="!isMapBased" class="stratbook-popover__actions">
                                            <button @click.stop="startEdit(sb)" class="p-0.5 rounded text-white/20 hover:text-primary" title="Rename"><span class="material-symbols text-[13px]">edit</span></button>
                                            <button @click.stop="handleDelete(sb.id)" class="p-0.5 rounded text-white/20 hover:text-red-400" title="Delete"><span class="material-symbols text-[13px]">delete</span></button>
                                        </span>
                                    </template>
                                </div>
                            </template>
                            <div v-else class="px-3 py-3 text-[11px] text-white/25 italic text-center">
                                {{ stratbookSearch ? 'No matches' : (isMapBased ? 'No maps yet' : 'No stratbooks yet') }}
                            </div>
                        </div>
                        <div class="stratbook-popover__footer">
                            <button v-if="!isMapBased" @click="handleCreateStratbook(); isStratbookPopoverOpen = false" class="stratbook-popover__foot-btn stratbook-popover__foot-btn--new">
                                <span class="material-symbols text-[14px]">add</span> New
                            </button>
                            <RouterLink :to="isMapBased ? '/maps' : '/stratbooks'" @click="isStratbookPopoverOpen = false; closeSidebar()" class="stratbook-popover__foot-btn">
                                View all
                            </RouterLink>
                        </div>
                    </div>
                </transition>
            </div>

            <!-- Tools header -->
            <div class="sidebar-section-header">
                <span>Tools</span>
            </div>

            <template v-for="link in toolRoutes" :key="link.path">
                <!-- Strat Builder (Round Planner) is Team+. On Starter, open
                     the upgrade modal instead of navigating to the route. -->
                <button
                    v-if="link.gated && !can(link.gated)"
                    class="nav-item"
                    :title="isCollapsed ? link.name : undefined"
                    @click="openUpgrade({ feature: link.gated, requiredTier: 'Team' }); closeSidebar()"
                >
                    <span class="material-symbols nav-icon" aria-hidden="true">{{ link.icon }}</span>
                    <span class="sidebar-fade">{{ link.name }}</span>
                    <span class="material-symbols sidebar-fade text-[14px] ml-auto opacity-30" aria-hidden="true">lock</span>
                </button>
                <RouterLink
                    v-else
                    :to="link.path"
                    @click="closeSidebar"
                    class="nav-item"
                    :class="isCurrentRoute(link.path) && 'nav-item--active'"
                    :title="isCollapsed ? link.name : undefined"
                >
                    <span class="material-symbols nav-icon" aria-hidden="true">{{ link.icon }}</span>
                    <span class="sidebar-fade">{{ link.name }}</span>
                </RouterLink>
            </template>

            <!-- Admin -->
            <template v-if="user?.is_admin">
                <div class="sidebar-section-header">
                    <span>Admin</span>
                </div>
                <RouterLink
                    v-for="link in adminRoutes"
                    :key="link.path"
                    :to="link.path"
                    @click="closeSidebar"
                    class="nav-item"
                    :class="isCurrentRoute(link.path) && 'nav-item--active'"
                    :title="isCollapsed ? link.name : undefined"
                >
                    <span class="material-symbols nav-icon" aria-hidden="true">{{ link.icon }}</span>
                    <span class="sidebar-fade">{{ link.name }}</span>
                </RouterLink>

                <!-- Plan switcher — lets an admin preview every tier's gating.
                     Not a route: opens a dialog that writes an admin override.
                     The badge shows the active tier (or "No plan" before the
                     user query resolves); accent colour marks an admin-override
                     preview so it reads as a preview, not a real subscription. -->
                <button
                    class="nav-item"
                    :title="isCollapsed ? `Plan: ${tierLabel}` : undefined"
                    @click="planSwitcherVisible = true; closeSidebar()"
                >
                    <span class="material-symbols nav-icon" aria-hidden="true">swap_horiz</span>
                    <span class="sidebar-fade">Plan switcher</span>
                    <span
                        class="sidebar-fade plan-badge"
                        :class="plan?.is_admin_override ? 'plan-badge--preview' : 'plan-badge--real'"
                    >{{ tierLabel }}</span>
                </button>
            </template>
        </nav>

        <!-- ── Bottom ───────────────────────────────────────────────────────── -->
        <div class="shrink-0 sidebar-bottom">
            <!-- Profile -->
            <div @click="openProfile" class="sidebar-profile" role="button" tabindex="0" :title="isCollapsed ? (user?.name || 'Profile') : undefined">
                <div class="sidebar-profile-avatar">
                    <img v-if="user?.profile_picture?.base64" :src="user?.profile_picture?.base64" class="w-full h-full rounded-full object-cover" alt="Profile" />
                    <span v-else class="material-symbols text-white/40 text-[14px]">person</span>
                </div>
                <div class="flex flex-col flex-1 min-w-0 sidebar-fade">
                    <span class="font-semibold text-xs text-white truncate">{{ user?.name || 'Profile' }}</span>
                    <span class="text-[10px] text-white/25 truncate">Settings</span>
                </div>
                <span class="material-symbols text-white/15 text-[14px] sidebar-fade">chevron_right</span>
            </div>

            <!-- Logout -->
            <a href="/logout" class="sidebar-logout">
                <span class="material-symbols text-[14px]">logout</span>
                <span class="sidebar-fade">Log out</span>
            </a>
        </div>
    </aside>

    <Profile v-model:visible="visible" />
    <CreateStratbook v-model:visible="createStratbook" />
    <CreateTeam v-model:visible="createTeam" />
    <AdminPlanSwitcher v-if="user?.is_admin" v-model:visible="planSwitcherVisible" />
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import Button from '@/components/volt/Button.vue';
import InputText from '@/components/volt/InputText.vue';
import Profile from './Profile.vue';
import useStratbooks from '@/composables/useStratbooks';
import CreateStratbook from '@/componentsnew/stratbooks/CreateStratbook.vue';
import CreateTeam from '@/componentsnew/teams/CreateTeam.vue';
import SeatOccupancy from '@/componentsnew/SeatOccupancy.vue';
import AdminPlanSwitcher from '@/componentsnew/AdminPlanSwitcher.vue';
import { usePlan } from '@/composables/usePlan';
import { useUpgradeModal } from '@/composables/useUpgradeModal';

const vFocus = { mounted: (el: HTMLElement) => el.focus() };

const { Stratbooks } = useStratbooks();
const store = useStore();
const route = useRoute();
const router = useRouter();

const { user, teams, selectedTeamId, plan, tier } = storeToRefs(store);
const { can } = usePlan();
const { open: openUpgrade } = useUpgradeModal();
const visible = ref(false);
const createStratbook = ref(false);
const createTeam = ref(false);
const planSwitcherVisible = ref(false);
const isSidebarOpen = ref(false);
const isTeamPopoverOpen = ref(false);
const isStratbookPopoverOpen = ref(false);
const stratbookSearch = ref('');

const filteredStratbooks = computed(() => {
    const list = currentTeam.value?.stratbooks ?? [];
    const q = stratbookSearch.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((s: any) => s.stratbook_name?.toLowerCase().includes(q));
});

function toggleStratbookPopover() {
    isStratbookPopoverOpen.value = !isStratbookPopoverOpen.value;
    isTeamPopoverOpen.value = false;
    if (!isStratbookPopoverOpen.value) stratbookSearch.value = '';
}
function selectStratbook(id: number) {
    router.push(`/stratbook/${id}`);
    isStratbookPopoverOpen.value = false;
    stratbookSearch.value = '';
    closeSidebar();
}

const currentTeam = computed(() => teams.value?.find(t => t.id === selectedTeamId.value));
const isMapBased = computed(() => currentTeam.value?.entitlement_mode === 'map_based');
const stratbookLabel = computed(() => isMapBased.value ? 'Maps' : 'Stratbooks');
function getInitials(name: string): string { return name.split(/\s+/).map(w => w[0]).join('').slice(0, 2).toUpperCase(); }
const teamInitials = computed(() => currentTeam.value ? getInitials(currentTeam.value.name) : '??');
function switchTeam(id: number) { selectedTeamId.value = id; isTeamPopoverOpen.value = false; }

// Plan label for the switcher badge. Shows the real tier name, or a
// migration/fallback hint when there's no Paddle tier yet.
const tierLabel = computed(() => {
    if (tier.value) return tier.value;
    if (plan.value?.needs_migration) return 'Migrate';
    return 'No plan';
});

const COLLAPSE_KEY = 'sidebar-collapsed';
// User's explicit collapse preference. Applies on normal routes. Canvas-heavy
// routes (Board, Strat Builder, Demo viewers) force the rail regardless, to
// hand the canvas back its width — see route `meta.canvas` in router.ts.
const userCollapsed = ref(localStorage.getItem(COLLAPSE_KEY) === 'true');
const isHoverExpanded = ref(false);
// Per-view override: lets the user pin the rail open on a canvas route without
// touching their global preference. Reset when they navigate off the canvas.
const sessionExpanded = ref(false);

const routeForcesRail = computed(() => !!route.meta?.canvas);

const isCollapsed = computed(() => {
    // When the mobile drawer is explicitly opened, always show the full nav —
    // a forced rail would leave the drawer a useless 60px sliver.
    if (isSidebarOpen.value) return false;
    if (isHoverExpanded.value) return false;
    if (routeForcesRail.value) return !sessionExpanded.value;
    return userCollapsed.value;
});

watch(routeForcesRail, (forces) => { if (!forces) sessionExpanded.value = false; });

function toggleCollapse() {
    isHoverExpanded.value = false;
    // On a canvas route the toggle pins the rail open for this view only.
    if (routeForcesRail.value) { sessionExpanded.value = !sessionExpanded.value; return; }
    userCollapsed.value = !userCollapsed.value;
    localStorage.setItem(COLLAPSE_KEY, String(userCollapsed.value));
}

let hoverTimer: ReturnType<typeof setTimeout> | null = null;
function handleMouseEnter() {
    if (!isCollapsed.value) return;
    hoverTimer = setTimeout(() => { isHoverExpanded.value = true; }, 300);
}
function handleMouseLeave() {
    if (hoverTimer) { clearTimeout(hoverTimer); hoverTimer = null; }
    isHoverExpanded.value = false;
    isTeamPopoverOpen.value = false;
    if (editingId.value === null) isStratbookPopoverOpen.value = false;
}

const primaryRoutes = [
    { name: 'Team',      path: '/team',      icon: 'group' },
    { name: 'Tactics',   path: '/tactics',   icon: 'grid_view' },
    { name: 'Demos',     path: '/demos',     icon: 'videocam' },
    { name: 'AntiStrat', path: '/antistrat', icon: 'shield', gated: 'ai_antistrat' as const },
];
const toolRoutes = [
    { name: 'Strat Builder', path: '/strat-builder', icon: 'build', gated: 'round_planner' as const },
    { name: 'Board',         path: '/board',         icon: 'edit' },
];
const adminRoutes = [
    { name: 'Teams',     path: '/admin/teams',        icon: 'group' },
    { name: 'Commands',  path: '/admin/commands',      icon: 'terminal' },
    { name: 'Demo Scanner', path: '/admin/demo-scanner', icon: 'radar' },
    { name: 'Create Tactic', path: '/admin/tactics/create',  icon: 'add_circle' },
    { name: 'Releases',      path: '/admin/tactics/release', icon: 'publish' },
    { name: 'Callouts',      path: '/admin/callouts',        icon: 'place' },
    { name: 'Referrers',     path: '/admin/referrers',       icon: 'handshake' },
];
const isCurrentRoute = (path: string) => route.path === path;

watch(isSidebarOpen, (v) => { if (typeof window !== 'undefined') document.body.style.overflow = v ? 'hidden' : ''; });
onUnmounted(() => { if (typeof window !== 'undefined') document.body.style.overflow = ''; if (hoverTimer) clearTimeout(hoverTimer); });

const openSidebar = () => isSidebarOpen.value = true;
const closeSidebar = () => isSidebarOpen.value = false;
const openProfile = () => { visible.value = true; closeSidebar(); };
const handleCreateStratbook = () => createStratbook.value = true;
const handleCreateTeam = () => createTeam.value = true;

const editingId = ref<number | null>(null);
const editName = ref('');
const startEdit = (s: any) => { editingId.value = s.id; editName.value = s.stratbook_name; };
const saveRename = async (id: number) => { if (!editName.value.trim()) return; try { await Stratbooks.update(id, { stratbook_name: editName.value }); editingId.value = null; } catch (e) { console.error(e); } };
const handleDelete = async (id: number) => { if (confirm('Delete this stratbook permanently?')) await Stratbooks.delete(id); };

watch(() => route.path, (p) => { if (p.includes('/profile')) openProfile(); isTeamPopoverOpen.value = false; }, { immediate: true });
onMounted(() => { const p = new URLSearchParams(window.location.search); if (p.get('create_team') === '1') { createTeam.value = true; const u = new URL(window.location.href); u.searchParams.delete('create_team'); window.history.replaceState({}, '', u.toString()); } });
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════════
   SIDEBAR — all sizing driven by --sw (sidebar width) on parent class.
   Collapsed state overrides use .sidebar--collapsed parent selector.
   Every interactive element centers its icon in the same column.
   ═══════════════════════════════════════════════════════════════════════════════ */

.sidebar-chrome {
    /* Sits below the app well (oklch(0.125 0.012 250)) so the content area
       reads as the lit, focal surface and the rail recedes as chrome. Same
       blue-violet chroma family as the app, just a darker step. */
    /* Themeable: dark themes fall back to the values below; the daylight
       theme sets --app-sidebar / --app-sidebar-ink to flip the rail. */
    --sidebar-bg: var(--app-sidebar, oklch(0.10 0.012 250));
    --sb-ink: var(--app-sidebar-ink, 1 0 0);
    --rail: 60px;
    --full: 272px;
    background: var(--sidebar-bg);
    /* Full hairline (not just the right edge) so the rounded corners are
       actually outlined — sidebar and canvas are both dark, so without a
       traced border the curve doesn't read. Stronger than the old 0.06. */
    border-right: 1px solid oklch(var(--sb-ink) / 0.14);
    /* Soft 12px curve on the content-facing edge only — flush to the screen
       edge so it reads as a rounded rail, not a floating card (stays
       Flat-At-Rest). 12px is exacting, not bubbly. */
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
    transition: width 280ms cubic-bezier(0.4, 0, 0.2, 1), margin-right 280ms cubic-bezier(0.4, 0, 0.2, 1), border-radius 280ms cubic-bezier(0.4, 0, 0.2, 1), transform 300ms ease-in-out;
    overflow: hidden;
}
.sidebar--expanded { width: var(--full); }
/* In the rail, drop the curve so the rail reads as a flush slab, not a
   floating pill. The 12px rounding only reads when the rail is expanded. */
.sidebar--collapsed {
    width: var(--rail);
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

/* Hover peek: the rail expands to full width but keeps its rail-sized footprint
   via a compensating negative margin, so it floats *over* the content (with a
   shadow) instead of pushing it — no layout reflow / canvas resize. width and
   margin-right share the same transition, so the footprint stays constant. */
.sidebar--peek {
    margin-right: calc(var(--rail) - var(--full));
    box-shadow: 0 20px 40px -8px oklch(0 0 0 / 0.5);
}

/* Seat-occupancy row reveals in sync with the rail opening — no hard pop. */
.seat-reveal {
    overflow: hidden;
    max-height: 2.75rem;
    opacity: 1;
    transition: max-height 280ms cubic-bezier(0.4, 0, 0.2, 1), opacity 180ms ease 80ms;
}
.sidebar--collapsed .seat-reveal {
    max-height: 0;
    opacity: 0;
    pointer-events: none;
    transition: max-height 280ms cubic-bezier(0.4, 0, 0.2, 1), opacity 80ms ease;
}

/* ── Fade helper — opacity only, sidebar overflow:hidden handles clipping ──── */
.sidebar-fade {
    opacity: 1;
    white-space: nowrap;
    transition: opacity 180ms ease 80ms;
}
.sidebar--collapsed .sidebar-fade {
    opacity: 0;
    transition: opacity 80ms ease;
    pointer-events: none;
}

/* ── Section headers — keep height, hide text via opacity ─────────────────────── */
.sidebar-section-header {
    margin-top: 0.75rem;
    margin-bottom: 0.125rem;
    padding: 0.375rem 0.75rem;
    overflow: hidden;
    white-space: nowrap;
    font-size: 0.625rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: oklch(var(--sb-ink) / 0.25);
    transition: color 180ms ease;
}
.sidebar--collapsed .sidebar-section-header {
    color: transparent;
}
.sidebar-section-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    background: none;
    border: none;
    color: inherit;
    font: inherit;
    cursor: pointer;
    padding: 0;
    transition: color 150ms;
}
.sidebar-section-btn:hover { color: oklch(var(--sb-ink) / 0.4); }

.sidebar-section-count {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0;
    color: oklch(var(--sb-ink) / 0.3);
    font-variant-numeric: tabular-nums;
    margin-left: 0.375rem;
}

/* Cap the stratbook list and let it scroll internally, so many stratbooks
   never push Tools/Admin out of reach. */
.sidebar-stratbooks {
    display: flex;
    flex-direction: column;
    gap: 2px;
    max-height: 240px;
    overflow-y: auto;
    overflow-x: hidden;
}

/* ── Top area ────────────────────────────────────────────────────────────────── */
.sidebar-top {
    padding: 1rem 0.375rem 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    overflow: visible;
    position: relative;
}

.sidebar-logo-row {
    display: flex;
    align-items: center;
    min-height: 2.25rem;
    padding: 0 0.375rem;
    overflow: hidden;
}
/* When collapsed the row centers its single visible child (toggle) */
.sidebar--collapsed .sidebar-logo-row { justify-content: center; }

.sidebar-toggle {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.375rem;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: oklch(var(--sb-ink) / 0.25);
    transition: color 150ms, background 150ms;
    background: none;
    border: none;
    cursor: pointer;
}
.sidebar-toggle:hover { color: oklch(var(--sb-ink) / 0.6); background: oklch(var(--sb-ink) / 0.04); }

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    text-decoration: none;
    margin-left: 0.25rem;
    opacity: 1;
    transition: opacity 180ms ease 80ms;
}
.sidebar-logo-mark { width: 22px; height: 22px; flex-shrink: 0; }
.sidebar-logo-word {
    font-size: 1.25rem;
    font-weight: 500;
    letter-spacing: -0.02em;
    line-height: 1;
    color: oklch(var(--sb-ink));
    white-space: nowrap;
}
.sidebar--collapsed .sidebar-logo { opacity: 0; width: 0; overflow: hidden; pointer-events: none; transition: opacity 80ms ease; }

/* ── Team badge ──────────────────────────────────────────────────────────────── */
.sidebar-team-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.375rem;
    border-radius: 0.5rem;
    border: none;
    background: none;
    cursor: pointer;
    overflow: hidden;
    transition: background 150ms;
}
.sidebar-team-badge:hover { background: oklch(var(--sb-ink) / 0.04); }
.sidebar-team-badge--open { background: oklch(var(--sb-ink) / 0.06); }

.sidebar-team-initials {
    width: 2.25rem;
    height: 1.75rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: 900;
    text-transform: uppercase;
    flex-shrink: 0;
    background: oklch(var(--sb-ink) / 0.06);
    color: oklch(var(--sb-ink) / 0.6);
}

/* Collapsed — badge stays left-aligned, sidebar clips text */

/* ── Nav items ───────────────────────────────────────────────────────────────── */
.sidebar-nav {
    padding: 0.5rem 0.375rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.375rem;
    border-radius: 0.5rem;
    font-size: 0.875rem !important;
    font-weight: 500;
    color: oklch(var(--sb-ink) / 0.5);
    transition: color 150ms, background 150ms;
    cursor: pointer;
    text-decoration: none;
    user-select: none;
    white-space: nowrap;
    overflow: hidden;
    position: relative;
}
.nav-item:hover { color: oklch(var(--sb-ink) / 0.8); background: oklch(var(--sb-ink) / 0.04); }
.nav-item--active { color: oklch(var(--sb-ink)); background: oklch(var(--sb-ink) / 0.08); }
.nav-item--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    bottom: 0.5rem;
    width: 2px;
    border-radius: 2px;
    background: var(--color-primary, #f97316);
}
.nav-item--active > .nav-icon { color: var(--color-primary, #f97316); }
.nav-item--sub { font-size: 0.75rem; }

/* Plan badge on the admin plan-switcher row. Small mono pill, pushed to the
   end of the nav-item so the label stays left-aligned. Preview (admin
   override) uses the saffron accent so it reads as a preview, not a real
   subscription; a real Paddle tier uses the muted surface tone. */
.plan-badge {
    margin-left: auto;
    padding: 0.125rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    white-space: nowrap;
}
.plan-badge--preview {
    color: var(--color-primary, #ff8c00);
    background: oklch(from var(--color-primary, #ff8c00) l c h / 0.12);
}
.plan-badge--real {
    color: oklch(var(--sb-ink) / 0.55);
    background: oklch(var(--sb-ink) / 0.06);
}
.sidebar--collapsed .plan-badge { display: none; }

/* Icon column — fixed size, always centered within itself */
.nav-icon {
    width: 2.25rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.25rem;
}

/* When collapsed, sidebar overflow clips text — icons stay in place */

.nav-item--ghost {
    color: oklch(var(--sb-ink) / 0.2);
    border: 1px dashed oklch(var(--sb-ink) / 0.06);
    justify-content: center;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.nav-item--ghost:hover {
    color: var(--color-primary, #f97316);
    border-color: var(--color-primary, #f97316);
    background: oklch(from var(--color-primary, #f97316) l c h / 0.06);
}

/* ── Bottom area ─────────────────────────────────────────────────────────────── */
.sidebar-bottom {
    flex-shrink: 0;
    padding: 0.75rem 0.375rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    border-top: 1px solid oklch(var(--sb-ink) / 0.04);
}

.sidebar-profile {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background 150ms;
    overflow: hidden;
}
.sidebar-profile:hover { background: oklch(var(--sb-ink) / 0.04); }
.sidebar-profile-avatar {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: oklch(var(--sb-ink) / 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.sidebar--collapsed .sidebar-profile { justify-content: center; }

/* When collapsed, drop sidebar-fade siblings to width:0 so the icon centers cleanly */
.sidebar--collapsed .sidebar-profile .sidebar-fade,
.sidebar--collapsed .sidebar-logout .sidebar-fade {
    width: 0;
    min-width: 0;
    flex: 0 0 0;
    padding: 0;
    margin: 0;
}

.sidebar-logout {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    color: oklch(var(--sb-ink) / 0.25);
    transition: color 150ms, background 150ms;
    text-decoration: none;
    overflow: hidden;
    justify-content: center;
}
.sidebar-logout:hover { color: oklch(var(--sb-ink) / 0.5); background: oklch(var(--sb-ink) / 0.03); }

/* Collapsed logout: kill the gap so the lone icon stays dead-centered */
.sidebar--collapsed .sidebar-logout { gap: 0; }

/* ── Popover ─────────────────────────────────────────────────────────────────── */
.sidebar-popover {
    position: absolute;
    left: 0.5rem;
    right: 0.5rem;
    z-index: 30;
    border-radius: 0.5rem;
    border: 1px solid oklch(var(--sb-ink) / 0.06);
    background: var(--app-surface, oklch(0.16 0.005 250));
    box-shadow: 0 20px 40px -8px oklch(0 0 0 / 0.5);
    overflow: hidden;
}
.popover-enter-active { transition: opacity 150ms ease, transform 150ms cubic-bezier(0.4, 0, 0.2, 1); }
.popover-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.popover-enter-from, .popover-leave-to { opacity: 0; transform: translateY(-4px) scale(0.98); }

/* ── Stratbook switcher ──────────────────────────────────────────────────────── */
.stratbook-switch { position: relative; }
.stratbook-row { display: flex; align-items: center; }
.stratbook-caret {
    display: flex; align-items: center; justify-content: center;
    width: 1.75rem; height: 1.75rem; flex-shrink: 0; margin-right: 0.1rem;
    border: none; background: none; border-radius: 0.375rem;
    color: oklch(var(--sb-ink) / 0.3); cursor: pointer;
    transition: color 150ms, background 150ms;
}
.stratbook-caret:hover, .stratbook-caret--open { color: oklch(var(--sb-ink) / 0.7); background: oklch(var(--sb-ink) / 0.05); }
/* In the rail the caret collapses away with the labels; the row icon navigates. */
.sidebar--collapsed .stratbook-caret { width: 0; margin: 0; pointer-events: none; }

.stratbook-popover { top: calc(100% + 4px); }
.stratbook-popover__search {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.4rem 0.6rem;
    border-bottom: 1px solid oklch(var(--sb-ink) / 0.05);
}
.stratbook-popover__row { display: flex; align-items: center; padding: 0 0.35rem 0 0; }
.stratbook-popover__row--active { background: oklch(var(--sb-ink) / 0.06); border-radius: 0.375rem; }
.stratbook-popover__item {
    display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0;
    padding: 0.4rem 0.6rem; cursor: pointer;
    font-size: 0.75rem; color: oklch(var(--sb-ink) / 0.6);
    transition: color 150ms;
}
.stratbook-popover__item:hover { color: oklch(var(--sb-ink) / 0.95); }
.stratbook-popover__row--active .stratbook-popover__item { color: oklch(var(--sb-ink)); }
.stratbook-popover__actions { display: flex; gap: 0.1rem; flex-shrink: 0; opacity: 0; transition: opacity 150ms; }
.stratbook-popover__row:hover .stratbook-popover__actions { opacity: 1; }
.stratbook-popover__footer {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem; border-top: 1px solid oklch(var(--sb-ink) / 0.05);
}
.stratbook-popover__foot-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.3rem;
    padding: 0.4rem; border: none; background: none; border-radius: 0.375rem;
    text-decoration: none; cursor: pointer;
    font-size: 0.6875rem; font-weight: 600; color: oklch(var(--sb-ink) / 0.4);
    transition: color 150ms, background 150ms, border-color 150ms;
}
.stratbook-popover__foot-btn:hover { color: oklch(var(--sb-ink)); background: oklch(var(--sb-ink) / 0.04); }
.stratbook-popover__foot-btn--new { border: 1px dashed oklch(var(--sb-ink) / 0.08); }
.stratbook-popover__foot-btn--new:hover { color: var(--color-primary, #ff8c00); border-color: var(--color-primary, #ff8c00); background: none; }

/* ── Scrollbar ───────────────────────────────────────────────────────────────── */
.sidebar-scroll::-webkit-scrollbar { width: 3px; }
.sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: oklch(var(--sb-ink) / 0.06); border-radius: 10px; }
.sidebar-scroll:hover::-webkit-scrollbar-thumb { background: oklch(var(--sb-ink) / 0.12); }
.sidebar-scroll { scrollbar-width: thin; scrollbar-color: oklch(var(--sb-ink) / 0.06) transparent; }

/* ── Vue transitions ─────────────────────────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.accordion-enter-active, .accordion-leave-active { transition: max-height 250ms cubic-bezier(0.4, 0, 0.2, 1), opacity 200ms ease; max-height: 600px; opacity: 1; }
.accordion-enter-from, .accordion-leave-to { max-height: 0; opacity: 0; }

/* ── Material Symbols ────────────────────────────────────────────────────────── */
.material-symbols {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>