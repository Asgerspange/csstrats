<template>
  <div>
    <Button 
      @click="openDialog" 
    >
        Export PDF
    </Button>

    <Dialog 
      v-model:visible="visible" 
      header="Export Stratbook PDF" 
      :style="{ width: '600px' }" 
      modal 
      :draggable="false"
      class="dark-theme-dialog"
    >
      <div class="flex flex-col gap-6 py-2">
        
        <div class="rounded-xl border border-white/5 bg-white/[0.02] p-4">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2 text-orange-500 font-bold uppercase tracking-wider text-sm">
              <i class="material-symbols text-xl">sort</i>
              <span>Multi-Level Sorting</span>
            </div>
            <Button 
              label="Add Level" 
              icon="pi pi-plus" 
              class="p-button-text p-button-sm !text-orange-400" 
              @click="addSortLevel" 
            />
          </div>
          
          <div class="flex flex-col gap-3">
            <div v-for="(rule, index) in sortStack" :key="index" 
                 class="flex items-center gap-3 bg-black/40 p-2 rounded-lg border border-white/5">
              
              <span class="w-6 text-center text-white/30 font-mono text-sm">{{ index + 1 }}</span>
              
              <div class="flex-1">
                <Select 
                  v-model="rule.column" 
                  :options="availableColumns" 
                  optionLabel="label" 
                  optionValue="value" 
                  class="w-full !bg-transparent !border-white/10"
                />
              </div>
              
              <div class="flex bg-white/5 p-1 rounded-md border border-white/10">
                <button 
                  v-for="opt in directionOptions" 
                  :key="opt.value"
                  @click="rule.dir = opt.value"
                  :class="[
                    'px-3 py-1 rounded transition-all flex items-center justify-center',
                    rule.dir === opt.value ? 'bg-orange-500 text-white shadow-lg' : 'text-white/40 hover:text-white/70'
                  ]"
                  type="button"
                >
                  <i :class="['pi text-xs', opt.icon]"></i>
                </button>
              </div>
              
              <Button 
                icon="pi pi-trash" 
                class="p-button-danger p-button-text !p-2" 
                @click="removeSortLevel(index)" 
                :disabled="sortStack.length === 1" 
              />
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-white/5 bg-white/[0.02] p-4">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2 text-orange-500 font-bold uppercase tracking-wider text-sm">
              <i class="material-symbols text-xl">checklist</i>
              <span>Include Tactics ({{ filteredTactics.length }})</span>
            </div>
            <div class="flex gap-2">
              <button @click="selectAll" class="text-[10px] uppercase font-bold text-white/40 hover:text-orange-500 transition-colors">All</button>
              <button @click="selectNone" class="text-[10px] uppercase font-bold text-white/40 hover:text-orange-500 transition-colors">None</button>
            </div>
          </div>

          <div class="relative mb-4">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-white/20 text-sm"></i>
            <input 
              v-model="searchQuery"
              type="text" 
              placeholder="Search by name, map, or type..." 
              class="w-full bg-black/40 border border-white/10 rounded-lg py-2.5 pl-10 pr-4 text-sm text-white focus:outline-none focus:border-orange-500/50 transition-colors"
            />
          </div>

          <div class="max-h-60 overflow-y-auto pr-2 custom-scrollbar space-y-1">
            <div v-for="tactic in filteredTactics" :key="tactic.id" 
                 class="flex items-center p-3 rounded-lg border border-transparent hover:border-white/10 hover:bg-white/[0.03] transition-all group">
              
              <Checkbox 
                v-model="selectedIds" 
                :value="tactic.id" 
                :inputId="'t-' + tactic.id" 
                class="mr-3"
              />
              
              <label :for="'t-' + tactic.id" class="flex flex-1 items-center cursor-pointer">
                <div class="flex items-center gap-3 w-full">
                  <div :class="[
                    'px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tighter',
                    tactic.side === 'CT' ? 'bg-blue-600/20 text-blue-400 border border-blue-600/30' : 'bg-red-600/20 text-red-400 border border-red-600/30'
                  ]">
                    {{ tactic.side }}
                  </div>
                  
                  <div class="flex flex-col">
                    <span class="text-white/90 font-medium leading-tight">{{ tactic.name }}</span>
                    <span class="text-[10px] text-white/40 uppercase tracking-widest">{{ tactic.type }}</span>
                  </div>

                  <span class="ml-auto text-xs font-mono text-white/30 group-hover:text-orange-500/50 transition-colors">
                    {{ tactic.map }}
                  </span>
                </div>
              </label>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-white/5 bg-white/[0.02] p-4 flex gap-6">
          <div class="flex items-center gap-2">
            <Checkbox v-model="pdfOptions.includeToc" :binary="true" inputId="toc-check" />
            <label for="toc-check" class="text-sm text-white/60 cursor-pointer">Table of Contents</label>
          </div>
        </div>

      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full pt-2">
          <span class="text-[10px] uppercase font-bold text-white/20 tracking-widest">
            {{ selectedIds.length }} tactics included
          </span>
          <div class="flex gap-3">
            <Button label="Cancel" @click="visible = false" class="p-button-text !text-white/50" />
            <Button 
              label="Generate PDF" 
              icon="pi pi-file-export" 
              class="!bg-orange-500 hover:!bg-orange-600 !border-none px-6" 
              @click="handleExport" 
              :disabled="!selectedIds.length" 
            />
          </div>
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Button from '@/components/volt/Button.vue';
import Dialog from '@/components/volt/Dialog.vue';
import Select from '@/components/volt/Select.vue';
import Checkbox from 'primevue/checkbox';
import useStratbooks from '@/composables/useStratbooks';
import { useUXWizz } from '@/composables/useUXWizz'
const { track } = useUXWizz()

const props = defineProps({
  stratbookId: { type: Number, required: true },
  allTactics: { type: Array, default: () => [] }
});

const { Stratbooks } = useStratbooks();

// --- UI State ---
const visible = ref(false);
const searchQuery = ref('');
const selectedIds = ref([]);
const pdfOptions = ref({
  includeToc: true,
  onePerPage: true,
  download: true // Flag to signal download over open
});

const sortStack = ref([
  { column: 'side', dir: 'asc' },
  { column: 'name', dir: 'asc' }
]);

// --- Live Filtering Logic ---
const filteredTactics = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return props.allTactics;
  
  return props.allTactics.filter(t => 
    t.name?.toLowerCase().includes(query) || 
    t.map?.toLowerCase().includes(query) || 
    t.type?.toLowerCase().includes(query) ||
    t.side?.toLowerCase().includes(query)
  );
});

// --- Constants ---
const availableColumns = [
  { label: 'Side (CT/T)', value: 'side' },
  { label: 'Tactic Name', value: 'name' },
  { label: 'Map Name', value: 'map' },
  { label: 'Strategy Type', value: 'type' }
];

const directionOptions = [
  { icon: 'pi-sort-amount-up-alt', value: 'asc' },
  { icon: 'pi-sort-amount-down', value: 'desc' }
];

// --- Logic ---
const openDialog = () => {
  selectedIds.value = props.allTactics.map(t => t.id);
  searchQuery.value = '';
  visible.value = true;
};

const addSortLevel = () => sortStack.value.push({ column: 'name', dir: 'asc' });
const removeSortLevel = (index) => sortStack.value.splice(index, 1);

const selectAll = () => {
  const ids = filteredTactics.value.map(t => t.id);
  selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
};

const selectNone = () => {
  const filteredIds = filteredTactics.value.map(t => t.id);
  selectedIds.value = selectedIds.value.filter(id => !filteredIds.includes(id));
};

const handleExport = () => {
  track('stratbook:export')
  // Correctly passing parameters to avoid the forEach error
  Stratbooks.export(
    props.stratbookId, 
    selectedIds.value, 
    sortStack.value,
    {
      include_toc: pdfOptions.value.includeToc,
      download: true // Explicitly request download behavior
    }
  );
};
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { 
  background: rgba(255, 255, 255, 0.1); 
  border-radius: 10px; 
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(249, 115, 22, 0.5); }
</style>