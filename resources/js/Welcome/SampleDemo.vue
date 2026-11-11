<script setup lang="ts">
import { useScrollReveal } from './composables/useScrollReveal'
import Container from './primitives/Container.vue'
import Button from './primitives/Button.vue'
import DemoViewerEmbed from './primitives/DemoViewerEmbed.vue'
import WelcomeFooter from './sections/WelcomeFooter.vue'
import { captureReferral } from '@/composables/useReferral'
import { useLandingData } from './useLandingData'

// Banner deep-links land here with utm_source=hltv; capture before render
// so a later visit to pricing still carries the offer.
captureReferral()
useScrollReveal()

const { user } = useLandingData()
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
                            <span aria-hidden="true">●</span> Sample · parsed match
                        </p>
                        <h1
                            class="max-w-[24ch] font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                            data-reveal
                        >
                            This is what a parsed demo looks like.
                        </h1>
                        <p class="max-w-[58ch] text-lg text-bone" data-reveal>
                            A real pro match, parsed by CsStrats. Scrub any round, follow any player, watch the utility land. Your scrims look exactly like this inside.
                        </p>
                    </div>
                </Container>
            </section>

            <section class="pb-16 sm:pb-24">
                <Container>
                    <DemoViewerEmbed :height="600" />

                    <div class="mt-10 flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between" data-reveal>
                        <p class="max-w-[48ch] text-bone">
                            Upload your own .dem files and get every round on a timeline, with annotations your whole team can see.
                        </p>
                        <Button as="a" :href="user ? '/#pricing' : '/register'" variant="primary" size="lg">
                            Get this for your team
                        </Button>
                    </div>
                </Container>
            </section>
        </main>

        <WelcomeFooter />
    </div>
</template>
