<template>
    <Dialog v-model:visible="visible" modal :closable="true" :style="{ width: '580px' }"
        header="Upload Demos" @hide="onClose">

        <!-- Drop zone -->
        <div
            class="relative mt-2 rounded-2xl border-2 border-dashed transition-all duration-200 cursor-pointer select-none"
            :class="isDragging
                ? 'border-primary bg-primary/10 scale-[1.01]'
                : 'border-zinc-700 bg-zinc-900/50 hover:border-zinc-500 hover:bg-zinc-900'"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="fileInputRef?.click()"
        >
            <input
                ref="fileInputRef"
                type="file"
                multiple
                accept=".dem,.zip,.rar"
                class="hidden"
                @change="onFileInput"
            />
            <div class="flex flex-col items-center justify-center py-10 gap-3 pointer-events-none">
                <div class="w-14 h-14 rounded-2xl bg-zinc-800 flex items-center justify-center">
                    <Icon icon="lucide:upload-cloud" class="text-2xl text-zinc-400" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold text-white">Drop demos here or click to browse</p>
                    <p class="text-xs text-zinc-500 mt-1">.dem files · .zip archives containing .dem files</p>
                </div>
                <div class="flex gap-2 mt-1">
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-500 font-mono">.dem</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-500 font-mono">.zip</span>
                </div>
            </div>
        </div>

        <!-- Queue -->
        <div v-if="storeItems.length > 0" class="mt-4 space-y-2 max-h-72 overflow-y-auto custom-track pr-1">
            <TransitionGroup name="list">
                <div
                    v-for="item in storeItems" :key="item.id"
                    class="flex items-center gap-3 rounded-xl bg-zinc-900 border border-zinc-800 p-3"
                >
                    <div class="w-8 h-8 rounded-lg shrink-0 flex items-center justify-center"
                        :class="{
                            'bg-zinc-800':     item.status === 'pending',
                            'bg-primary/20':   item.status === 'uploading',
                            'bg-green-900/40': item.status === 'done',
                            'bg-red-900/40':   item.status === 'error',
                            'bg-amber-900/40': item.status === 'extracting',
                        }">
                        <Icon v-if="item.status === 'pending'"    icon="lucide:file"            class="text-zinc-500 text-sm" />
                        <Icon v-if="item.status === 'extracting'" icon="lucide:archive"         class="text-amber-400 text-sm animate-pulse" />
                        <Icon v-if="item.status === 'uploading'"  icon="lucide:upload"          class="text-primary text-sm" />
                        <Icon v-if="item.status === 'done'"       icon="lucide:check"           class="text-green-400 text-sm" />
                        <Icon v-if="item.status === 'error'"      icon="lucide:alert-triangle"  class="text-red-400 text-sm" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <input
                                v-if="item.status === 'pending'"
                                :value="item.name"
                                @input="renamePending(item.id, ($event.target as HTMLInputElement).value)"
                                class="bg-transparent text-sm font-medium text-white outline-none border-b border-zinc-700 focus:border-primary transition-colors w-full"
                                @click.stop
                                placeholder="Demo name"
                            />
                            <span v-else class="text-sm font-medium text-white truncate">{{ item.name }}</span>
                        </div>

                        <div v-if="item.status === 'uploading'" class="mt-1.5 h-1 bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full transition-all duration-200"
                                :style="{ width: item.progress + '%' }" />
                        </div>
                        <p v-else-if="item.status === 'error'" class="text-[10px] text-red-400 mt-0.5 truncate">{{ item.error }}</p>
                        <p v-else class="text-[10px] text-zinc-600 mt-0.5">
                            {{ statusLabel(item) }} · {{ formatSize(item.size) }}
                        </p>
                    </div>

                    <button v-if="item.status === 'pending'"
                        @click.stop="uploadStore.remove(item.id)"
                        class="text-zinc-700 hover:text-red-400 transition-colors shrink-0">
                        <Icon icon="lucide:x" class="text-sm" />
                    </button>

                    <span v-if="item.status === 'uploading'"
                        class="text-xs font-mono text-primary shrink-0 w-10 text-right">
                        {{ item.progress }}%
                    </span>
                </div>
            </TransitionGroup>
        </div>

        <template #footer>
            <div class="flex items-center justify-between gap-3 w-full">
                <p v-if="hasActive" class="text-xs text-zinc-500">
                    {{ uploadStore.active.length }} uploading · {{ uploadStore.done.length }} done
                </p>
                <p v-else-if="storeItems.length === 0" class="text-xs text-zinc-600">No demos selected yet</p>
                <p v-else class="text-xs text-zinc-500">{{ uploadStore.pending.length }} pending</p>

                <div class="flex gap-2">
                    <button v-if="uploadStore.done.length > 0"
                        class="btn-secondary text-xs"
                        @click="uploadStore.clearDone()">
                        Clear done
                    </button>
                    <button class="btn-secondary text-sm" @click="visible = false">
                        {{ hasActive ? 'Minimise' : 'Close' }}
                    </button>
                    <button
                        class="btn-primary text-sm flex items-center gap-1.5"
                        :disabled="uploadStore.pending.length === 0 || hasActive"
                        @click="startAll"
                    >
                        <Icon icon="lucide:upload" class="text-sm" />
                        Upload {{ uploadStore.pending.length > 0 ? uploadStore.pending.length + ' demo' + (uploadStore.pending.length > 1 ? 's' : '') : '' }}
                    </button>
                </div>
            </div>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Icon } from '@iconify/vue';
import Dialog from '@/components/volt/Dialog.vue';
import { useUploadQueueStore, type UploadItem } from '@/stores/uploadQueueStore';
import { useUXWizz } from '@/composables/useUXWizz';
import useDemos from '@/composables/useDemos';

const props = defineProps<{ folderId?: number | null }>();
const visible = defineModel<boolean>('visible', { default: false });

const { track }   = useUXWizz();
const uploadStore = useUploadQueueStore();
const { Demos }   = useDemos();

// Expose store items for template
const storeItems = computed(() => uploadStore.items);
const hasActive  = computed(() => uploadStore.hasActive);

// ── Local pending name map (editable names before upload) ─────────────────────
const pendingNames = ref<Record<string, string>>({});

const renamePending = (id: string, name: string) => {
    pendingNames.value[id] = name;
    const item = uploadStore.items.find(i => i.id === id);
    if (item) uploadStore.upsert({ ...item, name });
};

// ── File handling ─────────────────────────────────────────────────────────────
const isDragging   = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const onDrop = (e: DragEvent) => {
    isDragging.value = false;
    processFiles(Array.from(e.dataTransfer?.files ?? []));
};

const onFileInput = (e: Event) => {
    processFiles(Array.from((e.target as HTMLInputElement).files ?? []));
    (e.target as HTMLInputElement).value = '';
};

const processFiles = async (files: File[]) => {
    for (const file of files) {
        const ext = file.name.split('.').pop()?.toLowerCase();
        if (ext === 'dem') {
            addToQueue(file);
        } else if (ext === 'zip') {
            await extractZip(file);
        } else if (ext === 'rar') {
            uploadStore.upsert({
                id: crypto.randomUUID(), name: file.name, size: file.size,
                status: 'error', progress: 0,
                error: 'RAR not supported — extract to .dem or .zip first',
            });
        }
    }
};

const addToQueue = (file: File, nameOverride?: string) => {
    const id   = crypto.randomUUID();
    const name = (nameOverride ?? file.name).replace(/\.dem$/i, '');
    // Store the file reference locally — store only holds serialisable metadata
    fileMap.value[id] = file;
    uploadStore.upsert({ id, name, size: file.size, status: 'pending', progress: 0 });
};

// Local file map — store can't hold File objects (not serialisable)
const fileMap = ref<Record<string, File>>({});

// ── ZIP extraction ────────────────────────────────────────────────────────────
const extractZip = async (archiveFile: File) => {
    const placeholderId = crypto.randomUUID();
    uploadStore.upsert({
        id: placeholderId, name: archiveFile.name,
        size: archiveFile.size, status: 'extracting', progress: 0,
    });

    try {
        const { unzip } = await import('fflate');
        const buffer    = await archiveFile.arrayBuffer();
        const uint8     = new Uint8Array(buffer);

        await new Promise<void>((resolve, reject) => {
            unzip(uint8, (err, files) => {
                if (err) { reject(err); return; }

                const demFiles = Object.entries(files).filter(([name]) =>
                    name.toLowerCase().endsWith('.dem') && !name.includes('__MACOSX')
                );

                for (const [name, data] of demFiles) {
                    const blob = new Blob([data], { type: 'application/octet-stream' });
                    const f    = new File([blob], name.split('/').pop()!);
                    addToQueue(f, name.split('/').pop()!.replace(/\.dem$/i, ''));
                }

                resolve();
            });
        });
    } catch (err) {
        console.error('ZIP extraction failed:', err);
    } finally {
        uploadStore.remove(placeholderId);
    }
};

// ── Upload ────────────────────────────────────────────────────────────────────
const CHUNK_SIZE        = 15 * 1024 * 1024; // 15MB
const CONCURRENCY_LIMIT = 6;
const MAX_RETRIES       = 3;

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const startAll = async () => {
    const pending = uploadStore.pending.map(i => ({ ...i, file: fileMap.value[i.id] }))
        .filter(i => i.file);

    // Upload up to 3 demos concurrently
    const iterator = pending[Symbol.iterator]();
    await Promise.all(Array.from({ length: Math.min(3, pending.length) }, async () => {
        for (const item of iterator) {
            await uploadItem(item);
        }
    }));

    Demos.refresh();
};

const uploadItem = async (item: UploadItem & { file: File }) => {
    uploadStore.upsert({ ...item, status: 'uploading', progress: 0 });

    try {
        // 1. Init
        const initRes = await fetch('/api/demos/requestUpload', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body:    JSON.stringify({
                fileName: item.name,
                fileSize: item.file.size,
                folderId: props.folderId ?? null,
                mimeType: 'application/octet-stream',
            }),
        });
        if (!initRes.ok) throw new Error('Init failed: ' + (await initRes.text()));
        const { demoId } = await initRes.json();

        // 2. Chunked upload
        const totalChunks = Math.ceil(item.file.size / CHUNK_SIZE);
        const chunkQueue  = Array.from({ length: totalChunks }, (_, i) => i);
        let uploaded = 0;

        const worker = async () => {
            while (chunkQueue.length > 0) {
                const idx = chunkQueue.shift()!;
                const start = idx * CHUNK_SIZE;
                const blob  = item.file.slice(start, Math.min(item.file.size, start + CHUNK_SIZE));
                await uploadChunk(demoId, idx, totalChunks, blob);
                uploaded++;
                uploadStore.upsert({
                    ...uploadStore.items.find(i => i.id === item.id)!,
                    progress: Math.round((uploaded / totalChunks) * 95),
                });
            }
        };

        await Promise.all(Array.from({ length: CONCURRENCY_LIMIT }, () => worker()));

        // 3. Finalize
        const finalRes = await fetch(`/api/demos/${demoId}/finalize`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body:    JSON.stringify({ demoId }),
        });
        if (!finalRes.ok) throw new Error('Finalize failed');

        uploadStore.upsert({ ...item, status: 'done', progress: 100 });
        track('demo:uploaded');

        // Clean up file reference
        delete fileMap.value[item.id];

    } catch (err: any) {
        uploadStore.upsert({ ...item, status: 'error', error: err.message });
        console.error(`Upload failed for ${item.name}:`, err);
    }
};

const uploadChunk = async (
    demoId: number, chunkIndex: number, totalChunks: number, blob: Blob, retry = 0
): Promise<void> => {
    // Match exactly what the old App.vue sent — this is what the backend expects
    const form = new FormData();
    form.append('demoId',      String(demoId));
    form.append('file',        blob);              // raw blob, no filename hint
    form.append('chunkIndex',  chunkIndex as any);
    form.append('totalChunks', totalChunks as any);

    const res = await fetch(`/api/demos/${demoId}/upload`, {
        method:  'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body:    form,
    });

    if (!res.ok) {
        if (retry < MAX_RETRIES) {
            await new Promise(r => setTimeout(r, 1000 * (retry + 1)));
            return uploadChunk(demoId, chunkIndex, totalChunks, blob, retry + 1);
        }
        const err = await res.json().catch(() => ({}));
        throw new Error(err.error ?? `Chunk ${chunkIndex} failed`);
    }
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const onClose = () => {
    // Don't clear — uploads continue in background, visible via floating indicator
};

const statusLabel = (item: UploadItem) => ({
    pending:    'Ready to upload',
    extracting: 'Extracting…',
    uploading:  `Uploading ${item.progress}%`,
    done:       'Uploaded ✓',
    error:      'Error',
}[item.status] ?? '');

const formatSize = (bytes: number) => {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576)    return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1024).toFixed(0) + ' KB';
};
</script>

<style scoped>
.list-enter-active, .list-leave-active { transition: all 0.2s ease; }
.list-enter-from { opacity: 0; transform: translateY(-6px); }
.list-leave-to   { opacity: 0; transform: translateX(20px); }
.custom-track::-webkit-scrollbar       { width: 4px; }
.custom-track::-webkit-scrollbar-track { background: transparent; }
.custom-track::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 10px; }
</style>