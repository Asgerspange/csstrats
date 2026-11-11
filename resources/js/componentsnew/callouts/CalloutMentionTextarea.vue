<template>
    <div class="relative">
        <textarea
            ref="textareaRef"
            :value="modelValue"
            @input="onInput"
            @keydown="onKeydown"
            class="custom-input text-sm resize-none w-full"
            :rows="rows"
            :placeholder="placeholder"
        />

        <!-- Callout dropdown -->
        <Teleport to="body">
            <div
                v-if="showDropdown && filteredZones.length"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-[9999] bg-neutral-900 border border-neutral-700 rounded-xl shadow-2xl overflow-hidden"
                style="min-width:200px;max-width:280px;"
            >
                <div class="px-3 py-2 border-b border-neutral-800">
                    <span class="text-[10px] text-neutral-500 uppercase font-bold tracking-widest">Callouts — {{ currentMapName }}</span>
                </div>
                <div style="max-height:220px;overflow-y:auto;">
                    <button
                        v-for="(zone, i) in filteredZones"
                        :key="zone.id"
                        @mousedown.prevent="insertCallout(zone)"
                        class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 transition-colors"
                        :class="i === activeIndex
                            ? 'bg-orange-500/20 text-white'
                            : 'text-neutral-300 hover:bg-neutral-800'"
                    >
                        <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" :style="{ background: zone.color }"></span>
                        {{ zone.display_name }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, inject, isRef } from 'vue'

const props = defineProps<{
    modelValue: string
    placeholder?: string
    rows?: number
    mapName?: string        // current tactic map — filters zones to this map
    teamId?: number | null
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void
}>()

// Zones are injected from a parent provider — handle both ref and raw array
const _injected = inject<any>('callout-zones', [])
const allZones = computed(() => {
    const v = isRef(_injected) ? _injected.value : _injected
    return Array.isArray(v) ? v : []
})

const textareaRef = ref<HTMLTextAreaElement | null>(null)
const dropdownRef = ref<HTMLDivElement | null>(null)
const showDropdown = ref(false)
const searchQuery = ref('')
const activeIndex = ref(0)
const triggerPos = ref(0) // caret position when / was typed
const dropdownStyle = ref({})

const currentMapName = computed(() => props.mapName ?? '')

const filteredZones = computed(() => {
    const q = searchQuery.value.toLowerCase()
    return allZones.value
        .filter(z => {
            // Filter to current map if mapName is set
            if (props.mapName && z.map_name !== props.mapName) return false
            return z.display_name.toLowerCase().includes(q)
        })
        .slice(0, 12)
})

function onInput(e: Event) {
    const target = e.target as HTMLTextAreaElement
    emit('update:modelValue', target.value)

    const caret = target.selectionStart ?? 0
    const textBefore = target.value.slice(0, caret)

    // Find the last / before the caret
    const slashIdx = textBefore.lastIndexOf('/')
    if (slashIdx !== -1) {
        const afterSlash = textBefore.slice(slashIdx + 1)
        // Only open dropdown if we're right after /word (no spaces)
        if (!afterSlash.includes(' ') && !afterSlash.includes('\n')) {
            searchQuery.value = afterSlash
            activeIndex.value = 0
            triggerPos.value = slashIdx
            showDropdown.value = true
            nextTick(positionDropdown)
            return
        }
    }
    showDropdown.value = false
}

function onKeydown(e: KeyboardEvent) {
    if (!showDropdown.value) return

    if (e.key === 'ArrowDown') {
        e.preventDefault()
        activeIndex.value = Math.min(activeIndex.value + 1, filteredZones.value.length - 1)
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        activeIndex.value = Math.max(activeIndex.value - 1, 0)
    } else if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault()
        if (filteredZones.value[activeIndex.value]) {
            insertCallout(filteredZones.value[activeIndex.value])
        }
    } else if (e.key === 'Escape') {
        showDropdown.value = false
    }
}

function insertCallout(zone: any) {
    const el = textareaRef.value
    if (!el) return

    const val = props.modelValue
    const before = val.slice(0, triggerPos.value)
    const after = val.slice(el.selectionStart ?? triggerPos.value + searchQuery.value.length + 1)

    // Embed as [callout:zone_id:display_name] token
    const token = `[callout:${zone.id}:${zone.display_name}]`
    const newVal = before + token + after

    emit('update:modelValue', newVal)
    showDropdown.value = false

    nextTick(() => {
        if (el) {
            const pos = (before + token).length
            el.setSelectionRange(pos, pos)
            el.focus()
        }
    })
}

function positionDropdown() {
    const el = textareaRef.value
    if (!el) return

    const rect = el.getBoundingClientRect()
    const pos = getCaretCoordinates(el, el.selectionStart ?? 0)

    // pos gives px offset from top-left of textarea
    const top = rect.top + pos.top + pos.height + 4
    const left = rect.left + pos.left

    // Keep dropdown on screen horizontally
    const dropdownWidth = 280
    const adjustedLeft = Math.min(left, window.innerWidth - dropdownWidth - 8)

    dropdownStyle.value = {
        top: `${top}px`,
        left: `${Math.max(8, adjustedLeft)}px`,
    }
}

/**
 * Calculates pixel coordinates of a caret position inside a textarea.
 * Creates a mirror div with identical styles, measures where the caret is.
 */
function getCaretCoordinates(el: HTMLTextAreaElement, position: number) {
    const mirror = document.createElement('div')
    const computed = window.getComputedStyle(el)

    mirror.style.cssText = [
        'position:absolute', 'visibility:hidden', 'overflow:hidden', 'white-space:pre-wrap',
        'word-wrap:break-word', 'box-sizing:border-box',
        `width:${el.offsetWidth}px`,
        `padding:${computed.padding}`,
        `border:${computed.border}`,
        `font:${computed.font}`,
        `line-height:${computed.lineHeight}`,
        `letter-spacing:${computed.letterSpacing}`,
    ].join(';')

    const textBeforeCaret = el.value.slice(0, position)
    mirror.textContent = textBeforeCaret

    const caret = document.createElement('span')
    caret.textContent = el.value.slice(position) || '.'
    mirror.appendChild(caret)

    document.body.appendChild(mirror)
    const caretRect = caret.getBoundingClientRect()
    const mirrorRect = mirror.getBoundingClientRect()
    document.body.removeChild(mirror)

    const lineHeight = parseInt(computed.lineHeight) || 20

    return {
        top: caretRect.top - mirrorRect.top - el.scrollTop,
        left: caretRect.left - mirrorRect.left,
        height: lineHeight,
    }
}

// Close dropdown on outside click
function onOutsideClick(e: MouseEvent) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        showDropdown.value = false
    }
}

watch(showDropdown, (v) => {
    if (v) document.addEventListener('mousedown', onOutsideClick)
    else document.removeEventListener('mousedown', onOutsideClick)
})
</script>

<style scoped>
.custom-input {
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    padding: 0.75rem;
    color: white;
    font-size: 0.875rem;
    transition: all 0.2s;
    width: 100%;
    box-sizing: border-box;
}
.custom-input:focus {
    outline: none;
    border-color: var(--color-primary, #f97316);
    background-color: rgba(255, 255, 255, 0.08);
}
</style>