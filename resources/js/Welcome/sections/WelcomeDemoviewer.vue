<script setup lang="ts">
import { ref, computed } from 'vue'
import Container from '../primitives/Container.vue'
import Eyebrow from '../primitives/Eyebrow.vue'
import DemoViewerEmbed from '../primitives/DemoViewerEmbed.vue'

// The real parsed match leads; the hand-built mock below stays as the
// fallback when the viewer key is unconfigured or the API is down.
const viewerFailed = ref(false)

interface KillEvent {
    t: number          // seconds into round
    by: 'us' | 'them'
    weapon: 'rifle' | 'awp' | 'pistol' | 'knife' | 'nade'
}
interface Round {
    n: number
    side: 'T' | 'CT'
    result: 'won' | 'lost'
    method: 'elim' | 'plant' | 'defuse' | 'time'
    length: number     // seconds
    kills: KillEvent[]
}

// MR12: half at 12, first to 13 wins. Final 13-5 csstrats. 18 rounds total.
// First half (T side): 9-3. Second half (CT side): 4-2 → 13-5.
const rounds: Round[] = [
    { n: 1,  side: 'T',  result: 'won',  method: 'plant', length: 78, kills: [{ t: 18, by: 'us', weapon: 'pistol' }, { t: 36, by: 'us', weapon: 'pistol' }, { t: 52, by: 'them', weapon: 'pistol' }, { t: 67, by: 'us', weapon: 'pistol' }] },
    { n: 2,  side: 'T',  result: 'won',  method: 'plant', length: 95, kills: [{ t: 22, by: 'us', weapon: 'rifle' }, { t: 41, by: 'them', weapon: 'rifle' }, { t: 58, by: 'us', weapon: 'awp' }, { t: 74, by: 'us', weapon: 'rifle' }, { t: 88, by: 'us', weapon: 'rifle' }] },
    { n: 3,  side: 'T',  result: 'lost', method: 'elim',  length: 64, kills: [{ t: 14, by: 'them', weapon: 'awp' }, { t: 28, by: 'us', weapon: 'rifle' }, { t: 41, by: 'them', weapon: 'rifle' }, { t: 56, by: 'them', weapon: 'rifle' }] },
    { n: 4,  side: 'T',  result: 'won',  method: 'plant', length: 102, kills: [{ t: 24, by: 'us', weapon: 'rifle' }, { t: 38, by: 'us', weapon: 'rifle' }, { t: 55, by: 'them', weapon: 'awp' }, { t: 71, by: 'us', weapon: 'rifle' }, { t: 88, by: 'us', weapon: 'nade' }] },
    { n: 5,  side: 'T',  result: 'won',  method: 'elim',  length: 73, kills: [{ t: 19, by: 'us', weapon: 'rifle' }, { t: 32, by: 'us', weapon: 'rifle' }, { t: 47, by: 'them', weapon: 'awp' }, { t: 63, by: 'us', weapon: 'rifle' }] },
    { n: 6,  side: 'T',  result: 'lost', method: 'elim',  length: 86, kills: [{ t: 21, by: 'them', weapon: 'rifle' }, { t: 39, by: 'us', weapon: 'rifle' }, { t: 54, by: 'them', weapon: 'rifle' }, { t: 72, by: 'them', weapon: 'rifle' }] },
    { n: 7,  side: 'T',  result: 'won',  method: 'plant', length: 91, kills: [{ t: 26, by: 'us', weapon: 'rifle' }, { t: 42, by: 'us', weapon: 'awp' }, { t: 59, by: 'them', weapon: 'rifle' }, { t: 77, by: 'us', weapon: 'rifle' }] },
    { n: 8,  side: 'T',  result: 'won',  method: 'elim',  length: 68, kills: [{ t: 17, by: 'us', weapon: 'pistol' }, { t: 31, by: 'us', weapon: 'rifle' }, { t: 49, by: 'them', weapon: 'rifle' }, { t: 60, by: 'us', weapon: 'rifle' }] },
    { n: 9,  side: 'T',  result: 'lost', method: 'time',  length: 110, kills: [{ t: 28, by: 'them', weapon: 'awp' }, { t: 51, by: 'us', weapon: 'rifle' }, { t: 78, by: 'them', weapon: 'rifle' }] },
    { n: 10, side: 'T',  result: 'won',  method: 'plant', length: 96, kills: [{ t: 23, by: 'us', weapon: 'rifle' }, { t: 41, by: 'us', weapon: 'rifle' }, { t: 58, by: 'them', weapon: 'rifle' }, { t: 75, by: 'us', weapon: 'rifle' }, { t: 89, by: 'us', weapon: 'rifle' }] },
    { n: 11, side: 'T',  result: 'won',  method: 'plant', length: 84, kills: [{ t: 20, by: 'us', weapon: 'awp' }, { t: 38, by: 'us', weapon: 'rifle' }, { t: 55, by: 'them', weapon: 'rifle' }, { t: 70, by: 'us', weapon: 'rifle' }] },
    { n: 12, side: 'T',  result: 'won',  method: 'elim',  length: 76, kills: [{ t: 24, by: 'us', weapon: 'rifle' }, { t: 40, by: 'us', weapon: 'rifle' }, { t: 55, by: 'them', weapon: 'rifle' }, { t: 68, by: 'us', weapon: 'awp' }] },
    { n: 13, side: 'CT', result: 'won',  method: 'elim',  length: 71, kills: [{ t: 22, by: 'us', weapon: 'awp' }, { t: 35, by: 'us', weapon: 'rifle' }, { t: 51, by: 'them', weapon: 'rifle' }, { t: 65, by: 'us', weapon: 'rifle' }] },
    { n: 14, side: 'CT', result: 'lost', method: 'plant', length: 88, kills: [{ t: 27, by: 'them', weapon: 'rifle' }, { t: 44, by: 'us', weapon: 'rifle' }, { t: 60, by: 'them', weapon: 'awp' }, { t: 76, by: 'them', weapon: 'rifle' }] },
    { n: 15, side: 'CT', result: 'won',  method: 'time',  length: 115, kills: [{ t: 31, by: 'us', weapon: 'awp' }, { t: 58, by: 'them', weapon: 'rifle' }, { t: 82, by: 'us', weapon: 'rifle' }] },
    { n: 16, side: 'CT', result: 'won',  method: 'defuse', length: 94, kills: [{ t: 22, by: 'us', weapon: 'rifle' }, { t: 38, by: 'them', weapon: 'rifle' }, { t: 56, by: 'us', weapon: 'awp' }, { t: 73, by: 'us', weapon: 'rifle' }, { t: 88, by: 'us', weapon: 'rifle' }] },
    { n: 17, side: 'CT', result: 'lost', method: 'plant', length: 81, kills: [{ t: 25, by: 'them', weapon: 'rifle' }, { t: 41, by: 'us', weapon: 'rifle' }, { t: 58, by: 'them', weapon: 'rifle' }, { t: 73, by: 'them', weapon: 'awp' }] },
    { n: 18, side: 'CT', result: 'won',  method: 'elim',  length: 67, kills: [{ t: 18, by: 'us', weapon: 'rifle' }, { t: 32, by: 'us', weapon: 'rifle' }, { t: 48, by: 'them', weapon: 'rifle' }, { t: 61, by: 'us', weapon: 'awp' }] },
]

const selected = ref<number>(15) // index 15 → round 16 (the defuse clutch)

const round = computed(() => rounds.find((r) => r.n === selected.value + 1) ?? rounds[0])

const formatRoundLength = (s: number) => {
    const m = Math.floor(s / 60)
    const sec = s % 60
    return `${m}:${sec.toString().padStart(2, '0')}`
}

const score = computed(() => {
    let us = 0, them = 0
    for (const r of rounds) {
        if (r.n > selected.value + 1) break
        if (r.result === 'won') us++; else them++
    }
    return { us, them }
})

const finalScore = computed(() => {
    let us = 0, them = 0
    for (const r of rounds) {
        if (r.result === 'won') us++; else them++
    }
    return { us, them }
})

const weaponGlyph = (w: KillEvent['weapon']) => {
    switch (w) {
        case 'awp':    return 'AWP'
        case 'rifle':  return 'AK'
        case 'pistol': return 'USP'
        case 'knife':  return 'KNF'
        case 'nade':   return 'HE'
    }
}

// Random handles, not real pros. K-D-A sized for 18 rounds (13-5 result).
const players = [
    { team: 'us',   name: 'rax',  role: 'IGL',     k: 16, d: 10, a: 7,  hs: 0.54 },
    { team: 'us',   name: 'vyx',  role: 'AWP',     k: 24, d: 11, a: 3,  hs: 0.46 },
    { team: 'us',   name: 'hex',  role: 'Entry',   k: 18, d: 13, a: 5,  hs: 0.61 },
    { team: 'us',   name: 'lulo', role: 'Support', k: 10, d: 12, a: 11, hs: 0.48 },
    { team: 'us',   name: 'nori', role: 'Lurk',    k: 14, d: 10, a: 6,  hs: 0.51 },
    { team: 'them', name: 'bram', role: 'IGL',     k: 11, d: 16, a: 5,  hs: 0.43 },
    { team: 'them', name: 'kep',  role: 'AWP',     k: 14, d: 15, a: 2,  hs: 0.41 },
    { team: 'them', name: 'sav',  role: 'Entry',   k:  9, d: 18, a: 5,  hs: 0.55 },
    { team: 'them', name: 'brux', role: 'Support', k:  7, d: 17, a: 7,  hs: 0.39 },
    { team: 'them', name: 'donn', role: 'Lurk',    k: 11, d: 14, a: 4,  hs: 0.47 },
]

const usPlayers = players.filter((p) => p.team === 'us')
const themPlayers = players.filter((p) => p.team === 'them')

const select = (i: number) => {
    selected.value = i
}
</script>

<template>
    <section id="demoviewer" class="relative py-16 sm:py-20 lg:py-24" data-reveal-group>
        <Container>
            <div class="grid gap-16 lg:grid-cols-[7fr_5fr] lg:gap-24">
                <!-- Live parsed match (asymmetric flip from Tactics) -->
                <div v-show="!viewerFailed" class="order-2 lg:order-1" data-reveal>
                    <DemoViewerEmbed
                        :height="620"
                        @unavailable="viewerFailed = true"
                    />
                </div>

                <!-- Fallback: hand-built match artifact -->
                <figure
                    v-if="viewerFailed"
                    class="artifact-dark relative order-2 overflow-hidden rounded-[var(--radius-md)] border border-ash bg-slate lg:order-1"
                >
                    <!-- Match header -->
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ash px-5 py-3">
                        <div class="flex items-center gap-4">
                            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                                Demo · de_mirage · csstrats vs Sørby Riot
                            </p>
                        </div>
                        <div class="flex items-center gap-3 font-mono text-[11px] font-medium uppercase tracking-[0.08em]">
                            <span class="text-chalk">Final {{ finalScore.us }}<span class="text-bone"> - </span>{{ finalScore.them }}</span>
                        </div>
                    </header>

                    <!-- Round selector: 18 bars, click to scrub -->
                    <div class="px-5 pt-5 pb-3">
                        <div class="mb-2 flex items-baseline justify-between">
                            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                                Round timeline · click any round
                            </p>
                            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                                <span class="text-chalk">{{ score.us }}-{{ score.them }}</span> after R{{ selected + 1 }}
                            </p>
                        </div>
                        <div class="grid grid-cols-18 gap-[3px]" role="tablist" aria-label="Round selection">
                            <button
                                v-for="(r, i) in rounds"
                                :key="r.n"
                                type="button"
                                class="group relative flex aspect-[1/3] flex-col items-center justify-end transition-opacity hover:opacity-100"
                                :class="i === selected ? 'opacity-100' : 'opacity-80 hover:opacity-100'"
                                role="tab"
                                :aria-selected="i === selected"
                                :aria-label="`Round ${r.n}, ${r.side} side, ${r.result}`"
                                @click="select(i)"
                            >
                                <span
                                    class="block w-full origin-bottom transition-all duration-[180ms]"
                                    :class="[
                                        r.result === 'won' ? 'bg-saffron' : 'bg-ash',
                                        i === selected ? 'scale-y-100' : 'scale-y-[0.6] group-hover:scale-y-100',
                                    ]"
                                    :style="{ height: `${Math.max(30, (r.kills.length + 1) * 14)}%` }"
                                />
                                <span
                                    v-if="i === selected"
                                    class="mt-1 block h-[2px] w-full bg-chalk"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                        <div class="mt-2 flex items-baseline justify-between font-mono text-[10px] uppercase tracking-[0.08em] text-bone">
                            <span>R1</span>
                            <span class="text-chalk">Half · {{ selected < 12 ? '1st (T)' : '2nd (CT)' }}</span>
                            <span>R18</span>
                        </div>
                    </div>

                    <!-- Selected round detail -->
                    <div class="border-t border-ash px-5 py-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-4">
                            <div class="flex items-baseline gap-4">
                                <p class="font-display text-3xl font-medium tracking-[-0.01em] text-chalk">
                                    R{{ round.n.toString().padStart(2, '0') }}
                                </p>
                                <p
                                    class="font-mono text-xs font-medium uppercase tracking-[0.08em]"
                                    :class="round.side === 'T' ? 'text-saffron' : 'text-steel'"
                                >
                                    {{ round.side }} side
                                </p>
                                <p
                                    class="font-mono text-xs font-medium uppercase tracking-[0.08em]"
                                    :class="round.result === 'won' ? 'text-saffron' : 'text-bone'"
                                >
                                    <span aria-hidden="true">●</span> {{ round.result === 'won' ? 'Won' : 'Lost' }} · {{ round.method }}
                                </p>
                            </div>
                            <p class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone">
                                Length {{ formatRoundLength(round.length) }}
                            </p>
                        </div>

                        <!-- Kill event timeline within round -->
                        <div class="mt-5">
                            <p class="mb-2 font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-bone">
                                Kill events
                            </p>
                            <div class="relative h-12 border-t border-b border-ash">
                                <!-- Time ruler -->
                                <span class="absolute left-0 top-0 h-2 w-px bg-ash" aria-hidden="true" />
                                <span class="absolute left-1/4 top-0 h-2 w-px bg-ash" aria-hidden="true" />
                                <span class="absolute left-1/2 top-0 h-2 w-px bg-ash" aria-hidden="true" />
                                <span class="absolute left-3/4 top-0 h-2 w-px bg-ash" aria-hidden="true" />
                                <span class="absolute right-0 top-0 h-2 w-px bg-ash" aria-hidden="true" />

                                <!-- Frag events -->
                                <div
                                    v-for="(ev, i) in round.kills"
                                    :key="i"
                                    class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2"
                                    :style="{ left: `${(ev.t / round.length) * 100}%` }"
                                >
                                    <span
                                        class="block h-4 w-4 rounded-full ring-2 ring-slate"
                                        :class="ev.by === 'us' ? 'bg-saffron' : 'bg-bone'"
                                        :title="`${ev.t}s · ${ev.by === 'us' ? 'csstrats' : 'Sørby Riot'} · ${ev.weapon}`"
                                    />
                                </div>
                            </div>
                            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-bone">
                                <li
                                    v-for="(ev, i) in round.kills"
                                    :key="`l-${i}`"
                                    class="flex items-center gap-1.5"
                                >
                                    <span
                                        class="inline-block h-1.5 w-1.5 rounded-full"
                                        :class="ev.by === 'us' ? 'bg-saffron' : 'bg-bone'"
                                        aria-hidden="true"
                                    />
                                    {{ ev.t }}s
                                    <span class="text-ash">·</span>
                                    {{ weaponGlyph(ev.weapon) }}
                                    <span class="text-ash">·</span>
                                    <span :class="ev.by === 'us' ? 'text-chalk' : 'text-bone'">
                                        {{ ev.by === 'us' ? 'csstrats' : 'Sørby Riot' }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Match scoreboard (totals across all rounds) -->
                    <footer class="grid grid-cols-1 gap-px bg-ash sm:grid-cols-2">
                        <div class="bg-slate p-5">
                            <p class="mb-3 font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-saffron">
                                csstrats · {{ finalScore.us }}
                            </p>
                            <ul class="flex flex-col gap-1.5">
                                <li
                                    v-for="p in usPlayers"
                                    :key="p.name"
                                    class="grid grid-cols-[1fr_auto] gap-2 font-mono text-[11px]"
                                >
                                    <span class="text-chalk">
                                        {{ p.name }}<span class="text-bone"> · {{ p.role }}</span>
                                    </span>
                                    <span class="text-chalk tabular-nums">
                                        {{ p.k }}<span class="text-bone"> - </span>{{ p.d }}<span class="text-bone"> - </span>{{ p.a }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                        <div class="bg-slate p-5">
                            <p class="mb-3 font-mono text-[10px] font-medium uppercase tracking-[0.08em] text-bone">
                                Sørby Riot · {{ finalScore.them }}
                            </p>
                            <ul class="flex flex-col gap-1.5">
                                <li
                                    v-for="p in themPlayers"
                                    :key="p.name"
                                    class="grid grid-cols-[1fr_auto] gap-2 font-mono text-[11px]"
                                >
                                    <span class="text-bone">
                                        {{ p.name }}<span class="text-ash"> · {{ p.role }}</span>
                                    </span>
                                    <span class="text-bone tabular-nums">
                                        {{ p.k }}<span class="text-ash"> - </span>{{ p.d }}<span class="text-ash"> - </span>{{ p.a }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </footer>
                </figure>

                <header class="order-1 flex flex-col gap-6 lg:order-2 lg:sticky lg:top-32 lg:self-start">
                    <Eyebrow index="02" label="Demoviewer" />
                    <h2
                        class="font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                        data-reveal
                    >
                        Every round,
                        <br />
                        on tape.
                    </h2>
                    <p class="max-w-[42ch] text-bone" data-reveal>
                        Drop a .dem file. We parse the match, scrub round by round, and surface every kill, plant, defuse, and util pop on a timeline. Pin a note where the rotation broke. Share the round to Discord.
                    </p>
                    <ul class="flex flex-col gap-3 pt-4" data-reveal>
                        <li class="flex items-baseline gap-3 text-sm text-bone">
                            <span class="font-mono text-[11px] uppercase tracking-[0.08em] text-saffron" aria-hidden="true">+</span>
                            <span>2D and 3D playback for the same match. Pick the view that helps.</span>
                        </li>
                        <li class="flex items-baseline gap-3 text-sm text-bone">
                            <span class="font-mono text-[11px] uppercase tracking-[0.08em] text-saffron" aria-hidden="true">+</span>
                            <span>Round-by-round annotations. Pin a note where it happened.</span>
                        </li>
                        <li class="flex items-baseline gap-3 text-sm text-bone">
                            <span class="font-mono text-[11px] uppercase tracking-[0.08em] text-saffron" aria-hidden="true">+</span>
                            <span>Callouts attached to the geometry. Your callouts, not Valve's.</span>
                        </li>
                    </ul>
                </header>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.grid-cols-18 {
    grid-template-columns: repeat(18, minmax(0, 1fr));
}
</style>
