import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export interface UploadItem {
    id:       string;
    name:     string;
    size:     number;
    status:   'pending' | 'extracting' | 'uploading' | 'done' | 'error';
    progress: number;
    error?:   string;
}

export const useUploadQueueStore = defineStore('uploadQueue', () => {
    const items = ref<UploadItem[]>([]);

    const active    = computed(() => items.value.filter(i => i.status === 'uploading' || i.status === 'extracting'));
    const pending   = computed(() => items.value.filter(i => i.status === 'pending'));
    const done      = computed(() => items.value.filter(i => i.status === 'done'));
    const hasActive = computed(() => active.value.length > 0);
    const overallProgress = computed(() => {
        const uploading = items.value.filter(i => i.status === 'uploading' || i.status === 'done');
        if (!uploading.length) return 0;
        return Math.round(uploading.reduce((s, i) => s + i.progress, 0) / uploading.length);
    });

    const upsert = (item: UploadItem) => {
        const idx = items.value.findIndex(i => i.id === item.id);
        if (idx >= 0) items.value[idx] = { ...items.value[idx], ...item };
        else items.value.push(item);
    };

    const remove = (id: string) => {
        items.value = items.value.filter(i => i.id !== id);
    };

    const clearDone = () => {
        items.value = items.value.filter(i => i.status !== 'done' && i.status !== 'error');
    };

    return { items, active, pending, done, hasActive, overallProgress, upsert, remove, clearDone };
});