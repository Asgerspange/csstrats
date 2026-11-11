<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useScrollReveal } from './composables/useScrollReveal'
import Container from './primitives/Container.vue'
import Button from './primitives/Button.vue'
import WelcomePricing from './sections/WelcomePricing.vue'
import WelcomeFooter from './sections/WelcomeFooter.vue'
import { captureReferral } from '@/composables/useReferral'
import { useLandingData } from './useLandingData'

interface WeeklyTactic {
    id: number
    team_name: string | null
    map: string | null
    side: string | null
    type: string | null
    description: string | null
    video: string | null
    release_date: string | null
}

interface LockedTactic {
    id: number
    team_name: string | null
    map: string | null
    side: string | null
    type: string | null
    release_date: string | null
}

captureReferral()
useScrollReveal()

const { user } = useLandingData()

const { data } = useQuery({
    queryKey: ['weeklyTactic'],
    queryFn: async (): Promise<{ tactic: WeeklyTactic | null; drop: LockedTactic[] }> => {
        const res = await fetch('/tactics/weekly-data', { headers: { Accept: 'application/json' } })
        if (!res.ok) throw new Error('Failed to load weekly tactic')
        return res.json()
    },
    staleTime: 1000 * 60 * 10,
})

const tactic = computed(() => data.value?.tactic ?? null)
const drop = computed(() => data.value?.drop ?? [])

// Tactic videos are stored as YouTube links; render via the nocookie host.
const youtubeId = computed(() => {
    const url = tactic.value?.video ?? ''
    const match = url.match(/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/)
    return match?.[1] ?? null
})

const releasedAt = (iso: string | null) => {
    if (!iso) return ''
    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}
</script>

<template>
    <div class="relative min-h-screen bg-concrete text-chalk antialiased">
        <header class="border-b border-ash">
            <Container>
                <div class="flex h-16 items-center justify-between">
                    <a href="/" class="font-display text-lg font-medium tracking-[-0.01em] text-chalk">
                        cs<span class="text-saffron">strats</span>
                    </a>
                    <a
                        v-if="!user"
                        href="/login"
                        class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone transition-colors duration-[180ms] hover:text-chalk"
                    >
                        Log in
                    </a>
                </div>
            </Container>
        </header>

        <main id="main">
            <section class="relative pt-16 pb-12 sm:pt-20 sm:pb-16">
                <Container>
                    <div class="flex flex-col gap-6">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-saffron" data-reveal>
                            <span aria-hidden="true">●</span> Tactic of the week
                            <template v-if="tactic">
                                · {{ tactic.map }}<template v-if="tactic.side"> · {{ tactic.side }}</template>
                            </template>
                        </p>
                        <h1
                            class="max-w-[26ch] font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                            data-reveal
                        >
                            <template v-if="tactic">
                                {{ tactic.team_name ? `How ${tactic.team_name} ran it.` : 'They ran it on LAN. We wrote it down.' }}
                            </template>
                            <template v-else>
                                They ran it on LAN. We wrote it down.
                            </template>
                        </h1>
                        <p v-if="tactic?.description" class="max-w-[62ch] text-lg text-bone" data-reveal>
                            {{ tactic.description }}
                        </p>
                    </div>
                </Container>
            </section>

            <section v-if="youtubeId" class="pb-16 sm:pb-20">
                <Container>
                    <div class="overflow-hidden rounded-[var(--radius-md)] border border-ash bg-slate" data-reveal>
                        <div class="aspect-video w-full">
                            <iframe
                                :src="`https://www.youtube-nocookie.com/embed/${youtubeId}`"
                                class="h-full w-full"
                                title="Tactic of the week video breakdown"
                                frameborder="0"
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        </div>
                    </div>
                </Container>
            </section>

            <!-- The rest of this week's drop, visibly locked. The list does the selling. -->
            <section v-if="drop.length" class="pb-16 sm:pb-24" data-reveal-group>
                <Container>
                    <div class="mb-8 flex flex-col gap-3" data-reveal>
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-bone">
                            Also in this week's drop
                        </p>
                        <h2 class="max-w-[28ch] font-display text-[clamp(1.5rem,3vw,2.25rem)] font-medium leading-[1.1] tracking-[-0.02em] text-chalk">
                            {{ drop.length }} more tactics, waiting inside.
                        </h2>
                    </div>

                    <ul class="overflow-hidden rounded-[var(--radius-md)] border border-ash" data-reveal>
                        <li
                            v-for="locked in drop"
                            :key="locked.id"
                            class="flex items-center justify-between gap-4 border-b border-ash bg-slate px-5 py-4 last:border-b-0"
                        >
                            <div class="flex min-w-0 items-baseline gap-4">
                                <span class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-saffron">
                                    {{ locked.map }}
                                </span>
                                <span class="truncate text-sm text-bone">
                                    {{ locked.team_name ?? 'Pro tactic' }}<template v-if="locked.type"> · {{ locked.type }}</template><template v-if="locked.side"> · {{ locked.side }}</template>
                                </span>
                            </div>
                            <div class="flex flex-shrink-0 items-center gap-4">
                                <span class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone tabular-nums">
                                    {{ releasedAt(locked.release_date) }}
                                </span>
                                <span class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone" aria-label="Locked">
                                    Locked
                                </span>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-8 flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between" data-reveal>
                        <p class="max-w-[48ch] text-bone">
                            The library updates every week with executes pulled from Tier 1 and Tier 2 matches. One is free to study. The rest come with the plan.
                        </p>
                        <Button as="a" :href="user ? '#pricing' : '/register'" variant="primary" size="lg">
                            Unlock the full library
                        </Button>
                    </div>
                </Container>
            </section>

            <WelcomePricing />
        </main>

        <WelcomeFooter />
    </div>
</template>
