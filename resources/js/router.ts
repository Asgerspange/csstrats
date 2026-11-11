import { createMemoryHistory, createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/tactics',
        name: 'Tactics',
        component: () => import('@/PagesNew/Tactics.vue'),
    },
    {
        path: '/profile',
        name: 'Profile',
        component: () => import('@/PagesNew/Tactics.vue'),
    },
    {
        path: '/team',
        component: () => import('@/PagesNew/Team/Index.vue'),
        children: [
            {
                path: '',
                name: 'TeamStratbooks',
                component: () => import('@/PagesNew/Team/TeamDefault.vue'),
            },
            {
                path: 'members',
                name: 'TeamMembers',
                component: () => import('@/PagesNew/Team/Members.vue'),
            },
            {
                path: 'settings',
                name: 'TeamSettings',
                component: () => import('@/PagesNew/Team/Settings.vue'),
            },
        ],
    },
    {
        path: '/maps',
        name: 'Maps',
        component: () => import('@/PagesNew/Team/MapsPage.vue'),
    },
    {
        path: '/board',
        name: 'Board',
        component: () => import('@/PagesNew/Board.vue'),
        meta: { canvas: true },
    },
    {
        path: '/strat-builder',
        name: 'StratBuilder',
        component: () => import('@/PagesNew/RoundPlanner/RoundPlanner.vue'),
        meta: { canvas: true },
    },
    {
        path: '/plan/:token',
        name: 'PublicRoundPlan',
        component: () => import('@/PagesNew/RoundPlanner/PublicRoundPlan.vue'),
        meta: { canvas: true, public: true },
    },
    {
        path: '/stratbooks',
        name: 'StratbooksIndex',
        component: () => import('@/PagesNew/Stratbooks/Index.vue'),
    },
    {
        path: '/stratbook/:id',
        name: 'Stratbook',
        component: () => import('@/PagesNew/Stratbook.vue'),
    },
    {
        path: '/antistrat',
        name: 'AntiStrat',
        component: () => import('@/PagesNew/AntiStrat/OpponentTeamList.vue'),
    },
    {
        path: '/antistrat/:id',
        name: 'OpponentTeamProfile',
        component: () => import('@/PagesNew/AntiStrat/OpponentTeamProfile.vue'),
    },
    {
        path: '/demos',
        name: 'Demo',
        component: () => import('@/PagesNew/Demo/Index.vue'),
    },
    {
        path: '/demos/demo/:id',
        component: () => import('@/PagesNew/Demo/DemoLayout.vue'),
        children: [
            {
                path: 'overview',
                name: 'DemoOverview',
                component: () => import('@/PagesNew/Demo/Overview.vue'),
            },
            {
                path: 'heatmaps',
                name: 'DemoHeatmaps',
                component: () => import('@/PagesNew/Demo/Heatmap.vue'),
            },
            {
                path: 'grenades',
                name: 'DemoGrenades',
                component: () => import('@/PagesNew/Demo/Grenades.vue'),
            },
            {
                path: 'viewer-2d',
                name: 'Demo2D',
                component: () => import('@/PagesNew/Demo/2D/Viewer.vue'),
                meta: { canvas: true },
            },
            {
                path: 'viewer-3d',
                name: 'Demo3D',
                component: () => import('@/PagesNew/Demo/3D/Viewer.vue'),
                meta: { canvas: true },
            }
        ]
    },
    {
        path: '/demos/folder/:id',
        name: 'DemoFolder',
        component: () => import('@/PagesNew/Demo/Folder.vue'),
    },
    {
        path: '/access-denied',
        name: 'access-denied',
        component: () => import('@/PagesNew/AccessDenied.vue'),
        props: route => ({ code: Number(route.query.code) })
    },
    {
        path: '/admin/teams',
        name: 'AdminTeams',
        component: () => import('@/PagesNew/Admin/Teams.vue'),
    },
    {
        path: '/admin/commands',
        name: 'AdminCommands',
        component: () => import('@/PagesNew/Admin/Commands.vue'),
    },
    {
        path: '/admin/callouts',
        name: 'AdminCallouts',
        component: () => import('@/PagesNew/Admin/Callouts.vue'),
    },
    {
        path: '/admin/referrers',
        name: 'AdminReferrers',
        component: () => import('@/PagesNew/Admin/Referrers.vue'),
    },
    {
        path: '/admin/demo-scanner',
        name: 'AdminDemoScanner',
        component: () => import('@/PagesNew/Admin/DemoScanner.vue'),
    },
    {
        path: '/admin/tactics/create',
        name: 'AdminTacticCreate',
        component: () => import('@/PagesNew/Admin/TacticEditor.vue'),
    },
    {
        path: '/admin/tactics/:id/edit',
        name: 'AdminTacticEdit',
        component: () => import('@/PagesNew/Admin/TacticEditor.vue'),
    },
    {
        path: '/admin/tactics/release',
        name: 'AdminTacticReleases',
        component: () => import('@/PagesNew/Admin/Releases.vue'),
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: () => import('@/PagesNew/NotFound.vue'),
    }
];

const router = createRouter({
    history: import.meta.env.SSR ? createMemoryHistory() : createWebHistory(),
    routes,
});

import { useUXWizz } from '@/composables/useUXWizz'
const { trackPage } = useUXWizz()
 
router.afterEach((to) => {
    trackPage(to.name?.toString() ?? to.path)
})

export default router;