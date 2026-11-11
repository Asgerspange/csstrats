<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useScrollReveal } from './composables/useScrollReveal'
import Container from './primitives/Container.vue'
import Button from './primitives/Button.vue'
import DemoViewerEmbed from './primitives/DemoViewerEmbed.vue'
import WelcomePartners from './sections/WelcomePartners.vue'
import WelcomePricing from './sections/WelcomePricing.vue'
import WelcomeFooter from './sections/WelcomeFooter.vue'
import { captureReferral } from '@/composables/useReferral'
import { useLandingData } from './useLandingData'

// The HLTV offer is page-scoped here (pricing below renders in HLTV mode
// because of the path). It only PERSISTS to other pages when the visitor
// actually arrived with utm_source=hltv.
captureReferral()
useScrollReveal()

const { user } = useLandingData()

const allowAutoplay = ref(false)
const videoEl = ref<HTMLVideoElement | null>(null)

onMounted(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    allowAutoplay.value = !reduced
    if (reduced && videoEl.value) {
        videoEl.value.pause()
    }
})
</script>

<template>
    <div class="relative min-h-screen bg-concrete text-chalk antialiased">
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-[var(--radius-sm)] focus:bg-saffron focus:px-4 focus:py-2 focus:font-mono focus:text-xs focus:font-medium focus:uppercase focus:tracking-[0.08em] focus:text-concrete"
        >
            Skip to content
        </a>

        <!-- Single-purpose header: wordmark home link, nothing competing with the CTA -->
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
            <section class="relative overflow-hidden pt-20 pb-16 sm:pt-28 sm:pb-24">
                <Container>
                    <div class="grid gap-16 lg:grid-cols-[7fr_5fr] lg:items-end lg:gap-20">
                        <div class="flex flex-col gap-8">
                            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-saffron" data-reveal>
                                <span aria-hidden="true">●</span> HLTV reader offer
                            </p>

                            <h1
                                class="max-w-[18ch] font-display text-[clamp(2.5rem,6vw,4.5rem)] font-medium leading-[1.04] tracking-[-0.025em] text-chalk"
                                data-reveal
                            >
                                The teams you read about prepare. Yours can too.
                            </h1>

                            <p class="max-w-[56ch] text-lg text-bone" data-reveal>
                                CsStrats is the working surface for competitive CS2 teams. A curated library of Tier 1 tactics updated weekly, a collaborative stratbook and board, and a demo viewer that parses every round of your scrims.
                            </p>

                            <p
                                class="max-w-[58ch] font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone tabular-nums"
                                data-reveal
                            >
                                <span class="text-chalk">30% off Monthly for 3 months</span> · code HLTV30 applied at checkout · team of up to 8 · ≈ €2.18 / player / month while the offer runs
                            </p>

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center" data-reveal>
                                <Button as="a" href="#pricing" variant="primary" size="lg">
                                    Get access
                                </Button>
                                <Button as="a" href="#sample" variant="ghost" size="lg">
                                    See a parsed demo
                                </Button>
                            </div>
                        </div>

                        <figure class="flex flex-col gap-3" data-reveal>
                            <header class="flex items-baseline justify-between">
                                <p class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone">
                                    Clip · 00:14:32 · de_mirage
                                </p>
                                <p class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone tabular-nums">
                                    R12 / 24
                                </p>
                            </header>
                            <div class="relative aspect-video overflow-hidden rounded-[var(--radius-md)] border border-ash bg-slate">
                                <video
                                    ref="videoEl"
                                    src="/appvid.webm"
                                    class="h-full w-full object-cover"
                                    :autoplay="allowAutoplay"
                                    muted
                                    loop
                                    playsinline
                                    preload="metadata"
                                    aria-label="Looping clip of the CsStrats interface"
                                />
                            </div>
                            <figcaption class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                                The demo viewer, as your team sees it
                            </figcaption>
                        </figure>
                    </div>
                </Container>
            </section>

            <Container>
                <WelcomePartners include-hltv />
            </Container>

            <!-- Proof before the ask: a real parsed match, scrubbable in place -->
            <section id="sample" class="mt-16 pb-16 sm:mt-24 sm:pb-24" data-reveal-group>
                <Container>
                    <div class="mb-8 flex flex-col gap-4" data-reveal>
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-saffron">
                            <span aria-hidden="true">●</span> Don't take our word for it
                        </p>
                        <h2 class="max-w-[26ch] font-display text-[clamp(1.75rem,3.5vw,2.5rem)] font-medium leading-[1.1] tracking-[-0.02em] text-chalk">
                            Scrub a real pro match, right here.
                        </h2>
                    </div>
                    <div data-reveal>
                        <DemoViewerEmbed :height="600" />
                    </div>
                </Container>
            </section>

            <WelcomePricing />
        </main>

        <WelcomeFooter />
    </div>
</template>
