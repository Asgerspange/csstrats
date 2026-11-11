<template>
    <div class="sbsw" :class="{ 'sbsw--open': open }">
        <!-- Trigger — mirrors the sidebar team / stratbook switcher row -->
        <button type="button" class="sbsw__trigger" @click.stop="toggle">
            <span class="material-symbols sbsw__lead">menu_book</span>
            <span class="sbsw__value" :class="{ 'sbsw__value--placeholder': !selected }">
                {{ selected ? selected.stratbook_name : placeholder }}
            </span>
            <span class="material-symbols sbsw__caret">unfold_more</span>
        </button>

        <!-- Click-away backdrop -->
        <div v-if="open" class="sbsw__backdrop" @click="close" />

        <transition name="sbsw-pop">
            <div v-if="open" class="sbsw__pop" @click.stop>
                <div class="sbsw__search">
                    <span class="material-symbols sbsw__search-icon">search</span>
                    <input
                        ref="searchEl"
                        v-model="search"
                        type="text"
                        placeholder="Search stratbooks…"
                        class="sbsw__search-input"
                        @keydown.esc="close"
                    />
                </div>

                <div class="sbsw__list sbsw-scroll">
                    <button
                        v-for="s in filtered"
                        :key="s.id"
                        type="button"
                        class="sbsw__item"
                        :class="{ 'sbsw__item--active': s.id === modelValue }"
                        @click="select(s.id)"
                    >
                        <span class="material-symbols sbsw__item-icon" :class="s.id === modelValue ? 'sbsw__item-icon--active' : ''">menu_book</span>
                        <span class="sbsw__item-name">{{ s.stratbook_name }}</span>
                        <span v-if="s.id === modelValue" class="material-symbols sbsw__check">check</span>
                    </button>

                    <div v-if="!filtered.length" class="sbsw__empty">
                        {{ loading ? 'Loading…' : (search ? 'No matches' : 'No stratbooks yet') }}
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';

const props = withDefaults(defineProps<{
    modelValue: number | null;
    options: any[];
    placeholder?: string;
    loading?: boolean;
}>(), {
    placeholder: 'Pick a stratbook…',
    loading: false,
});

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();

const open = ref(false);
const search = ref('');
const searchEl = ref<HTMLInputElement | null>(null);

const selected = computed(() => (props.options ?? []).find((s: any) => s.id === props.modelValue) ?? null);

const filtered = computed(() => {
    const list = props.options ?? [];
    const q = search.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((s: any) => s.stratbook_name?.toLowerCase().includes(q));
});

const close = () => { open.value = false; search.value = ''; };
const toggle = () => {
    open.value = !open.value;
    if (open.value) nextTick(() => searchEl.value?.focus());
    else search.value = '';
};
const select = (id: number) => { emit('update:modelValue', id); close(); };

// Close if the selected stratbook disappears from the list (e.g. deleted).
watch(() => props.options, (list) => {
    if (props.modelValue && list && !list.some((s: any) => s.id === props.modelValue)) {
        emit('update:modelValue', null);
    }
});
</script>

<style scoped>
.sbsw {
    --sbsw-surface:   var(--app-surface, var(--select-bg));
    --sbsw-surface-2: var(--app-surface-2, var(--card-btn-bg, oklch(0.24 0.016 250)));
    --sbsw-border:    var(--app-border-2, oklch(1 0 0 / 0.14));
    --sbsw-border-1:  var(--app-border, oklch(1 0 0 / 0.07));
    --sbsw-text:      var(--app-text, var(--primary-text-color, oklch(1 0 0 / 0.96)));
    --sbsw-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --sbsw-text-3:    var(--app-text-3, oklch(1 0 0 / 0.55));
    --sbsw-accent:    var(--color-primary, var(--main-color, #ff8c00));

    position: relative;
    display: inline-flex;
    min-width: 0;
}

/* ── Trigger ─────────────────────────────────────────────────────────────── */
.sbsw__trigger {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    max-width: 100%;
    padding: 0.4rem 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid var(--sbsw-border);
    background: var(--sbsw-surface);
    color: var(--sbsw-text);
    cursor: pointer;
    transition: border-color 150ms, background 150ms;
}
.sbsw__trigger:hover { border-color: var(--app-border-3, oklch(1 0 0 / 0.24)); }
.sbsw--open .sbsw__trigger { border-color: color-mix(in oklch, var(--sbsw-accent) 60%, transparent); }
.sbsw__lead { font-size: 16px; color: var(--sbsw-accent); flex-shrink: 0; }
.sbsw__value {
    flex: 1;
    min-width: 0;
    text-align: left;
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sbsw__value--placeholder { color: var(--sbsw-text-3); font-weight: 500; }
.sbsw__caret { font-size: 15px; color: var(--sbsw-text-3); flex-shrink: 0; }

/* ── Popover ─────────────────────────────────────────────────────────────── */
.sbsw__backdrop { position: fixed; inset: 0; z-index: 40; }
.sbsw__pop {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    z-index: 50;
    width: max(100%, 16rem);
    max-width: min(22rem, 90vw);
    border-radius: 0.5rem;
    border: 1px solid var(--sbsw-border);
    background: var(--sbsw-surface);
    box-shadow: 0 20px 40px -8px oklch(0 0 0 / 0.5);
    overflow: hidden;
}

.sbsw__search {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.6rem;
    border-bottom: 1px solid var(--sbsw-border-1);
}
.sbsw__search-icon { font-size: 15px; color: var(--sbsw-text-3); }
.sbsw__search-input {
    flex: 1;
    min-width: 0;
    background: none;
    border: none;
    outline: none;
    color: var(--sbsw-text);
    font-size: 0.75rem;
    padding: 0.1rem 0;
}
.sbsw__search-input::placeholder { color: var(--sbsw-text-3); }

.sbsw__list { padding: 0.375rem; max-height: 260px; overflow-y: auto; display: flex; flex-direction: column; gap: 1px; }

.sbsw__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.45rem 0.5rem;
    border: none;
    background: none;
    border-radius: 0.375rem;
    cursor: pointer;
    font-size: 0.75rem;
    color: var(--sbsw-text-2);
    transition: color 150ms, background 150ms;
}
.sbsw__item:hover { color: var(--sbsw-text); background: var(--sbsw-surface-2); }
.sbsw__item--active { color: var(--sbsw-text); background: color-mix(in oklch, var(--sbsw-accent) 12%, transparent); font-weight: 600; }
.sbsw__item-icon { font-size: 15px; color: var(--sbsw-text-3); flex-shrink: 0; }
.sbsw__item-icon--active { color: var(--sbsw-accent); }
.sbsw__item-name { flex: 1; min-width: 0; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sbsw__check { font-size: 15px; color: var(--sbsw-accent); flex-shrink: 0; margin-left: auto; }

.sbsw__empty { padding: 0.75rem; text-align: center; font-size: 0.6875rem; font-style: italic; color: var(--sbsw-text-3); }

.sbsw-scroll::-webkit-scrollbar { width: 3px; }
.sbsw-scroll::-webkit-scrollbar-thumb { background: var(--sbsw-border); border-radius: 10px; }

.material-symbols { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; display: inline-flex; align-items: center; justify-content: center; }

.sbsw-pop-enter-active { transition: opacity 150ms ease, transform 150ms cubic-bezier(0.4, 0, 0.2, 1); }
.sbsw-pop-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.sbsw-pop-enter-from, .sbsw-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(0.98); }

@media (prefers-reduced-motion: reduce) {
    .sbsw__trigger, .sbsw__item { transition: none; }
}
</style>
