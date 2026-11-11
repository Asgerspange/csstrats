<template>
    <div
        class="group relative flex items-center gap-3 rounded-lg border border-white/[0.06] bg-zinc-900/40 backdrop-blur-sm px-4 py-3 cursor-pointer transition-all hover:bg-white/[0.04] hover:border-white/15"
        @click="$emit('open')"
    >
        <!-- Color stripe -->
        <span
            class="absolute inset-y-0 left-0 w-[3px] rounded-l-lg"
            :style="{ backgroundColor: stripeColor }"
        />

        <!-- Icon tile -->
        <div
            class="shrink-0 w-9 h-9 rounded-md flex items-center justify-center"
            :style="{ backgroundColor: iconTileBg }"
        >
            <span class="material-symbols text-lg" :style="{ color: stripeColor }">folder_open</span>
        </div>

        <!-- Text -->
        <div class="flex flex-col min-w-0 flex-1">
            <span class="text-sm font-bold text-white truncate leading-tight">{{ folder.name }}</span>
            <span class="text-[10px] font-mono uppercase tracking-[0.1em] text-zinc-500 mt-0.5 tabular-nums">
                {{ folder.children_number ?? 0 }} {{ folder.children_number === 1 ? 'demo' : 'demos' }}
            </span>
        </div>

        <!-- Edit -->
        <button
            type="button"
            class="opacity-0 group-hover:opacity-100 transition-opacity w-7 h-7 flex items-center justify-center rounded-md text-zinc-500 hover:text-primary hover:bg-primary/10"
            title="Edit folder"
            @click.stop="$emit('edit', $event)"
        >
            <span class="material-symbols" style="font-size: 14px">edit</span>
        </button>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Folder {
    id: number;
    name: string;
    color?: string;
    children_number?: number;
}

const props = defineProps<{ folder: Folder }>();
defineEmits<{ open: []; edit: [event: Event] }>();

const stripeColor = computed(() => `#${props.folder.color ?? 'ff8c00'}`);
const iconTileBg = computed(() => `#${props.folder.color ?? 'ff8c00'}26`);
</script>
