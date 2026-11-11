<template>
    <MultiSelect
        v-model="selectedTags"
        :options="availableTags"
        placeholder="Select Tags"
        showClear
        @change="emitChange"
    />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import MultiSelect from '@/components/volt/MultiSelect.vue'

/* ----------------------------------
 * Types
 * ---------------------------------- */
type Tag = string
type TagsInput = Tag[] | Record<string, Tag>

/* ----------------------------------
 * Props & Emits
 * ---------------------------------- */
const props = defineProps<{
    tacticTags?: TagsInput
    teamTags?: Tag[] | null
    modelValue?: TagsInput
}>()

const emit = defineEmits<{
    (e: 'changed', value: Tag[]): void
    (e: 'update:modelValue', value: Tag[]): void
}>()

/* ----------------------------------
 * Constants
 * ---------------------------------- */
const BASE_TAGS: Tag[] = [
    'A',
    'B',
    'Mid',
    'Aggressive',
    'Defensive',
    'Fast',
    'Slow',
    'Fake',
    'Retake',
    'Controlled'
]

/* ----------------------------------
 * State
 * ---------------------------------- */
const selectedTags = ref<Tag[]>([])

/* ----------------------------------
 * Computed
 * ---------------------------------- */
const normalizedTacticTags = computed<Tag[]>(() => {
    const source = props.modelValue ?? props.tacticTags
    if (!source) return []

    return Array.isArray(source)
        ? [...source]
        : Object.values(source)
})

const availableTags = computed<Tag[]>(() => {
    const team = props.teamTags ?? []
    return Array.from(new Set([...BASE_TAGS, ...team]))
})

/* ----------------------------------
 * Watchers
 * ---------------------------------- */
watch(
    normalizedTacticTags,
    value => {
        selectedTags.value = [...value]
    },
    { immediate: true }
)

/* ----------------------------------
 * Event handlers
 * ---------------------------------- */
function emitChange() {
    emit('changed', selectedTags.value)
    emit('update:modelValue', selectedTags.value)
}
</script>
