<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useStore } from '@/store'
import { useToast } from '@/composables/useToast'
import ViewerIframe from '@/componentsnew/demo/ViewerIframe.vue'

interface DetectedTactic {
    id: number
    demo_id: number
    round_index: number
    side: 't' | 'ct'
    pattern_type: string
    pattern_subtype: string | null
    bombsite: string | null
    confidence: number
    tactical_value_score: number
    evidence: Record<string, any>
    description: string | null
    start_tick: number | null
    end_tick: number | null
    key_ticks: Array<{ tick: number; label: string }> | null
    player_steamids: string[] | null
    player_names: string[] | null
    team_name: string | null
    opponent_name: string | null
    status: string
    video_url: string | null
    tactic_id: number | null
}

interface ScannedDemo {
    id: number
    name: string
    map: string
    tactic_count: number
    pending_count: number
    latest_detection: string | null
}

const router = useRouter()
const store = useStore()
const toast = useToast()

const loading = ref(true)
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadFileName = ref('')

const scannedDemos = ref<ScannedDemo[]>([])
const selectedDemoId = ref<number | null>(null)
const tactics = ref<DetectedTactic[]>([])
const tacticsLoading = ref(false)
const selectedTactic = ref<DetectedTactic | null>(null)
const rejecting = ref<number | null>(null)
const approving = ref<number | null>(null)
const releasing = ref<number | null>(null)
const viewerTactic = ref<DetectedTactic | null>(null)

let pollTimer: ReturnType<typeof setInterval> | null = null

const selectedDemo = computed(() =>
    scannedDemos.value.find(d => d.id === selectedDemoId.value) || null
)

const sortedTactics = computed(() =>
    [...tactics.value].sort((a, b) => b.tactical_value_score - a.tactical_value_score)
)

const pendingTactics = computed(() =>
    tactics.value.filter(t => t.status === 'detected')
)

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''

// ── Upload ────────────────────────────────────────────────────────────────────

const CHUNK_SIZE = 15 * 1024 * 1024
const CONCURRENCY_LIMIT = 6

async function handleFileSelect(event: Event) {
    const input = event.target as HTMLInputElement
    if (!input.files?.length) return
    const file = input.files[0]
    uploadFileName.value = file.name
    await uploadDemo(file)
    input.value = ''
}

async function uploadDemo(file: File) {
    uploading.value = true
    uploadProgress.value = 0

    try {
        const initRes = await fetch('/api/demos/requestUpload', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({
                fileName: file.name,
                fileSize: file.size,
                mimeType: 'application/octet-stream',
            }),
        })
        if (!initRes.ok) throw new Error('Init failed')
        const { demoId } = await initRes.json()

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE)
        let uploaded = 0

        const uploadChunk = async (idx: number) => {
            const start = idx * CHUNK_SIZE
            const blob = file.slice(start, Math.min(file.size, start + CHUNK_SIZE))
            const form = new FormData()
            form.append('demoId', String(demoId))
            form.append('file', blob)
            form.append('chunkIndex', idx as any)
            form.append('totalChunks', totalChunks as any)

            const res = await fetch(`/api/demos/${demoId}/upload`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': getCsrf() },
                body: form,
            })
            if (!res.ok) throw new Error('Chunk upload failed')
        }

        const chunkQueue = Array.from({ length: totalChunks }, (_, i) => i)
        const worker = async () => {
            while (chunkQueue.length > 0) {
                const idx = chunkQueue.shift()!
                await uploadChunk(idx)
                uploaded++
                uploadProgress.value = Math.round((uploaded / totalChunks) * 95)
            }
        }
        await Promise.all(Array.from({ length: CONCURRENCY_LIMIT }, () => worker()))

        const finalRes = await fetch(`/api/demos/${demoId}/finalize`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body: JSON.stringify({ demoId }),
        })
        if (!finalRes.ok) throw new Error('Finalize failed')

        uploadProgress.value = 100
        toast.success('Upload complete', 'Demo is being parsed and scanned for tactics.')

        // Start polling for detection results
        startPolling()
    } catch (err: any) {
        toast.error('Upload failed', err?.message || 'Could not upload demo.')
    } finally {
        uploading.value = false
    }
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadScannedDemos() {
    try {
        const { data } = await axios.get('/api/admin/scanned-demos')
        scannedDemos.value = data

        if (!selectedDemoId.value && data.length > 0) {
            selectedDemoId.value = data[0].id
            loadTactics(data[0].id)
        }
    } catch (e: any) {
        toast.error('Load failed', 'Could not load scanned demos.')
    } finally {
        loading.value = false
    }
}

async function loadTactics(demoId: number) {
    tacticsLoading.value = true
    try {
        const { data } = await axios.get('/api/admin/detected-tactics', {
            params: { demo_id: demoId },
        })
        tactics.value = data
    } catch (e: any) {
        toast.error('Load failed', 'Could not load detected tactics.')
    } finally {
        tacticsLoading.value = false
    }
}

async function rejectTactic(id: number) {
    rejecting.value = id
    try {
        await axios.post(`/api/admin/detected-tactics/${id}/reject`)
        tactics.value = tactics.value.map(t =>
            t.id === id ? { ...t, status: 'rejected' } : t
        )
        toast.success('Rejected', 'Tactic has been rejected.')
    } catch (e: any) {
        toast.error('Reject failed', 'Could not reject tactic.')
    } finally {
        rejecting.value = null
    }
}

function view3D(demoId: number) {
    router.push(`/demos/demo/${demoId}/viewer-3d`)
}

function openViewer(tactic: DetectedTactic) {
    viewerTactic.value = tactic
}

function closeViewer() {
    viewerTactic.value = null
}

async function approveTactic(id: number) {
    approving.value = id
    try {
        await axios.post(`/api/admin/detected-tactics/${id}/approve`)
        toast.success('Recording dispatched', 'All POV clips are being recorded on subtick.')
        startPolling()
    } catch (e: any) {
        toast.error('Approve failed', e?.response?.data?.message || 'Could not dispatch recording.')
    } finally {
        approving.value = null
    }
}

async function releaseTactic(id: number) {
    releasing.value = id
    try {
        await axios.post(`/api/admin/detected-tactics/${id}/release`)
        toast.success('YouTube upload dispatched', 'Video will be uploaded as unlisted.')
        startPolling()
    } catch (e: any) {
        toast.error('Release failed', e?.response?.data?.message || 'Could not release to YouTube.')
    } finally {
        releasing.value = null
    }
}

function editTactic(dtId: number) {
    router.push(`/admin/tactics/create?dt=${dtId}`)
}

// ── Polling ───────────────────────────────────────────────────────────────────
// Only poll while uploads are in progress. Stop once everything is done.

function startPolling() {
    stopPolling()
    pollTimer = setInterval(async () => {
        await loadScannedDemos()
        if (selectedDemoId.value) {
            await loadTactics(selectedDemoId.value)
        }
        // Stop polling when no uploads in progress and no tactics recording
        const hasRecording = tactics.value.some(t => t.status === 'recording')
        if (!uploading.value && !hasRecording) {
            stopPolling()
        }
    }, 5000)
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

watch(selectedDemoId, (id) => {
    if (id) loadTactics(id)
})

onMounted(() => {
    loadScannedDemos()
})

onUnmounted(() => {
    stopPolling()
})
</script>

<template>
    <div class="scanner-page">
        <header class="scanner-header">
            <h1>Demo Tactic Scanner</h1>
            <div class="header-actions">
                <label class="upload-btn" :class="{ disabled: uploading }">
                    <input
                        type="file"
                        accept=".dem"
                        @change="handleFileSelect"
                        :disabled="uploading"
                        style="display: none"
                    />
                    <span v-if="!uploading">Upload Demo</span>
                    <span v-else>Uploading {{ uploadFileName }}... {{ uploadProgress }}%</span>
                </label>
            </div>
        </header>

        <div class="scanner-body">
            <!-- Left panel: demo list -->
            <aside class="demo-list-panel">
                <div class="panel-title">Scanned Demos</div>
                <div v-if="loading" class="loading-text">Loading...</div>
                <div v-else-if="scannedDemos.length === 0" class="empty-text">
                    No demos scanned yet. Upload a .dem file to get started.
                </div>
                <div
                    v-else
                    v-for="demo in scannedDemos"
                    :key="demo.id"
                    class="demo-item"
                    :class="{ active: demo.id === selectedDemoId }"
                    @click="selectedDemoId = demo.id"
                >
                    <div class="demo-name">{{ demo.name }}</div>
                    <div class="demo-meta">
                        <span class="demo-map">{{ demo.map }}</span>
                        <span class="tactic-badge">{{ demo.tactic_count }}</span>
                    </div>
                </div>
            </aside>

            <!-- Main panel: tactic cards -->
            <main class="tactics-panel">
                <div v-if="tacticsLoading" class="loading-text">Loading tactics...</div>
                <div v-else-if="!selectedDemoId" class="empty-text">
                    Select a demo to view detected tactics.
                </div>
                <div v-else-if="sortedTactics.length === 0" class="empty-text">
                    No tactics detected for this demo yet. Parsing may still be in progress.
                </div>
                <div v-else class="tactic-grid">
                    <div
                        v-for="tactic in sortedTactics"
                        :key="tactic.id"
                        class="tactic-card"
                        :class="{ rejected: tactic.status === 'rejected' }"
                    >
                        <div class="tactic-header">
                            <div class="tactic-tags">
                                <span class="round-badge">Round {{ tactic.round_index }}</span>
                                <span
                                    class="side-badge"
                                    :class="tactic.side"
                                >{{ tactic.side.toUpperCase() }}</span>
                                <span class="pattern-badge">{{ tactic.pattern_type.replace(/_/g, ' ') }}</span>
                                <span v-if="tactic.bombsite" class="site-badge">{{ tactic.bombsite }}</span>
                            </div>
                            <div class="score-display">
                                <div class="score-bar">
                                    <div
                                        class="score-fill"
                                        :style="{ width: tactic.tactical_value_score + '%' }"
                                        :class="{
                                            high: tactic.tactical_value_score >= 70,
                                            mid: tactic.tactical_value_score >= 50 && tactic.tactical_value_score < 70,
                                            low: tactic.tactical_value_score < 50,
                                        }"
                                    ></div>
                                </div>
                                <span class="score-value">{{ tactic.tactical_value_score }}</span>
                            </div>
                        </div>

                        <div class="tactic-description">
                            {{ tactic.description || 'No description available.' }}
                        </div>

                        <div class="tactic-evidence">
                            <span v-if="tactic.team_name" class="evidence-item">
                                {{ tactic.team_name }} vs {{ tactic.opponent_name }}
                            </span>
                            <span
                                v-for="(value, key) in (tactic.evidence || {})"
                                :key="key"
                                class="evidence-chip"
                                v-show="!['team_name', 'opponent_name'].includes(key)"
                            >
                                {{ key }}: {{ value }}
                            </span>
                        </div>

                        <!-- Actions — different buttons per status -->
                        <div class="tactic-actions" v-if="tactic.status === 'detected'">
                            <button class="action-btn preview" @click="openViewer(tactic)">Preview 2D</button>
                            <button class="action-btn approve" @click="approveTactic(tactic.id)" :disabled="approving === tactic.id">
                                {{ approving === tactic.id ? '...' : 'Approve & Record' }}
                            </button>
                            <button class="action-btn view3d" @click="view3D(tactic.demo_id)">View 3D</button>
                            <button class="action-btn reject" @click="rejectTactic(tactic.id)" :disabled="rejecting === tactic.id">
                                {{ rejecting === tactic.id ? '...' : 'Reject' }}
                            </button>
                        </div>
                        <div class="tactic-status recording" v-else-if="tactic.status === 'recording'">
                            <span class="spinner"></span> Recording POVs...
                        </div>
                        <div class="tactic-actions" v-else-if="tactic.status === 'recorded'">
                            <button class="action-btn preview" @click="openViewer(tactic)">Preview 2D</button>
                            <button class="action-btn edit" @click="editTactic(tactic.id)">Fill Tactic Info</button>
                            <button class="action-btn release" @click="releaseTactic(tactic.id)" :disabled="releasing === tactic.id">
                                {{ releasing === tactic.id ? '...' : 'Release to YouTube' }}
                            </button>
                        </div>
                        <div class="tactic-status" v-else-if="tactic.status === 'rejected'">
                            Rejected
                        </div>
                        <div class="tactic-status released" v-else-if="tactic.status === 'released'">
                            Released on YouTube
                        </div>
                        <div class="tactic-status error" v-if="tactic.recording_error">
                            {{ tactic.recording_error }}
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- 2D Viewer modal — lazy, one at a time -->
        <div v-if="viewerTactic" class="viewer-modal" @click.self="closeViewer">
            <div class="viewer-modal-content">
                <div class="viewer-modal-header">
                    <span>Round {{ viewerTactic.round_index }} [{{ viewerTactic.side.toUpperCase() }}] {{ viewerTactic.pattern_type.replace(/_/g, ' ') }}</span>
                    <button class="viewer-close" @click="closeViewer">&times;</button>
                </div>
                <ViewerIframe
                    :key="viewerTactic.id"
                    :demoId="String(viewerTactic.demo_id)"
                    :roundIndex="viewerTactic.round_index"
                    :map="scannedDemos.find(d => d.id === viewerTactic.demo_id)?.map || ''"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.scanner-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.scanner-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}

.scanner-header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.upload-btn {
    display: inline-flex;
    align-items: center;
    padding: 8px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    background: var(--primary-color, #f97316);
    color: #fff;
    transition: opacity 0.15s;
}

.upload-btn.disabled {
    opacity: 0.6;
    cursor: default;
}

.scanner-body {
    display: flex;
    flex: 1;
    overflow: hidden;
}

/* Demo list panel */
.demo-list-panel {
    width: 280px;
    flex-shrink: 0;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    overflow-y: auto;
    padding: 12px 0;
}

.panel-title {
    padding: 8px 16px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
}

.demo-item {
    padding: 12px 16px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background 0.1s;
}

.demo-item:hover {
    background: rgba(255, 255, 255, 0.04);
}

.demo-item.active {
    background: rgba(255, 255, 255, 0.06);
    border-left-color: var(--primary-color, #f97316);
}

.demo-name {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.demo-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.demo-map {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

.tactic-badge {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Tactics panel */
.tactics-panel {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
}

.loading-text, .empty-text {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.9rem;
}

.tactic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 16px;
}

.tactic-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.tactic-card.rejected {
    opacity: 0.5;
}

.tactic-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.tactic-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.round-badge, .side-badge, .pattern-badge, .site-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.round-badge {
    background: rgba(255, 255, 255, 0.08);
}

.side-badge.t {
    background: rgba(251, 146, 60, 0.2);
    color: #fb923c;
}

.side-badge.ct {
    background: rgba(96, 165, 250, 0.2);
    color: #60a5fa;
}

.pattern-badge {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    text-transform: capitalize;
}

.site-badge {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

.score-display {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.score-bar {
    width: 60px;
    height: 6px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.1);
    overflow: hidden;
}

.score-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}

.score-fill.high { background: #4ade80; }
.score-fill.mid  { background: #fbbf24; }
.score-fill.low  { background: #f87171; }

.score-value {
    font-size: 0.8rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    min-width: 28px;
    text-align: right;
}

.tactic-description {
    font-size: 0.825rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.7);
}

.tactic-evidence {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.evidence-item {
    font-size: 0.75rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.6);
}

.evidence-chip {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.5);
}

.tactic-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 6px 16px;
    border-radius: 5px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s;
}

.action-btn.reject {
    background: rgba(248, 113, 113, 0.1);
    color: #f87171;
    border-color: rgba(248, 113, 113, 0.2);
}

.action-btn.reject:hover {
    background: rgba(248, 113, 113, 0.2);
}

.action-btn.reject:disabled {
    opacity: 0.5;
    cursor: default;
}

.action-btn.view3d {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.8);
}

.action-btn.view3d:hover {
    background: rgba(255, 255, 255, 0.1);
}

.tactic-status {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.4);
    padding: 6px 0;
}

.action-btn.preview {
    background: rgba(96, 165, 250, 0.1);
    color: #60a5fa;
    border-color: rgba(96, 165, 250, 0.2);
}

.action-btn.preview:hover {
    background: rgba(96, 165, 250, 0.2);
}

.action-btn.approve {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border-color: rgba(34, 197, 94, 0.3);
}

.action-btn.approve:hover {
    background: rgba(34, 197, 94, 0.25);
}

.action-btn.approve:disabled {
    opacity: 0.5;
    cursor: default;
}

.action-btn.edit {
    background: rgba(168, 85, 247, 0.1);
    color: #c084fc;
    border-color: rgba(168, 85, 247, 0.2);
}

.action-btn.edit:hover {
    background: rgba(168, 85, 247, 0.2);
}

.action-btn.release {
    background: rgba(255, 0, 0, 0.1);
    color: #f87171;
    border-color: rgba(255, 0, 0, 0.2);
}

.action-btn.release:hover {
    background: rgba(255, 0, 0, 0.2);
}

.action-btn.release:disabled {
    opacity: 0.5;
    cursor: default;
}

.tactic-status.recording {
    color: #fbbf24;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tactic-status.released {
    color: #4ade80;
}

.tactic-status.error {
    color: #f87171;
    font-size: 0.75rem;
    padding: 4px 0 0;
}

.spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(251, 191, 36, 0.3);
    border-top-color: #fbbf24;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.viewer-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.viewer-modal-content {
    width: 90%;
    max-width: 900px;
    height: 600px;
    background: #1a1a1a;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.viewer-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    font-size: 0.9rem;
    font-weight: 500;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}

.viewer-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0 8px;
    line-height: 1;
}

.viewer-close:hover {
    color: #fff;
}

.viewer-modal :deep(.round-viewer) {
    height: 100%;
    border-radius: 0;
    box-shadow: none;
}
</style>
