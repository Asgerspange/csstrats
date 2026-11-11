<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import axios from 'axios'
import { useStore } from '@/store'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const store = useStore()
const toast = useToast()
const { user } = storeToRefs(store)

// ── List state ──
const loading = ref(true)
const teams = ref<any[]>([])
const search = ref('')
const page = ref(1)
const total = ref(0)
const lastPage = ref(1)

// ── Detail state ──
const detailVisible = ref(false)
const detailLoading = ref(false)
const detailTeam = ref<any>(null)
const detailMaps = ref<any[]>([])

// ── Edit modals ──
const editStratbook = ref<any>(null)
const editStratbookName = ref('')
const editStratbookMapId = ref<number | null>(null)

const editTactic = ref<any>(null)
const editTacticName = ref('')
const editTacticType = ref('')
const editTacticMap = ref('')
const editTacticSide = ref('')
const editTacticStratbookId = ref<number | null>(null)

const moveTacticTarget = ref<number | null>(null)

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/admin/teams', {
            params: { search: search.value || undefined, page: page.value },
        })
        teams.value = data.data
        total.value = data.total
        lastPage.value = data.last_page
    } catch {
        toast.error('Failed to load teams')
    } finally {
        loading.value = false
    }
}

async function loadDetail(id: number) {
    detailLoading.value = true
    detailVisible.value = true
    try {
        const { data } = await axios.get(`/api/admin/teams/${id}`)
        detailTeam.value = data.team
        detailMaps.value = data.maps
    } catch {
        toast.error('Failed to load team')
    } finally {
        detailLoading.value = false
    }
}

function closeDetail() {
    detailVisible.value = false
    detailTeam.value = null
}

async function convertToMapBased(teamId: number) {
    if (!confirm('Convert this team to map_based? Stratbooks will be matched to maps by name, duplicates merged, missing maps provisioned.')) return
    try {
        await axios.post(`/api/admin/teams/${teamId}/convert`)
        toast.success('Converted', 'Team is now map_based.')
        await loadDetail(teamId)
        await load()
    } catch (e: any) {
        toast.error('Conversion failed', e?.response?.data?.message ?? 'Unknown error')
    }
}

function openEditStratbook(sb: any) {
    editStratbook.value = sb
    editStratbookName.value = sb.stratbook_name
    editStratbookMapId.value = sb.map_id
}

async function saveStratbook() {
    if (!editStratbook.value) return
    try {
        await axios.patch(`/api/admin/stratbooks/${editStratbook.value.id}`, {
            stratbook_name: editStratbookName.value,
            map_id: editStratbookMapId.value,
        })
        toast.success('Stratbook updated')
        editStratbook.value = null
        await loadDetail(detailTeam.value.id)
    } catch {
        toast.error('Update failed')
    }
}

function openEditTactic(t: any) {
    editTactic.value = t
    editTacticName.value = t.name ?? ''
    editTacticType.value = t.type ?? ''
    editTacticMap.value = t.map ?? ''
    editTacticSide.value = t.side ?? ''
    editTacticStratbookId.value = t.stratbook_id
}

async function saveTactic() {
    if (!editTactic.value) return
    try {
        await axios.patch(`/api/admin/tactics/${editTactic.value.id}`, {
            name: editTacticName.value,
            type: editTacticType.value,
            map: editTacticMap.value,
            side: editTacticSide.value,
            stratbook_id: editTacticStratbookId.value || undefined,
        })
        toast.success('Tactic updated')
        editTactic.value = null
        await loadDetail(detailTeam.value.id)
    } catch {
        toast.error('Update failed')
    }
}

async function duplicateTactic(t: any) {
    try {
        await axios.post(`/api/admin/tactics/${t.id}/duplicate`)
        toast.success('Tactic duplicated')
        await loadDetail(detailTeam.value.id)
    } catch {
        toast.error('Duplicate failed')
    }
}

async function deleteTactic(t: any) {
    if (!confirm(`Permanently delete "${t.name ?? t.type}"?`)) return
    try {
        await axios.delete(`/api/admin/tactics/${t.id}`)
        toast.success('Tactic deleted')
        await loadDetail(detailTeam.value.id)
    } catch {
        toast.error('Delete failed')
    }
}

async function assignMapToStratbook(stratbookId: number, mapId: number, merge: boolean) {
    try {
        await axios.post(`/api/admin/stratbooks/${stratbookId}/assign-map`, { map_id: mapId, merge })
        toast.success('Map assigned')
        await loadDetail(detailTeam.value.id)
    } catch (e: any) {
        toast.error('Assign failed', e?.response?.data?.message ?? 'Unknown error')
    }
}

function stratbooksForMove(combatTacticId: number) {
    if (!detailTeam.value) return []
    return detailTeam.value.stratbooks.filter((sb: any) => sb.id !== combatTacticId)
}

onMounted(() => {
    if (user.value && !user.value.is_admin) { router.push('/tactics'); return }
    load()
})

const filteredTeams = computed(() => teams.value)
</script>

<template>
    <div class="h-full overflow-y-auto p-6 text-white">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold">Teams & Tactic Management</h1>
                    <p class="text-sm text-white/40">Manage teams, stratbooks, tactics, and convert to map_based.</p>
                </div>
            </div>

            <!-- Search -->
            <div class="flex gap-3 mb-5">
                <input v-model="search" type="search" placeholder="Search team or owner name/email..."
                    class="flex-1 bg-white/[0.04] border border-white/10 rounded-lg px-4 py-2 text-sm outline-none focus:border-primary"
                    @keydown.enter="page = 1; load()" />
                <button @click="page = 1; load()" class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2">Search</button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-white/40 py-8 text-center">Loading teams...</div>

            <!-- Table -->
            <div v-else class="rounded-lg border border-white/10 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-white/[0.03] text-white/50 text-xs uppercase">
                        <tr>
                            <th class="text-left px-4 py-3">Team</th>
                            <th class="text-left px-4 py-3">Owner</th>
                            <th class="text-left px-4 py-3">Mode</th>
                            <th class="text-right px-4 py-3">Stratbooks</th>
                            <th class="text-right px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in filteredTeams" :key="t.id" class="border-t border-white/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 font-medium">{{ t.name }}</td>
                            <td class="px-4 py-3 text-white/60">{{ t.owner?.name }} <span class="text-white/30 text-xs">{{ t.owner?.email }}</span></td>
                            <td class="px-4 py-3">
                                <span :class="t.entitlement_mode === 'map_based' ? 'text-primary' : 'text-white/40'" class="text-xs font-semibold uppercase">
                                    {{ t.entitlement_mode }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-white/60">{{ t.stratbooks?.length ?? 0 }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="loadDetail(t.id)" class="text-primary text-xs font-semibold hover:underline">Manage</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1" class="flex items-center justify-between mt-4 text-sm text-white/40">
                <span class="tabular-nums">Page {{ page }} of {{ lastPage }} ({{ total }} teams)</span>
                <div class="flex gap-2">
                    <button v-if="page > 1" @click="page--; load()" class="px-3 py-1 rounded border border-white/10 hover:bg-white/5">Prev</button>
                    <button v-if="page < lastPage" @click="page++; load()" class="px-3 py-1 rounded border border-white/10 hover:bg-white/5">Next</button>
                </div>
            </div>
        </div>

        <!-- ── Team detail modal ── -->
        <div v-if="detailVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="closeDetail">
            <div class="bg-zinc-900 border border-white/10 rounded-xl w-full max-w-4xl max-h-[85vh] overflow-y-auto">
                <!-- Modal header -->
                <div class="flex items-center justify-between p-5 border-b border-white/10 sticky top-0 bg-zinc-900 z-10">
                    <div>
                        <h2 class="text-lg font-bold">{{ detailTeam?.name }}</h2>
                        <p class="text-sm text-white/40">
                            Owner: {{ detailTeam?.owner?.name }} ({{ detailTeam?.owner?.email }})
                            <span class="ml-2 text-xs font-semibold uppercase" :class="detailTeam?.entitlement_mode === 'map_based' ? 'text-primary' : 'text-white/30'">
                                {{ detailTeam?.entitlement_mode }}
                            </span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button v-if="detailTeam?.entitlement_mode === 'legacy'" @click="convertToMapBased(detailTeam.id)"
                            class="bg-primary text-black font-semibold text-xs rounded-md px-3 py-1.5 hover:brightness-105">
                            Convert to map_based
                        </button>
                        <button @click="closeDetail" class="text-white/40 hover:text-white px-2">
                            <span class="material-symbols">close</span>
                        </button>
                    </div>
                </div>

                <div v-if="detailLoading" class="p-8 text-center text-white/40">Loading...</div>

                <div v-else class="p-5">
                    <!-- Stratbooks -->
                    <h3 class="text-sm font-bold uppercase text-white/50 mb-3">Stratbooks</h3>
                    <div class="space-y-2 mb-6">
                        <div v-for="sb in detailTeam?.stratbooks" :key="sb.id"
                            class="rounded-lg border border-white/10 bg-white/[0.02] p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="font-medium text-sm">{{ sb.stratbook_name }}</span>
                                    <span v-if="sb.map_id" class="text-xs text-primary bg-primary/10 px-1.5 py-0.5 rounded">
                                        Map ID: {{ sb.map_id }}
                                    </span>
                                    <span v-else class="text-xs text-white/30">No map assigned</span>
                                    <span class="text-xs text-white/30 tabular-nums">{{ sb.tactics_count }} tactic{{ sb.tactics_count !== 1 ? 's' : '' }}</span>
                                </div>
                                <button @click="openEditStratbook(sb)" class="text-primary text-xs font-semibold hover:underline shrink-0">Edit</button>
                            </div>

                            <!-- Tactics inside this stratbook -->
                            <div v-if="sb.tactics?.length" class="mt-2 ml-4 space-y-1">
                                <div v-for="t in sb.tactics" :key="t.id"
                                    class="flex items-center justify-between gap-2 text-xs text-white/60 py-1 px-2 rounded hover:bg-white/[0.03]">
                                    <span class="truncate">
                                        {{ t.name || t.type }}
                                        <span class="text-white/30 ml-1">{{ t.map }} {{ t.side }}</span>
                                        <span v-if="t.tactic_id" class="text-primary/40 ml-1">(library)</span>
                                    </span>
                                    <div class="flex gap-2 shrink-0">
                                        <button @click="openEditTactic(t)" class="text-primary/70 hover:text-primary">Edit</button>
                                        <button @click="duplicateTactic(t)" class="text-primary/70 hover:text-primary">Copy</button>
                                        <button @click="deleteTactic(t)" class="text-red-400/70 hover:text-red-400">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Edit stratbook modal ── -->
        <div v-if="editStratbook" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4" @click.self="editStratbook = null">
            <div class="bg-zinc-900 border border-white/10 rounded-xl w-full max-w-md p-5">
                <h3 class="text-base font-bold mb-4">Edit stratbook</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-white/40 uppercase">Name</label>
                        <input v-model="editStratbookName" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary" />
                    </div>
                    <div>
                        <label class="text-xs text-white/40 uppercase">Map assignment</label>
                        <select v-model="editStratbookMapId" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary">
                            <option :value="null">No map</option>
                            <option v-for="m in detailMaps" :key="m.id" :value="m.id">{{ m.display_name }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button @click="editStratbook = null" class="px-4 py-2 text-sm text-white/50 hover:text-white">Cancel</button>
                    <button @click="saveStratbook" class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2">Save</button>
                </div>
            </div>
        </div>

        <!-- ── Edit tactic modal ── -->
        <div v-if="editTactic" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4" @click.self="editTactic = null">
            <div class="bg-zinc-900 border border-white/10 rounded-xl w-full max-w-md p-5">
                <h3 class="text-base font-bold mb-4">Edit tactic</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-white/40 uppercase">Name</label>
                        <input v-model="editTacticName" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-white/40 uppercase">Type</label>
                            <input v-model="editTacticType" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary" />
                        </div>
                        <div>
                            <label class="text-xs text-white/40 uppercase">Side</label>
                            <select v-model="editTacticSide" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary">
                                <option value="">-</option>
                                <option value="CT">CT</option>
                                <option value="T">T</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-white/40 uppercase">Map</label>
                            <input v-model="editTacticMap" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary" />
                        </div>
                        <div>
                            <label class="text-xs text-white/40 uppercase">Move to stratbook</label>
                            <select v-model="editTacticStratbookId" class="w-full mt-1 bg-white/[0.04] border border-white/10 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary">
                                <option v-for="sb in detailTeam?.stratbooks" :key="sb.id" :value="sb.id">{{ sb.stratbook_name }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button @click="editTactic = null" class="px-4 py-2 text-sm text-white/50 hover:text-white">Cancel</button>
                    <button @click="saveTactic" class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
