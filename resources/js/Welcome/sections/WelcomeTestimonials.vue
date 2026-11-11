<script setup lang="ts">
import Container from '../primitives/Container.vue'
import Eyebrow from '../primitives/Eyebrow.vue'

interface Testimonial {
    author: string
    team: string
    role?: string
    content: string
}

const featured: Testimonial = {
    author: 'Rennie',
    team: 'Sørby Riot',
    role: 'IGL',
    content:
        'CsStrats has really helped me out as a new IGL. The overview of the stratbook is solid, finding and creating strats is fast, and the support is direct contact to the developers. We use it every week.',
}

const supporting: Testimonial[] = [
    {
        author: 'M4JOR',
        team: 'Flaming North',
        role: 'Coach',
        content:
            'My team has gotten a lot of structure integrated into our pracs. We prepare much easier beforehand. I will recommend it to anyone running a tier 2 roster.',
    },
    {
        author: 'Numo',
        team: 'Evo Novo',
        role: 'Manager',
        content:
            'I can\'t imagine practicing without this tool. My players think it\'s great, and as a manager I rely on the structure it gives us.',
    },
    {
        author: 'Frosty',
        team: 'Nordic Titans',
        role: 'Player',
        content:
            'Planning strats, reviewing pracs, sharing feedback. Smooth, reliable, and keeps the team in one place. Must-have for serious squads.',
    },
    {
        author: 'Wambo',
        team: 'Euphoria & Unsigned Emerge',
        role: 'Coach',
        content:
            'Great structure, easy strats to come by, and a nice way to set up your own. Support will do everything they can to make your life on the site easier.',
    },
]
</script>

<template>
    <section id="testimonials" class="relative py-16 sm:py-20 lg:py-24" data-reveal-group>
        <Container>
            <header class="mb-16 flex flex-col gap-6 lg:mb-20">
                <Eyebrow index="06" label="Who's using it" />
                <h2
                    class="max-w-[28ch] font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                    data-reveal
                >
                    Teams run their week
                    through it.
                </h2>
            </header>

            <div class="grid gap-px overflow-hidden rounded-[var(--radius-md)] border border-ash bg-ash lg:grid-cols-[7fr_5fr]">
                <!-- Featured large quote -->
                <figure class="flex flex-col gap-8 bg-slate p-10 lg:p-12" data-reveal>
                    <p class="font-display text-2xl font-medium leading-[1.3] tracking-[-0.01em] text-chalk lg:text-3xl">
                        &ldquo;{{ featured.content }}&rdquo;
                    </p>
                    <figcaption class="flex flex-col gap-1 border-t border-ash pt-6">
                        <p class="font-display text-lg font-medium text-chalk">{{ featured.author }}</p>
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-saffron">
                            {{ featured.role }} · {{ featured.team }}
                        </p>
                    </figcaption>
                </figure>

                <!-- Supporting quotes column -->
                <div class="grid grid-rows-2 gap-px bg-ash">
                    <figure
                        v-for="t in supporting.slice(0, 2)"
                        :key="t.author"
                        class="flex flex-col gap-4 bg-slate p-8"
                        data-reveal
                    >
                        <p class="text-bone leading-[1.5]">&ldquo;{{ t.content }}&rdquo;</p>
                        <figcaption class="flex flex-col gap-1 pt-2">
                            <p class="font-display font-medium text-chalk">{{ t.author }}</p>
                            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                                {{ t.role }} · {{ t.team }}
                            </p>
                        </figcaption>
                    </figure>
                </div>
            </div>

            <!-- Lower row: two more, tighter -->
            <div class="mt-px grid gap-px overflow-hidden rounded-[var(--radius-md)] border border-ash bg-ash lg:grid-cols-2">
                <figure
                    v-for="t in supporting.slice(2)"
                    :key="t.author"
                    class="flex flex-col gap-4 bg-slate p-8"
                    data-reveal
                >
                    <p class="text-bone leading-[1.5]">&ldquo;{{ t.content }}&rdquo;</p>
                    <figcaption class="flex flex-col gap-1 pt-2">
                        <p class="font-display font-medium text-chalk">{{ t.author }}</p>
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                            {{ t.role }} · {{ t.team }}
                        </p>
                    </figcaption>
                </figure>
            </div>
        </Container>
    </section>
</template>
