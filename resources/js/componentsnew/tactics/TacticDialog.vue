<script setup lang="ts">
import { computed, reactive, ref, watch, toRefs, onMounted, onUnmounted, type Ref } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';

import Dialog from '@/components/volt/Dialog.vue';
import Select from '@/components/volt/Select.vue';
import Button from '@/components/volt/Button.vue';
import ViewerIframe from '@/componentsnew/demo/ViewerIframe.vue';
import CalloutRenderer from '@/componentsnew/callouts/CalloutRenderer.vue';
import RoundPlanViewer from '@/PagesNew/RoundPlanner/RoundPlanViewer.vue';

import { useApi } from '@/composables/useApi';
import useStratbooks from '@/composables/useStratbooks';
import useStratbookTactics, { useStratbookTacticsScroll } from '@/composables/useStratbookTactics';
import { useWeaponLoadout } from '@/composables/useWeaponLoadout';
import { tacticLabel } from '@/lib/tacticLabel';
import { useStore } from '@/store';
import { useCallouts } from '@/composables/useCallouts';
import { useToast } from '@/composables/useToast';

const props = withDefaults(defineProps<{
    tacticId: number | null;
    /** 'library' = read-only library tactic (add-to-stratbook). 'stratbook' = owned tactic (edit/delete/export). */
    context?: 'library' | 'stratbook';
}>(), { context: 'library' });

const emit = defineEmits<{ (e: 'edit', tactic: any): void }>();
const visible = defineModel<boolean>('visible', { default: false });

const isStratbook = props.context === 'stratbook';

const toast = useToast();
const { findWeapon, getGroupedLoadout } = useWeaponLoadout();
const { tacticId } = toRefs(props);

/* ── Media tabs: Round Demo (2D playback) + Video (YouTube + POV clips) ──────────
   The video tab surfaces POV as a feature: a row of player chips lets the
   user jump the video to each player's timestamped moment. The per-card
   "POV" button switches to the video tab and selects that player. */
const mediaTab = ref<'demo' | 'video'>('demo');
const povPlayer = ref<number | null>(null);

function extractIframeSrc(html: string | null | undefined): string {
    if (!html) return '';
    const match = html.match(/src="([^"]+)"/);
    return match ? match[1] : '';
}
function withAutoplay(src: string): string {
    if (!src) return '';
    return src + (src.includes('?') ? '&' : '?') + 'autoplay=1';
}

function selectPov(index: number) {
    povPlayer.value = index;
    mediaTab.value = 'video';
}

/* ── Stratbook-only UI state ───────────────────────────────────── */
const isExporting = ref(false);
const isExpanded = ref(false);            // snapshot lightbox
const zoomLevel = ref(1);
const viewingRoundPlan = ref(false);
const viewingRoundPlanId = ref<number | null>(null);

/* ── Library-only UI state ─────────────────────────────────────── */
// Shares the "last used stratbook" with the Tactics page so the Add target
// stays consistent whether you add from a card or from this dialog.
const TARGET_KEY = 'tactics-target-stratbook';
const selectedStratbook = ref<number>(Number(localStorage.getItem(TARGET_KEY)) || 0);
watch(selectedStratbook, v => {
    if (v) localStorage.setItem(TARGET_KEY, String(v));
});

/* ── Data source + actions (mode-specific) ─────────────────────── */
const route = useRoute();
const stratbookId = computed(() => Number(route.params.id));

let tactic!: Ref<any>;
let isLoading!: Ref<boolean>;
let isError!: Ref<boolean>;
let refetch: () => void = () => {};
let stratbooks: Ref<any[]> = ref([]);

let exportTactic: () => void | Promise<void> = () => {};
let editTactic: () => void = () => {};
let deleteTactic: () => void | Promise<void> = () => {};
let addToStratbook: () => void = () => {};
const openRoundPlan = (t: any) => {
    viewingRoundPlanId.value = t.round_plan.id;
    viewingRoundPlan.value = true;
};

if (isStratbook) {
    // Callouts power CalloutRenderer inside player descriptions
    const store = useStore();
    const { selectedTeamId } = storeToRefs(store);
    const { provideCallouts } = useCallouts(selectedTeamId.value);
    provideCallouts();

    const { StratbookTactics } = useStratbookTactics(stratbookId);
    const filters = reactive({ map: null, side: null, type: null, tags: [] as string[] });
    const { refetch: refetchList } = useStratbookTacticsScroll(stratbookId, filters);

    const q = StratbookTactics.find(tacticId);
    tactic = q.data;
    isLoading = q.isLoading;
    isError = q.isError;
    refetch = q.refetch;

    exportTactic = async () => {
        if (!tacticId.value || isExporting.value) return;
        try {
            isExporting.value = true;
            const blob = await StratbookTactics.export(tacticId.value);
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `${tactic.value?.name || 'tactic'}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            toast.error('Export failed', 'Could not generate the PDF. Please try again.');
        } finally {
            isExporting.value = false;
        }
    };

    editTactic = () => {
        if (tactic.value) emit('edit', tactic.value);
    };

    deleteTactic = async () => {
        if (!tacticId.value) return;
        if (!window.confirm('Are you sure you want to delete this tactic? This action cannot be undone.')) return;
        try {
            await StratbookTactics.delete(tacticId.value);
            visible.value = false;
            refetchList();
        } catch (error) {
            toast.error('Delete failed', 'Could not delete the tactic. Please try again.');
        }
    };
} else {
    const { useGet } = useApi();
    const q = useGet([`tactic/${props.tacticId}`], `/api/tactics/${props.tacticId}`);
    tactic = q.data;
    isLoading = computed(() => !q.data.value);
    isError = ref(false);

    const { Stratbooks } = useStratbooks();
    stratbooks = Stratbooks.get().data;

    const { StratbookTactics: target } = useStratbookTactics(selectedStratbook);
    addToStratbook = () => {
        if (!tactic.value || !selectedStratbook.value) return;
        target.create({ tacticId: props.tacticId }).then(() => {
            toast.success('Tactic added', 'The tactic has been added to your stratbook.');
        }).catch(() => {
            toast.error('Addition failed', 'Failed to add tactic to stratbook. Please try again.');
        });
    };
}

/* Summary header (matches the tactic card's data-first identity) */
const sideClass = computed(() =>
    tactic.value?.side === 'CT'
        ? 'border-sky-400/30 bg-sky-400/15 text-sky-200'
        : 'border-[var(--color-primary)]/30 bg-[var(--color-primary)]/15 text-[var(--color-primary)]'
);
const formattedDate = computed(() => {
    const raw = tactic.value?.matchdate;
    if (!raw) return '';
    const d = new Date(raw);
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
});
const displayName = computed(() => (tactic.value ? tacticLabel(tactic.value) : 'Tactic Details'));

/* ── Media availability + POV ───────────────────────────────────────────────── */
const hasDemo = computed(() => !!tactic.value?.demo?.demo_id);
const videoSrc = computed(() => extractIframeSrc(tactic.value?.video));
const hasVideo = computed(() => !!videoSrc.value);
const povPlayers = computed(() => {
    const players = tactic.value?.team?.players ?? [];
    const timestamps = tactic.value?.player_timestamps ?? [];
    return players
        .map((p: any, i: number) => ({ index: i, name: p.name }))
        .filter(({ index }) => !!extractIframeSrc(timestamps[index]?.timestamps));
});
const hasPov = computed(() => povPlayers.value.length > 0);
const currentVideoSrc = computed(() => {
    if (povPlayer.value !== null) {
        const ts = tactic.value?.player_timestamps?.[povPlayer.value]?.timestamps;
        return withAutoplay(extractIframeSrc(ts));
    }
    return videoSrc.value;
});
const showMediaTabs = computed(() => hasDemo.value && hasVideo.value);

watch(tactic, (t) => {
    if (!t) return;
    mediaTab.value = t?.demo?.demo_id ? 'demo' : 'video';
    povPlayer.value = null;
}, { immediate: true });

/* ESC closes the lightbox before the Dialog handles it (stratbook only) */
const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isExpanded.value) {
        e.stopImmediatePropagation();
        e.preventDefault();
        isExpanded.value = false;
    }
};
onMounted(() => window.addEventListener('keydown', handleKeydown, true));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown, true));
</script>

<template>
    <RoundPlanViewer
        v-if="isStratbook && viewingRoundPlan"
        :plan-id="viewingRoundPlanId"
        @close="viewingRoundPlan = false"
    />

    <Dialog
        v-model:visible="visible"
        :closable="true"
        :draggable="false"
        :modal="true"
        :resizable="false"
        :dismissableMask="!isStratbook"
        :closeOnEscape="true"
        :header="displayName"
        appendTo="body"
        class="w-[92vw] max-w-[900px]"
    >
        <div v-if="isLoading" class="flex flex-col items-center justify-center min-h-[40vh] gap-4">
            <div class="w-8 h-8 rounded-full border-2 border-white/10 border-t-primary animate-spin"></div>
            <p class="text-sm text-zinc-500">Fetching tactic details...</p>
        </div>

        <div v-else-if="isError" class="p-8 text-center">
            <p class="text-red-500">Failed to load tactic. Please try again.</p>
            <Button @click="refetch" class="mt-4" label="Retry" />
        </div>

        <template v-else-if="tactic">
            <!-- Data-first summary header -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pb-3">
                <span class="text-sm font-semibold text-white">{{ tactic.team_name || 'Unknown team' }}</span>
                <span class="font-mono text-[11px] uppercase tracking-wider text-zinc-500">{{ tactic.map }}</span>
                <span class="rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wider border" :class="sideClass">{{ tactic.side }}</span>
                <span v-if="tactic.type" class="font-mono text-[10px] uppercase tracking-wider text-zinc-400">{{ tactic.type }}</span>
                <span v-if="formattedDate" class="ml-auto font-mono text-[10px] tabular-nums text-zinc-500">{{ formattedDate }}</span>
            </div>

            <!-- ── Media tabs: Round Demo + Video (with POV) ─────────────────────── -->
            <div v-if="hasDemo || hasVideo" class="mb-4">
                <!-- Tab bar (only when both demo and video exist) -->
                <div v-if="showMediaTabs" class="flex items-center gap-1 mb-2 border-b border-[var(--app-border,oklch(1_0_0_/_0.09))]">
                    <button
                        @click="mediaTab = 'demo'; povPlayer = null"
                        class="px-3 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.12em] transition-colors -mb-px border-b-2"
                        :class="mediaTab === 'demo' ? 'text-primary border-primary' : 'text-zinc-500 border-transparent hover:text-zinc-300'"
                    >Round Demo</button>
                    <button
                        @click="mediaTab = 'video'"
                        class="px-3 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.12em] transition-colors -mb-px border-b-2"
                        :class="mediaTab === 'video' ? 'text-primary border-primary' : 'text-zinc-500 border-transparent hover:text-zinc-300'"
                    >Video<span v-if="hasPov" class="ml-1 text-[8px] opacity-80">· POV</span></button>
                </div>

                <!-- Demo tab: 2D round playback -->
                <div v-if="mediaTab === 'demo' && hasDemo" class="relative w-full aspect-video bg-[var(--app-surface-2,oklch(1_0_0_/_0.04))] rounded-md overflow-hidden border border-[var(--app-border,oklch(1_0_0_/_0.09))]">
                    <ViewerIframe
                        :demoId="tactic?.demo.demo_id"
                        :roundIndex="tactic?.demo.round"
                        :map="tactic?.demo.map"
                    />
                </div>

                <!-- Video tab: YouTube embed + POV player chips -->
                <div v-else-if="mediaTab === 'video' && hasVideo">
                    <div class="relative w-full aspect-video bg-black rounded-md overflow-hidden border border-[var(--app-border,oklch(1_0_0_/_0.09))]">
                        <iframe
                            v-if="currentVideoSrc"
                            :src="currentVideoSrc"
                            class="w-full h-full"
                            frameborder="0"
                            allow="autoplay; encrypted-media; fullscreen"
                            allowfullscreen
                        />
                    </div>
                    <!-- POV chips: jump to each player's timestamped moment -->
                    <div v-if="hasPov" class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="font-mono text-[10px] uppercase tracking-[0.12em] text-zinc-500 mr-1">POV</span>
                        <button
                            @click="povPlayer = null"
                            class="pov-chip"
                            :class="povPlayer === null ? 'pov-chip--active' : ''"
                        >Full video</button>
                        <button
                            v-for="p in povPlayers"
                            :key="p.index"
                            @click="selectPov(p.index)"
                            class="pov-chip"
                            :class="povPlayer === p.index ? 'pov-chip--active' : ''"
                        >{{ p.name }}</button>
                    </div>
                </div>
            </div>

            <!-- Description (full width, compact) -->
            <section class="mb-4">
                <h3 class="font-mono text-[10px] font-semibold uppercase tracking-[0.12em] text-zinc-500 mb-1.5">Description</h3>
                <p class="text-sm text-zinc-300 leading-relaxed bg-[var(--app-surface-2,oklch(1_0_0_/_0.04))] p-3 rounded-md border border-[var(--app-border,oklch(1_0_0_/_0.09))] line-clamp-3">
                    {{ tactic?.description || 'No description provided.' }}
                </p>
            </section>

            <!-- Interactive round plan (stratbook only, full width) -->
            <div
                v-if="isStratbook && tactic.round_plan"
                class="group relative cursor-pointer overflow-hidden rounded-md border border-[var(--app-border,oklch(1_0_0_/_0.09))] bg-[var(--app-surface-2,oklch(1_0_0_/_0.04))] hover:bg-[var(--app-surface-3,oklch(1_0_0_/_0.07))] transition-colors duration-200 p-3 flex items-center justify-between mb-4"
                @click="openRoundPlan(tactic)"
            >
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-md bg-primary/15 flex items-center justify-center text-primary border border-primary/25 group-hover:scale-105 transition-transform duration-200">
                        <span class="material-symbols text-lg">route</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-white font-semibold text-sm">Interactive Round Plan</span>
                        <span class="text-xs text-zinc-400">View animated player paths, utility, and timings</span>
                    </div>
                </div>
                <div class="text-primary opacity-50 group-hover:opacity-100 transition-all -translate-x-2 group-hover:translate-x-0 transform duration-200">
                    <span class="material-symbols text-2xl">arrow_right_alt</span>
                </div>
            </div>

            <!-- Board snapshot (stratbook only, compact thumbnail) -->
            <div
                v-if="isStratbook && tactic?.snapshot?.url"
                class="group relative cursor-pointer overflow-hidden rounded-md border border-[var(--app-border,oklch(1_0_0_/_0.09))] mb-4 h-32"
                @click="isExpanded = true"
            >
                <img
                    :src="tactic.snapshot.url"
                    alt="Tactic Snapshot"
                    class="w-full h-full object-cover transition-transform group-hover:scale-105"
                />
                <div class="absolute inset-0 flex items-center justify-center bg-[oklch(0.08_0.012_250)]/50 opacity-0 transition-opacity group-hover:opacity-100">
                    <div class="rounded-md bg-primary p-2 text-black">
                        <i class="pi pi-expand text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Team Loadouts (full-width player grid) -->
            <section>
                <h3 class="font-mono text-[10px] font-semibold uppercase tracking-[0.12em] text-zinc-500 mb-2">Team Loadouts</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    <div
                        v-for="(player, index) in tactic.team?.players"
                        :key="index"
                        class="flex flex-col p-3 bg-[var(--app-surface-2,oklch(1_0_0_/_0.04))] rounded-md border border-[var(--app-border,oklch(1_0_0_/_0.09))]"
                    >
                        <span class="text-sm font-semibold text-white uppercase tracking-tight truncate" :title="player.name">{{ player.name }}</span>
                        <span class="text-xs text-zinc-400 leading-relaxed mt-1">
                            <CalloutRenderer
                                v-if="isStratbook"
                                :text="tactic.player_descriptions?.[index]?.descriptions ?? ''"
                                :map-image-path="'/images/demo/de_' + tactic.map?.toLowerCase().replace(' ', '_') + '.svg'"
                            />
                            <template v-else>
                                {{ tactic.player_descriptions?.[index]?.descriptions || 'Standard positioning.' }}
                            </template>
                        </span>

                        <!-- Weapons: inline row, grouped -->
                        <div v-if="tactic.player_loadouts?.[index]?.loadouts" class="flex items-center gap-1.5 mt-2 flex-wrap">
                            <img
                                v-for="weapon in getGroupedLoadout(tactic.player_loadouts[index].loadouts).primaries"
                                :key="weapon"
                                :src="findWeapon(weapon)"
                                class="h-6 object-contain filter drop-shadow-md"
                            />
                            <img
                                v-for="weapon in getGroupedLoadout(tactic.player_loadouts[index].loadouts).secondaries"
                                :key="weapon"
                                :src="findWeapon(weapon)"
                                class="h-5 object-contain opacity-90"
                            />
                            <img
                                v-for="weapon in getGroupedLoadout(tactic.player_loadouts[index].loadouts).utilities"
                                :key="weapon"
                                :src="findWeapon(weapon)"
                                class="h-4 object-contain opacity-70"
                            />
                        </div>

                        <button
                            v-if="tactic.player_timestamps?.[index]?.timestamps"
                            @click="selectPov(index)"
                            class="flex items-center gap-1 text-[11px] font-semibold text-primary border border-primary/30 bg-primary/10 hover:bg-primary/20 rounded px-2 py-0.5 transition-colors mt-2 self-start"
                            title="Jump to this player's POV in the video"
                        >
                            <span class="material-symbols" style="font-size:12px">videocam</span>
                            POV
                        </button>
                    </div>
                </div>
            </section>
        </template>

        <template #footer>
            <div class="flex justify-between w-full items-center gap-2">
                <Button @click="visible = false" severity="secondary" :variant="isStratbook ? 'text' : undefined">Close</Button>

                <!-- Stratbook: manage the owned tactic -->
                <div v-if="isStratbook" class="flex gap-2">
                    <Button @click="deleteTactic" icon="pi pi-trash" label="Delete" />
                    <!-- <Button @click="exportTactic" :loading="isExporting" icon="pi pi-file-pdf" label="Download PDF" /> -->
                    <Button @click="editTactic" icon="pi pi-pencil" label="Edit" severity="primary" />
                </div>

                <!-- Library: add the tactic to one of the team's stratbooks -->
                <div v-else class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 w-full sm:w-auto sm:ml-auto">
                    <Select
                        v-model="selectedStratbook"
                        :options="stratbooks"
                        optionLabel="stratbook_name"
                        optionValue="id"
                        placeholder="Select a Stratbook"
                        class="w-full sm:flex-1 sm:min-w-[12rem]"
                    />
                    <Button
                        @click="addToStratbook"
                        icon="pi pi-plus"
                        label="Add to Stratbook"
                        :disabled="!selectedStratbook"
                        class="shrink-0"
                    />
                </div>
            </div>
        </template>
    </Dialog>

    <!-- Board snapshot lightbox (stratbook only) -->
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="isStratbook && isExpanded"
                class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[oklch(0.04_0.012_250)]"
                @click.self="isExpanded = false"
            >
                <div class="absolute top-0 w-full flex justify-between items-center z-[10000] p-6 bg-gradient-to-b from-[oklch(0.04_0.012_250)]/60 to-transparent">
                    <h2 class="text-white text-xl font-semibold uppercase tracking-widest">{{ tactic?.name }} Board</h2>
                    <div class="flex gap-4">
                        <Button as="a" severity="secondary" rounded :href="tactic?.snapshot?.url" download target="_blank">
                            <span class="pi pi-download"></span>
                        </Button>
                        <Button severity="primary" rounded @click="isExpanded = false">
                            <span class="pi pi-times"></span>
                        </Button>
                    </div>
                </div>

                <div class="relative w-full h-full flex items-center justify-center p-12 overflow-auto">
                    <img
                        :src="tactic?.snapshot?.url"
                        class="max-h-full max-w-full shadow-2xl transition-transform duration-300"
                        :style="{ transform: `scale(${zoomLevel})` }"
                    />
                </div>

                <div class="absolute bottom-8 px-4 py-2 bg-white/5 rounded-full border border-white/10">
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-widest">
                        Press <span class="text-primary font-bold">ESC</span> to Close
                    </span>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
    transform: scale(1.05);
}

/* POV chips — jump to each player's timestamped moment in the video */
.pov-chip {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    border: 1px solid oklch(1 0 0 / 0.12);
    background: transparent;
    color: oklch(1 0 0 / 0.6);
    font-size: 0.6875rem;
    font-weight: 500;
    cursor: pointer;
    transition: color 150ms ease, border-color 150ms ease, background 150ms ease;
}
.pov-chip:hover {
    color: oklch(1 0 0 / 0.9);
    border-color: oklch(1 0 0 / 0.2);
}
.pov-chip--active {
    color: var(--color-primary, #ff8c00);
    border-color: oklch(from var(--color-primary, #ff8c00) l c h / 0.5);
    background: oklch(from var(--color-primary, #ff8c00) l c h / 0.12);
}
.pov-chip--active:hover {
    color: var(--color-primary, #ff8c00);
    border-color: oklch(from var(--color-primary, #ff8c00) l c h / 0.6);
    background: oklch(from var(--color-primary, #ff8c00) l c h / 0.16);
}
</style>
