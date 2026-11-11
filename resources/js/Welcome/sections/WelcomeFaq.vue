<script setup lang="ts">
import Container from '../primitives/Container.vue'
import Eyebrow from '../primitives/Eyebrow.vue'

// Questions are kept in sync with the FAQPage JSON-LD in
// resources/views/layouts/wlayout.blade.php — answers must match for the
// schema to validate against the rendered page.
const faqs = [
    {
        q: 'What is CsStrats?',
        a: 'CsStrats is a web app for competitive Counter-Strike 2 teams. It pairs a curated library of Tier 1 and Tier 2 pro tactics with a collaborative Strat Builder, team stratbooks, and a .dem demo viewer with 2D and 3D playback. Built in Odense by IS Digital ApS.',
    },
    {
        q: 'Is there a free plan or trial?',
        a: 'No. CsStrats is a paid product for competitive teams. Pick a plan to get full access: every feature, every map, the whole stack. Plans cancel any time from your profile.',
    },
    {
        q: 'Which games does CsStrats support?',
        a: 'CsStrats is built for Counter-Strike 2 (CS2). The demo parser accepts .dem files from CS2 matchmaking, FACEIT, ESEA, and tournament servers.',
    },
    {
        q: 'How do I analyse a demo?',
        a: 'Drop a .dem file or paste a FACEIT match URL. CsStrats parses the match in the background, surfaces every round on a timeline, and lets you scrub through kills, plants, defuses, and utility pops in 2D or 3D. Round-by-round annotations and team callouts attach to the map geometry.',
    },
    {
        q: 'How much does CsStrats cost?',
        a: 'Team plans start at €15.83 per month billed annually (€190 per year), €19.95 per month billed every six months, or €24.95 per month on the rolling monthly plan. Every plan covers up to eight team members, unlimited tactics, and unlimited demos. VAT is added where applicable.',
    },
    {
        q: 'Can I cancel any time?',
        a: 'Yes. All plans can be cancelled at any time from your profile. The subscription stays active until the end of the paid period. Promo codes from tournaments apply at checkout and then auto-renew at the regular rate every billing cycle until you cancel.',
    },
    {
        q: 'What is the Strat Builder?',
        a: 'The Strat Builder is a collaborative 2D map tool inside CsStrats. Pick a player slot, draw a timed path with run and walk speeds, schedule utility (smoke, flash, HE, molotov) with throw and land positions, and play the round back from start to finish.',
    },
    {
        q: 'Who is CsStrats for?',
        a: 'CsStrats is built for IGLs, coaches, and managers running competitive CS2 teams worldwide, from amateur and semi-pro to academy rosters. Serious individual players also use it to analyse their own demos before promotion games.',
    },
]
</script>

<template>
    <section id="faq" class="relative py-16 sm:py-20 lg:py-24" data-reveal-group>
        <Container>
            <div class="grid gap-16 lg:grid-cols-[5fr_7fr] lg:gap-24">
                <header class="flex flex-col gap-6 lg:sticky lg:top-32 lg:self-start">
                    <Eyebrow index="07" label="FAQ" />
                    <h2
                        class="font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                        data-reveal
                    >
                        Questions worth
                        answering up front.
                    </h2>
                    <p class="max-w-[42ch] text-bone" data-reveal>
                        Short answers to the things IGLs ask before they sign up. Anything else, the team replies on Discord within the hour.
                    </p>
                </header>

                <div class="flex flex-col" data-reveal>
                    <details
                        v-for="(faq, i) in faqs"
                        :key="faq.q"
                        class="group border-t border-ash py-5 last:border-b"
                        :open="i === 0"
                    >
                        <summary class="flex cursor-pointer items-center justify-between gap-4 list-none">
                            <h3 class="font-display text-lg font-medium text-chalk lg:text-xl">
                                {{ faq.q }}
                            </h3>
                            <span
                                class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-[var(--radius-xs)] border border-ash text-bone transition-transform duration-[180ms] ease-[var(--ease-quart)] group-open:rotate-45 group-open:border-saffron group-open:text-saffron"
                                aria-hidden="true"
                            >
                                <svg width="12" height="12" viewBox="0 0 12 12">
                                    <path d="M6 1v10M1 6h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" />
                                </svg>
                            </span>
                        </summary>
                        <p class="mt-3 max-w-[62ch] pr-12 text-bone">
                            {{ faq.a }}
                        </p>
                    </details>
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
summary::-webkit-details-marker {
    display: none;
}
</style>
