<script setup lang="ts">
import { ref } from 'vue';
import useTeams from '@/composables/useTeams';
import Dialog from '@/components/volt/Dialog.vue';
import InputText from '@/components/volt/InputText.vue';
import Button from '@/components/volt/Button.vue';
import { useToast } from '@/composables/useToast';
import { useUXWizz } from '@/composables/useUXWizz'
const { track } = useUXWizz()
const toast = useToast();
const { Teams } = useTeams();
const teamName = ref('');

const visible = defineModel<boolean>('visible', { default: false });

const saveTeam = async () => {
    if (!teamName.value) return;
    track('team:created')
    try {
        await Teams.create({ 
            team_name: teamName.value 
        });
        teamName.value = '';
        visible.value = false;
        toast.success("Team created", "Your new team has been created successfully.");
    } catch (error) {
        console.error("Failed to create team:", error);
        toast.error("Failed to create team", "Please try again later.");
    }
};
</script>

<template>
    <Dialog v-model:visible="visible" header="New Team" :modal="true" appendTo="body" class="w-[400px]">
        <div class="flex flex-col gap-4 py-4">
            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-semibold">Team Name</label>
                <InputText id="name" v-model="teamName" placeholder="e.g. Mirage T-Side" class="w-full" />
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" severity="secondary" @click="visible = false" />
                <Button label="Create Team" @click="saveTeam" :disabled="!teamName" />
            </div>
        </div>
    </Dialog>
</template>