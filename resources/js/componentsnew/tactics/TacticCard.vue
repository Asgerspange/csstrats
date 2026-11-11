<template>
    <article
        class="group relative h-full overflow-hidden cursor-pointer transition-colors duration-150"
        @click="$emit('click')"
    >
        <!-- Image Header -->
        <img :src="mapBg" alt="" class="h-46 w-full rounded-lg object-cover" :class="tactic.side.toLowerCase() + '-card'" />

        <!-- Content -->
        <div class="relative z-10 flex h-full flex-col justify-between p-3">

            <!-- Top: pro team + map, side marker -->
            <header class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-white" :title="tactic.team_name">
                                {{ displayName || 'Unknown team' }}
                            </div>
                            <div class="mt-0.5 flex min-h-[14px] min-w-0 items-center gap-1 overflow-hidden font-mono text-[10px] uppercase tracking-wider text-zinc-300">
                                <span class="shrink-0 whitespace-nowrap">{{ tactic.map }}</span>
                                <span class="shrink-0 whitespace-nowrap">•</span>
                                <span class="shrink-0 whitespace-nowrap">{{ tactic.type }}</span>
                            </div>
                        </div>
                        <p v-if="tactic.description" class="line-clamp-2 min-w-0 flex-1 text-[11px] leading-relaxed text-zinc-300">
                            {{ tactic.description }}
                        </p>
                    </div>
                </div>
                <span
                    class="shrink-0 rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wider border backdrop-blur-[2px]"
                    :class="sideClass"
                >{{ tactic.side }}</span>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        v-if="tactic.round_plan"
                        class="flex items-center gap-0.5 rounded-md border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/15 px-1.5 py-0.5 text-[var(--color-primary)] backdrop-blur-[2px] transition-colors hover:bg-[var(--color-primary)]/25"
                        title="View interactive round plan"
                        @click.stop="$emit('open-round-plan', tactic)"
                    >
                        <span class="material-symbols" style="font-size:11px">route</span>
                        <span class="text-[8px] font-bold uppercase tracking-wider">Plan</span>
                    </button>

                    <span
                        v-if="added"
                        class="flex items-center gap-0.5 rounded-md border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/15 px-1.5 py-0.5 text-[var(--color-primary)] backdrop-blur-[2px]"
                        :title="`Already in ${addTargetName}`"
                    >
                        <span class="material-symbols" style="font-size:12px">check</span>
                        <span class="text-[8px] font-bold uppercase tracking-wider">In book</span>
                    </span>
                    <button
                        v-else
                        class="flex items-center gap-0.5 rounded-md border px-1.5 py-0.5 backdrop-blur-[2px] transition-colors disabled:cursor-not-allowed disabled:opacity-40 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                        :class="canAdd
                            ? 'border-white/20 bg-black/30 text-zinc-100 hover:border-[var(--color-primary)]/50 hover:bg-[var(--color-primary)]/15 hover:text-[var(--color-primary)]'
                            : 'border-white/10 bg-black/30 text-zinc-300'"
                        :disabled="!canAdd"
                        :title="canAdd ? `Add to ${addTargetName}` : 'Pick a target stratbook first'"
                        @click.stop="$emit('add', tactic)"
                    >
                        <span class="material-symbols" style="font-size:12px">add</span>
                        <span class="text-[8px] font-bold uppercase tracking-wider">Add</span>
                    </button>
                </div>
            </header>
        </div>
    </article>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { tacticLabel } from '@/lib/tacticLabel';

interface RoundPlan {
    id: number;
}

interface Tactic {
    id: number;
    name: string;
    team_name: string;
    team: any;
    player_descriptions: any;
    player_timestamps: any;
    type: string;
    description: string;
    tags: string[];
    map: string;
    matchdate: string;
    matchlink: string;
    video: string;
    side: string;
    created_at: string;
    updated_at: string;
    round_plan?: RoundPlan | null;
}

const props = withDefaults(defineProps<{
    tactic: Tactic;
    canAdd?: boolean;
    added?: boolean;
    addTargetName?: string;
}>(), { canAdd: false, added: false, addTargetName: 'stratbook' });

defineEmits<{
    click: [];
    'open-round-plan': [tactic: Tactic];
    add: [tactic: Tactic];
}>();

const MAP_BG: Record<string, string> = {
    'Dust II':  '/images/maps/dust2.webp',
    'Mirage':   '/images/maps/mirage.webp',
    'Inferno':  '/images/maps/inferno.webp',
    'Nuke':     '/images/maps/nuke.webp',
    'Overpass': '/images/maps/overpass.webp',
    'Ancient':  '/images/maps/ancient.webp',
    'Anubis':   '/images/maps/anubis.webp',
    'Train':    '/images/maps/train.webp',
    'Vertigo':  '/images/maps/vertigo.webp',
};

const mapBg = computed(() => MAP_BG[props.tactic.map] ?? '');
const displayName = computed(() => tacticLabel(props.tactic));

// CT gets the quiet steel-blue counter; T carries the saffron brand accent.
const sideClass = computed(() =>
    props.tactic.side === 'CT'
        ? 'border-sky-400/30 bg-sky-400/15 text-sky-200'
        : 'border-[var(--color-primary)]/30 bg-[var(--color-primary)]/15 text-[var(--color-primary)]'
);

const formattedDate = computed(() => {
    const raw = props.tactic.matchdate;
    if (!raw) return '';
    const d = new Date(raw);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: '2-digit' });
});
</script>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    .tactic-card .card-bg { transition: none; }
    .tactic-card:hover .card-bg { transform: none; }
}
</style>
