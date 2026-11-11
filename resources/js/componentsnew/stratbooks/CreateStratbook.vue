<script setup lang="ts">
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useStore } from '@/store';
import useStratbooks from '@/composables/useStratbooks';
import Dialog from '@/components/volt/Dialog.vue';
import InputText from '@/components/volt/InputText.vue';
import Button from '@/components/volt/Button.vue';
import { AppErrorCode } from '@/constants/errorCodes';
import { useToast } from '@/composables/useToast';
import { useUpgradeModal } from '@/composables/useUpgradeModal';

const toast = useToast();
const { open: openUpgrade } = useUpgradeModal();

const { Stratbooks } = useStratbooks();
const newStratbookName = ref('');
const store = useStore();
const { selectedTeamId } = storeToRefs(store);

// Use the defineModel correctly
const visible = defineModel<boolean>('visible', { default: false });

const saveStratbook = async () => {
    if (!newStratbookName.value || !selectedTeamId.value) return;

    try {
        await Stratbooks.create({
            stratbook_name: newStratbookName.value
        });
        newStratbookName.value = '';
        visible.value = false;
        toast.success("Stratbook created", "Your new stratbook has been created successfully.");
    } catch (error: any) {
        // Backend tier gate → pop the upgrade modal instead of a generic toast.
        const appCode = error?.response?.data?.app_code;
        if (appCode === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
            const body = error.response.data;
            openUpgrade({
                feature: body.feature ?? 'stratbooks',
                requiredTier: body.required_tier ?? null,
                limit: body.limit ?? null,
            });
            visible.value = false;
            return;
        }
        toast.error("Failed to create stratbook", "Please try again later.");
    }
};
</script>

<template>
    <Dialog v-model:visible="visible" header="New Stratbook" :modal="true" appendTo="body" class="w-[400px]">
        <div class="flex flex-col gap-4 py-4">
            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-semibold">Stratbook Name</label>
                <InputText id="name" v-model="newStratbookName" placeholder="e.g. Mirage T-Side" class="w-full" />
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" severity="secondary" @click="visible = false" />
                <Button label="Create Stratbook" @click="saveStratbook" :disabled="!newStratbookName" />
            </div>
        </div>
    </Dialog>
</template>