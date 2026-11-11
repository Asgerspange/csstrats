<template>
    <Select
        v-model="selectedMapId"
        :options="mapOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Select a Map"
        filter
        showClear
        :loading="isLoading"
        :disabled="disabled"
        @change="onChange"
    />
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import Select from '@/components/volt/Select.vue'
import { useApi } from '@/composables/useApi'

/* ----------------------------------
 * Types
 * ---------------------------------- */
interface Map {
    id: number
    name: string
    slug: string
    display_name: string
    image_path: string | null
    width: number
    height: number
    active: boolean
}

interface SelectOption {
    label: string
    value: number
}

/* ----------------------------------
 * Props & Emits
 * ---------------------------------- */
const props = defineProps<{
    tacticMapId?: number | null
    modelValue?: number | string | null
    disabled?: boolean
}>()

const emit = defineEmits<{
    (e: 'changed', value: number | null): void
    (e: 'update:modelValue', value: number | null): void
}>()

/* ----------------------------------
 * State
 * ---------------------------------- */
const selectedMapId = ref<number | null>(null)

/* ----------------------------------
 * API
 * ---------------------------------- */
const { useGet } = useApi()

const { data: maps, isLoading } = useGet<Map[]>(
    ['maps'],
    '/api/maps'
)

/* ----------------------------------
 * Computed
 * ---------------------------------- */
const mapOptions = computed<SelectOption[]>(() =>
    maps.value?.map(map => ({
        label: map.display_name,
        value: map.id
    })) ?? []
)

/* ----------------------------------
 * Sync prop → state
 * ---------------------------------- */
watch(
    () => props.modelValue ?? props.tacticMapId,
    value => {
        if (typeof value === 'string') {
            const map = maps.value?.find(m => m.display_name === value)
            selectedMapId.value = map?.id ?? null
        } else {
            selectedMapId.value = value ?? null
        }
    },
    { immediate: true }
)

/* ----------------------------------
 * Handlers
 * ---------------------------------- */
function onChange() {
    emit('changed', selectedMapId.value)
    emit('update:modelValue', selectedMapId.value)
}
</script>
