<script setup lang="ts">
import { computed } from 'vue'
import Container from '../primitives/Container.vue'
import Button from '../primitives/Button.vue'
import WelcomePartners from './WelcomePartners.vue'
import { useLandingData } from '../useLandingData'
import { getReferral } from '@/composables/useReferral'

const { user, tactics, demos } = useLandingData()
const isHltv = getReferral() === 'hltv'

const primaryCta = computed(() => {
    if (user.value) return { label: 'Open the builder', href: '/strat-builder' }
    return { label: 'Start a stratbook', href: '/register' }
})

const secondaryCta = computed(() => {
    if (user.value) return { label: 'Browse demos', href: '/demos' }
    return { label: 'See a parsed demo', href: '#demoviewer' }
})

// Live library counts double as proof. Falls back to the standing claim
// while the landing query loads (or if it ever fails).
const fmt = (n: number) => n.toLocaleString('en-US')
const statsLine = computed(() => {
    if (tactics.value > 0 && demos.value > 0) {
        return `${fmt(tactics.value)} pro tactics · ${fmt(demos.value)} demos parsed · updated weekly`
    }
    return 'Tier 1 pro tactics · every demo parsed · updated weekly'
})
</script>

<template>
    <section class="relative overflow-hidden pt-36 pb-28 sm:pt-44 sm:pb-36 lg:pt-52 lg:pb-44">
        <Container>
            <div class="mx-auto flex max-w-[58rem] flex-col items-center gap-9 text-center">
                <h1
                    class="max-w-[18ch] text-balance font-display text-[clamp(2.75rem,7.5vw,5.75rem)] font-medium leading-[1.0] tracking-[-0.03em] text-chalk"
                    data-reveal
                >
                    The room where the
                    <span class="text-saffron">strats</span> live.
                </h1>

                <p
                    class="max-w-[52ch] text-pretty text-lg text-bone sm:text-xl"
                    data-reveal
                >
                    CsStrats is the working surface for competitive CS2 teams. Browse Tier 1 tactics, parse every demo, write the call before the server boots. One tool, the way the IGL already wished it worked.
                </p>

                <p
                    class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone tabular-nums"
                    data-reveal
                >
                    <span class="text-chalk">{{ statsLine }}</span>
                    <span v-if="isHltv" class="block pt-1 text-saffron sm:inline sm:pt-0"> · HLTV offer: 30% off Monthly for 3 months, applied at checkout</span>
                </p>

                <div class="flex flex-col items-stretch gap-4 pt-2 sm:flex-row sm:items-center sm:justify-center" data-reveal>
                    <Button as="a" :href="primaryCta.href" variant="primary" size="lg">
                        {{ primaryCta.label }}
                    </Button>
                    <Button as="a" :href="secondaryCta.href" variant="ghost" size="lg">
                        {{ secondaryCta.label }}
                    </Button>
                </div>

                <div class="pt-6" data-reveal>
                    <WelcomePartners center />
                </div>
            </div>
        </Container>
    </section>
</template>
