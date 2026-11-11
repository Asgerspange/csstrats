<template>
    <Dialog 
        v-model:visible="visible" 
        :closable="true" 
        :draggable="false" 
        :resizable="false"
        :header="props.tactic ? 'Edit Tactic' : 'New Tactic'"
        appendTo="body"
        class="w-[1200px]"
    >
        <div class="flex gap-8 p-6 max-h-[75vh] overflow-hidden">
            <div class="w-1/3 flex flex-col gap-6 border-r border-white/5 pr-8">
                <div class="flex flex-col gap-4">
                    <h3 class="text-primary font-bold uppercase tracking-widest text-xs">Tactic</h3>

                    <div class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] text-gray-500 font-bold uppercase">Tactic Name</label>
                            <input v-model="form.name" type="text" class="custom-input" placeholder="e.g. A-Site Contact" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] text-gray-500 font-bold uppercase">Map</label>
                                <MapDropdown v-model="form.map" :tacticMapId="form.map" :disabled="isMapBased" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] text-gray-500 font-bold uppercase">Side</label>
                                <SideDropdown v-model="form.side" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] text-gray-500 font-bold uppercase">Tags</label>
                                <TagsDropdown v-model="form.tags" :teamTags="team?.settings?.['tags']" />
                            </div>
                             <div class="flex flex-col gap-2">
                                <label class="text-[10px] text-gray-500 font-bold uppercase">Buy Type</label>
                                <TypeDropdown v-model="form.type" :teamTypes="team?.settings?.['types']" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] text-gray-500 font-bold uppercase">Description</label>
                            <textarea v-model="form.description" rows="6" class="custom-input resize-none" placeholder="General flow of the round..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-2/3 flex flex-col gap-6 overflow-y-auto custom-track pr-4">
                <div class="flex justify-between items-center sticky top-0 z-10 pb-2">
                    <h3 class="text-primary font-bold uppercase text-xs">Players</h3>
                    <Button severity="secondary" size="sm" @click="addPlayer" v-if="form.players.length < 5">
                        <i class="pi pi-plus mr-2"></i> Add Player
                    </Button>
                </div>

                <div class="space-y-2">
                    <div 
                        v-for="(player, index) in form.players" 
                        :key="index"
                        class="bg-white/5 border border-white/5 rounded-2xl p-4 transition-all hover:border-primary/20"
                    >
                        <div class="flex gap-4 mb-4 items-center">
                            <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center text-primary font-black text-xs">
                                {{ index + 1 }}
                            </div>
                            <input 
                                v-model="player.name" 
                                class="bg-transparent border-b border-white/10 flex-1 text-md font-bold focus:border-primary focus:outline-none pb-1 text-white"
                                placeholder="Player / Role Name"
                            />
                            <button @click="removePlayer(index)" class="text-gray-600 hover:text-red-500 transition-colors">
                                <i class="pi pi-trash"></i>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-8">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] text-gray-500 font-bold uppercase">Description</label>
                                <CalloutMentionTextarea
                                     v-model="player.description"
                                     :rows="5"
                                     :map-name="form.map ? maps?.find((m: any) => m.id === form.map)?.display_name : null"
                                     :team-id="selectedTeamId"
                                     placeholder="e.g. Push B main, type / to mention a callout..."
                                />
                            </div>

                            <div class="flex flex-col gap-3">
                                <label class="text-[10px] text-gray-500 font-bold uppercase flex justify-between">
                                    Loadout <span>{{ player.loadout.length }} Items</span>
                                </label>
                                
                                <div 
                                    @click="openLoadoutSelector(index)"
                                    class="flex-1 bg-white/5 rounded-xl border border-dashed border-white/10 hover:border-primary/50 hover:bg-primary/5 transition-all cursor-pointer p-3 flex flex-wrap gap-2 content-start min-h-[128px]"
                                >
                                    <div v-if="player.loadout.length === 0" class="text-gray-600 text-xs italic m-auto">
                                        Click to select weapons...
                                    </div>
                                    <div 
                                        v-for="weaponName in player.loadout" 
                                        :key="weaponName"
                                        class="bg-black/40 border border-white/10 rounded px-2 py-1 flex items-center gap-2 group"
                                    >
                                        <img :src="getWeaponIcon(weaponName)" class="h-4 opacity-80" />
                                        <span class="text-[10px] font-bold uppercase tracking-tighter">{{ weaponName }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2 pt-4 px-6 pb-6">
                <Button severity="secondary" @click="close()" class="px-6">Cancel</Button>
                <Button :loading="isCreating" @click="submit" :disabled="!isValid" class="px-10">
                    {{ props.tactic ? 'Update' : 'Create' }}
                </Button>
            </div>
        </template>
    </Dialog>

    <Dialog 
        v-model:visible="loadoutSelectorVisible" 
        header="Select Loadout" 
        appendTo="body"
        class="w-[900px]"
        :modal="true"
        :draggable="false"
    >
        <div class="p-4 max-h-[70vh] overflow-y-auto custom-track">
            <div v-for="(items, category) in groupedWeapons" :key="category" class="mb-8">
                <h4 class="text-primary font-black uppercase text-[10px] tracking-widest mb-4 border-b border-primary/20 pb-2">
                    {{ category }}
                </h4>
                <div class="grid grid-cols-4 gap-3">
                    <div 
                        v-for="weapon in items" 
                        :key="weapon.id"
                        @click="toggleWeaponSelection(weapon.name)"
                        :class="[
                            'relative flex flex-col items-center justify-center p-4 rounded-xl border transition-all cursor-pointer group',
                            isWeaponSelected(weapon.name) 
                                ? 'bg-primary/10 border-primary shadow-[0_0_15px_rgba(var(--color-primary-rgb),0.1)]' 
                                : 'bg-white/5 border-white/5 hover:border-white/20'
                        ]"
                    >
                        <img :src="weapon.url || weapon.base64" class="h-10 object-contain mb-2 group-hover:scale-110 transition-transform" />
                        <span class="text-[10px] font-bold uppercase text-center tracking-tight" :class="isWeaponSelected(weapon.name) ? 'text-primary' : 'text-gray-400'">
                            {{ weapon.name }}
                        </span>
                        <div v-if="isWeaponSelected(weapon.name)" class="absolute top-2 right-2">
                            <i class="pi pi-check-circle text-primary text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end p-4 border-t border-white/5 bg-[#0c0c0c]">
                <Button severity="primary" @click="loadoutSelectorVisible = false" class="px-8">Done</Button>
            </div>
        </template>
    </Dialog>
</template>

<script setup lang="ts">
import { reactive, computed, toRefs, ref, watch, provide } from 'vue'
import Dialog from '@/components/volt/Dialog.vue'
import Button from '@/components/volt/Button.vue'
import MapDropdown from '@/componentsnew/MapDropdown.vue'
import SideDropdown from '@/componentsnew/SideDropdown.vue'
import TagsDropdown from '@/componentsnew/TagsDropdown.vue'
import TypeDropdown from '@/componentsnew/TypeDropdown.vue'
import useStratbookTactics from '@/composables/useStratbookTactics'
import useImages from '@/composables/useImages'
import useMaps from '@/composables/useMaps'
import { useToast } from '@/composables/useToast';
import { useCallouts } from '@/composables/useCallouts'
import CalloutMentionTextarea from '@/componentsnew/callouts/CalloutMentionTextarea.vue'
import { storeToRefs } from 'pinia'
import { useStore } from '@/store'
 
const { selectedTeamId } = storeToRefs(useStore())


const toast = useToast();
const props = withDefaults(defineProps<{ stratbookId: number, tactic: any }>(), {
    tactic: null
})

const { stratbookId } = toRefs(props)
const visible = defineModel<boolean>('visible', { default: false })
const emit = defineEmits(['update:visible'])

const store = useStore()
const team = computed(() => store.selectedTeam)
const isMapBased = computed(() => team.value?.entitlement_mode === 'map_based')
const { StratbookTactics, isCreating } = useStratbookTactics(stratbookId)
const { Images } = useImages()
const { Maps } = useMaps()
const { data: weapons } = Images.get('weapon')
const { data: maps } = Maps.get()
const { zones, load } = useCallouts(selectedTeamId.value)
provide('callout-zones', zones)

watch(selectedTeamId, (id) => {
    if (id) load()
}, { immediate: true })
// --- Main Form State ---
const form = reactive({
    name: '',
    map: null as string | null,
    side: null as 'CT' | 'T' | null,
    type: null as string | null,
    description: '',
    tags: [] as string[],
    players: [] as any[]
})

// --- Logic to stitch together the normalized JSON ---
const populateForm = (data: any) => {
    if (data) {
        console.log(data)
        form.name = data.name || ''
        const mapId = maps.value?.find((m: any) => m.display_name.toLowerCase() === data.map?.toLowerCase())?.id || null
        form.map = mapId || null
        form.side = data.side || null
        form.type = data.type || null
        form.description = data.description || ''
        form.tags = [...(data.tags || [])]

        // Stitching logic: 
        // 1. Get the list of players from the team object
        // 2. Map descriptions from player_descriptions array
        // 3. Map loadouts from player_loadouts array
        if (data.team?.players) {
            form.players = data.team.players.map((p: any) => {
                let descMatch = data.player_descriptions?.find((d: any) => d.player === p.name)
                let loadoutMatch = data.player_loadouts?.find((l: any) => l.player === p.name)
                if (!descMatch) {
                    descMatch = data.player_descriptions?.find((d: any) => d.player.name.toLowerCase() === p.name.toLowerCase())
                }

                if (!loadoutMatch) {
                    loadoutMatch = data.player_loadouts?.find((l: any) => l.player.name.toLowerCase() === p.name.toLowerCase())
                }
                return {
                    name: p.name,
                    role: p.role,
                    description: descMatch ? descMatch.descriptions : '', // based on your JSON key 'descriptions'
                    loadout: loadoutMatch ? [...loadoutMatch.loadouts] : [] // based on your JSON key 'loadouts'
                }
            })
        }
    } else {
        resetForm()
    }
}

const resetForm = () => {
    form.name = ''
    form.map = null
    form.side = null
    form.type = null
    form.description = ''
    form.tags = []
    const teamUsers = team.value?.users || []
    form.players = teamUsers
        .filter((player: any) => !['owner', 'coach'].includes(player.role?.toLowerCase()))
        .map((player: any) => ({
            name: player.name,
            description: '',
            loadout: [],
            role: player.role || 'Player'
        }))

    // For map_based teams, pre-set the map from the stratbook's map_id
    if (isMapBased.value) {
        const stratbook = team.value?.stratbooks?.find((s: any) => s.id === stratbookId.value)
        if (stratbook?.map_id) {
            form.map = stratbook.map_id
        }
    }
}

// React to Tactic change
watch(() => props.tactic, (newVal) => {
    populateForm(newVal)
}, { immediate: true })

// --- Weapon/Loadout Logic ---
const loadoutSelectorVisible = ref(false)
const activePlayerIndex = ref<number | null>(null)

const openLoadoutSelector = (index: number) => {
    activePlayerIndex.value = index
    loadoutSelectorVisible.value = true
}

const toggleWeaponSelection = (name: string) => {
    if (activePlayerIndex.value === null) return
    const loadout = form.players[activePlayerIndex.value].loadout
    const index = loadout.indexOf(name)
    if (index > -1) {
        loadout.splice(index, 1)
    } else {
        loadout.push(name)
    }
}

const isWeaponSelected = (name: string) => {
    if (activePlayerIndex.value === null) return false
    return form.players[activePlayerIndex.value].loadout.includes(name)
}

const getWeaponIcon = (name: string) => {
    const w = weapons.value?.find((i: any) => i.name === name)
    return w?.url || w?.base64 || ''
}

const groupedWeapons = computed(() => {
    if (!weapons.value) return {}
    const groups: Record<string, any[]> = {
        'Pistols': [], 'Rifles': [], 'Snipers': [], 'SMGs': [], 'Shotguns & Heavy': [], 'Equipment & Utility': []
    }
    const categories: Record<string, string[]> = {
        'Pistols': ['Glock-18', 'USP-S', 'P2000', 'Desert-Eagle', 'Dual-Berettas', 'Five-SeveN', 'P250', 'Tec-9', 'CZ75-Auto', 'R8-Revolver'],
        'Rifles': ['AK-47', 'M4A4', 'M4A1-S', 'Galil-AR', 'FAMAS', 'AUG', 'SG-553'],
        'Snipers': ['AWP', 'SSG-08', 'SCAR-20', 'G3SG1'],
        'SMGs': ['MAC-10', 'MP9', 'MP7', 'MP5-SD', 'UMP-45', 'P90', 'PP-Bizon'],
        'Shotguns & Heavy': ['Nova', 'XM1014', 'MAG-7', 'Sawed-Off', 'M249', 'Negev'],
        'Equipment & Utility': ['C4', 'Flashbang', 'HE-Grenade', 'Smoke-Grenade', 'Molotov', 'Incendiary-Grenade', 'Decoy-Grenade', 'Zeus-x27', 'Defuser', 'item_kevlar', 'item_assault']
    }
    weapons.value.forEach((w: any) => {
        for (const [catName, names] of Object.entries(categories)) {
            if (names.includes(w.name)) {
                groups[catName].push(w)
                return
            }
        }
    })
    return groups
})

const isValid = computed(() => {
    return (form.name?.length ?? 0) >= 3 && form.map && form.side && form.players.length > 0
})

const addPlayer = () => {
    if (form.players.length < 5) {
        form.players.push({ name: '', description: '', loadout: [], role: 'Player' })
    }
}

const removePlayer = (index: number) => form.players.splice(index, 1)

const close = () => {
    visible.value = false
    emit('update:visible', false)
}

async function submit() {
    try {
        if (props.tactic?.id) {
            await StratbookTactics.update(props.tactic.id, form).then(() => {
                toast.success("Tactic updated", "Your tactic has been updated successfully.");
            }).catch(() => {
                toast.error("Failed to update tactic", "Please try again later.");
            });
        } else {
            await StratbookTactics.createCustom(form).then(() => {
                toast.success("Tactic created", "Your new tactic has been created successfully.");
            }).catch(() => {
                toast.error("Failed to create tactic", "Please try again later.");
            });
        }
        visible.value = false
    } catch (e) {
        console.error(e)
    }
}
</script>

<style scoped>
.custom-input {
    width: 100%;
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    padding: 0.75rem;
    color: white;
    font-size: 0.875rem;
    transition: all 0.2s;
}
.custom-input:focus {
    outline: none;
    border-color: var(--color-primary);
    background-color: rgba(255, 255, 255, 0.08);
}
.custom-track::-webkit-scrollbar { width: 5px; }
.custom-track::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
.custom-track::-webkit-scrollbar-thumb:hover { background: var(--color-primary); }
</style>