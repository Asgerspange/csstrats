<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useStore } from '@/store'
import { storeToRefs } from 'pinia'

const router = useRouter()
const store = useStore()
const { user } = storeToRefs(store)

interface Source { id: number; code: string; label: string | null }
interface Referrer {
    id: number; name: string; email: string;
    commission_rate: number; commission_type: 'first' | 'recurring';
    recurring_months: number | null; is_active: boolean;
    sources: Source[];
    stats: {
        visits: number; unique_visitors: number; signups: number;
        paying_customers: number; orders: number;
        by_currency: { currency: string; orders: number; gross: number; earned: number }[];
        earned_eur: number | null;
    };
}

const referrers = ref<Referrer[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const emptyForm = () => ({
    id: null as number | null,
    name: '', email: '', password: '',
    commission_rate: 20, commission_type: 'first' as 'first' | 'recurring',
    recurring_months: null as number | null, is_active: true,
    code: '', label: '',
})
const form = reactive(emptyForm())
const showForm = ref(false)
const isEdit = ref(false)

// Detail drawer
const detail = ref<any>(null)
const detailLoading = ref(false)
const newSourceCode = ref('')
const newSourceLabel = ref('')

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/admin/referrers')
        referrers.value = data.referrers
    } catch (e: any) {
        error.value = e?.response?.data?.message || 'Failed to load referrers.'
    } finally {
        loading.value = false
    }
}

function openCreate() {
    Object.assign(form, emptyForm())
    isEdit.value = false
    showForm.value = true
    error.value = ''
}

function openEdit(r: Referrer) {
    Object.assign(form, {
        id: r.id, name: r.name, email: r.email, password: '',
        commission_rate: r.commission_rate, commission_type: r.commission_type,
        recurring_months: r.recurring_months, is_active: r.is_active,
        code: '', label: '',
    })
    isEdit.value = true
    showForm.value = true
    error.value = ''
}

async function save() {
    saving.value = true
    error.value = ''
    try {
        const payload: any = {
            name: form.name, email: form.email,
            commission_rate: form.commission_rate, commission_type: form.commission_type,
            recurring_months: form.commission_type === 'recurring' ? form.recurring_months : null,
            is_active: form.is_active,
        }
        if (form.password) payload.password = form.password
        if (!isEdit.value) {
            if (form.code) { payload.code = form.code; payload.label = form.label || null }
            await axios.post('/api/admin/referrers', payload)
        } else {
            await axios.put(`/api/admin/referrers/${form.id}`, payload)
        }
        showForm.value = false
        await load()
    } catch (e: any) {
        const errs = e?.response?.data?.errors
        error.value = errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message || 'Save failed.')
    } finally {
        saving.value = false
    }
}

async function removeReferrer(r: Referrer) {
    if (!confirm(`Delete ${r.name}? This removes their login and all tracking data.`)) return
    await axios.delete(`/api/admin/referrers/${r.id}`)
    if (detail.value?.referrer?.id === r.id) detail.value = null
    await load()
}

async function openDetail(r: Referrer) {
    detailLoading.value = true
    detail.value = null
    try {
        const { data } = await axios.get(`/api/admin/referrers/${r.id}`)
        detail.value = data
    } finally {
        detailLoading.value = false
    }
}

async function addSource(referrerId: number) {
    if (!newSourceCode.value.trim()) return
    try {
        await axios.post(`/api/admin/referrers/${referrerId}/sources`, {
            code: newSourceCode.value.trim(),
            label: newSourceLabel.value.trim() || null,
        })
        newSourceCode.value = ''
        newSourceLabel.value = ''
        await load()
        const updated = referrers.value.find(r => r.id === referrerId)
        if (updated) await openDetail(updated)
    } catch (e: any) {
        alert(e?.response?.data?.errors?.code?.[0] || 'Could not add code.')
    }
}

async function removeSource(sourceId: number, referrerId: number) {
    if (!confirm('Remove this code? Existing attributions are kept but new visits to it stop tracking.')) return
    await axios.delete(`/api/admin/referral-sources/${sourceId}`)
    await load()
    const updated = referrers.value.find(r => r.id === referrerId)
    if (updated) await openDetail(updated)
}

function money(v: number, cur = 'EUR') {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(v ?? 0)
    } catch {
        return `${(v ?? 0).toFixed(2)} ${cur}`
    }
}
function fmtDate(v: string | null) {
    return v ? new Date(v).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: '2-digit' }) : '—'
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
                    <h1 class="text-xl font-bold">Referral partners</h1>
                    <p class="text-sm text-white/40">Manage partner logins, commission terms, and utm_source codes.</p>
                </div>
                <button @click="openCreate"
                    class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2 hover:brightness-105">
                    + New referrer
                </button>
            </div>

            <div v-if="loading" class="text-white/40 text-sm py-10">Loading…</div>

            <div v-else class="rounded-lg border border-white/10 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-white/[0.03] text-white/40 text-xs">
                        <tr>
                            <th class="text-left font-semibold px-4 py-2.5">Partner</th>
                            <th class="text-left font-semibold px-4 py-2.5">Codes</th>
                            <th class="text-left font-semibold px-4 py-2.5">Commission</th>
                            <th class="text-right font-semibold px-4 py-2.5">Visits</th>
                            <th class="text-right font-semibold px-4 py-2.5">Signups</th>
                            <th class="text-right font-semibold px-4 py-2.5">Subs</th>
                            <th class="text-right font-semibold px-4 py-2.5">Earned (EUR)</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in referrers" :key="r.id"
                            class="border-t border-white/10 hover:bg-white/[0.02] cursor-pointer"
                            @click="openDetail(r)">
                            <td class="px-4 py-3">
                                <div class="font-semibold flex items-center gap-2">
                                    {{ r.name }}
                                    <span v-if="!r.is_active" class="text-[10px] uppercase bg-white/10 text-white/50 rounded px-1.5 py-0.5">off</span>
                                </div>
                                <div class="text-white/40 text-xs">{{ r.email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span v-for="s in r.sources" :key="s.id"
                                    class="inline-block font-mono text-xs bg-white/[0.06] rounded px-1.5 py-0.5 mr-1 mb-1">{{ s.code }}</span>
                                <span v-if="!r.sources.length" class="text-white/30 text-xs">none</span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ r.commission_rate }}%
                                <span class="text-white/40">
                                    {{ r.commission_type === 'first' ? 'first order' : (r.recurring_months ? `recurring ${r.recurring_months}mo` : 'recurring') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ r.stats.visits }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ r.stats.signups }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ r.stats.paying_customers }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-primary"
                                :title="r.stats.by_currency.map(b => money(b.earned, b.currency)).join(' + ')">
                                {{ r.stats.earned_eur == null ? '—' : money(r.stats.earned_eur) }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                                <button @click="openEdit(r)" class="text-white/40 hover:text-white text-xs mr-3">Edit</button>
                                <button @click="removeReferrer(r)" class="text-white/40 hover:text-red-400 text-xs">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!referrers.length">
                            <td colspan="8" class="px-4 py-10 text-center text-white/30 text-sm">No referrers yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create / edit form -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="showForm = false">
            <div class="bg-zinc-900 border border-white/10 rounded-xl w-full max-w-md p-5">
                <h2 class="font-bold mb-4">{{ isEdit ? 'Edit referrer' : 'New referrer' }}</h2>
                <div v-if="error" class="text-red-300 text-xs bg-red-500/10 border border-red-500/30 rounded px-3 py-2 mb-3">{{ error }}</div>

                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Name</label>
                        <input v-model="form.name" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Email (login)</label>
                        <input v-model="form.email" type="email" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">{{ isEdit ? 'New password (leave blank to keep)' : 'Password' }}</label>
                        <input v-model="form.password" type="text" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Commission %</label>
                            <input v-model.number="form.commission_rate" type="number" min="0" max="100" step="0.5"
                                class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1">Type</label>
                            <select v-model="form.commission_type" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2">
                                <option value="first">First order only</option>
                                <option value="recurring">Every renewal</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="form.commission_type === 'recurring'">
                        <label class="block text-xs text-white/40 mb-1">Cap (months, blank = lifetime)</label>
                        <input v-model.number="form.recurring_months" type="number" min="1" max="120"
                            class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2" />
                    </div>
                    <div v-if="!isEdit" class="grid grid-cols-2 gap-3 pt-1 border-t border-white/10">
                        <div>
                            <label class="block text-xs text-white/40 mb-1 mt-2">First code (optional)</label>
                            <input v-model="form.code" placeholder="hltv" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs text-white/40 mb-1 mt-2">Code label</label>
                            <input v-model="form.label" placeholder="HLTV banner" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-white/60">
                        <input v-model="form.is_active" type="checkbox" /> Active
                    </label>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button @click="showForm = false" class="text-sm text-white/50 px-4 py-2">Cancel</button>
                    <button @click="save" :disabled="saving"
                        class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2 disabled:opacity-50">
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail drawer -->
        <div v-if="detail || detailLoading" class="fixed inset-0 z-40 flex justify-end bg-black/50" @click.self="detail = null">
            <div class="bg-zinc-900 border-l border-white/10 w-full max-w-2xl h-full overflow-y-auto p-6">
                <div v-if="detailLoading" class="text-white/40 text-sm">Loading…</div>
                <template v-else-if="detail">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-bold">{{ detail.referrer.name }}</h2>
                            <p class="text-white/40 text-xs">{{ detail.referrer.email }}</p>
                        </div>
                        <button @click="detail = null" class="text-white/40 hover:text-white text-xl leading-none">&times;</button>
                    </div>

                    <div class="grid grid-cols-4 gap-2 mb-6 text-center">
                        <div class="bg-white/[0.03] rounded-lg py-3">
                            <div class="text-lg font-bold tabular-nums">{{ detail.referrer.stats.visits }}</div>
                            <div class="text-[10px] uppercase text-white/40">Visits</div>
                        </div>
                        <div class="bg-white/[0.03] rounded-lg py-3">
                            <div class="text-lg font-bold tabular-nums">{{ detail.referrer.stats.signups }}</div>
                            <div class="text-[10px] uppercase text-white/40">Signups</div>
                        </div>
                        <div class="bg-white/[0.03] rounded-lg py-3">
                            <div class="text-lg font-bold tabular-nums">{{ detail.referrer.stats.paying_customers }}</div>
                            <div class="text-[10px] uppercase text-white/40">Subs</div>
                        </div>
                        <div class="bg-white/[0.03] rounded-lg py-3">
                            <div class="text-lg font-bold tabular-nums text-primary">{{ detail.referrer.stats.earned_eur == null ? '—' : money(detail.referrer.stats.earned_eur) }}</div>
                            <div class="text-[10px] uppercase text-white/40">Earned (EUR)</div>
                        </div>
                    </div>

                    <!-- Earnings by currency -->
                    <template v-if="detail.referrer.stats.by_currency.length">
                        <h3 class="text-xs uppercase tracking-wider text-white/40 font-bold mb-2">Earnings by currency</h3>
                        <table class="w-full text-xs mb-6">
                            <thead class="text-white/40">
                                <tr><th class="text-left py-1">Currency</th><th class="text-right">Orders</th><th class="text-right">Gross</th><th class="text-right">Earned</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in detail.referrer.stats.by_currency" :key="b.currency" class="border-t border-white/5">
                                    <td class="py-1.5 font-semibold">{{ b.currency }}</td>
                                    <td class="text-right tabular-nums">{{ b.orders }}</td>
                                    <td class="text-right tabular-nums">{{ money(b.gross, b.currency) }}</td>
                                    <td class="text-right tabular-nums text-primary">{{ money(b.earned, b.currency) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </template>

                    <!-- Sources -->
                    <h3 class="text-xs uppercase tracking-wider text-white/40 font-bold mb-2">utm_source codes</h3>
                    <div class="space-y-1 mb-3">
                        <div v-for="s in detail.referrer.sources" :key="s.id"
                            class="flex items-center justify-between bg-white/[0.03] rounded px-3 py-2">
                            <span class="font-mono text-xs">{{ s.code }} <span class="text-white/40">{{ s.label }}</span></span>
                            <button @click="removeSource(s.id, detail.referrer.id)" class="text-white/30 hover:text-red-400 text-xs">remove</button>
                        </div>
                    </div>
                    <div class="flex gap-2 mb-6">
                        <input v-model="newSourceCode" placeholder="code" class="flex-1 bg-white/[0.04] border border-white/10 rounded px-3 py-1.5 text-sm font-mono" />
                        <input v-model="newSourceLabel" placeholder="label" class="flex-1 bg-white/[0.04] border border-white/10 rounded px-3 py-1.5 text-sm" />
                        <button @click="addSource(detail.referrer.id)" class="bg-white/10 hover:bg-white/15 text-sm rounded px-3">Add</button>
                    </div>

                    <!-- Commissions -->
                    <h3 class="text-xs uppercase tracking-wider text-white/40 font-bold mb-2">Recent commissions</h3>
                    <table class="w-full text-xs mb-6">
                        <thead class="text-white/40">
                            <tr><th class="text-left py-1">Date</th><th class="text-left">Customer</th><th class="text-right">Order</th><th class="text-right">Earned</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in detail.commissions" :key="c.id" class="border-t border-white/5">
                                <td class="py-1.5">{{ fmtDate(c.billed_at) }}</td>
                                <td>{{ c.user?.name || '—' }}</td>
                                <td class="text-right tabular-nums">{{ money(Number(c.order_total), c.currency) }}</td>
                                <td class="text-right tabular-nums text-primary">{{ money(Number(c.commission_amount), c.currency) }}</td>
                            </tr>
                            <tr v-if="!detail.commissions.length"><td colspan="4" class="py-4 text-center text-white/30">No commissions yet.</td></tr>
                        </tbody>
                    </table>

                    <!-- Signups -->
                    <h3 class="text-xs uppercase tracking-wider text-white/40 font-bold mb-2">Recent signups</h3>
                    <table class="w-full text-xs">
                        <thead class="text-white/40">
                            <tr><th class="text-left py-1">Name</th><th class="text-left">Email</th><th class="text-right">Referred</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in detail.signups" :key="u.id" class="border-t border-white/5">
                                <td class="py-1.5">{{ u.name }}</td>
                                <td class="text-white/50">{{ u.email }}</td>
                                <td class="text-right">{{ fmtDate(u.referred_at) }}</td>
                            </tr>
                            <tr v-if="!detail.signups.length"><td colspan="3" class="py-4 text-center text-white/30">No signups yet.</td></tr>
                        </tbody>
                    </table>
                </template>
            </div>
        </div>
    </div>
</template>
