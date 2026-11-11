<script setup lang="ts">
import Popover from '@/components/volt/Popover.vue';
import InputText from '@/components/volt/InputText.vue';
import Button from '@/components/volt/Button.vue';
import MultiSelect from '@/components/volt/MultiSelect.vue';
import ColorPicker from 'primevue/colorpicker';
import { ref, watch } from 'vue';
import useTeams from '@/composables/useTeams';
import useDemoFolders from '@/composables/useDemoFolders';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from '@/composables/useToast';
const toast = useToast();
const confirm = useConfirm();

const { Teams } = useTeams();
const { DemoFolders } = useDemoFolders();
const { data: teams } = Teams.get();

const props = defineProps<{
    folder: any
}>();

const folderName = ref(props.folder.name);
const folderColor = ref(props.folder.color || '#ffffff');
const folderTeams = ref(props.folder?.shared_teams?.map((team: any) => team.id) || []);

const op = ref();

defineExpose({
    toggle: (event: any) => op.value.toggle(event)
});

const saveFolder = () => {
    DemoFolders.update(props.folder.id, {
        folder_name: folderName.value,
        color: folderColor.value,
        teams: folderTeams.value
    }).then(() => {
        toast.success("Folder updated", "Your folder has been updated successfully.");
    }).catch(() => {
        toast.error("Failed to update folder", "Please try again later.");
    }).finally(() => {
        op.value.hide();
    });
};

watch(() => props.folder, (newFolder) => {
    folderName.value = newFolder.name;
    folderColor.value = newFolder.color || '#ffffff';
    folderTeams.value = newFolder?.shared_teams?.map((team: any) => team.id) || [];
}, { immediate: true });

const deleteFolder = () => {
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
            DemoFolders.delete(props.folder.id);
            op.value.hide();
            toast.success("Folder deleted", "Your folder has been deleted successfully.");
        },
        reject: () => {
            op.value.hide();
        }
    });
};
</script>

<template>
    <Popover ref="op">
        <div class="flex flex-col gap-4 w-[22rem] p-2">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-white text-lg">Edit Folder</h3>
                <Button class="material-symbols text-red-400 !bg-transparent border-0" rounded @click="deleteFolder()">
                    delete
                </Button>
            </div>
            
            <div class="flex flex-col gap-2">
                <label class="text-sm text-gray-400">Folder Name</label>
                <InputText v-model="folderName" class="w-full" placeholder="Enter folder name..." />
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm text-gray-400">Folder Color</label>
                <div class="flex items-center gap-3 p-2 rounded-md">
                    <ColorPicker v-model="folderColor" format="hex" />
                    <span class="text-xs font-mono text-gray-300 uppercase">
                        #{{ folderColor }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm text-gray-400">Share with Teams</label>
                <MultiSelect
                    v-model="folderTeams"
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
                    @click="op.hide()" 
                />
                <Button 
                    label="Save Changes" 
                    @click="saveFolder()" 
                />
            </div>
        </div>
    </Popover>
</template>

<style>
.p-colorpicker-preview {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.p-colorpicker-panel {
    background: var(--card-bg) !important;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
}
</style>