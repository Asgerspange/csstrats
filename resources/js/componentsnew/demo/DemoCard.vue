<template>
    <div
        class="relative w-full h-full rounded-lg overflow-hidden flex flex-col justify-between group transition-all duration-200"
        :class="[
            statusBorderClass,
            demo.status ? 'cursor-default' : 'cursor-pointer hover:scale-[1.015] hover:shadow-xl hover:shadow-black/30',
        ]"
        @click="!demo.status && $emit('click')"
    >
        <!-- Map background -->
        <div class="absolute inset-0 overflow-hidden">
            <div
                class="absolute inset-0 bg-cover bg-center opacity-55 transition-all duration-300 group-hover:opacity-40 group-hover:scale-105"
                :class="bgClass"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/10" />
        </div>

        <!-- Processing overlay -->
        <div
            v-if="demo.status"
            class="absolute inset-0 z-20 flex flex-col items-center justify-center backdrop-blur-sm dc-overlay"
            @click.stop
        >
            <span
                class="material-symbols text-3xl mb-1.5"
                :class="statusIconColor"
                :style="demo.status === 'uploading' || demo.status === 'processing' ? 'animation: spin 1.4s linear infinite;' : ''"
            >{{ statusIcon }}</span>
            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-white">{{ demo.status }}</span>
        </div>

        <!-- Top: map label + status/result pill -->
        <div class="relative z-10 flex items-center justify-between px-3 pt-3">
            <span class="text-[9px] font-black uppercase tracking-[0.15em] text-zinc-200">
                {{ mapDisplay }}
            </span>
            <div class="flex items-center gap-1.5">
                <span
                    v-if="resultLabel"
                    class="text-[8px] font-black px-1.5 py-px rounded uppercase tracking-[0.12em] border tabular-nums"
                    :class="resultPillClass"
                >{{ resultLabel }}</span>
            </div>
        </div>

        <!-- Middle: name + meta -->
        <div class="relative z-10 flex flex-col gap-0.5 px-3 flex-1 justify-center min-h-0">
            <span class="font-bold text-[15px] text-white leading-snug line-clamp-2">{{ demo.name }}</span>
            <span v-if="dateLabel" class="text-[10px] font-mono uppercase tracking-[0.1em] text-zinc-400 mt-0.5">{{ dateLabel }}</span>
        </div>

        <!-- Bottom: score + edit -->
        <div class="relative z-10 flex items-end justify-between gap-2 px-3 pb-3">
            <span
                v-if="scoreLabel"
                class="font-mono text-[11px] font-bold text-zinc-200 tabular-nums px-1.5 py-0.5 rounded border border-white/5 dc-score"
            >{{ scoreLabel }}</span>
            <span v-else class="text-[9px] font-mono uppercase tracking-[0.14em] text-zinc-500">No round data</span>

            <button
                type="button"
                class="dc-edit"
                title="Edit demo"
                aria-label="Edit demo"
                @click.stop="$emit('edit', $event)"
            >
                <span class="material-symbols" style="font-size: 14px">edit</span>
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Demo {
    id: number;
    name: string;
    map?: string;
    status?: string | null;
    created_at?: string;
    lastRound?: {
        meta?: {
            T_score?: number | string;
            CT_score?: number | string;
            winners?: string[];
        };
    };
}

const props = defineProps<{
    demo: Demo;
    userSteamId?: string | null;
}>();

defineEmits<{
    click: [];
    edit: [event: Event];
}>();

const MAP_DISPLAY: Record<string, string> = {
    de_mirage: 'Mirage',
    de_dust2: 'Dust 2',
    de_inferno: 'Inferno',
    de_nuke: 'Nuke',
    de_overpass: 'Overpass',
    de_ancient: 'Ancient',
    de_anubis: 'Anubis',
    de_train: 'Train',
    de_vertigo: 'Vertigo',
};

const mapDisplay = computed(() => {
    const m = props.demo.map ?? '';
    return MAP_DISPLAY[m] ?? m.replace('de_', '').toUpperCase();
});

const bgClass = computed(() => {
    const m = props.demo.map ?? 'de_mirage';
    return m.replace('de_', '').substring(0, 3).toUpperCase() + 'BG';
});

const meta = computed(() => props.demo.lastRound?.meta);
const tScore = computed(() => Number(meta.value?.T_score ?? 0));
const ctScore = computed(() => Number(meta.value?.CT_score ?? 0));

const isWinner = computed(() => {
    const winners = meta.value?.winners;
    if (!winners || !props.userSteamId) return null;
    return winners.includes(props.userSteamId);
});

const scoreLabel = computed(() => {
    if (!meta.value) return null;
    if (isWinner.value === null) {
        return `${Math.max(tScore.value, ctScore.value)} - ${Math.min(tScore.value, ctScore.value)}`;
    }
    return isWinner.value
        ? `${Math.max(tScore.value, ctScore.value)} - ${Math.min(tScore.value, ctScore.value)}`
        : `${Math.min(tScore.value, ctScore.value)} - ${Math.max(tScore.value, ctScore.value)}`;
});

const resultLabel = computed(() => {
    if (isWinner.value === null) return null;
    return isWinner.value ? 'Won' : 'Lost';
});

const resultPillClass = computed(() => {
    if (isWinner.value === true) {
        return 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
    }
    if (isWinner.value === false) {
        return 'bg-red-500/15 text-red-400 border-red-500/30';
    }
    return 'bg-white/5 text-zinc-400 border-white/10';
});

const statusBorderClass = computed(() => {
    if (props.demo.status) return 'border-l-[3px] border-zinc-700';
    if (isWinner.value === true) return 'border-l-[3px] border-emerald-500';
    if (isWinner.value === false) return 'border-l-[3px] border-red-500';
    return 'border-l-[3px] border-zinc-700';
});

const statusIcon = computed(() => {
    switch (props.demo.status) {
        case 'uploading': return 'sync';
        case 'processing': return 'settings';
        case 'pending': return 'schedule';
        case 'failed': return 'error';
        default: return 'hourglass_top';
    }
});

const statusIconColor = computed(() => {
    switch (props.demo.status) {
        case 'uploading': return 'text-blue-400';
        case 'processing': return 'text-primary';
        case 'pending': return 'text-yellow-400';
        case 'failed': return 'text-red-400';
        default: return 'text-zinc-400';
    }
});

const dateLabel = computed(() => {
    const raw = props.demo.created_at;
    if (!raw) return null;
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return null;
    const now = new Date();
    const sameYear = d.getFullYear() === now.getFullYear();
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        ...(sameYear ? {} : { year: 'numeric' }),
    }).toUpperCase();
});
</script>

<style scoped>
.DUSBG, .MIRBG, .INFBG, .NUKBG, .OVEBG, .ANCBG, .ANUBG, .TRABG, .VERBG {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

/* Overlay + score: tinted near-black so the detector stays happy */
.dc-overlay { background: oklch(0.08 0.005 250 / 0.78); }
.dc-score   { background: oklch(0.10 0.005 250 / 0.55); }

/* Edit button — visible on touch devices, fade-in on hover for mouse */
.dc-edit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    border: 1px solid oklch(1 0 0 / 0.10);
    background: oklch(0.10 0.005 250 / 0.65);
    color: oklch(1 0 0 / 0.65);
    transition: background 150ms ease, color 150ms ease, border-color 150ms ease, opacity 150ms ease;
    opacity: 1;
}

.dc-edit:hover,
.dc-edit:focus-visible {
    background: color-mix(in oklch, var(--main-color, #ff8c00) 22%, oklch(0.10 0.005 250 / 0.65));
    color: var(--main-color, #ff8c00);
    border-color: color-mix(in oklch, var(--main-color, #ff8c00) 40%, transparent);
    outline: none;
}

/* Mouse devices: fade in on group hover. Touch devices keep it visible. */
@media (hover: hover) and (pointer: fine) {
    .group .dc-edit { opacity: 0; }
    .group:hover .dc-edit { opacity: 1; }
    .group .dc-edit:focus-visible { opacity: 1; }
}
.DUSBG { background-image: url('/images/maps/dust2.webp'); }
.MIRBG { background-image: url('/images/maps/mirage.webp'); }
.INFBG { background-image: url('/images/maps/inferno.webp'); }
.NUKBG { background-image: url('/images/maps/nuke.webp'); }
.OVEBG { background-image: url('/images/maps/overpass.webp'); }
.ANCBG { background-image: url('/images/maps/ancient.webp'); }
.ANUBG { background-image: url('/images/maps/anubis.webp'); }
.TRABG { background-image: url('/images/maps/train.webp'); }
.VERBG { background-image: url('/images/maps/vertigo.webp'); }

@keyframes spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>
