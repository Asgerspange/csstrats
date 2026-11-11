<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import axios from 'axios'
import { useStore } from '@/store'
import { useToast } from '@/composables/useToast'
import useImages from '@/composables/useImages'

import TeamDropdown from '@/componentsnew/TeamDropdown.vue'
import TypeDropdown from '@/componentsnew/TypeDropdown.vue'
import MapDropdown from '@/componentsnew/MapDropdown.vue'
import TagsDropdown from '@/componentsnew/TagsDropdown.vue'
import SideDropdown from '@/componentsnew/SideDropdown.vue'
import Select from '@/components/volt/Select.vue'
import WeaponSelectModal from '@/componentsnew/tactics/WeaponSelectModal.vue'
import ViewerIframe from '@/componentsnew/demo/ViewerIframe.vue'

const route = useRoute()
const router = useRouter()
const store = useStore()
const toast = useToast()
const { user } = storeToRefs(store)

const tacticId = computed(() => (route.params.id ? Number(route.params.id) : null))
const isEdit = computed(() => tacticId.value !== null)
const detectedTacticId = computed(() => route.query.dt ? Number(route.query.dt) : null)
const detectedTacticInfo = ref<any>(null)

/* ---------------------------------------------------------------------------
 * Reference data
 * ------------------------------------------------------------------------ */
const { Images } = useImages()
const { data: weaponImages } = Images.get('weapon')
const weaponUrl = (name: string): string => {
    const w = (weaponImages.value || []).find((i: any) => i.name?.toLowerCase() === name.toLowerCase())
    return w ? (w.url || w.base64) : ''
}

const maps = ref<any[]>([])
const demos = ref<any[]>([])
const selectedDemo = ref<any>(null)
const selectedDemoRound = ref<any>(null)
const previewVisible = ref(false)
const saving = ref(false)

/* ---------------------------------------------------------------------------
 * Tactic form state
 * ------------------------------------------------------------------------ */
function emptyPlayers() {
    return Array.from({ length: 5 }, () => ({ name: '', role: 'Player' }))
}
function emptyArr(key: 'timestamps' | 'descriptions') {
    return Array.from({ length: 5 }, () => ({ player: '', [key]: '' }))
}
function emptyLoadouts() {
    return Array.from({ length: 5 }, () => ({ player: '', loadouts: [] as string[] }))
}

const tactic = reactive<any>({
    team: { name: '', players: emptyPlayers() },
    player_timestamps: emptyArr('timestamps'),
    player_descriptions: emptyArr('descriptions'),
    player_loadouts: emptyLoadouts(),
    type: '',
    description: '',
    tags: [] as string[],
    map: '',
    video: '',
    matchlink: '',
    matchdate: '',
    side: '' as '' | 'CT' | 'T',
})

/* Weapon modal */
const weaponModal = reactive<{ visible: boolean; index: number; loadouts: string[]; side: string }>({
    visible: false,
    index: 0,
    loadouts: [],
    side: 'CT',
})

/* ---------------------------------------------------------------------------
 * Load reference data (+ existing tactic in edit mode)
 * ------------------------------------------------------------------------ */
async function loadReferenceData() {
    const [mapsRes, demosRes] = await Promise.all([
        axios.get('/api/maps'),
        axios.get('/api/demos', { params: { curated: 1 } }),
    ])
    maps.value = mapsRes.data ?? []
    // Only demos that can be previewed/round-loaded (have an id + map).
    demos.value = (demosRes.data ?? [])
        .filter((d: any) => d && d.id && d.map)
        .map((d: any) => ({ ...d, _label: `${d.name} — ${d.map}` }))
}

async function loadTactic() {
    if (!tacticId.value) return
    const { data } = await axios.get(`/api/admin/tactics/${tacticId.value}`)
    tactic.team = data.team && data.team.players ? data.team : { name: data.team_name ?? '', players: emptyPlayers() }
    tactic.player_timestamps = normalizeArr(data.player_timestamps, 'timestamps', tactic.team.players)
    tactic.player_descriptions = normalizeArr(data.player_descriptions, 'descriptions', tactic.team.players)
    tactic.player_loadouts = normalizeLoadouts(data.player_loadouts, tactic.team.players)
    tactic.type = data.type ?? ''
    tactic.description = data.description ?? ''
    tactic.tags = Array.isArray(data.tags) ? data.tags : (data.tags ? Object.values(data.tags) : [])
    tactic.map = data.map ?? ''
    tactic.video = data.video ?? ''
    tactic.matchlink = data.matchlink ?? ''
    tactic.matchdate = data.matchdate ? new Date(data.matchdate).toISOString().slice(0, 10) : ''
    tactic.side = data.side ?? ''
    // In edit mode players are already known, so show the player cards.
    if (data.demo) {
        // Try to surface the existing demo + round for an inline preview.
        const demo = demos.value.find((d: any) => String(d.id) === String(data.demo.demo_id))
        if (demo) {
            selectedDemo.value = demo
            await fetchRounds(demo.id)
            selectedDemoRound.value = (demo.rounds || []).find((r: any) => r.round_index === data.demo.round) ?? null
        }
    }
}

function normalizeArr(arr: any, key: string, players: any[]) {
    const base = players.map((p: any) => ({ player: p.name, [key]: '' }))
    if (Array.isArray(arr)) {
        arr.forEach((item: any, i: number) => {
            if (base[i]) base[i][key] = item?.[key] ?? ''
        })
    }
    return base
}
function normalizeLoadouts(arr: any, players: any[]) {
    const base = players.map((p: any) => ({ player: p.name, loadouts: [] as string[] }))
    if (Array.isArray(arr)) {
        arr.forEach((item: any, i: number) => {
            if (base[i]) base[i].loadouts = Array.isArray(item?.loadouts) ? item.loadouts : []
        })
    }
    return base
}

/* ---------------------------------------------------------------------------
 * Dropdown handlers
 * ------------------------------------------------------------------------ */
function onTeamChanged(name: string | string[] | null) {
    tactic.team.name = Array.isArray(name) ? (name[0] ?? '') : (name ?? '')
}
function onTypeChanged(value: string | null) {
    tactic.type = value ?? ''
}
function onMapChanged(mapId: number | null) {
    const m = maps.value.find((x: any) => x.id === mapId)
    tactic.map = m ? m.display_name : ''
}
function onTagsChanged(value: string[]) {
    tactic.tags = value
}
function onSideChanged(value: 'CT' | 'T' | null) {
    tactic.side = value ?? ''
    if (selectedDemoRound.value) autofillPlayersFromRound()
}

/* ---------------------------------------------------------------------------
 * Demo selection + round autofill
 * ------------------------------------------------------------------------ */
async function fetchRounds(demoId: number) {
    const { data } = await axios.get(`/api/demos/${demoId}/rounds`)
    const rounds = (data ?? [])
        .filter((r: any) => r.round_index > 0)
        .map((r: any) => ({ ...r, _label: `Round ${r.round_index}` }))
    if (selectedDemo.value && selectedDemo.value.id === demoId) {
        selectedDemo.value.rounds = rounds
    }
    return rounds
}

watch(selectedDemo, async (demo) => {
    selectedDemoRound.value = null
    previewVisible.value = false
    if (demo && demo.id && !demo.rounds) {
        await fetchRounds(demo.id)
    }
})

function onRoundChanged() {
    previewVisible.value = false
    if (tactic.side) autofillPlayersFromRound()
}

async function autofillPlayersFromRound() {
    if (!tactic.side || !selectedDemoRound.value) return

    // Prefer the curated freeze-time snapshot when the round carries one
    // (legacy admin demos stored it in meta.players). Demos parsed by the
    // unified pipeline don't, so reconstruct it from the round's frame_data.
    const meta = selectedDemoRound.value.meta
    const snapshot = (Array.isArray(meta?.players) && meta.players.length)
        ? meta.players
        : await playersFromFrameData(selectedDemo.value?.id, selectedDemoRound.value.round_index)
    if (!snapshot) return

    const players = snapshot.filter(
        (p: any) => (p.team_name || '').toLowerCase() === tactic.side.toLowerCase()
    )
    if (!players.length) return

    tactic.team.players = players.map((p: any) => ({ name: p.name, role: 'Player' }))
    tactic.player_loadouts = players.map((p: any) => ({ player: p.name, loadouts: getInventory(p.inventory) }))
    tactic.player_descriptions = players.map((p: any) => ({ player: p.name, descriptions: p.description || '' }))
    tactic.player_timestamps = players.map((p: any) => ({ player: p.name, timestamps: '' }))
}

/* Reconstruct the freeze-time player snapshot (name, side, inventory) from a
 * round's gzipped frame_data — the same payload the 2D viewer loads. Used when
 * meta.players is absent (demos parsed by the unified pipeline). */
const frameDataCache = new Map<string, any[] | null>()
async function playersFromFrameData(demoId: number | undefined, roundIndex: number): Promise<any[] | null> {
    if (!demoId) return null
    const cacheKey = `${demoId}:${roundIndex}`
    if (frameDataCache.has(cacheKey)) return frameDataCache.get(cacheKey) ?? null

    let players: any[] | null = null
    try {
        const { data } = await axios.get('/api/demo-chunk', { params: { demo_id: demoId, round_index: roundIndex } })
        players = freezeSnapshot(data)
    } catch {
        players = null
    }
    frameDataCache.set(cacheKey, players)
    return players
}

function playersAtTick(data: any, ticks: number[], target: number): any[] {
    let best = ticks[0]
    let bestDiff = Math.abs(ticks[0] - target)
    for (const t of ticks) {
        const d = Math.abs(t - target)
        if (d < bestDiff) { best = t; bestDiff = d }
    }
    return data.ticks[best]?.players ?? []
}

function freezeSnapshot(data: any): any[] | null {
    if (!data || !data.ticks) return null
    const ticks = Object.keys(data.ticks).map(Number).sort((a, b) => a - b)
    if (!ticks.length) return null

    const freezeEnd = Number(data.meta?.freeze_end ?? data.meta?.start ?? ticks[0])
    const base = playersAtTick(data, ticks, freezeEnd + 10)
    if (!base.length) return null

    // Fold in items acquired just after freeze (finished buys, dropped-weapon
    // pickups) so the loadout reflects the full round-start kit.
    const laterBySteam: Record<string, string[]> = {}
    for (const p of playersAtTick(data, ticks, freezeEnd + 100)) {
        laterBySteam[p.steamid] = Array.isArray(p.inventory) ? p.inventory : []
    }

    return base.map((p: any) => {
        const inv = Array.isArray(p.inventory) ? [...p.inventory] : []
        for (const item of (laterBySteam[p.steamid] || [])) if (!inv.includes(item)) inv.push(item)
        return { name: p.name, team_name: p.team_name, inventory: inv, description: '' }
    })
}

/* ---------------------------------------------------------------------------
 * Weapon name mapping (parsed demo inventory -> canonical sprite names)
 * ------------------------------------------------------------------------ */
const WEAPON_ALIASES: Record<string, string> = {
    'high explosive grenade': 'HE-Grenade', 'he_grenade': 'HE-Grenade', 'smoke': 'Smoke-Grenade',
    'smoke grenade': 'Smoke-Grenade', 'incendiary grenade': 'Incendiary-Grenade', 'dual berettas': 'Dual-Berettas',
    'desert eagle': 'Desert-Eagle', 'galil ar': 'Galil-AR', 'decoy': 'Decoy-Grenade',
    'incendiary_grenade': 'Incendiary-Grenade', 'usp_silencer': 'USP-S', 'glock': 'Glock-18', 'hkp2000': 'P2000',
    'elite': 'Dual-Berettas', 'ssg 08': 'SSG-08', 'ssg08': 'SSG-08', 'scar 20': 'SCAR-20', 'fiveseven': 'Five-SeveN',
    'ak47': 'AK-47', 'galilar': 'Galil-AR', 'mac10': 'MAC-10', 'm4a1_silencer': 'M4A1-S', 'deagle': 'Desert-Eagle',
    'tec9': 'Tec-9', 'hegrenade': 'HE-Grenade', 'm4a1': 'M4A1-S', 'decoy grenade': 'Decoy-Grenade', 'ump45': 'UMP-45',
    'zeus x27': 'Zeus-x27', 'c4 explosive': 'C4',
}
function mapWeapon(weapon: string): string | undefined {
    const known = (weaponImages.value || []).find((i: any) => i.name?.toLowerCase() === weapon.toLowerCase())
    if (known) return known.name
    return WEAPON_ALIASES[weapon.toLowerCase()]
}
function getInventory(inventory: any): string[] {
    const weapons: string[] = []
    for (const key in inventory) {
        if (inventory[key]) {
            const mapped = mapWeapon(inventory[key])
            if (mapped) weapons.push(mapped)
        }
    }
    return weapons
}

const WEAPON_ORDER = ['Galil-AR','FAMAS','AK-47','M4A4','M4A1-S','SSG-08','SG-553','AUG','AWP','G3SG1','SCAR-20','MAC-10','MP9','MP7','UMP-45','P90','PP-Bizon','MP5-SD','Nova','XM1014','Sawed-Off','MAG-7','M249','Negev','Glock-18','P2000','USP-S','Dual-Berettas','P250','Five-SeveN','CZ75-Auto','Tec-9','Desert-Eagle','R8-Revolver','Decoy-Grenade','Flashbang','Flashbang-2','HE-Grenade','Incendiary-Grenade','Molotov','Smoke-Grenade','Defuser','Zeus-x27','C4']
function sortedWeapons(loadouts: string[]): string[] {
    return WEAPON_ORDER.filter(w => loadouts.includes(w))
}

/* ---------------------------------------------------------------------------
 * Weapon modal
 * ------------------------------------------------------------------------ */
function openWeapon(index: number) {
    if (!tactic.side) {
        toast.warn('Select a side first', 'Pick CT or T before editing loadouts.')
        return
    }
    weaponModal.index = index
    weaponModal.loadouts = [...(tactic.player_loadouts[index]?.loadouts ?? [])]
    weaponModal.side = tactic.side
    weaponModal.visible = true
}
function onWeaponsChanged(payload: [number, string[]]) {
    const [index, weapons] = payload
    if (tactic.player_loadouts[index]) tactic.player_loadouts[index].loadouts = weapons
}

/* ---------------------------------------------------------------------------
 * Video / timestamp helpers (only convert plain inputs; leave embeds intact)
 * ------------------------------------------------------------------------ */
function extractVideoId(value: string): string | null {
    const watch = value.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&"?/]+)/)
    return watch ? watch[1] : null
}
function videoToEmbed(value: string): string {
    if (!value) return ''
    if (value.includes('<iframe')) return value // already an embed
    const id = extractVideoId(value)
    if (!id) return value
    return `<iframe class="tactic-video" src="https://www.youtube.com/embed/${id}" frameborder="0" allowfullscreen></iframe>`
}
function timestampToEmbed(ts: string, videoId: string | null): string {
    if (!ts) return ''
    if (ts.includes('<iframe')) return ts
    const parts = ts.split(':').map(Number)
    if (parts.length < 2 || parts.some(isNaN) || !videoId) return ts
    const seconds = parts.length === 3
        ? parts[0] * 3600 + parts[1] * 60 + parts[2]
        : parts[0] * 60 + parts[1]
    return `<iframe class="tactic-video" src="https://www.youtube.com/embed/${videoId}?start=${seconds}" frameborder="0" allowfullscreen></iframe>`
}

/* ---------------------------------------------------------------------------
 * YouTube description → per-player timestamps + match link autofill
 * Paste a video URL and the editor pulls the video description from the
 * YouTube Data API, matches "MM:SS - PlayerName" lines to the loaded players,
 * and lifts the HLTV match link out of the description.
 * ------------------------------------------------------------------------ */
const YOUTUBE_API_KEY = import.meta.env.VITE_YOUTUBE_API_KEY

async function autofillFromVideoDescription(videoUrl: string) {
    const videoId = extractVideoId(videoUrl)
    if (!videoId) return
    try {
        const res = await fetch(`https://www.googleapis.com/youtube/v3/videos?part=snippet&id=${videoId}&key=${YOUTUBE_API_KEY}`)
        const data = await res.json()
        const description: string | undefined = data?.items?.[0]?.snippet?.description
        if (!description) return

        // Lines like "0:42 - playerName" (optionally HH:MM:SS).
        const matches = description.match(/(?:\d{1,2}:)?\d{1,2}:\d{1,2} - [^\n]+/g) || []
        for (const line of matches) {
            const [timestamp, player] = line.split(' - ')
            if (!player) continue
            const idx = tactic.team.players.findIndex(
                (p: any) => p.name && p.name.toLowerCase() === player.trim().toLowerCase()
            )
            if (idx !== -1 && tactic.player_timestamps[idx]) {
                tactic.player_timestamps[idx].timestamps = timestamp.trim()
            }
        }

        // Lift the HLTV match link out of the description if we don't have one.
        const hltv = description.match(/https:\/\/www\.hltv\.org\/matches\/\d+\/[^\s]+/)
        if (hltv && !tactic.matchlink) tactic.matchlink = hltv[0]
    } catch (e) {
        // Non-fatal: the admin can still fill timestamps manually.
        console.error('YouTube description autofill failed', e)
    }
}

watch(() => tactic.video, (val) => {
    if (val) autofillFromVideoDescription(val)
})

/* ---------------------------------------------------------------------------
 * Submit
 * ------------------------------------------------------------------------ */
const canSubmit = computed(() =>
    !!tactic.team.name && !!tactic.type && !!tactic.map && !!tactic.side && !!tactic.matchdate
)

async function submit() {
    if (!canSubmit.value) {
        toast.warn('Missing fields', 'Team, type, map, side and match date are required.')
        return
    }
    saving.value = true

    const videoId = extractVideoId(tactic.video)
    const payload: any = {
        team: { name: tactic.team.name, players: tactic.team.players },
        type: tactic.type,
        description: tactic.description,
        tags: tactic.tags,
        map: tactic.map,
        side: tactic.side,
        video: videoToEmbed(tactic.video),
        matchlink: tactic.matchlink,
        matchdate: tactic.matchdate ? new Date(tactic.matchdate).toISOString() : '',
        player_timestamps: tactic.team.players.map((p: any, i: number) => ({
            player: p,
            timestamps: timestampToEmbed(tactic.player_timestamps[i]?.timestamps ?? '', videoId),
        })),
        player_descriptions: tactic.team.players.map((p: any, i: number) => ({
            player: p,
            descriptions: tactic.player_descriptions[i]?.descriptions ?? '',
        })),
        player_loadouts: tactic.team.players.map((p: any, i: number) => ({
            player: p,
            loadouts: tactic.player_loadouts[i]?.loadouts ?? [],
        })),
    }
    if (selectedDemo.value) {
        payload.demo_id = selectedDemo.value.id
        payload.demo_round = selectedDemoRound.value?.round_index
        payload.demo_map = selectedDemo.value.map
    }

    try {
        if (isEdit.value) {
            await axios.put(`/api/admin/tactics/${tacticId.value}`, payload)
            toast.success('Tactic updated')
        } else {
            const { data: created } = await axios.post('/api/admin/tactics', payload)
            toast.success('Tactic created', 'It starts unreleased — publish it from Releases.')

            // Link back to the detected tactic if we came from the scanner
            if (detectedTacticId.value && created?.id) {
                try {
                    await axios.post(`/api/admin/detected-tactics/${detectedTacticId.value}/link`, {
                        tactic_id: created.id,
                    })
                } catch {
                    // Non-fatal — the tactic is still created
                }
            }
        }
        router.push('/admin/tactics/release')
    } catch (e: any) {
        const errs = e?.response?.data?.errors
        toast.error('Save failed', errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message || 'Please try again.'))
    } finally {
        saving.value = false
    }
}

/* ---------------------------------------------------------------------------
 * Init
 * ------------------------------------------------------------------------ */
const playersVisible = computed(() => isEdit.value || !!selectedDemoRound.value)

onMounted(async () => {
    if (user.value && !user.value.is_admin) {
        router.push('/tactics')
        return
    }
    await loadReferenceData()
    if (isEdit.value) {
        await loadTactic()
    } else if (detectedTacticId.value) {
        await loadFromDetectedTactic(detectedTacticId.value)
    }
})

async function loadFromDetectedTactic(dtId: number) {
    try {
        const { data } = await axios.get(`/api/admin/detected-tactics/${dtId}`)
        detectedTacticInfo.value = data

        // Pre-fill tactic form from detected tactic
        tactic.map = data.demo?.map ?? ''
        tactic.side = data.side === 't' ? 'T' : 'CT'
        tactic.type = data.pattern_type ?? ''
        tactic.description = data.description ?? ''
        tactic.team = {
            name: data.team_name ?? '',
            players: (data.player_names ?? []).map((n: string) => ({ name: n, role: 'Player' })),
        }
        tactic.team.players = [...tactic.team.players, ...Array.from({ length: Math.max(0, 5 - (data.player_names ?? []).length) }, () => ({ name: '', role: 'Player' }))].slice(0, 5)
        tactic.player_timestamps = tactic.team.players.map((p: any) => ({ player: p.name, timestamps: '' }))
        tactic.player_descriptions = tactic.team.players.map((p: any) => ({ player: p.name, descriptions: '' }))
        tactic.player_loadouts = tactic.team.players.map((p: any) => ({ player: p.name, loadouts: [] as string[] }))

        // Set video URL if recorded
        if (data.video_url) {
            tactic.video = data.video_url
        }

        // Set matchdate from demo creation time
        if (data.demo?.created_at) {
            tactic.matchdate = new Date(data.demo.created_at).toISOString().slice(0, 10)
        }

        // Select the demo + round
        const demo = demos.value.find((d: any) => String(d.id) === String(data.demo_id))
        if (demo) {
            selectedDemo.value = demo
            await fetchRounds(demo.id)
            selectedDemoRound.value = (demo.rounds || []).find((r: any) => r.round_index === data.round_index) ?? null
            if (tactic.side) autofillPlayersFromRound()
        }

        toast.success('Pre-filled from detected tactic', `Round ${data.round_index} — ${data.pattern_type}`)
    } catch (e: any) {
        toast.error('Load failed', 'Could not load detected tactic data.')
    }
}
</script>

<template>
    <div class="h-full overflow-y-auto p-6 text-white">
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <button @click="router.push('/admin/tactics/release')" class="material-symbols text-white/40 hover:text-white">west</button>
                    <div>
                        <h1 class="text-xl font-bold">{{ isEdit ? 'Edit tactic' : 'Create tactic' }}</h1>
                        <p class="text-sm text-white/40">Pick a demo round to autofill players, then refine and save.</p>
                    </div>
                </div>
                <button @click="submit" :disabled="saving || !canSubmit"
                    class="bg-primary text-black font-semibold text-sm rounded-md px-4 py-2 disabled:opacity-50 hover:brightness-105">
                    {{ saving ? 'Saving…' : (isEdit ? 'Save changes' : 'Save tactic') }}
                </button>
            </div>

            <!-- Detected tactic info banner -->
            <div v-if="detectedTacticInfo" class="mb-4 p-3 rounded-md bg-blue-500/10 border border-blue-500/20 text-sm">
                <span class="text-blue-400 font-medium">From Demo Scanner:</span>
                Round {{ detectedTacticInfo.round_index }} [{{ detectedTacticInfo.side.toUpperCase() }}]
                {{ detectedTacticInfo.pattern_type.replace(/_/g, ' ') }}
                — Score: {{ detectedTacticInfo.tactical_value_score }}
                <span v-if="detectedTacticInfo.status === 'recorded'" class="text-green-400 ml-2">(Recorded)</span>
            </div>

            <!-- Meta form -->
            <div class="rounded-lg border border-white/10 bg-white/[0.02] p-5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Team</label>
                        <TeamDropdown :selectedTeams="tactic.team.name ? [tactic.team.name] : []" @changed="onTeamChanged" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Type</label>
                        <TypeDropdown :tacticType="tactic.type" @changed="onTypeChanged" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Map</label>
                        <MapDropdown :tacticMapId="tactic.map" @changed="onMapChanged" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Side</label>
                        <SideDropdown v-model="tactic.side" @changed="onSideChanged" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Tags</label>
                        <TagsDropdown :tacticTags="tactic.tags" @changed="onTagsChanged" />
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Match date</label>
                        <input v-model="tactic.matchdate" type="date" class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm" />
                    </div>

                    <div v-if="tactic.side && tactic.team.name">
                        <label class="block text-xs text-white/40 mb-1">Demo</label>
                        <Select v-model="selectedDemo" :options="demos" filter
                            optionLabel="_label" placeholder="Select demo" class="w-full" showClear />
                    </div>
                    <div v-if="selectedDemo">
                        <label class="block text-xs text-white/40 mb-1">Round</label>
                        <Select v-model="selectedDemoRound" :options="selectedDemo.rounds || []" filter
                            optionLabel="_label" placeholder="Select round" class="w-full"
                            @change="onRoundChanged" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-xs text-white/40 mb-1">Tactic description</label>
                        <textarea v-model="tactic.description" rows="3"
                            class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm"
                            placeholder="Describe the overall strategy…"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-white/40 mb-1">Video link</label>
                        <input v-model="tactic.video" placeholder="https://youtube.com/watch?v=…"
                            class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-white/40 mb-1">Match link (HLTV)</label>
                        <input v-model="tactic.matchlink" placeholder="https://hltv.org/matches/…"
                            class="w-full bg-white/[0.04] border border-white/10 rounded px-3 py-2 text-sm" />
                    </div>
                </div>

                <!-- Round preview -->
                <div v-if="selectedDemoRound" class="mt-5">
                    <button v-if="!previewVisible" @click="previewVisible = true"
                        class="text-xs text-primary border border-primary/30 rounded px-3 py-1.5 hover:bg-primary/10">
                        Show round preview
                    </button>
                    <div v-else class="relative w-full aspect-video bg-black/60 rounded-lg overflow-hidden border border-white/10">
                        <ViewerIframe :demoId="String(selectedDemo.id)" :roundIndex="selectedDemoRound.round_index" :map="selectedDemo.map" />
                    </div>
                </div>
            </div>

            <!-- Players -->
            <div v-if="playersVisible">
                <h2 class="text-sm font-bold uppercase tracking-wide text-white/50 mb-3">Players</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div v-for="(player, index) in tactic.team.players" :key="index"
                        class="rounded-lg border border-white/10 bg-white/[0.02] overflow-hidden"
                        :class="tactic.side === 'CT' ? 'border-t-2 border-t-[#4a9eff]' : 'border-t-2 border-t-[#ff6b35]'">
                        <div class="flex items-center gap-2 px-4 py-3 bg-white/[0.03]">
                            <span class="w-6 h-6 rounded-full bg-white/10 flex items-center justify-center text-xs font-bold">{{ index + 1 }}</span>
                            <input v-model="tactic.team.players[index].name" placeholder="Player name"
                                class="flex-1 bg-transparent border-b border-white/10 px-1 py-1 text-sm font-semibold focus:outline-none focus:border-primary" />
                        </div>
                        <div class="p-4 flex flex-col gap-3">
                            <div>
                                <label class="block text-[11px] uppercase text-white/40 mb-1">Timestamp</label>
                                <input v-model="tactic.player_timestamps[index].timestamps" placeholder="0:42"
                                    class="w-full bg-white/[0.04] border border-white/10 rounded px-2.5 py-1.5 text-sm" />
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase text-white/40 mb-1">Role &amp; responsibilities</label>
                                <textarea v-model="tactic.player_descriptions[index].descriptions" rows="2"
                                    class="w-full bg-white/[0.04] border border-white/10 rounded px-2.5 py-1.5 text-sm"
                                    placeholder="Describe the player's role…"></textarea>
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase text-white/40 mb-1">Loadout</label>
                                <div @click="openWeapon(index)"
                                    class="min-h-[64px] rounded border border-dashed border-white/15 bg-white/[0.03] flex flex-wrap gap-1.5 items-center justify-center p-2 cursor-pointer hover:border-primary/50">
                                    <template v-if="tactic.player_loadouts[index]?.loadouts?.length">
                                        <img v-for="w in sortedWeapons(tactic.player_loadouts[index].loadouts)" :key="w"
                                            :src="weaponUrl(w)" :alt="w" class="h-7 object-contain" />
                                    </template>
                                    <span v-else class="text-xs text-white/30">+ Select loadout</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-white/30 text-sm py-10 border border-dashed border-white/10 rounded-lg">
                Select a side, team, demo and round to load players.
            </div>
        </div>

        <WeaponSelectModal
            :visible="weaponModal.visible"
            :playerWeapons="weaponModal.loadouts"
            :index="weaponModal.index"
            :currentSide="weaponModal.side"
            @changed="onWeaponsChanged"
            @closed="weaponModal.visible = false"
        />
    </div>
</template>
