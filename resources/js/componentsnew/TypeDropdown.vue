<template>
    <Select
        v-model="selectedType"
        showClear
        filter
        :options="availableTypes"
        placeholder="Select Type"
        @change="emitChange"
    />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Select from '@/components/volt/Select.vue'

/* ----------------------------------
 * Props & Emits
 * ---------------------------------- */
const props = defineProps<{
    tacticType?: string | null
    teamTypes?: string[] | null
    modelValue?: string | null
}>()

const emit = defineEmits<{
    (e: 'changed', value: string | null): void
    (e: 'update:modelValue', value: string | null): void
}>()

/* ----------------------------------
 * Base types
 * ---------------------------------- */
const BASE_TYPES = [
    'Pistol round',
    'Anti-eco',
    'Default',
    'Execute',
    'CT setup',
    'Forcebuy',
    'Eco',
    'Setup'
]

/* ----------------------------------
 * Reactive state
 * ---------------------------------- */
const selectedType = ref<string | null>(props.modelValue ?? props.tacticType ?? null)

/* ----------------------------------
 * Computed merged types
 * ---------------------------------- */
const availableTypes = computed<string[]>(() => {
    const team = props.teamTypes ?? []
    return Array.from(new Set([...BASE_TYPES, ...team]))
})

/* ----------------------------------
 * Watch prop for initial value change
 * ---------------------------------- */
watch(
    () => props.modelValue ?? props.tacticType,
    value => {
        selectedType.value = value ?? null
    }
)

/* ----------------------------------
 * Event handlers
 * ---------------------------------- */
function emitChange() {
    emit('changed', selectedType.value)
    emit('update:modelValue', selectedType.value)
}
</script>
