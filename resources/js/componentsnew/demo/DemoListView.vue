<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- List header -->
        <div class="flex items-center gap-2 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-zinc-500 border-b border-white/5 shrink-0 select-none">
            <span class="w-3 shrink-0"></span>
            <span class="flex-1 min-w-0">Demo</span>
            <span class="w-24 hidden md:block">Map</span>
            <span class="w-24 hidden lg:block">Date</span>
            <span class="w-20 text-center hidden sm:block">Score</span>
            <span class="w-16 text-center hidden sm:block">Result</span>
            <span class="w-8"></span>
        </div>

        <!-- Body -->
        <div
            class="flex-1 overflow-y-auto custom-track"
            @scroll.passive="$emit('scroll', $event)"
        >
            <div
                v-for="demo in demos"
                :key="demo.id"
                class="group relative flex items-center gap-2 px-4 py-3 border-b border-white/[0.03] transition-colors duration-100"
                :class="demo.status ? 'cursor-default opacity-90' : 'cursor-pointer hover:bg-white/[0.03]'"
                @click="!demo.status && $emit('select', demo.id)"
            >
                <!-- Result stripe -->
                <span
                    class="w-1 h-7 rounded-full shrink-0"
                    :class="resultStripeClass(demo)"
                />

                <!-- Name + status / desc -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-white truncate leading-tight">{{ demo.name }}</span>
                        <span
                            v-if="demo.status"
                            class="text-[9px] font-black uppercase tracking-[0.14em] px-1.5 py-0.5 rounded border whitespace-nowrap"
                            :class="statusPillClass(demo.status)"
                        >
                            <span class="material-symbols align-middle mr-0.5" style="font-size: 10px">{{ statusIcon(demo.status) }}</span>
                            {{ demo.status }}
                        </span>
                    </div>
                    <div class="text-[11px] text-zinc-500 truncate leading-tight mt-0.5">
                        {{ mapDisplay(demo.map) }}<span v-if="dateLabel(demo)"> · {{ dateLabel(demo) }}</span>
                    </div>
                </div>

                <!-- Map (md+) -->
                <span class="w-24 text-[11px] text-zinc-400 uppercase tracking-wider truncate hidden md:block">
                    {{ mapDisplay(demo.map) }}
                </span>

                <!-- Date (lg+) -->
                <span class="w-24 text-[11px] text-zinc-500 truncate hidden lg:block font-mono uppercase tracking-[0.08em]">
                    {{ dateLabel(demo) || '—' }}
                </span>

                <!-- Score -->
                <span class="w-20 text-center text-xs font-mono font-bold tabular-nums hidden sm:block" :class="scoreColorClass(demo)">
                    {{ scoreLabel(demo) || '—' }}
                </span>

                <!-- Result badge -->
                <div class="w-16 hidden sm:flex items-center justify-center">
                    <span
                        v-if="resultLabel(demo)"
                        class="text-[9px] font-black px-1.5 py-0.5 rounded border uppercase tracking-[0.14em]"
                        :class="resultPillClass(demo)"
                    >{{ resultLabel(demo) }}</span>
                    <span v-else class="text-[9px] text-zinc-600 font-mono uppercase tracking-[0.14em]">N/A</span>
                </div>

                <!-- Edit -->
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center rounded-md text-zinc-500 hover:text-primary hover:bg-primary/10 transition-colors"
                    title="Edit demo"
                    @click.stop="$emit('edit', { event: $event, demo })"
                >
                    <span class="material-symbols" style="font-size: 16px">edit</span>
                </button>
            </div>

            <div v-if="!demos.length" class="text-center py-16 opacity-50 text-xs uppercase tracking-widest">
                No demos to show
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
interface DemoMeta {
    T_score?: number | string;
    CT_score?: number | string;
    winners?: string[];
}

interface Demo {
    id: number;
    name: string;
    map?: string;
    status?: string | null;
    created_at?: string;
    lastRound?: { meta?: DemoMeta };
}

const props = defineProps<{
    demos: Demo[];
    userSteamId?: string | null;
}>();

defineEmits<{
    (e: 'select', id: number): void;
    (e: 'edit', payload: { event: Event; demo: Demo }): void;
    (e: 'scroll', event: Event): void;
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

const mapDisplay = (m?: string) => {
    if (!m) return '—';
    return MAP_DISPLAY[m] ?? m.replace('de_', '').toUpperCase();
};

const isWinner = (demo: Demo): boolean | null => {
    const w = demo.lastRound?.meta?.winners;
    if (!w || !props.userSteamId) return null;
    return w.includes(props.userSteamId);
};

const scoreLabel = (demo: Demo) => {
    const m = demo.lastRound?.meta;
    if (!m) return null;
    const t = Number(m.T_score ?? 0);
    const c = Number(m.CT_score ?? 0);
    const w = isWinner(demo);
    if (w === null) return `${Math.max(t, c)} - ${Math.min(t, c)}`;
    return w
        ? `${Math.max(t, c)} - ${Math.min(t, c)}`
        : `${Math.min(t, c)} - ${Math.max(t, c)}`;
};

const resultLabel = (demo: Demo) => {
    const w = isWinner(demo);
    if (w === null) return null;
    return w ? 'Won' : 'Lost';
};

const resultStripeClass = (demo: Demo) => {
    if (demo.status) return 'bg-zinc-700';
    const w = isWinner(demo);
    if (w === true) return 'bg-emerald-500';
    if (w === false) return 'bg-red-500';
    return 'bg-zinc-700';
};

const resultPillClass = (demo: Demo) => {
    const w = isWinner(demo);
    if (w === true) return 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
    if (w === false) return 'bg-red-500/15 text-red-400 border-red-500/30';
    return 'bg-white/5 text-zinc-400 border-white/10';
};

const scoreColorClass = (demo: Demo) => {
    const w = isWinner(demo);
    if (w === true) return 'text-emerald-400';
    if (w === false) return 'text-red-400';
    return 'text-zinc-300';
};

const statusPillClass = (status: string) => {
    switch (status) {
        case 'uploading': return 'border-blue-500/30 text-blue-400 bg-blue-500/10';
        case 'processing': return 'border-primary/30 text-primary bg-primary/10';
        case 'pending': return 'border-yellow-500/30 text-yellow-400 bg-yellow-500/10';
        case 'failed': return 'border-red-500/30 text-red-400 bg-red-500/10';
        default: return 'border-white/10 text-zinc-400 bg-white/5';
    }
};

const statusIcon = (status: string) => {
    switch (status) {
        case 'uploading': return 'sync';
        case 'processing': return 'settings';
        case 'pending': return 'schedule';
        case 'failed': return 'error';
        default: return 'hourglass_top';
    }
};

const dateLabel = (demo: Demo) => {
    const raw = demo.created_at;
    if (!raw) return null;
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return null;
    const now = new Date();
    const sameYear = d.getFullYear() === now.getFullYear();
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        ...(sameYear ? {} : { year: '2-digit' }),
    }).toUpperCase();
};
</script>

<style scoped>
.custom-track::-webkit-scrollbar { width: 6px; }
.custom-track::-webkit-scrollbar-thumb { background-color: var(--color-primary); border-radius: 10px; }
.custom-track::-webkit-scrollbar-track { background-color: transparent; }
.custom-track { scrollbar-width: thin; scrollbar-color: var(--color-primary) transparent; }
</style>
