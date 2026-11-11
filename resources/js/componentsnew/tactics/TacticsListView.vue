<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- List header row -->
        <div class="flex items-center gap-3 px-4 py-2.5 font-mono text-[10px] uppercase tracking-widest text-zinc-500 border-b border-white/5 shrink-0 select-none">
            <span class="flex-1 min-w-0">Tactic</span>
            <span class="w-14 text-center hidden sm:block">Side</span>
            <span class="w-28 hidden md:block">Type</span>
            <span class="w-24 hidden lg:block">Map</span>
            <span class="w-32 hidden xl:block">Team</span>
            <span class="w-40 hidden 2xl:block">Tags</span>
            <span class="w-16 text-right hidden xl:block">Date</span>
            <span class="w-16"></span>
        </div>

        <!-- Scrollable list body -->
        <div
            class="flex-1 overflow-y-auto custom-track"
            @scroll.passive="$emit('scroll', $event)"
        >
            <div
                v-for="tactic in tactics"
                :key="tactic.id"
                class="group flex items-center gap-3 px-4 py-2.5 border-b border-white/[0.03] transition-colors duration-100 cursor-pointer hover:bg-white/[0.03]"
                @click="$emit('select', tactic.id)"
            >
                <!-- Name + description -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block w-1.5 h-1.5 rounded-full shrink-0"
                            :class="tactic.side === 'CT' ? 'bg-sky-400' : 'bg-[var(--color-primary)]'"
                        />
                        <span class="font-semibold text-sm text-white truncate leading-tight">{{ tacticLabel(tactic) }}</span>
                    </div>
                    <div v-if="tactic.description" class="text-[11px] text-zinc-500 truncate leading-tight mt-0.5 ml-3.5">{{ tactic.description }}</div>
                </div>

                <!-- Side badge -->
                <span
                    class="w-14 text-center hidden sm:inline-block font-mono text-[10px] font-semibold px-2 py-0.5 rounded uppercase tracking-wider border"
                    :class="tactic.side === 'CT'
                        ? 'bg-sky-400/10 text-sky-300 border-sky-400/25'
                        : 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] border-[var(--color-primary)]/25'"
                >{{ tactic.side }}</span>

                <!-- Type -->
                <span class="w-28 text-xs text-zinc-400 truncate hidden md:block">{{ tactic.type }}</span>

                <!-- Map -->
                <span class="w-24 font-mono text-[11px] text-zinc-400 uppercase tracking-wider truncate hidden lg:block">{{ tactic.map }}</span>

                <!-- Team -->
                <span class="w-32 text-[12px] text-zinc-300 truncate hidden xl:block">{{ tactic.team_name || tactic.team?.name }}</span>

                <!-- Tags -->
                <div class="w-40 hidden 2xl:flex flex-wrap gap-1 overflow-hidden max-h-6">
                    <span
                        v-for="tag in (tactic.tags ?? []).slice(0, 2)"
                        :key="tag"
                        class="font-mono text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded border border-white/10 text-zinc-400 truncate max-w-[70px]"
                    >{{ tag }}</span>
                </div>

                <!-- Date -->
                <span class="w-16 text-right font-mono text-[10px] tabular-nums text-zinc-600 hidden xl:block">{{ formattedDate(tactic.matchdate) }}</span>

                <!-- Actions -->
                <div class="w-16 flex justify-end items-center gap-1">
                    <button
                        v-if="tactic.round_plan"
                        class="grid place-items-center w-6 h-6 rounded-md border border-[var(--color-primary)]/30 bg-[var(--color-primary)]/10 text-[var(--color-primary)] transition-colors hover:bg-[var(--color-primary)]/20"
                        title="View interactive round plan"
                        @click.stop="$emit('open-round-plan', tactic)"
                    >
                        <span class="material-symbols" style="font-size:13px">route</span>
                    </button>
                    <span
                        v-if="addedIds.has(tactic.id)"
                        class="grid place-items-center w-6 h-6 rounded-md border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/10 text-[var(--color-primary)]"
                        :title="`Already in ${addTargetName}`"
                    >
                        <span class="material-symbols" style="font-size:14px">check</span>
                    </span>
                    <button
                        v-else
                        class="grid place-items-center w-6 h-6 rounded-md border transition-colors disabled:cursor-not-allowed disabled:opacity-40 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                        :class="canAdd
                            ? 'border-white/15 bg-white/5 text-zinc-200 hover:border-[var(--color-primary)]/40 hover:bg-[var(--color-primary)]/10 hover:text-[var(--color-primary)]'
                            : 'border-white/10 bg-white/5 text-zinc-400'"
                        :disabled="!canAdd"
                        :title="canAdd ? `Add to ${addTargetName}` : 'Pick a target stratbook first'"
                        @click.stop="$emit('add', tactic)"
                    >
                        <span class="material-symbols" style="font-size:14px">add</span>
                    </button>
                </div>
            </div>

            <!-- Loading / end states -->
            <div v-if="loading" class="text-center py-8 opacity-60 text-sm">Loading more...</div>
            <div v-if="!hasMore && !loading" class="text-center py-8 text-zinc-600 text-xs uppercase tracking-widest">End of library</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { tacticLabel } from '@/lib/tacticLabel';

interface RoundPlan { id: number }

interface Tactic {
    id: number;
    name: string;
    description?: string;
    side: string;
    type: string;
    map: string;
    team_name?: string;
    team?: { name?: string };
    tags?: string[];
    matchdate?: string;
    round_plan?: RoundPlan | null;
}

withDefaults(defineProps<{
    tactics: Tactic[];
    loading?: boolean;
    hasMore?: boolean;
    canAdd?: boolean;
    addedIds?: Set<number>;
    addTargetName?: string;
}>(), { canAdd: false, addedIds: () => new Set<number>(), addTargetName: 'stratbook' });

defineEmits<{
    (e: 'select', id: number): void;
    (e: 'scroll', event: Event): void;
    (e: 'add', tactic: Tactic): void;
    (e: 'open-round-plan', tactic: Tactic): void;
}>();

function formattedDate(raw?: string): string {
    if (!raw) return '';
    const d = new Date(raw);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short' });
}
</script>

<style scoped>
.custom-track::-webkit-scrollbar { width: 6px; }
.custom-track::-webkit-scrollbar-thumb { background-color: oklch(var(--app-sidebar-ink, 1 0 0) / 0.12); border-radius: 10px; }
.custom-track::-webkit-scrollbar-track { background-color: transparent; }
.custom-track:hover::-webkit-scrollbar-thumb { background-color: oklch(var(--app-sidebar-ink, 1 0 0) / 0.20); }
.custom-track { scrollbar-width: thin; scrollbar-color: oklch(var(--app-sidebar-ink, 1 0 0) / 0.12) transparent; }
</style>
