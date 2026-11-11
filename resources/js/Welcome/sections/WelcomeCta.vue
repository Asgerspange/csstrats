<script setup lang="ts">
import Container from '../primitives/Container.vue'
import Button from '../primitives/Button.vue'
import Eyebrow from '../primitives/Eyebrow.vue'
import { useLandingData } from '../useLandingData'

const { user } = useLandingData()

// Folded in from the old standalone Workflow section: the demo → call loop,
// now a compact rail that leads straight into the closing CTA.
const steps = [
    { img: 'https://csstrats.b-cdn.net/features/tactics/billede_2026-08-02_040245753.png', title: 'Find a tactic.', body: 'Search our library of professional tactics, or create your own from scratch.' },
    { img: 'https://csstrats.b-cdn.net/features/stratbuilder/billede_2026-08-02_213010708.png', title: 'Visualize the tactic.', body: 'Scrub the round, tag where the rotation broke, drop a callout on the geometry.' },
    { img: 'https://csstrats.b-cdn.net/features/stratbook/billede_2026-08-02_213153959.png', title: 'Run it in your match.', body: 'The stratbook is ready when you need it. No more Google Sheets.' },
]
</script>

<template>
    <section id="workflow" class="relative overflow-hidden py-16 sm:py-20 lg:py-24" data-reveal-group>
        <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true">
            <img
                src="/images/demo/de_overpass.svg"
                alt=""
                loading="lazy"
                decoding="async"
                class="absolute -left-60 top-0 w-[1100px] max-w-none"
            />
        </div>

        <Container>
            <div class="flex flex-col gap-12 border-t border-ash pt-16 sm:pt-20">
                <!-- <header class="flex flex-col gap-6">
                    <Eyebrow label="How it works" />
                    <h2
                        class="max-w-[24ch] font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                        data-reveal
                    >
                        From demo to call, in one afternoon.
                    </h2>
                </header> -->

                <!-- Compact step rail -->
                <ol class="grid gap-6 sm:gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" data-reveal>
                    <li v-for="step in steps" class="text-center flex flex-col">
                        <div class="flex min-h-[164px] flex-col justify-center gap-3 bg-[#ffffff] p-4 sm:p-6 rounded-2xl shadow-sm flex-1">
                            <img :src="step.img" alt="" class="mx-auto h-40 sm:h-56 w-full rounded-lg object-cover" />
                        </div>
                        <div class="max-w-xs text-center mx-auto mt-3 sm:mt-4 flex flex-col gap-2">
                            <h3 class="font-display text-lg sm:text-xl font-medium tracking-[-0.01em] text-chalk">{{ step.title }}</h3>
                            <p class="text-xs sm:text-sm text-bone">{{ step.body }}</p>
                        </div>
                    </li>
                </ol>

                <!-- Closing CTA -->
                <div class="flex flex-col gap-6 border-t border-ash pt-12 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex max-w-[44ch] flex-col gap-4">
                        <h2
                            class="font-display text-[clamp(1.75rem,3.5vw,2.5rem)] font-medium leading-[1.1] tracking-[-0.02em] text-chalk"
                            data-reveal
                        >
                            Build the stratbook your team needs.
                        </h2>
                    </div>

                    <div class="flex flex-shrink-0 flex-col gap-4 sm:flex-row sm:items-center" data-reveal>
                        <Button as="a" :href="user ? '/profile' : '/register'" variant="primary" size="lg">
                            {{ user ? 'Open your dashboard' : 'Get started' }}
                        </Button>
                        <Button as="a" href="https://discord.gg/Rmp5p4r7CT" variant="ghost" size="lg">
                            Join the Discord
                        </Button>
                    </div>
                </div>
            </div>
        </Container>
    </section>
</template>
