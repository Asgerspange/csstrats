<template>
    <MultiSelect
        v-if="isMultiSelect"
        v-model="selectedMulti"
        showClear
        filter
        :options="teams"
        optionLabel="name"
        placeholder="Pro teams"
        :loading="isLoading"
        @change="emitMulti"
    />

    <Select
        v-else
        v-model="selectedSingle"
        filter
        :options="teams"
        optionLabel="name"
        placeholder="Pro team"
        :loading="isLoading"
        @change="emitSingle"
    />
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import Select from '@/components/volt/Select.vue'
import MultiSelect from '@/components/volt/MultiSelect.vue'
import { useApi } from '@/composables/useApi'

/* ----------------------------------
 * Types
 * ---------------------------------- */
interface ProTeam {
    id: number
    name: string
}

/* ----------------------------------
 * Props & Emits
 * ---------------------------------- */
const props = defineProps<{
    isMultiSelect?: boolean
    selectedTeams?: string[]
    modelValue?: string[]
}>()

const emit = defineEmits<{
    (e: 'changed', value: string[] | string | null): void
    (e: 'update:modelValue', value: string[] | string | null): void
}>()

/* ----------------------------------
 * State
 * ---------------------------------- */
const selectedSingle = ref<ProTeam | null>(null)
const selectedMulti = ref<ProTeam[]>([])

/* ----------------------------------
 * API
 * ---------------------------------- */
const { useGet } = useApi()

const { data, isLoading } = useGet<ProTeam[]>(
    ['pro-teams'],
    '/api/pro-teams'
)
const teams = computed<ProTeam[]>(() => data.value ?? [])


/* ----------------------------------
 * Sync incoming selection
 * ---------------------------------- */
const incomingTeams = computed<string[]>(() => props.modelValue ?? props.selectedTeams ?? [])

watch(
    () => [incomingTeams.value, teams.value],
    () => {
        if (!teams.value.length) return

        if (props.isMultiSelect) {
            selectedMulti.value = teams.value.filter(team =>
                incomingTeams.value.includes(team.name)
            )
        } else {
            selectedSingle.value =
                teams.value.find(team =>
                    incomingTeams.value[0] === team.name
                ) ?? null
        }
    },
    { immediate: true }
)

/* ----------------------------------
 * Emit handlers
 * ---------------------------------- */
function emitSingle() {
    const value = selectedSingle.value?.name ?? null
    emit('changed', value)
    emit('update:modelValue', value)
}

function emitMulti() {
    const value = selectedMulti.value.map(team => team.name)
    emit('changed', value)
    emit('update:modelValue', value)
}
</script>
