<script setup lang="ts">
import { ref } from 'vue';
import useMaps from '@/composables/useMaps';
import Dialog from '@/components/volt/Dialog.vue';
import InputText from '@/components/volt/InputText.vue';
import Select from '@/components/volt/Select.vue';
import Button from '@/components/volt/Button.vue';
import { useToast } from '@/composables/useToast';
const toast = useToast();
const { Maps } = useMaps();
const { data: maps } = Maps.get()
const boardName = ref('');
const mapId = ref<number | null>(null);
const emit = defineEmits(['boardCreated']);

const visible = ref(false);
const create = () => {
    if (!boardName.value || !mapId.value) return;

    emit('boardCreated', { name: boardName.value, map_id: mapId.value });
    toast.success("Board created", "Your board has been created successfully.");
    boardName.value = '';
    mapId.value = null;
    visible.value = false;
};
</script>

<template>
    <Button @click="visible=true">Create Board</Button>

    <Dialog v-model:visible="visible" header="New Stratbook" :modal="true" appendTo="body" class="w-[400px]">
        <div class="flex flex-col gap-4 py-4">
            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-semibold">Board Name</label>
                <InputText id="name" v-model="boardName" placeholder="e.g. Mirage T-Side" class="w-full" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="map" class="text-sm font-semibold">Map</label>
                <Select id="map" v-model="mapId" :options="maps" optionLabel="display_name" option-value="id" placeholder="Select a map" class="w-full" />
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" severity="secondary" @click="visible = false" />
                <Button label="Create Board" @click="create" :disabled="!boardName || !mapId" />
            </div>
        </div>
    </Dialog>
</template>