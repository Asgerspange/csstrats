<template>
    <div class="min-h-screen bg-neutral-950 flex flex-col items-center justify-center p-4 relative overflow-hidden">

        <!-- Background glow -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-orange-500/8 rounded-full blur-[120px]" />
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-red-600/8 rounded-full blur-[100px]" />
        </div>

        <!-- Logo -->
        <a href="/" class="absolute top-6 left-6 text-xl font-black tracking-tighter italic text-white z-10">
            CS<span class="text-orange-500">STRATS</span>
        </a>

        <!-- Card -->
        <div class="relative z-10 w-full max-w-md">

            <!-- Top accent -->
            <div class="h-[3px] bg-gradient-to-r from-orange-500 via-red-500 to-orange-400 rounded-t-2xl" />

            <div class="bg-neutral-900 border border-neutral-800 border-t-0 rounded-b-2xl shadow-2xl shadow-black/60 overflow-hidden">

                <!-- ─── STEP: Invalid token ─── -->
                <div v-if="!invite.team" class="p-10 text-center space-y-4">
                    <div class="text-5xl">🔗</div>
                    <h2 class="text-xl font-bold text-white">Invalid invite link</h2>
                    <p class="text-neutral-400 text-sm">This invite link has expired or is no longer valid.</p>
                    <a href="/" class="inline-block mt-4 px-6 py-2.5 bg-orange-500 hover:bg-orange-400 text-white text-sm font-bold rounded-lg transition-all">
                        Go to CsStrats
                    </a>
                </div>

                <!-- ─── STEP: Already a member ─── -->
                <div v-else-if="invite.already_member" class="p-10 text-center space-y-4">
                    <div class="text-5xl">✅</div>
                    <h2 class="text-xl font-bold text-white">You're already on this team</h2>
                    <p class="text-neutral-400 text-sm">You're already a member of <span class="text-white font-semibold">{{ invite.team.name }}</span>.</p>
                    <a href="/team" class="inline-block mt-4 px-6 py-2.5 bg-orange-500 hover:bg-orange-400 text-white text-sm font-bold rounded-lg transition-all">
                        Go to Team
                    </a>
                </div>

                <!-- ─── STEP: Team full ─── -->
                <div v-else-if="invite.team.is_full" class="p-10 text-center space-y-4">
                    <div class="text-5xl">😔</div>
                    <h2 class="text-xl font-bold text-white">Team is full</h2>
                    <p class="text-neutral-400 text-sm">
                        <span class="text-white font-semibold">{{ invite.team.name }}</span> has reached its maximum of {{ invite.team.max_members }} members.
                    </p>
                    <a href="/" class="inline-block mt-4 px-6 py-2.5 bg-neutral-800 hover:bg-neutral-700 text-white text-sm font-bold rounded-lg transition-all">
                        Go to CsStrats
                    </a>
                </div>

                <!-- ─── STEP 1: Not logged in ─── -->
                <div v-else-if="!invite.is_auth && currentStep === 1">
                    <div class="p-8 border-b border-neutral-800">
                        <TeamPreview :team="invite.team" />
                    </div>
                    <div class="p-8 space-y-4">
                        <h2 class="text-lg font-bold text-white text-center">Sign in to join</h2>
                        <p class="text-neutral-400 text-sm text-center">You need a CsStrats account to accept this invite.</p>
                        <a
                            :href="`/login?redirect=/invite/${invite.token}`"
                            class="flex items-center justify-center gap-2 w-full py-3 bg-orange-500 hover:bg-orange-400 text-white text-sm font-bold rounded-lg transition-all shadow-lg shadow-orange-500/20"
                        >
                            Sign In
                        </a>
                    </div>
                </div>

                <!-- ─── STEP 2: Logged in — invite preview + role selection ─── -->
                <div v-else-if="currentStep === 2">
                    <div class="p-8 border-b border-neutral-800">
                        <TeamPreview :team="invite.team" />
                    </div>
                    <div class="p-8 space-y-6">
                        <div>
                            <p class="text-xs text-neutral-500 uppercase tracking-widest font-bold mb-3">Select your role</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="role in invite.roles"
                                    :key="role"
                                    class="flex items-center gap-2 px-4 py-3 rounded-xl border text-sm font-medium transition-all text-left"
                                    :class="selectedRole === role
                                        ? 'border-orange-500 bg-orange-500/10 text-white'
                                        : 'border-neutral-700 bg-neutral-800/50 text-neutral-400 hover:border-neutral-500'"
                                    @click="selectedRole = role"
                                >
                                    <span class="text-base">{{ roleIcon(role) }}</span>
                                    <span>{{ role }}</span>
                                </button>
                            </div>
                            <p class="text-xs text-neutral-600 mt-3">
                                Your role is cosmetic and can be changed later by a coach.
                            </p>
                        </div>

                        <button
                            class="w-full py-3 bg-orange-500 hover:bg-orange-400 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-black uppercase tracking-wider rounded-lg transition-all shadow-lg shadow-orange-500/20"
                            :disabled="!selectedRole || joining"
                            @click="joinTeam"
                        >
                            {{ joining ? 'Joining...' : `Join ${invite.team.name}` }}
                        </button>

                        <p v-if="error" class="text-red-400 text-xs text-center">{{ error }}</p>
                    </div>
                </div>

                <!-- ─── STEP 3: Success ─── -->
                <div v-else-if="currentStep === 3" class="p-10 text-center space-y-5">
                    <div class="relative inline-flex items-center justify-center">
                        <div class="w-20 h-20 rounded-full bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-4xl">
                            🎉
                        </div>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-white mb-2">You're in!</h2>
                        <p class="text-neutral-400 text-sm">
                            You've joined <span class="text-white font-semibold">{{ invite.team.name }}</span> as <span class="text-orange-400 font-semibold">{{ selectedRole }}</span>.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <a href="/team"
                            class="flex flex-col items-center gap-1.5 p-3 rounded-xl border border-neutral-800 bg-neutral-900/50 hover:border-orange-500/40 transition-all group">
                            <span class="text-lg">👥</span>
                            <span class="text-[10px] text-neutral-500 group-hover:text-neutral-300 font-medium transition-colors">View Team</span>
                        </a>
                        <a href="/tactics"
                            class="flex flex-col items-center gap-1.5 p-3 rounded-xl border border-neutral-800 bg-neutral-900/50 hover:border-orange-500/40 transition-all group">
                            <span class="text-lg">⚡</span>
                            <span class="text-[10px] text-neutral-500 group-hover:text-neutral-300 font-medium transition-colors">Browse Tactics</span>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Step indicator (only for logged-in flow) -->
            <div v-if="invite.is_auth && invite.team && !invite.already_member && !invite.team.is_full"
                class="flex justify-center gap-2 mt-4">
                <div v-for="i in 2" :key="i"
                    class="transition-all duration-300 rounded-full"
                    :class="i === currentStep - 1 || (currentStep === 3 && i === 2)
                        ? 'w-6 h-2 bg-orange-500'
                        : 'w-2 h-2 bg-neutral-700'" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps<{ invite: any }>()

const currentStep = ref(props.invite.is_auth ? 2 : 1)
const selectedRole = ref<string | null>(null)
const joining = ref(false)
const error = ref<string | null>(null)

// Track funnel event
onMounted(() => {
    try {
        if (window.UST?.addTag) window.UST.addTag('event:invite:viewed')
    } catch {}
})

function roleIcon(role: string): string {
    const icons: Record<string, string> = {
        'IGL':       '🎙️',
        'Entry':     '🔫',
        'AWP':       '🎯',
        'Support':   '🛡️',
        'Lurker':    '👻',
        'Player':    '🎮',
        'Stand-in':  '⏱️',
    }
    return icons[role] ?? '🎮'
}

async function joinTeam() {
    if (!selectedRole.value || joining.value) return
    joining.value = true
    error.value = null

    try {
        await axios.post(`/api/invite/${props.invite.token}/accept`, {
            role: selectedRole.value,
        })
        currentStep.value = 3

        // Track in UXWizz
        try {
            if (window.UST?.addTag) window.UST.addTag('event:team:joined_via_invite')
        } catch {}

        // Track in Grafana funnel endpoint
        try {
            axios.post('/api/metrics/event', { event: 'team:joined_via_invite' })
        } catch {}

    } catch (err: any) {
        const msg = err?.response?.data?.message
        if (msg === 'already_member') {
            currentStep.value = 3
        } else {
            error.value = msg || 'Something went wrong. Please try again.'
        }
    } finally {
        joining.value = false
    }
}
</script>

<script lang="ts">
// TeamPreview sub-component defined inline
import { defineComponent, h } from 'vue'

const TeamPreview = defineComponent({
    props: { team: Object },
    setup(props) {
        return () => h('div', { class: 'flex items-center gap-4' }, [
            // Logo / avatar
            h('div', {
                class: 'w-16 h-16 rounded-2xl bg-neutral-800 border border-neutral-700 flex items-center justify-center overflow-hidden flex-shrink-0'
            }, [
                props.team?.logo
                    ? h('img', { src: props.team.logo, class: 'w-full h-full object-cover' })
                    : h('span', { class: 'text-2xl font-black text-orange-500' },
                        props.team?.name?.[0]?.toUpperCase() ?? '?')
            ]),
            // Info
            h('div', { class: 'flex-1 min-w-0' }, [
                h('p', { class: 'text-xs text-neutral-500 uppercase tracking-widest font-bold mb-0.5' },
                    `${props.team?.owner_name} invited you to`),
                h('h3', { class: 'text-xl font-black text-white truncate' }, props.team?.name),
                h('p', { class: 'text-xs text-neutral-500 mt-1' },
                    `${props.team?.member_count} / ${props.team?.max_members} members`),
            ])
        ])
    }
})

export default { components: { TeamPreview } }
</script>