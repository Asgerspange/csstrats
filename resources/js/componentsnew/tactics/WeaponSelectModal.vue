<template>
    <!-- Custom Modal Overlay -->
    <div v-if="visible" class="modal-overlay" @click="handleOverlayClick">
        <!-- Custom Weapons Dialog -->
        <div class="weapons-dialog" @click.stop>
            <!-- Header -->
            <div class="dialog-header" :class="[currentSide.toLowerCase()]">
                <button class="close-btn" @click="close()">
                    <i class="material-symbols">close</i>
                </button>

                <div class="header-content">
                    <div class="header-left">
                        <div class="team-badge" :class="[currentSide.toLowerCase()]">{{ currentSide }}</div>
                        <div class="weapons-info">
                            <h1 class="weapons-title">
                                <i class="material-symbols">inventory_2</i>
                                Weapon Loadout
                            </h1>
                            <div class="weapons-meta">
                                <span class="selected-count">{{ selected.length }} weapons selected</span>
                                <span class="viewing-side">Viewing {{ viewingSide }} arsenal</span>
                            </div>
                        </div>
                    </div>

                    <div class="header-actions">
                        <div class="team-toggle">
                            <button
                                class="toggle-btn ct"
                                :class="{ active: viewingSide === 'CT' }"
                                @click="switchToSide('CT')"
                            >
                                <i class="material-symbols">shield</i>
                                CT Weapons
                            </button>
                            <button
                                class="toggle-btn t"
                                :class="{ active: viewingSide === 'T' }"
                                @click="switchToSide('T')"
                            >
                                <i class="material-symbols">local_fire_department</i>
                                T Weapons
                            </button>
                        </div>

                        <button class="clear-btn" @click="clearAll" v-if="selected.length > 0">
                            <i class="material-symbols">clear_all</i>
                            Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scrollable Content -->
            <div class="dialog-content">
                <div class="dialog-body">
                    <!-- Weapon Categories -->
                    <div class="weapons-grid">
                        <div
                            v-for="(group, categoryName) in currentViewWeapons"
                            :key="categoryName"
                            class="weapon-category"
                        >
                            <div class="category-header">
                                <h3 class="category-title">
                                    <i class="material-symbols">{{ getCategoryIcon(categoryName) }}</i>
                                    {{ categoryName }}
                                </h3>
                                <div class="category-stats">
                                    {{ getSelectedInCategory(group) }}/{{ group.length }}
                                </div>
                            </div>

                            <div class="weapons-list">
                                <div
                                    v-for="weapon in group"
                                    :key="weapon"
                                    class="weapon-item"
                                    :class="{
                                        'selected': selected.includes(weapon),
                                        'cross-team': isOppositeSideWeapon(weapon),
                                        [viewingSide.toLowerCase()]: true
                                    }"
                                    @click="toggleWeapon(weapon)"
                                >
                                    <div class="weapon-image">
                                        <svg class="w-20 h-8" viewBox="10 0 60 60" preserveAspectRatio="xMidYMid meet">
                                            <path :d="getWeaponSvg(weapon)" fill="currentColor" />
                                        </svg>
                                    </div>
                                    <div class="weapon-name">{{ formatWeaponName(weapon) }}</div>
                                    <div class="weapon-selector">
                                        <i class="material-symbols" v-if="selected.includes(weapon)">check_circle</i>
                                        <i class="material-symbols" v-else>radio_button_unchecked</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="dialog-footer">
                <div class="selected-summary">
                    <span class="summary-text">{{ selected.length }} weapons selected</span>
                    <div class="selected-preview">
                        <svg
                            v-for="weapon in selected.slice(0, 6)"
                            :key="weapon"
                            class="preview-weapon"
                            :class="[currentSide.toLowerCase() + '-weapon']"
                            viewBox="20 0 60 40"
                            preserveAspectRatio="xMidYMid meet"
                        >
                            <path :d="getWeaponSvg(weapon)" fill="currentColor" />
                        </svg>
                        <span v-if="selected.length > 6" class="more-count">
                            +{{ selected.length - 6 }}
                        </span>
                    </div>
                </div>

                <div class="footer-actions">
                    <button class="secondary-btn" @click="close()">
                        <i class="material-symbols">cancel</i>
                        Cancel
                    </button>
                    <button class="primary-btn" @click="saveAndClose()">
                        <i class="material-symbols">save</i>
                        Save Loadout
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import useImages from '@/composables/useImages'

type Side = 'CT' | 'T'

const props = withDefaults(defineProps<{
    visible: boolean
    playerWeapons?: string[]
    index?: number
    currentSide: Side | string
}>(), {
    playerWeapons: () => [],
    index: 0,
})

const emit = defineEmits<{
    (e: 'closed'): void
    (e: 'changed', payload: [number, string[]]): void
}>()

const categorizedWeapons: Record<string, Record<string, string[]>> = {
    t: {
        Rifles: ['AK-47', 'SG-553', 'Galil-AR', 'AWP', 'SSG-08', 'G3SG1'],
        SMGs: ['MAC-10', 'MP7', 'UMP-45', 'P90', 'PP-Bizon', 'MP5-SD'],
        Shotguns: ['Nova', 'XM1014', 'Sawed-Off'],
        Heavy: ['M249', 'Negev'],
        Pistols: ['Glock-18', 'Dual-Berettas', 'P250', 'CZ75-Auto', 'Tec-9', 'Desert-Eagle', 'R8-Revolver'],
        Utility: ['Decoy-Grenade', 'Flashbang', 'Flashbang-2', 'HE-Grenade', 'Molotov', 'Smoke-Grenade', 'Zeus-x27', 'C4'],
    },
    ct: {
        Rifles: ['M4A1-S', 'M4A4', 'AUG', 'FAMAS', 'AWP', 'SSG-08', 'SCAR-20'],
        SMGs: ['MP9', 'MP7', 'UMP-45', 'P90', 'PP-Bizon', 'MP5-SD'],
        Shotguns: ['Nova', 'XM1014', 'MAG-7'],
        Heavy: ['M249', 'Negev'],
        Pistols: ['P2000', 'USP-S', 'Dual-Berettas', 'P250', 'Five-SeveN', 'CZ75-Auto', 'Desert-Eagle', 'R8-Revolver'],
        Utility: ['Decoy-Grenade', 'Flashbang', 'Flashbang-2', 'HE-Grenade', 'Incendiary-Grenade', 'Smoke-Grenade', 'Defuser', 'Zeus-x27'],
    },
}

// Local working copy of the player's selection, synced when the modal opens.
const selected = ref<string[]>([...props.playerWeapons])
const viewingSide = ref<string>(props.currentSide)

watch(() => props.visible, (open) => {
    if (open) {
        selected.value = [...props.playerWeapons]
        viewingSide.value = props.currentSide
    }
})

const { Images } = useImages()
const { data: weaponImages } = Images.get('weapon')
const weaponLookup = computed<Record<string, string>>(() =>
    Object.fromEntries((weaponImages.value || []).map((w: any) => [w.name, w.svg_path]))
)

const currentViewWeapons = computed(() => categorizedWeapons[viewingSide.value.toLowerCase()] || {})

function toggleWeapon(weapon: string) {
    const i = selected.value.indexOf(weapon)
    if (i >= 0) selected.value.splice(i, 1)
    else selected.value.push(weapon)
}

function switchToSide(side: Side) {
    viewingSide.value = side
}

function isOppositeSideWeapon(weapon: string): boolean {
    const oppositeSide = props.currentSide === 'CT' ? 'T' : 'CT'
    const oppositeWeapons = categorizedWeapons[oppositeSide.toLowerCase()]
    for (const category in oppositeWeapons) {
        if (oppositeWeapons[category].includes(weapon)) {
            return viewingSide.value !== props.currentSide
        }
    }
    return false
}

function clearAll() {
    selected.value = []
}

function close() {
    emit('closed')
}

function saveAndClose() {
    emit('changed', [props.index ?? 0, [...selected.value]])
    close()
}

function handleOverlayClick(event: MouseEvent) {
    if (event.target === event.currentTarget) close()
}

function getWeaponSvg(weapon: string): string {
    return weaponLookup.value[weapon] || ''
}

function formatWeaponName(weapon: string): string {
    return weapon.replace(/-/g, ' ')
}

function getCategoryIcon(category: string): string {
    const icons: Record<string, string> = {
        Rifles: 'my_location',
        SMGs: 'speed',
        Shotguns: 'local_fire_department',
        Heavy: 'fitness_center',
        Pistols: 'radio_button_checked',
        Utility: 'science',
    }
    return icons[category] || 'inventory_2'
}

function getSelectedInCategory(weapons: string[]): number {
    return weapons.filter(w => selected.value.includes(w)).length
}
</script>

<style lang="scss" scoped>
// Custom Modal Overlay
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: fadeIn 0.3s ease-out;
  padding: var(--indention-lg);

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
}

.weapons-dialog {
  width: 1000px;
  max-width: 100%;
  max-height: 90vh;
  border-radius: var(--border-radius);
  box-shadow: var(--elevation-bigger);
  background: var(--card-primary-bg);
  border: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  animation: slideInUp 0.4s ease-out;
  overflow: hidden;

  @keyframes slideInUp {
    from {
      opacity: 0;
      transform: translateY(30px) scale(0.95);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }
}

.dialog-header {
  padding: var(--indention-lg) var(--indention-xl);
  background: var(--card-header-bg);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
  flex-shrink: 0;

  &.ct {
    border-bottom-color: rgba(74, 158, 255, 0.3);
    background: linear-gradient(135deg, var(--main-bg-dark), rgba(74, 158, 255, 0.08));
  }

  &.t {
    border-bottom-color: rgba(255, 107, 53, 0.3);
    background: linear-gradient(135deg, var(--main-bg-dark), rgba(255, 107, 53, 0.08));
  }
}

.close-btn {
  position: absolute;
  top: var(--indention-md);
  right: var(--indention-md);
  width: 32px;
  height: 32px;
  border-radius: var(--border-radius);
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: var(--transition);

  &:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
  }

  i { font-size: 18px; }
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--indention-lg);

  @media (max-width: 768px) {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--indention-md);
  }
}

.header-left {
  display: flex;
  align-items: center;
  gap: var(--indention-md);
  flex: 1;
}

.team-badge {
  padding: var(--indention-xs) var(--indention-sm);
  border-radius: var(--border-radius);
  font-size: var(--font-size-small);
  font-weight: 700;
  letter-spacing: 1px;

  &.ct {
    background: rgba(74, 158, 255, 0.2);
    color: #4a9eff;
    border: 1px solid rgba(74, 158, 255, 0.4);
    box-shadow: 0 0 12px rgba(74, 158, 255, 0.3);
  }

  &.t {
    background: rgba(255, 107, 53, 0.2);
    color: #ff6b35;
    border: 1px solid rgba(255, 107, 53, 0.4);
    box-shadow: 0 0 12px rgba(255, 107, 53, 0.3);
  }
}

.weapons-info { flex: 1; }

.weapons-title {
  margin: 0 0 var(--indention-xs) 0;
  font-size: var(--font-size-xlarge);
  font-weight: 600;
  color: white;
  line-height: 1.2;
  display: flex;
  align-items: center;
  gap: var(--indention-sm);

  i {
    color: var(--main-color);
    font-size: 28px;
  }
}

.weapons-meta {
  display: flex;
  align-items: center;
  gap: var(--indention-sm);
  font-size: var(--font-size-small);
  color: rgba(255, 255, 255, 0.7);
}

.selected-count {
  color: var(--main-color);
  font-weight: 600;
}

.viewing-side {
  color: rgba(255, 255, 255, 0.5);
  font-size: var(--font-size-small);
  font-style: italic;
}

.header-actions {
  display: flex;
  flex-direction: column;
  gap: var(--indention-md);
  align-items: flex-end;

  @media (max-width: 1200px) {
    flex-direction: row;
    align-items: center;
  }

  @media (max-width: 768px) {
    width: 100%;
    justify-content: space-between;
  }
}

.team-toggle {
  display: flex;
  background: rgba(255, 255, 255, 0.05);
  border-radius: var(--border-radius);
  border: 1px solid rgba(255, 255, 255, 0.1);
  overflow: hidden;
}

.toggle-btn {
  display: flex;
  align-items: center;
  gap: var(--indention-xs);
  padding: var(--indention-sm) var(--indention-md);
  background: transparent;
  border: none;
  color: rgba(255, 255, 255, 0.7);
  cursor: pointer;
  transition: var(--transition);
  font-size: var(--font-size-small);
  font-weight: 500;

  &:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
  }

  &.active {
    color: white;

    &.ct {
      background: rgba(74, 158, 255, 0.3);
      border-color: rgba(74, 158, 255, 0.5);
      color: #4a9eff;

      &:hover { background: rgba(74, 158, 255, 0.4); }
    }

    &.t {
      background: rgba(255, 107, 53, 0.3);
      border-color: rgba(255, 107, 53, 0.5);
      color: #ff6b35;

      &:hover { background: rgba(255, 107, 53, 0.4); }
    }
  }

  i { font-size: 16px; }
}

.clear-btn {
  display: flex;
  align-items: center;
  gap: var(--indention-xs);
  padding: var(--indention-sm) var(--indention-md);
  border-radius: var(--border-radius);
  background: rgba(255, 107, 53, 0.2);
  border: 1px solid rgba(255, 107, 53, 0.4);
  color: #ff6b35;
  cursor: pointer;
  transition: var(--transition);
  font-size: var(--font-size-small);

  &:hover {
    background: rgba(255, 107, 53, 0.3);
    border-color: #ff6b35;
    transform: translateY(-1px);
  }

  i { font-size: 16px; }
}

.dialog-content {
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  min-height: 0;
  max-height: calc(90vh - 200px);

  &::-webkit-scrollbar { width: 6px; }
  &::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); border-radius: 3px; }
  &::-webkit-scrollbar-thumb {
    background: var(--main-color);
    border-radius: 3px;
    &:hover { background: var(--main-color-dark-5); }
  }
}

.dialog-body {
  padding: var(--indention-xl);
  background: var(--card-primary-bg);
}

.weapons-grid {
  display: grid;
  gap: var(--indention-xl);
}

.weapon-category {
  background: rgba(46, 46, 69, 0.6);
  border-radius: var(--border-radius);
  border: 1px solid rgba(255, 255, 255, 0.1);
  overflow: hidden;
}

.category-header {
  padding: var(--indention-lg);
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.category-title {
  margin: 0;
  font-size: var(--font-size-large);
  font-weight: 600;
  color: white;
  display: flex;
  align-items: center;
  gap: var(--indention-sm);

  i {
    color: var(--main-color);
    font-size: 20px;
  }
}

.category-stats {
  background: rgba(165, 214, 167, 0.2);
  color: var(--main-color);
  padding: var(--indention-xs) var(--indention-sm);
  border-radius: var(--border-radius);
  font-size: var(--font-size-small);
  font-weight: 600;
}

.weapons-list {
  padding: var(--indention-lg);
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--indention-md);

  @media (max-width: 768px) {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: var(--indention-sm);
  }
}

.weapon-item {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius);
  padding: var(--indention-md);
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--indention-sm);
  position: relative;

  &:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
  }

  &.selected {
    border-width: 2px;
    background: rgba(255, 255, 255, 0.1);

    &.ct {
      border-color: #4a9eff;
      box-shadow: 0 0 12px rgba(74, 158, 255, 0.3);
    }

    &.t {
      border-color: #ff6b35;
      box-shadow: 0 0 12px rgba(255, 107, 53, 0.3);
    }
  }

  &.cross-team {
    position: relative;

    &::before {
      content: 'PICKED UP';
      position: absolute;
      top: 2px;
      left: 2px;
      background: rgba(255, 193, 7, 0.9);
      color: rgba(0, 0, 0, 0.8);
      padding: 1px 4px;
      border-radius: 2px;
      font-size: 8px;
      font-weight: 700;
      letter-spacing: 0.5px;
      z-index: 2;
    }

    &:hover::before { background: rgba(255, 193, 7, 1); }
  }
}

.weapon-image {
  width: 60px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.weapon-name {
  font-size: var(--font-size-small);
  font-weight: 500;
  color: white;
  text-align: center;
  line-height: 1.3;
}

.weapon-selector {
  position: absolute;
  top: var(--indention-xs);
  right: var(--indention-xs);

  i {
    font-size: 18px;

    &:first-child { color: var(--main-color); }
    &:last-child { color: rgba(255, 255, 255, 0.3); }
  }
}

.dialog-footer {
  padding: var(--indention-lg) var(--indention-xl);
  background: var(--main-bg-dark);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
  gap: var(--indention-lg);

  @media (max-width: 768px) {
    padding: var(--indention-md);
    flex-direction: column;
    gap: var(--indention-md);
  }
}

.selected-summary {
  display: flex;
  flex-direction: column;
  gap: var(--indention-sm);
  flex: 1;
}

.summary-text {
  font-size: var(--font-size-small);
  color: rgba(255, 255, 255, 0.7);
  font-weight: 500;
}

.selected-preview {
  display: flex;
  gap: var(--indention-xs);
  align-items: center;
  color: white;
}

.preview-weapon {
  height: 20px;
  width: 50px;
  border-radius: 2px;

  &.ct-weapon { color: #4a9eff; }
  &.t-weapon { color: #ff6b35; }
}

.more-count {
  font-size: var(--font-size-small);
  color: rgba(255, 255, 255, 0.6);
  background: rgba(255, 255, 255, 0.1);
  padding: 2px var(--indention-xs);
  border-radius: var(--border-radius);
  margin-left: var(--indention-xs);
}

.footer-actions {
  display: flex;
  gap: var(--indention-md);

  @media (max-width: 768px) {
    width: 100%;
    justify-content: center;
  }
}

.primary-btn, .secondary-btn {
  display: flex;
  align-items: center;
  gap: var(--indention-sm);
  padding: var(--indention-sm) var(--indention-lg);
  border-radius: var(--border-radius);
  font-size: var(--font-size-regular);
  font-weight: 500;
  cursor: pointer;
  transition: var(--transition);
  border: none;
  min-width: 120px;
  justify-content: center;

  i { font-size: 18px; }
}

.primary-btn {
  background: var(--button-primary-bg);
  color: var(--main-bg-dark);

  &:hover {
    background: var(--main-color);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(88, 188, 130, 0.3);
  }
}

.secondary-btn {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);

  &:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
    border-color: rgba(255, 255, 255, 0.3);
  }
}

@media (max-width: 1050px) {
  .weapons-dialog {
    width: 95vw;
    max-width: 1000px;
  }
}

@media (max-width: 768px) {
  .modal-overlay { padding: var(--indention-md); }
  .weapons-dialog { width: 100%; max-width: 100%; max-height: 95vh; }
  .dialog-header { padding: var(--indention-md); }
  .dialog-body { padding: var(--indention-md); }
  .weapons-grid { gap: var(--indention-lg); }
  .category-header { padding: var(--indention-md); }
  .weapons-list { padding: var(--indention-md); }
  .weapon-item { padding: var(--indention-sm); }
  .weapon-image { width: 50px; height: 35px; }
}
</style>
