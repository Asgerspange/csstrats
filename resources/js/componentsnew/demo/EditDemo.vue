<script setup lang="ts">
import Popover from '@/components/volt/Popover.vue';
import InputText from '@/components/volt/InputText.vue';
import Button from '@/components/volt/Button.vue';
import MultiSelect from '@/components/volt/MultiSelect.vue';
import Select from '@/components/volt/Select.vue';
import { ref, watch } from 'vue';
import useTeams from '@/composables/useTeams';
import useDemos from '@/composables/useDemos';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from '@/composables/useToast';
const toast = useToast();
const confirm = useConfirm();

const { Teams } = useTeams();
const { Demos } = useDemos();
const { data: teams } = Teams.get();

const props = withDefaults(defineProps<{
    demo: any,
    folders: any[],
    moveBack?: boolean
}>(), {
    moveBack: false
});

const demoName = ref(props.demo.name);
const demoTeams = ref(props.demo?.shared_teams?.map((team: any) => team.id) || []);
const demoFolder = ref(props.demo.demo_folder_id || null);

const dop = ref();

defineExpose({
    toggle: (event: any) => dop.value.toggle(event)
});

const saveDemo = () => {
    Demos.update(props.demo.id, {
        demo_name: demoName.value,
        shared_teams: demoTeams.value,
        demo_folder_id: demoFolder.value
    }).then(() => {
        toast.success("Demo updated", "Your demo has been updated successfully.");
    }).catch(() => {
        toast.error("Failed to update demo", "Please try again later.");
    }).finally(() => {
        dop.value.hide();
    });
};

watch(() => props.demo, (newDemo) => {
    demoName.value = newDemo.name;
    demoTeams.value = newDemo?.shared_teams?.map((team: any) => team.id) || [];
}, { immediate: true });

const deleteDemo = () => {
    confirm.require({
        message: 'Are you sure you want to proceed?',
        header: 'Confirmation',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Confirm'
        },
        accept: () => {
            Demos.delete(props.demo.id);
            dop.value.hide();
            toast.success("Demo deleted", "Your demo has been deleted successfully.");
        },
        reject: () => {
            dop.value.hide();
        }
    });
};
</script>

<template>
    <Popover ref="dop">
        <div class="flex flex-col gap-4 w-[22rem] p-2">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-white text-lg">Edit Demo</h3>
                <Button class="material-symbols text-red-400 !bg-transparent border-0" rounded @click="deleteDemo()">
                    delete
                </Button>
            </div>
            
            <div class="flex flex-col gap-2">
                <label class="text-sm text-gray-400">Demo Name</label>
                <InputText v-model="demoName" class="w-full" placeholder="Enter demo name..." />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm text-gray-400">Move to Folder</label>
                <Button v-if="moveBack" label="Move Back to Main Folder" @click="demoFolder = null" class="w-full" />
                <Select v-else
                    v-model="demoFolder"
                    :options="folders"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select Folder"
                    :maxSelectedLabels="2"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm text-gray-400">Share with Teams</label>
                <MultiSelect
                    v-model="demoTeams"
                    :options="teams"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Select Teams"
                    :maxSelectedLabels="2"
                />
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-2 border-t border-white/10">
                <Button 
                    label="Cancel" 
                    severity="secondary" 
                    @click="dop.value.hide()" 
                />
                <Button 
                    label="Save Changes" 
                    @click="saveDemo()" 
                />
            </div>
        </div>
    </Popover>
</template>

<style scoped>
/* Ensure the color picker square is large enough to click easily */
:deep(.p-colorpicker-preview) {
    width: 2rem;
    height: 2rem;
    border-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

</style>