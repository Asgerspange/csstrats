<script setup lang="ts">
// A real-feeling CS round timeline used as data-as-imagery in the hero.
// Each cell is a single round; T/CT win pattern mimics an actual match.
// Used decoratively but reads like a parsed demo.

const rounds = [
    { side: 't', won: true },
    { side: 'ct', won: true },
    { side: 't', won: false },
    { side: 'ct', won: true },
    { side: 't', won: true },
    { side: 'ct', won: false },
    { side: 't', won: true },
    { side: 'ct', won: true },
    { side: 't', won: false },
    { side: 'ct', won: true },
    { side: 't', won: true },
    { side: 'ct', won: true },
    { side: 't', won: false },
    { side: 'ct', won: false },
    { side: 't', won: true },
    { side: 'ct', won: true },
    { side: 't', won: true },
    { side: 'ct', won: false },
    { side: 't', won: false },
    { side: 'ct', won: true },
    { side: 't', won: true },
    { side: 'ct', won: true },
    { side: 't', won: true },
    { side: 'ct', won: true },
] as const

const totalWon = rounds.filter((r) => r.won).length
const totalLost = rounds.length - totalWon
</script>

<template>
    <figure class="flex flex-col gap-3" aria-label="Match round-by-round timeline">
        <header class="flex items-baseline justify-between">
            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                Match · de_mirage · 16-{{ totalLost }}
            </p>
            <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                {{ rounds.length }} rounds
            </p>
        </header>

        <div class="grid grid-cols-[repeat(24,minmax(0,1fr))] gap-[3px]" aria-hidden="true">
            <span
                v-for="(round, i) in rounds"
                :key="i"
                class="block aspect-square rounded-[1px]"
                :class="[
                    round.won ? 'bg-saffron' : 'bg-ash',
                    round.side === 'ct' ? 'opacity-100' : 'opacity-70',
                ]"
            />
        </div>

        <div class="grid grid-cols-2 gap-4 pt-1">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-[1px] bg-saffron"></span>
                <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                    Won {{ totalWon }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-[1px] bg-ash"></span>
                <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                    Lost {{ totalLost }}
                </p>
            </div>
        </div>
    </figure>
</template>
