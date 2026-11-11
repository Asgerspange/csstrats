<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import axios from 'axios'
import { useStore } from '@/store'
import { useToast } from '@/composables/useToast'

interface TacticRow {
    id: number
    team: { name?: string } | null
    type: string
    description: string
    created_at: string | null
    release_date: string | null
    is_featured?: boolean
}

const router = useRouter()
const store = useStore()
const toast = useToast()
const { user } = storeToRefs(store)

const loading = ref(true)
const busy = ref(false)
const unreleased = ref<TacticRow[]>([])
const released = ref<TacticRow[]>([])

const tab = ref<'unreleased' | 'scheduled' | 'released'>('unreleased')
const search = ref('')
const selected = ref<Set<number>>(new Set())

/* Modals */
const showSchedule = ref(false)
const scheduleDate = ref('')
const showAmount = ref(false)
const amount = ref(5)
const amountDate = ref('')

const draftTactics = computed(() => unreleased.value.filter(t => !t.release_date))
const scheduledTactics = computed(() => unreleased.value.filter(t => !!t.release_date))

const rows = computed<TacticRow[]>(() => {
    const list = tab.value === 'unreleased' ? draftTactics.value
        : tab.value === 'scheduled' ? scheduledTactics.value
        : released.value
    const q = search.value.trim().toLowerCase()
    if (!q) return list
    return list.filter(t =>
        (t.team?.name || '').toLowerCase().includes(q) ||
        (t.type || '').toLowerCase().includes(q) ||
        (t.description || '').toLowerCase().includes(q)
    )
})

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/admin/tactics-release')
        unreleased.value = data.unreleased ?? []
        released.value = data.released ?? []
        selected.value = new Set()
    } catch (e: any) {
        toast.error('Load failed', e?.response?.data?.message || 'Could not load tactics.')
    } finally {
        loading.value = false
    }
}

/* Selection (only meaningful on the drafts tab) */
function toggleRow(id: number) {
    const s = new Set(selected.value)
    s.has(id) ? s.delete(id) : s.add(id)
    selected.value = s
}
function toggleAll() {
    if (selected.value.size === rows.value.length) selected.value = new Set()
    else selected.value = new Set(rows.value.map(r => r.id))
}
const allSelected = computed(() => rows.value.length > 0 && selected.value.size === rows.value.length)

async function run(label: string, fn: () => Promise<any>) {
    busy.value = true
    try {
        const { data } = await fn()
        toast.success('Done', data?.message || label)
        await load()
    } catch (e: any) {
        toast.error('Action failed', e?.response?.data?.message || 'Please try again.')
    } finally {
        busy.value = false
    }
}

const releaseSingle = (id: number) => run('Released', () => axios.post(`/api/admin/tactics/release/${id}`))
const unrelease = (id: number) => run('Moved to drafts', () => axios.post(`/api/admin/tactics/unreleased/${id}`))
const feature = (id: number) => run('Featured', () => axios.post(`/api/admin/tactics/feature/${id}`))
const unfeature = (id: number) => run('Unfeatured', () => axios.post(`/api/admin/tactics/unfeature/${id}`))
const cancelSchedule = (id: number) => run('Schedule cancelled', () => axios.post(`/api/admin/tactics/cancel-schedule/${id}`))

function releaseSelected() {
    if (!selected.value.size) return
    run('Released selected', () => axios.post('/api/admin/tactics/release-multiple', { tactic_ids: [...selected.value] }))
}
function confirmSchedule() {
    if (!selected.value.size || !scheduleDate.value) return
    run('Scheduled selected', () => axios.post('/api/admin/tactics/schedule-release', {
        tactic_ids: [...selected.value],
        release_date: new Date(scheduleDate.value).toISOString(),
    })).then(() => { showSchedule.value = false; scheduleDate.value = '' })
}
function confirmAmount() {
    if (!amount.value || !amountDate.value) return
    run('Scheduled batch', () => axios.post('/api/admin/tactics/release-amount', {
        amount: amount.value,
        release_date: new Date(amountDate.value).toISOString(),
    })).then(() => { showAmount.value = false })
}

async function remove(t: TacticRow) {
    if (!confirm(`Delete this ${t.team?.name || ''} ${t.type} tactic? This cannot be undone from here.`)) return
    run('Deleted', () => axios.delete(`/api/admin/tactics/${t.id}`))
}

function edit(id: number) {
    router.push(`/admin/tactics/${id}/edit`)
}

function fmt(v: string | null) {
    return v ? new Date(v).toLocaleString(undefined, { day: '2-digit', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'
}

onMounted(() => {
    if (user.value && !user.value.is_admin) { router.push('/tactics'); return }
    load()
})
</script>

<template>
    <div class="h-full overflow-y-auto p-6 text-white">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold">Tactic releases</h1>
                    <p class="text-sm text-white/40">Publish, schedule and feature tactics for the public library.</p>
                </div>
                <button @click="router.push('/admin/tactics/create')"
                    class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2 hover:brightness-105">
                    + New tactic
                </button>
            </div>

            <!-- Stat tabs -->
            <div class="grid grid-cols-3 gap-3 mb-5">
                <button @click="tab = 'unreleased'" :class="tab === 'unreleased' ? 'ring-1 ring-primary bg-white/[0.05]' : 'bg-white/[0.02]'"
                    class="rounded-lg border border-white/10 p-4 text-left">
                    <div class="text-2xl font-bold tabular-nums">{{ draftTactics.length }}</div>
                    <div class="text-xs uppercase text-white/40">Drafts</div>
                </button>
                <button @click="tab = 'scheduled'" :class="tab === 'scheduled' ? 'ring-1 ring-primary bg-white/[0.05]' : 'bg-white/[0.02]'"
                    class="rounded-lg border border-white/10 p-4 text-left">
                    <div class="text-2xl font-bold tabular-nums">{{ scheduledTactics.length }}</div>
                    <div class="text-xs uppercase text-white/40">Scheduled</div>
                </button>
                <button @click="tab = 'released'" :class="tab === 'released' ? 'ring-1 ring-primary bg-white/[0.05]' : 'bg-white/[0.02]'"
                    class="rounded-lg border border-white/10 p-4 text-left">
                    <div class="text-2xl font-bold tabular-nums">{{ released.length }}</div>
                    <div class="text-xs uppercase text-white/40">Released</div>
                </button>
            </div>

            <!-- Toolbar -->
            <div class="flex items-center gap-3 mb-3">
                <input v-model="search" placeholder="Search team, type, description…"
                    class="flex-1 bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm" />
                <template v-if="tab === 'unreleased'">
                    <button @click="releaseSelected" :disabled="!selected.size || busy"
                        class="text-sm bg-primary text-black font-semibold rounded px-3 py-2 disabled:opacity-40">Release ({{ selected.size }})</button>
                    <button @click="showSchedule = true" :disabled="!selected.size || busy"
                        class="text-sm bg-white/10 rounded px-3 py-2 disabled:opacity-40 hover:bg-white/15">Schedule</button>
                    <button @click="showAmount = true" :disabled="busy"
                        class="text-sm bg-white/10 rounded px-3 py-2 hover:bg-white/15">Schedule amount</button>
                </template>
            </div>

            <div v-if="loading" class="text-white/40 text-sm py-10">Loading…</div>

            <div v-else class="rounded-lg border border-white/10 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-white/[0.03] text-white/40 text-xs">
                        <tr>
                            <th v-if="tab === 'unreleased'" class="px-4 py-2.5 w-8">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll" />
                            </th>
                            <th class="text-left font-semibold px-4 py-2.5">Team</th>
                            <th class="text-left font-semibold px-4 py-2.5">Type</th>
                            <th class="text-left font-semibold px-4 py-2.5">Description</th>
                            <th class="text-left font-semibold px-4 py-2.5">{{ tab === 'released' ? 'Released' : tab === 'scheduled' ? 'Scheduled for' : 'Created' }}</th>
                            <th class="px-4 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in rows" :key="t.id" class="border-t border-white/10 hover:bg-white/[0.02]">
                            <td v-if="tab === 'unreleased'" class="px-4 py-3">
                                <input type="checkbox" :checked="selected.has(t.id)" @change="toggleRow(t.id)" />
                            </td>
                            <td class="px-4 py-3 font-semibold">
                                {{ t.team?.name || '—' }}
                                <span v-if="t.is_featured" class="ml-1 text-[10px] uppercase bg-primary/20 text-primary rounded px-1.5 py-0.5">featured</span>
                            </td>
                            <td class="px-4 py-3 text-white/70">{{ t.type }}</td>
                            <td class="px-4 py-3 text-white/50 max-w-xs truncate">{{ t.description }}</td>
                            <td class="px-4 py-3 text-white/50 tabular-nums">{{ fmt(tab === 'released' ? t.release_date : tab === 'scheduled' ? t.release_date : t.created_at) }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button @click="edit(t.id)" class="text-white/40 hover:text-white text-xs mr-3">Edit</button>

                                <template v-if="tab === 'unreleased'">
                                    <button @click="releaseSingle(t.id)" :disabled="busy" class="text-primary hover:brightness-110 text-xs mr-3">Release</button>
                                </template>
                                <template v-else-if="tab === 'scheduled'">
                                    <button @click="releaseSingle(t.id)" :disabled="busy" class="text-primary hover:brightness-110 text-xs mr-3">Release now</button>
                                    <button @click="cancelSchedule(t.id)" :disabled="busy" class="text-white/40 hover:text-white text-xs mr-3">Cancel</button>
                                </template>
                                <template v-else>
                                    <button v-if="!t.is_featured" @click="feature(t.id)" :disabled="busy" class="text-white/40 hover:text-white text-xs mr-3">Feature</button>
                                    <button v-else @click="unfeature(t.id)" :disabled="busy" class="text-white/40 hover:text-white text-xs mr-3">Unfeature</button>
                                    <button @click="unrelease(t.id)" :disabled="busy" class="text-white/40 hover:text-white text-xs mr-3">Unrelease</button>
                                </template>

                                <button @click="remove(t)" :disabled="busy" class="text-white/40 hover:text-red-400 text-xs">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="tab === 'unreleased' ? 6 : 5" class="px-4 py-10 text-center text-white/30 text-sm">Nothing here.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Schedule selected -->
        <div v-if="showSchedule" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="showSchedule = false">
            <div class="bg-zinc-900 border border-white/10 rounded-xl w-full max-w-sm p-5">
                <h2 class="font-bold mb-3">Schedule {{ selected.size }} tactic(s)</h2>
                <label class="block text-xs text-white/40 mb-1">Release date &amp; time</label>
                <input v-model="scheduleDate" type="datetime-local" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm mb-4" />
                <div class="flex justify-end gap-2">
                    <button @click="showSchedule = false" class="text-sm text-white/50 px-4 py-2">Cancel</button>
                    <button @click="confirmSchedule" :disabled="!scheduleDate || busy" class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2 disabled:opacity-50">Schedule</button>
                </div>
            </div>
        </div>

        <!-- Schedule amount -->
        <div v-if="showAmount" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="showAmount = false">
            <div class="bg-zinc-900 border border-white/10 rounded-xl w-full max-w-sm p-5">
                <h2 class="font-bold mb-3">Schedule the oldest N drafts</h2>
                <label class="block text-xs text-white/40 mb-1">Amount</label>
                <input v-model.number="amount" type="number" min="1" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm mb-3" />
                <label class="block text-xs text-white/40 mb-1">Release date &amp; time</label>
                <input v-model="amountDate" type="datetime-local" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm mb-4" />
                <div class="flex justify-end gap-2">
                    <button @click="showAmount = false" class="text-sm text-white/50 px-4 py-2">Cancel</button>
                    <button @click="confirmAmount" :disabled="!amount || !amountDate || busy" class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2 disabled:opacity-50">Schedule</button>
                </div>
            </div>
        </div>
    </div>
</template>
