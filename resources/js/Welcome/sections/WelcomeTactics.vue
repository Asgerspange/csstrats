<script setup lang="ts">
import { ref } from 'vue'
import Container from '../primitives/Container.vue'
import Eyebrow from '../primitives/Eyebrow.vue'

interface Tactic {
    name: string
    description: string
    side: 'T' | 'CT'
    type: string
    map: string
    team: string
    tags: Array<{ label: string; tone: 'site-a' | 'site-b' | 'speed' | 'mood' }>
}

const tactics: Tactic[] = [
    {
        name: 'Astralis CT setup Inferno',
        description: 'Taking the deep control of Banana, Astralis gains a lot of map control and wins the round',
        side: 'CT', type: 'CT setup', map: 'Inferno', team: 'Astralis',
        tags: [{ label: 'B', tone: 'site-b' }, { label: 'Aggressive', tone: 'mood' }],
    },
    {
        name: 'Astralis CT setup Inferno',
        description: 'Dropping off some extra utility at B, to make their opponent think there are 2 players there, to then have 4 players ready for a Fast A hit',
        side: 'CT', type: 'CT setup', map: 'Inferno', team: 'Astralis',
        tags: [{ label: 'Defensive', tone: 'mood' }, { label: 'A', tone: 'site-a' }],
    },
    {
        name: 'Vitality T Execute Mirage',
        description: 'Taking fast control of Middle, locking it down and getting full access to the B site',
        side: 'T', type: 'Execute', map: 'Mirage', team: 'Vitality',
        tags: [{ label: 'B', tone: 'site-b' }, { label: 'Aggressive', tone: 'mood' }],
    },
    {
        name: 'Vitality T Anti-eco Mirage',
        description: 'Taking control of Middle fast, and holds it down, waiting for any CT aggression, before taking the fights themselves',
        side: 'T', type: 'Anti-eco', map: 'Mirage', team: 'Vitality',
        tags: [{ label: 'Aggressive', tone: 'mood' }, { label: 'Fast', tone: 'speed' }],
    },
    {
        name: 'Vitality CT Forcebuy Mirage',
        description: 'Pushing Middle early, Vitality catches their opponents off guard and wins 2 early frags, helping them to win the round',
        side: 'CT', type: 'Forcebuy', map: 'Mirage', team: 'Vitality',
        tags: [{ label: 'Aggressive', tone: 'mood' }, { label: 'Fast', tone: 'speed' }],
    },
    {
        name: 'Astralis T Execute Mirage',
        description: 'Making early presence on Middle before wrapping around the A site, with players from Ramp and Middle',
        side: 'T', type: 'Execute', map: 'Mirage', team: 'Astralis',
        tags: [{ label: 'A', tone: 'site-a' }, { label: 'Slow', tone: 'speed' }],
    },
    {
        name: 'Vitality T Execute Mirage',
        description: 'Taking Middle early and putting pressure on the CT side, apEX falls back to support the A execute.',
        side: 'T', type: 'Execute', map: 'Mirage', team: 'Vitality',
        tags: [{ label: 'A', tone: 'site-a' }, { label: 'Slow', tone: 'speed' }],
    },
    {
        name: 'Astralis T Default Mirage',
        description: 'Taking their time, Astralis pushes 2 players through Tunnels, and hits a hard, when winning the opening duals on Middle',
        side: 'T', type: 'Default', map: 'Mirage', team: 'Astralis',
        tags: [{ label: 'A', tone: 'site-a' }, { label: 'Slow', tone: 'speed' }],
    },
]

const view = ref<'list' | 'grid'>('list')

const tagClass = (tone: 'site-a' | 'site-b' | 'speed' | 'mood') => {
    switch (tone) {
        case 'site-a':
            return 'border-saffron/40 text-saffron'
        case 'site-b':
            return 'border-steel/50 text-steel'
        case 'speed':
            return 'border-ash text-bone'
        case 'mood':
        default:
            return 'border-ash text-chalk'
    }
}
</script>

<template>
    <section id="tactics" class="relative py-16 sm:py-20 lg:py-24" data-reveal-group>
        <Container>
            <div class="grid gap-16 lg:grid-cols-[5fr_7fr] lg:gap-24">
                <header class="flex flex-col gap-6 lg:sticky lg:top-32 lg:self-start">
                    <Eyebrow index="01" label="Tactics" />
                    <h2
                        class="font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                        data-reveal
                    >
                        A pro stratbook,
                        <br />
                        on tap.
                    </h2>
                    <p class="max-w-[42ch] text-bone" data-reveal>
                        A curated catalog of Tier 1 and Tier 2 tactics by map, updated every week from the latest tournament play. Browse the call, copy the diagram, drop it into your stratbook.
                    </p>
                    <ul class="flex flex-col gap-3 pt-4" data-reveal>
                        <li class="flex items-baseline gap-3 text-sm text-bone">
                            <span class="font-mono text-[11px] uppercase tracking-[0.08em] text-saffron" aria-hidden="true">+</span>
                            <span>Weekly tournament adds; tactics surface as the meta moves.</span>
                        </li>
                        <li class="flex items-baseline gap-3 text-sm text-bone">
                            <span class="font-mono text-[11px] uppercase tracking-[0.08em] text-saffron" aria-hidden="true">+</span>
                            <span>Filter by side, type, map, team, tags. Save what fits.</span>
                        </li>
                        <li class="flex items-baseline gap-3 text-sm text-bone">
                            <span class="font-mono text-[11px] uppercase tracking-[0.08em] text-saffron" aria-hidden="true">+</span>
                            <span>Copy any tactic into a team stratbook in one click.</span>
                        </li>
                    </ul>
                </header>

                <!-- The data IS the imagery: the actual tactics list pattern from the product -->
                <figure
                    class="artifact-dark relative overflow-hidden rounded-[var(--radius-md)] border border-ash bg-slate"
                    data-reveal
                >
                    <header class="flex items-center justify-between border-b border-ash px-5 py-3">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                            1,150 tactics
                        </p>
                        <div class="inline-flex items-center gap-px rounded-[var(--radius-xs)] border border-ash p-px" role="group" aria-label="View mode">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-[var(--radius-xs)] transition-colors duration-[180ms]"
                                :class="view === 'grid' ? 'bg-smoke text-chalk' : 'text-bone hover:text-chalk'"
                                aria-label="Grid view"
                                :aria-pressed="view === 'grid'"
                                @click="view = 'grid'"
                            >
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                    <rect x="1" y="1" width="5" height="5" stroke="currentColor" stroke-width="1.2" />
                                    <rect x="8" y="1" width="5" height="5" stroke="currentColor" stroke-width="1.2" />
                                    <rect x="1" y="8" width="5" height="5" stroke="currentColor" stroke-width="1.2" />
                                    <rect x="8" y="8" width="5" height="5" stroke="currentColor" stroke-width="1.2" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-[var(--radius-xs)] transition-colors duration-[180ms]"
                                :class="view === 'list' ? 'bg-saffron text-concrete' : 'text-bone hover:text-chalk'"
                                aria-label="List view"
                                :aria-pressed="view === 'list'"
                                @click="view = 'list'"
                            >
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                    <path d="M1 3h12M1 7h12M1 11h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="square" />
                                </svg>
                            </button>
                        </div>
                    </header>

                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="grid grid-cols-[3fr_auto_1.2fr_1fr_1fr_1.4fr] items-center gap-3 px-5 py-3 font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                                <th class="text-left font-medium" scope="col">Name</th>
                                <th class="text-left font-medium" scope="col">Side</th>
                                <th class="text-left font-medium" scope="col">Type</th>
                                <th class="text-left font-medium" scope="col">Map</th>
                                <th class="text-left font-medium" scope="col">Team</th>
                                <th class="text-left font-medium" scope="col">Tags</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ash">
                            <tr
                                v-for="(t, i) in tactics.slice(0, 5)"
                                :key="i"
                                class="relative grid grid-cols-[3fr_auto_1.2fr_1fr_1fr_1.4fr] items-center gap-3 px-5 py-4 transition-colors duration-[180ms] hover:bg-smoke/60"
                            >
                                <td class="flex flex-col gap-1 pr-2">
                                    <span class="text-sm font-medium text-chalk">{{ t.name }}</span>
                                    <span class="text-xs text-bone">{{ t.description }}</span>
                                </td>
                                <td>
                                    <span
                                        class="inline-flex h-7 min-w-[36px] items-center justify-center rounded-[var(--radius-xs)] border px-2 font-mono text-[11px] font-medium uppercase tracking-[0.08em]"
                                        :class="t.side === 'T'
                                            ? 'border-saffron/40 bg-saffron/10 text-saffron'
                                            : 'border-steel/40 bg-steel/10 text-steel'"
                                    >
                                        {{ t.side }}
                                    </span>
                                </td>
                                <td class="text-xs text-chalk">{{ t.type }}</td>
                                <td class="font-mono text-[11px] uppercase tracking-[0.08em] text-bone">{{ t.map }}</td>
                                <td class="text-xs text-chalk">{{ t.team }}</td>
                                <td class="flex flex-wrap gap-1">
                                    <span
                                        v-for="tag in t.tags"
                                        :key="tag.label"
                                        class="inline-flex items-center rounded-[var(--radius-xs)] border bg-transparent px-2 py-0.5 font-mono text-[10px] font-medium uppercase tracking-[0.08em]"
                                        :class="tagClass(tag.tone)"
                                    >
                                        {{ tag.label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <footer class="border-t border-ash px-5 py-3">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                            Showing 5 of 1,150 · Filter by side, type, map, team, tags
                        </p>
                    </footer>
                </figure>
            </div>
        </Container>
    </section>
</template>
