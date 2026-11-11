<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import useUser from '@/composables/useUser'
import useTeams from '@/composables/useTeams'
import useDemos from '@/composables/useDemos'
import useStratbooks from '@/composables/useStratbooks'

// First-session checklist for the team owner. A team that invites its
// roster, uploads a demo, and fills a stratbook in week one is a team
// that renews; this card exists to make those three things happen.

const router = useRouter()

const { User } = useUser()
const { Teams } = useTeams()
const { data: user } = User.get()
const { data: team } = Teams.find()
const { data: demos } = useDemos().Demos.get(false)
const { data: stratbooks } = useStratbooks().Stratbooks.get()

const locallyDismissed = ref(false)

const isOwner = computed(() => !!user.value?.id && team.value?.owned_by === user.value.id)
const dismissed = computed(
    () => locallyDismissed.value || user.value?.settings?.captain_checklist_dismissed === true,
)

const items = computed(() => [
    {
        key: 'roster',
        label: 'Invite your roster',
        hint: 'The Board is more useful with two cursors on it.',
        done: (team.value?.users?.length ?? 0) > 1,
        go: () => router.push({ path: '/team', query: { tab: 'roster' } }),
    },
    {
        key: 'demo',
        label: 'Upload your last scrim demo',
        hint: 'Every round on a timeline, in a few minutes.',
        done: (demos.value?.length ?? 0) > 0,
        go: () => router.push({ path: '/demos' }),
    },
    {
        key: 'tactic',
        label: 'Add a tactic to your playbook',
        hint: 'Pick one from the library, make it yours.',
        done: (stratbooks.value ?? []).some(
            (sb: any) => (sb.tactics_count ?? sb.tactics?.length ?? 0) > 0,
        ),
        go: () => router.push({ path: '/tactics' }),
    },
])

const doneCount = computed(() => items.value.filter((i) => i.done).length)
const allDone = computed(() => doneCount.value === items.value.length)

const show = computed(
    () => isOwner.value && !dismissed.value && !allDone.value && !!team.value,
)

const dismiss = async () => {
    locallyDismissed.value = true
    try {
        await axios.post('/api/user/settings/dismiss-checklist')
        await User.refresh()
    } catch {
        // Local dismissal already hides it for this session.
    }
}
</script>

<template>
    <section
        v-if="show"
        class="mb-6 rounded-xl border border-white/[0.08] bg-white/[0.03] p-4 md:p-5 shrink-0"
        aria-label="Getting started checklist"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-primary tabular-nums">
                    Get your team in · {{ doneCount }} / {{ items.length }}
                </p>
                <p class="text-xs text-white/40">
                    Three steps between you and your first prepared scrim.
                </p>
            </div>
            <button
                @click="dismiss"
                class="text-[11px] font-semibold text-white/25 transition-colors hover:text-white/60"
                title="Hide this checklist permanently"
            >
                Dismiss
            </button>
        </div>

        <ul class="mt-4 grid gap-2 md:grid-cols-3">
            <li v-for="item in items" :key="item.key">
                <button
                    @click="!item.done && item.go()"
                    class="flex w-full items-start gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors"
                    :class="item.done
                        ? 'border-white/[0.04] bg-white/[0.02] cursor-default'
                        : 'border-white/[0.08] hover:border-primary/40 hover:bg-white/[0.04]'"
                    :disabled="item.done"
                >
                    <span
                        class="material-symbols mt-0.5 text-[16px]"
                        :class="item.done ? 'text-primary' : 'text-white/25'"
                    >
                        {{ item.done ? 'check_circle' : 'radio_button_unchecked' }}
                    </span>
                    <span class="flex flex-col gap-0.5">
                        <span
                            class="text-xs font-semibold"
                            :class="item.done ? 'text-white/35 line-through' : 'text-white/85'"
                        >
                            {{ item.label }}
                        </span>
                        <span class="text-[11px] text-white/30">{{ item.hint }}</span>
                    </span>
                </button>
            </li>
        </ul>
    </section>
</template>
