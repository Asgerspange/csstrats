<template>
    <div class="flex flex-col h-full overflow-hidden">
        <!-- List header row -->
        <div class="flex items-center gap-2 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-zinc-500 border-b border-white/5 shrink-0 select-none">
            <span class="w-10 text-center">#</span>
            <span class="w-5"></span><!-- drag handle column -->
            <span class="flex-1 min-w-0">Name</span>
            <span class="w-16 text-center hidden sm:block">Side</span>
            <span class="w-28 hidden md:block">Type</span>
            <span class="w-24 hidden lg:block">Map</span>
            <span class="w-40 hidden xl:block">Tags</span>
            <span class="w-8"></span><!-- round plan icon column -->
        </div>

        <!-- Scrollable list body -->
        <div
            ref="listContainer"
            class="flex-1 overflow-y-auto custom-track"
            @scroll.passive="$emit('scroll', $event)"
        >
            <div
                v-for="(tactic, index) in tactics"
                :key="tactic.id"
                class="group flex items-center gap-2 px-4 py-3 border-b border-white/[0.03] transition-all duration-150 cursor-pointer"
                :class="[
                    dragOverId === tactic.id && dragDirection === 'above' ? 'border-t-2 border-t-primary' : '',
                    dragOverId === tactic.id && dragDirection === 'below' ? 'border-b-2 border-b-primary' : '',
                    draggingId === tactic.id ? 'opacity-30' : 'hover:bg-white/[0.03]',
                ]"
                draggable="true"
                @dragstart="onDragStart($event, tactic, index)"
                @dragover.prevent="onDragOver($event, tactic)"
                @dragleave="onDragLeave"
                @drop.prevent="onDrop($event, tactic)"
                @dragend="onDragEnd"
                @click="$emit('select', tactic.id)"
            >
                <!-- # number -->
                <span class="w-10 text-center text-sm font-black tabular-nums" :class="tactic.side === 'CT' ? 'text-[#4A9EFF]' : 'text-[#FF6B35]'">
                    {{ index + 1 }}
                </span>

                <!-- Drag handle -->
                <span
                    class="w-5 flex items-center justify-center text-zinc-600 group-hover:text-zinc-400 transition-colors cursor-grab active:cursor-grabbing"
                    @mousedown.stop
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                        <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                    </svg>
                </span>

                <!-- Name + description -->
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-white truncate leading-tight">{{ tactic.name }}</div>
                    <div v-if="tactic.description" class="text-[11px] text-zinc-500 truncate leading-tight mt-0.5">{{ tactic.description }}</div>
                </div>

                <!-- Side badge -->
                <span
                    class="w-16 text-center hidden sm:inline-block text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-widest"
                    :class="tactic.side === 'CT'
                        ? 'bg-[#4A9EFF]/10 text-[#4A9EFF] border border-[#4A9EFF]/30'
                        : 'bg-[#FF6B35]/10 text-[#FF6B35] border border-[#FF6B35]/30'"
                >{{ tactic.side }}</span>

                <!-- Type -->
                <span class="w-28 text-xs text-zinc-400 truncate hidden md:block">{{ tactic.type }}</span>

                <!-- Map -->
                <span class="w-24 text-[11px] text-zinc-500 uppercase tracking-wider truncate hidden lg:block">{{ tactic.map }}</span>

                <!-- Tags -->
                <div class="w-40 hidden xl:flex flex-wrap gap-1 overflow-hidden max-h-6">
                    <span
                        v-for="tag in (tactic.tags ?? []).slice(0, 2)"
                        :key="tag"
                        class="text-[9px] font-bold px-1.5 py-0.5 rounded border truncate max-w-[70px]"
                        :class="tactic.side === 'CT' ? 'border-[#4A9EFF]/40 text-[#4A9EFF]/70' : 'border-[#FF6B35]/40 text-[#FF6B35]/70'"
                    >{{ tag }}</span>
                </div>

                <!-- Round plan badge -->
                <div class="w-8 flex items-center justify-center">
                    <button
                        v-if="tactic.round_plan"
                        class="flex items-center justify-center w-6 h-6 rounded-md border border-primary/40 bg-primary/10 text-primary transition-all hover:bg-primary/25 hover:scale-110"
                        title="View Interactive Round Plan"
                        @click.stop="$emit('open-round-plan', tactic)"
                    >
                        <span class="material-symbols" style="font-size:12px">route</span>
                    </button>
                </div>
            </div>

            <!-- Loading / end states -->
            <div v-if="loading" class="text-center py-8 opacity-60 text-sm">Loading more...</div>
            <div v-if="!hasMore && !loading" class="text-center py-8 opacity-40 text-xs uppercase tracking-widest">End of stratbook</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

interface Tactic {
    id: number;
    name: string;
    description?: string;
    side: string;
    type: string;
    map: string;
    tags?: string[];
    sort_order?: number;
    round_plan?: any;
}

const props = defineProps<{
    tactics: Tactic[];
    loading?: boolean;
    hasMore?: boolean;
}>();

const emit = defineEmits<{
    (e: 'select', id: number): void;
    (e: 'open-round-plan', tactic: Tactic): void;
    (e: 'reorder', items: { id: number; sort_order: number }[]): void;
    (e: 'scroll', event: Event): void;
}>();

const listContainer = ref<HTMLElement | null>(null);

// ── Drag and drop state ────────────────────────────────────────────────────────
const draggingId = ref<number | null>(null);
const draggingIndex = ref<number>(-1);
const dragOverId = ref<number | null>(null);
const dragDirection = ref<'above' | 'below' | null>(null);

function onDragStart(event: DragEvent, tactic: Tactic, index: number) {
    draggingId.value = tactic.id;
    draggingIndex.value = index;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(tactic.id));
    }
}

function onDragOver(event: DragEvent, tactic: Tactic) {
    if (tactic.id === draggingId.value) return;
    dragOverId.value = tactic.id;

    // Determine if cursor is in the top or bottom half of the row
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const midY = rect.top + rect.height / 2;
    dragDirection.value = event.clientY < midY ? 'above' : 'below';
}

function onDragLeave() {
    dragOverId.value = null;
    dragDirection.value = null;
}

function onDrop(event: DragEvent, targetTactic: Tactic) {
    if (draggingId.value === null || draggingId.value === targetTactic.id) {
        resetDragState();
        return;
    }

    const fromIndex = draggingIndex.value;
    const targetIndex = props.tactics.findIndex(t => t.id === targetTactic.id);
    if (fromIndex === -1 || targetIndex === -1) {
        resetDragState();
        return;
    }

    // Calculate the insertion index
    let insertIndex = targetIndex;
    if (dragDirection.value === 'below') {
        insertIndex = targetIndex + 1;
    }
    // Adjust if we're moving downward (the source removal shifts indices)
    if (fromIndex < insertIndex) {
        insertIndex--;
    }

    // Build the new order by removing the dragged item and inserting it
    const reordered = [...props.tactics];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(insertIndex, 0, moved);

    // Emit the full new ordering with sort_order values
    const items = reordered.map((t, i) => ({
        id: t.id,
        sort_order: i,
    }));

    emit('reorder', items);
    resetDragState();
}

function onDragEnd() {
    resetDragState();
}

function resetDragState() {
    draggingId.value = null;
    draggingIndex.value = -1;
    dragOverId.value = null;
    dragDirection.value = null;
}
</script>

<style scoped>
.custom-track::-webkit-scrollbar { width: 6px; }
.custom-track::-webkit-scrollbar-thumb { background-color: var(--color-primary); border-radius: 10px; }
.custom-track::-webkit-scrollbar-track { background-color: transparent; }
.custom-track { scrollbar-width: thin; scrollbar-color: var(--color-primary) transparent; }
</style>