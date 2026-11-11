<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import useTeams from '@/composables/useTeams'

// "3 of 8 seats" under the team badge. Empty seats are the earliest
// churn signal a team plan has; this keeps them visible to the captain.

const { Teams } = useTeams()
const { data: team } = Teams.find()

const memberCount = computed(() => team.value?.users?.length ?? 0)
const maxMembers = computed(() => team.value?.max_members ?? 0)
const freeSeats = computed(() => Math.max(0, maxMembers.value - memberCount.value))
const show = computed(() => maxMembers.value > 0)
</script>

<template>
    <RouterLink
        v-if="show"
        to="/team?tab=roster"
        class="mx-2 mb-1 flex items-center justify-between gap-2 rounded-md px-2 py-1.5 transition-colors hover:bg-white/[0.04]"
        :title="freeSeats > 0 ? `${freeSeats} free seat${freeSeats === 1 ? '' : 's'} on your plan. Invite your roster.` : 'Roster full'"
    >
        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-white/25 sidebar-fade sidebar-seats">
            Seats
        </span>
        <span class="flex items-center gap-1.5">
            <span class="text-[11px] font-semibold tabular-nums" :class="freeSeats > 0 ? 'text-white/50' : 'text-white/30'">
                {{ memberCount }} / {{ maxMembers }}
            </span>
            <span
                v-if="freeSeats > 0"
                class="text-[10px] font-semibold text-primary sidebar-fade seat-invite"
            >
                Invite
            </span>
        </span>
    </RouterLink>
</template>
