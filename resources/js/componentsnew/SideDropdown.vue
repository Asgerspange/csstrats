<template>
    <Select
        v-model="selectedSide"
        :options="options"
        optionLabel="label"
        optionValue="value"
        placeholder="Select Side"
        showClear
        @change="emitChange"
    />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import Select from '@/components/volt/Select.vue'

/* ----------------------------------
 * Types
 * ---------------------------------- */
export type Side = 'CT' | 'T'

interface SideOption {
    label: string
    value: Side
}

/* ----------------------------------
 * Props & Emits
 * ---------------------------------- */
const props = defineProps<{
    modelValue?: Side | null
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: Side | null): void
    (e: 'changed', value: Side | null): void
}>()

/* ----------------------------------
 * State
 * ---------------------------------- */
const selectedSide = ref<Side | null>(props.modelValue ?? null)

/* ----------------------------------
 * Options
 * ---------------------------------- */
const options: SideOption[] = [
    { label: 'CT', value: 'CT' },
    { label: 'T', value: 'T' }
]

/* ----------------------------------
 * Sync prop → local
 * ---------------------------------- */
watch(
    () => props.modelValue,
    value => {
        selectedSide.value = value ?? null
    }
)

/* ----------------------------------
 * Emit handlers
 * ---------------------------------- */
function emitChange() {
    emit('update:modelValue', selectedSide.value)
    emit('changed', selectedSide.value)
}
</script>
