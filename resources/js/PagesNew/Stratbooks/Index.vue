<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import router from '@/router';
import useStratbooks from '@/composables/useStratbooks';
import useTeams from '@/composables/useTeams';
import CreateStratbook from '@/componentsnew/stratbooks/CreateStratbook.vue';
import InputText from '@/components/volt/InputText.vue';
import { useToast } from '@/composables/useToast';
import { usePlan } from '@/composables/usePlan';
import { useUpgradeModal } from '@/composables/useUpgradeModal';

const { Teams } = useTeams();
const { data: team } = Teams.find();
const vueRouter = useRouter();

// Redirect map_based teams to /maps
onMounted(() => {
    if (team.value?.entitlement_mode === 'map_based') {
        vueRouter.replace('/maps');
    }
});
watch(() => team.value?.entitlement_mode, (mode) => {
    if (mode === 'map_based') {
        vueRouter.replace('/maps');
    }
});

const { Stratbooks } = useStratbooks();
const { data: stratbooks, isPending } = Stratbooks.get();
const toast = useToast();
const { can } = usePlan();
const { open: openUpgrade } = useUpgradeModal();

const search = ref('');
const createVisible = ref(false);

const filtered = computed<any[]>(() => {
    const list = (stratbooks.value as any[]) ?? [];
    const q = search.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((s) => s.stratbook_name?.toLowerCase().includes(q));
});

// Pre-empt the stratbook cap before opening the create dialog. The backend
// re-checks on POST /api/stratbooks (PLAN_LIMIT_EXCEEDED), so a stale list
// only means the dialog opens and the real 403 fires.
const tryCreate = () => {
    if (!can('stratbooks', filtered.value.length)) {
        openUpgrade({ feature: 'stratbooks', requiredTier: 'Team' });
        return;
    }
    createVisible.value = true;
};

const open = (id: number) => router.push({ name: 'Stratbook', params: { id } });

// ── Inline rename ──────────────────────────────────────────────────────────────
const editingId = ref<number | null>(null);
const editName = ref('');
const vFocus = { mounted: (el: HTMLElement) => el.focus() };

const startEdit = (s: any) => { editingId.value = s.id; editName.value = s.stratbook_name; };
const cancelEdit = () => { editingId.value = null; editName.value = ''; };
const saveRename = async (id: number) => {
    if (!editName.value.trim()) return cancelEdit();
    try {
        await Stratbooks.update(id, { stratbook_name: editName.value.trim() });
        toast.success('Stratbook renamed');
    } catch (e) {
        toast.error('Could not rename stratbook');
    } finally {
        cancelEdit();
    }
};

const handleDelete = async (s: any) => {
    if (!confirm(`Delete "${s.stratbook_name}" permanently? This cannot be undone.`)) return;
    try {
        await Stratbooks.delete(s.id);
        toast.success('Stratbook deleted');
    } catch (e) {
        toast.error('Could not delete stratbook');
    }
};

const fmtDate = (d: string | null) => {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }); }
    catch { return ''; }
};
</script>

<template>
    <div class="sb-page">
        <!-- Header -->
        <header class="sb-head">
            <div class="sb-head__title">
                <span class="sb-head__kicker">Team</span>
                <h1 class="sb-head__heading">Stratbooks</h1>
                <span class="sb-head__count">{{ filtered.length }}</span>
            </div>
            <button class="sb-btn sb-btn--primary" @click="tryCreate">
                <span class="material-symbols sb-btn__icon">add</span>
                <span>New stratbook</span>
            </button>
        </header>

        <!-- Search -->
        <label class="sb-search">
            <span class="material-symbols sb-search__icon">search</span>
            <input v-model="search" type="search" placeholder="Search stratbooks…" class="sb-search__input" />
        </label>

        <!-- Loading -->
        <div v-if="isPending" class="sb-empty">Loading stratbooks…</div>

        <!-- Empty -->
        <div v-else-if="!filtered.length && !search" class="sb-empty">
            <div class="sb-empty__icon"><span class="material-symbols">menu_book</span></div>
            <h3 class="sb-empty__title">No stratbooks yet</h3>
            <p class="sb-empty__body">Create your first stratbook to start collecting your team's tactics.</p>
            <button class="sb-btn sb-btn--primary" @click="tryCreate">
                <span class="material-symbols sb-btn__icon">add</span>
                <span>New stratbook</span>
            </button>
        </div>

        <div v-else-if="!filtered.length" class="sb-empty sb-empty--filtered">No stratbooks match “{{ search }}”.</div>

        <!-- Grid -->
        <div v-else class="sb-grid">
            <div
                v-for="s in filtered"
                :key="s.id"
                class="sb-card"
                role="button"
                tabindex="0"
                @click="editingId === s.id ? null : open(s.id)"
                @keydown.enter="editingId === s.id ? null : open(s.id)"
            >
                <div class="sb-card__top">
                    <span class="material-symbols sb-card__icon">menu_book</span>
                    <div class="sb-card__menu" @click.stop>
                        <button class="sb-card__act" title="Rename" @click="startEdit(s)"><span class="material-symbols">edit</span></button>
                        <button class="sb-card__act sb-card__act--danger" title="Delete" @click="handleDelete(s)"><span class="material-symbols">delete</span></button>
                    </div>
                </div>

                <div v-if="editingId === s.id" class="sb-card__rename" @click.stop>
                    <InputText
                        v-model="editName"
                        v-focus
                        class="!text-sm w-full"
                        @keyup.enter="saveRename(s.id)"
                        @keyup.esc="cancelEdit"
                    />
                    <div class="sb-card__rename-actions">
                        <button class="material-symbols sb-card__act" title="Save" @click="saveRename(s.id)">check</button>
                        <button class="material-symbols sb-card__act" title="Cancel" @click="cancelEdit">close</button>
                    </div>
                </div>
                <h3 v-else class="sb-card__name">{{ s.stratbook_name }}</h3>

                <div class="sb-card__meta">
                    <span class="sb-card__stat tabular-nums">{{ s.tactics_count ?? 0 }} tactic{{ (s.tactics_count ?? 0) === 1 ? '' : 's' }}</span>
                    <span v-if="s.updated_at" class="sb-card__date">{{ fmtDate(s.updated_at) }}</span>
                </div>
            </div>

            <!-- New stratbook tile -->
            <button class="sb-card sb-card--new" @click="tryCreate">
                <span class="material-symbols">add</span>
                <span>New stratbook</span>
            </button>
        </div>

        <CreateStratbook v-model:visible="createVisible" />
    </div>
</template>

<style scoped>
.sb-page {
    --sb-surface:   var(--app-surface, oklch(0.20 0.014 250));
    --sb-surface-2: var(--app-surface-2, oklch(0.24 0.016 250));
    --sb-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --sb-border-2:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --sb-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --sb-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --sb-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --sb-accent:    var(--main-color, #ff8c00);

    height: 100%;
    overflow-y: auto;
    padding: 1rem;
    max-width: 1600px;
    margin: 0 auto;
    color: var(--sb-text);
}
@media (min-width: 768px) { .sb-page { padding: 2.5rem 3rem; } }

.sb-head { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.sb-head__title { display: flex; align-items: baseline; gap: 0.625rem; flex: 1; min-width: 0; }
.sb-head__kicker { font-size: 0.625rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.16em; color: var(--sb-text-3); }
.sb-head__heading { font-size: 1.5rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
.sb-head__count { font-size: 0.75rem; font-weight: 700; color: var(--sb-text-3); font-variant-numeric: tabular-nums; }

.sb-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.85rem; border-radius: 0.5rem; border: 1px solid var(--sb-border-2); background: var(--sb-surface-2); color: var(--sb-text); font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: background 150ms, border-color 150ms; }
.sb-btn--primary { background: var(--sb-accent); border-color: var(--sb-accent); color: oklch(0.18 0.02 250); }
.sb-btn--primary:hover { filter: brightness(1.08); }
.sb-btn__icon { font-size: 18px; }

.sb-search { display: flex; align-items: center; gap: 0.5rem; max-width: 24rem; margin-bottom: 1.25rem; padding: 0 0.75rem; border: 1px solid var(--sb-border); border-radius: 0.5rem; background: var(--sb-surface); }
.sb-search__icon { font-size: 18px; color: var(--sb-text-3); }
.sb-search__input { flex: 1; background: none; border: none; outline: none; color: var(--sb-text); font-size: 0.875rem; padding: 0.5rem 0; }

.sb-grid { display: grid; gap: 0.875rem; grid-template-columns: 1fr; }
@media (min-width: 560px)  { .sb-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 768px)  { .sb-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1600px) { .sb-grid { grid-template-columns: repeat(4, 1fr); } }

.sb-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    padding: 1rem;
    min-height: 7.5rem;
    border: 1px solid var(--sb-border);
    border-radius: 0.75rem;
    background: var(--sb-surface);
    cursor: pointer;
    text-align: left;
    transition: border-color 150ms, background 150ms, transform 150ms;
}
.sb-card:hover { border-color: color-mix(in oklch, var(--sb-accent) 45%, var(--sb-border)); background: var(--sb-surface-2); }
.sb-card:focus-visible { outline: 2px solid var(--sb-accent); outline-offset: 2px; }
.sb-card__top { display: flex; align-items: flex-start; justify-content: space-between; }
.sb-card__icon { font-size: 22px; color: var(--sb-accent); }
.sb-card__menu { display: flex; gap: 0.125rem; opacity: 0; transition: opacity 150ms; }
.sb-card:hover .sb-card__menu, .sb-card:focus-within .sb-card__menu { opacity: 1; }
.sb-card__act { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border: none; background: none; border-radius: 0.375rem; color: var(--sb-text-3); cursor: pointer; transition: color 150ms, background 150ms; }
.sb-card__act .material-symbols, .sb-card__act.material-symbols { font-size: 16px; }
.sb-card__act:hover { color: var(--sb-text); background: oklch(1 0 0 / 0.06); }
.sb-card__act--danger:hover { color: oklch(0.7 0.19 22); }
.sb-card__name { font-size: 0.95rem; font-weight: 700; margin: 0; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.sb-card__rename { display: flex; align-items: center; gap: 0.25rem; }
.sb-card__rename-actions { display: flex; gap: 0.125rem; }
.sb-card__meta { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.6875rem; color: var(--sb-text-3); }
.sb-card__stat { font-weight: 700; color: var(--sb-text-2); }

.sb-card--new { align-items: center; justify-content: center; gap: 0.4rem; color: var(--sb-text-3); border-style: dashed; background: none; font-weight: 600; font-size: 0.8125rem; }
.sb-card--new .material-symbols { font-size: 22px; }
.sb-card--new:hover { color: var(--sb-accent); border-color: color-mix(in oklch, var(--sb-accent) 45%, var(--sb-border)); }

.sb-empty { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 4rem 1rem; text-align: center; color: var(--sb-text-3); }
.sb-empty__icon { font-size: 2rem; color: var(--sb-text-3); margin-bottom: 0.25rem; }
.sb-empty__title { font-size: 1rem; font-weight: 700; color: var(--sb-text); margin: 0; }
.sb-empty__body { font-size: 0.8125rem; max-width: 24rem; margin: 0 0 0.75rem; }
.sb-empty--filtered { padding: 2.5rem 1rem; }

@media (prefers-reduced-motion: reduce) {
    .sb-card { transition: none; }
}
</style>
