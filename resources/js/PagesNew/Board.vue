<script setup lang="ts">
import { onMounted, ref, onUnmounted, nextTick, computed, watch } from 'vue'
import useMaps from '@/composables/useMaps'
import useBoards from '@/composables/useBoard'
import Select from '@/components/volt/Select.vue'
import GroupSelect from '@/components/volt/GroupSelect.vue'
import Button from '@/components/volt/Button.vue'
import Dialog from '@/components/volt/Dialog.vue'
import InputText from '@/components/volt/InputText.vue'
import useStratbookTactics from '@/composables/useStratbookTactics'
import CreateBoard from '@/componentsnew/board/CreateBoard.vue'
import ContextPanel from '@/componentsnew/ContextPanel.vue'
import { useToast } from '@/composables/useToast';
import { useConfirm } from 'primevue/useconfirm';
const toast = useToast();
const confirm = useConfirm();

const { StratbookTactics } = useStratbookTactics()
const { Maps } = useMaps()
const { Boards, isDeleting } = useBoards()

const isSidebarMinimized = ref(true)
const { data: maps } = Maps.get()
const { data: tactics } = StratbookTactics.all()

const boardsQuery = Boards.get()

const UTIL_CONFIG = {
    smokes: { src: '/images/smoke.webp', initialSize: 45, explosionRadius: 40, colors: ['rgba(220, 220, 220, 0.8)', 'rgba(150, 150, 150, 0.5)', 'rgba(80, 80, 80, 0)'] },
    mol: { src: '/images/mol.webp', initialSize: 45, explosionRadius: 40, colors: ['rgba(255, 50, 0, 0.9)', 'rgba(255, 120, 20, 0.6)', 'rgba(200, 60, 0, 0)'] },
    he: { src: '/images/he.webp', initialSize: 40, explosionRadius: 40, colors: ['rgba(255, 200, 0, 0.9)', 'rgba(255, 100, 0, 0.7)', 'rgba(150, 50, 0, 0)'] },
    flashbang: { src: '/images/flashbang.webp', initialSize: 35, explosionRadius: 40, colors: ['rgba(255, 255, 255, 1.0)', 'rgba(255, 255, 200, 0)'] }
}

const PLAYER_CONFIG = {
    ct: { src: '/images/ct_player.svg' },
    t: { src: '/images/t_player.svg' }
}

const canvas = ref<HTMLCanvasElement | null>(null)

// --- EDITABLE TEXT STATE ---
const editingTextId = ref<number | null>(null)
const editingTextValue = ref('')
const textInputRef = ref<HTMLInputElement | null>(null)

// --- MAP STATE LOGIC ---
const selectedMap = ref(1)
const activeMapId = ref(1)

const activeMode = ref<'pan' | 'draw' | 'erase'>('pan')
const preSpaceMode = ref<'pan' | 'draw' | 'erase' | null>(null)
const activeColor = ref('#00ff00')
const eraserSize = ref(20)
const showShortcuts = ref(false)
// Focus trap / Escape / restore-focus are handled by the Volt Dialog wrapper.
const scale = ref(1)
const offset = ref({ x: 0, y: 0 })
const mapImage = ref<HTMLImageElement | null>(null)
const assetCache = new Map<string, HTMLImageElement>()
const toolbarPos = ref({ x: 24, y: 225 })
const isDraggingToolbar = ref(false)
const dragOffset = ref({ x: 0, y: 0 })

const triggerMapLoad = (mapId: number) => {
    activeMapId.value = mapId
    const targetMap = maps.value?.find((m: any) => m.id === mapId)
    if (!targetMap?.image_path) return
    if (!canvas.value) return
    activeMode.value = 'pan'; currentPath.value = []; selectedIds.value = []; paths.value = []; entities.value = []; groups.value = []; history.value = []; historyIndex.value = -1
    const img = new Image()
    img.src = targetMap.image_path
    img.onload = () => {
        mapImage.value = img
        centerMap()
        render()
    }
}

watch(maps, (newMaps) => {
    if (newMaps && newMaps.length > 0) {
        const firstId = newMaps.find((m: any) => m.id === activeMapId.value) ? activeMapId.value : newMaps[0].id
        selectedMap.value = firstId
        nextTick(() => triggerMapLoad(firstId))
    }
}, { immediate: true })

const startToolbarDrag = (e: MouseEvent) => {
    isDraggingToolbar.value = true
    dragOffset.value = { x: e.clientX - toolbarPos.value.x, y: e.clientY - toolbarPos.value.y }
    const onMouseMove = (moveEvent: MouseEvent) => {
        if (!isDraggingToolbar.value) return
        toolbarPos.value = { x: moveEvent.clientX - dragOffset.value.x, y: moveEvent.clientY - dragOffset.value.y }
    }
    const onMouseUp = () => {
        isDraggingToolbar.value = false
        window.removeEventListener('mousemove', onMouseMove)
        window.removeEventListener('mouseup', onMouseUp)
    }
    window.addEventListener('mousemove', onMouseMove)
    window.addEventListener('mouseup', onMouseUp)
}

const paths = ref<{ id: number; name: string; points: { x: number; y: number }[]; color: string; visible: boolean; locked: boolean }[]>([])
const entities = ref<any[]>([])
const groups = ref<{ id: number; name: string; collapsed: boolean; locked: boolean; visible: boolean }[]>([])
const currentPath = ref<{ x: number; y: number }[]>([])
const history = ref<string[]>([])
const historyIndex = ref(-1)
const selectedIds = ref<number[]>([])
const marqueeBox = ref<{ start: { x: number, y: number }, end: { x: number, y: number } } | null>(null)
const draggedId = ref<number | null>(null)
const isRotating = ref(false)
const isResizing = ref(false)
const isInteracting = ref(false)
const isMarqueeActive = ref(false)
const lastMousePos = ref({ x: 0, y: 0 })
const eraserCursor = ref<{ x: number; y: number } | null>(null)
const tacticId = ref<number | null>(null)
const boardId = ref<number | null>(null)
const isSaving = ref(false)
const boardName = ref('')

const isPathDeletable = (path: any) => !path.locked
const isPointNearPath = (px: number, py: number, path: { points: { x: number; y: number }[] }, threshold = 8) => {
    for (let i = 0; i < path.points.length - 1; i++) {
        const a = path.points[i]; const b = path.points[i + 1]
        const dx = b.x - a.x; const dy = b.y - a.y
        const lenSq = dx * dx + dy * dy
        if (lenSq === 0) { if (Math.hypot(px - a.x, py - a.y) < threshold) return true; continue }
        const t = Math.max(0, Math.min(1, ((px - a.x) * dx + (py - a.y) * dy) / lenSq))
        const projX = a.x + t * dx; const projY = a.y + t * dy
        if (Math.hypot(px - projX, py - projY) < threshold) return true
    }
    return false
}

const filteredBoards = computed(() => {
    const allBoards = boardsQuery.data.value?.boards || []
    if (!selectedMap.value) return allBoards
    return allBoards.filter((b: any) => b.map_id === selectedMap.value)
})

const createNewBoard = (boardData: { name: string; map_id: number }) => {
    selectedMap.value = boardData.map_id;
    boardId.value = null; tacticId.value = null;
    triggerMapLoad(boardData.map_id); tacticId.value = null; boardName.value = boardData.name; paths.value = []; entities.value = []; groups.value = []; history.value = []; historyIndex.value = -1; saveHistory()
}

const saveToDatabase = async () => {
    isSaving.value = true
    const file = await createFileFromCanvas()
    if (!file) { isSaving.value = false; return }
    const mapData = { paths: paths.value, entities: entities.value, groups: groups.value, viewport: { scale: scale.value, offset: offset.value } }
    const formData = new FormData()
    if (tacticId.value) formData.append('tactic_id', tacticId.value.toString())
    if (boardId.value) formData.append('id', boardId.value.toString())
    formData.append('name', boardName.value); formData.append('map_id', activeMapId.value.toString()); formData.append('data', JSON.stringify(mapData)); formData.append('file', file, 'strategy.png')
    try {
        const response = await Boards.create(formData)
        toast.success("Board saved", "Your board has been saved successfully.");
        if (response && response.id) boardId.value = response.id
    } catch (e) { console.error("Save failed", e) } finally { isSaving.value = false }
}

const handleDeleteBoard = (id: number) => {
    confirm.require({
        message:     'Are you sure you want to delete this board? This cannot be undone.',
        header:      'Delete board',
        icon:        'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Delete', severity: 'danger' },
        accept: async () => {
            await Boards.delete(id)
            toast.success("Board deleted", "The board has been deleted successfully.");
            if (tacticId.value === id) createNewBoard({ name: '', map_id: selectedMap.value })
        },
    })
}

const createFileFromCanvas = (): Promise<Blob | null> => {
    if (!mapImage.value) return Promise.resolve(null)
    const exportCanvas = document.createElement('canvas'); const ctx = exportCanvas.getContext('2d')!
    exportCanvas.width = mapImage.value.naturalWidth; exportCanvas.height = mapImage.value.naturalHeight; ctx.drawImage(mapImage.value, 0, 0)
    paths.value.forEach(p => {
        if (!p.visible) return
        ctx.strokeStyle = p.color; ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.beginPath()
        p.points.forEach((pt, i) => i === 0 ? ctx.moveTo(pt.x, pt.y) : ctx.lineTo(pt.x, pt.y)); ctx.stroke()
    })
    entities.value.forEach(item => {
        const parent = item.groupId ? groups.value.find(g => g.id === item.groupId) : null
        if (!item.visible || (parent && !parent.visible)) return
        ctx.save(); ctx.translate(item.x, item.y); ctx.rotate(item.rotation || 0); const s = item.size
        if (item.kind === 'util' || item.kind === 'player') {
            if (item.kind === 'util') drawExplosion(ctx, item)
            const img = getCachedImage(item.src); const dw = (img.naturalWidth / img.naturalHeight || 1) >= 1 ? s : s * (img.naturalWidth / img.naturalHeight); const dh = (img.naturalWidth / img.naturalHeight || 1) >= 1 ? s / (img.naturalWidth / img.naturalHeight) : s
            ctx.drawImage(img, -dw/2, -dh/2, dw, dh)
        } else if (item.kind === 'text') {
            ctx.fillStyle = item.color; ctx.font = `bold ${s}px Arial`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(item.text, 0, 0)
        }
        ctx.restore()
    })
    return new Promise((resolve) => exportCanvas.toBlob(resolve, 'image/png'))
}

const centerMap = () => {
    if (!canvas.value || !mapImage.value) return
    const padding = 50; const scaleW = (canvas.value.offsetWidth - padding) / mapImage.value.naturalWidth; const scaleH = (canvas.value.offsetHeight - padding) / mapImage.value.naturalHeight
    scale.value = Math.min(scaleW, scaleH, 1)
    offset.value = { x: (canvas.value.offsetWidth - mapImage.value.naturalWidth * scale.value) / 2, y: (canvas.value.offsetHeight - mapImage.value.naturalHeight * scale.value) / 2 }
}

const loadFromDatabase = async (strat: any) => {
    try {
        const d = JSON.parse(JSON.stringify(strat.data))
        
        triggerMapLoad(strat.map_id); selectedMap.value = strat.map_id; tacticId.value = strat.tactic_id; boardId.value = strat.id; boardName.value = strat.name || ''
        paths.value = (d.paths || []).map((p: any, i: number) => ({ id: p.id ?? Date.now() + i, name: p.name ?? `Line ${i + 1}`, points: p.points, color: p.color, visible: p.visible !== false, locked: p.locked ?? false }))
        entities.value = d.entities || []; groups.value = d.groups || []
        await nextTick();
        render()
        toast.success("Board loaded", "The board has been loaded successfully.");
    } catch (error) { console.error("Failed to hydrate strategy", error) }
}

const saveHistory = () => {
    const state = JSON.stringify({ paths: paths.value, entities: entities.value, groups: groups.value })
    if (historyIndex.value < history.value.length - 1) history.value = history.value.slice(0, historyIndex.value + 1)
    history.value.push(state); historyIndex.value++
}

const undo = () => {
    if (historyIndex.value > 0) {
        historyIndex.value--; const { paths: p, entities: e, groups: g } = JSON.parse(history.value[historyIndex.value])
        paths.value = p; entities.value = e; groups.value = g || []; render()
    }
}

const redo = () => {
    if (historyIndex.value < history.value.length - 1) {
        historyIndex.value++; const { paths: p, entities: e, groups: g } = JSON.parse(history.value[historyIndex.value])
        paths.value = p; entities.value = e; groups.value = g || []; render()
    }
}

const isItemDeletable = (entity: any) => {
    if (entity.locked) return false
    if (entity.groupId) { const parent = groups.value.find(g => g.id === entity.groupId); if (parent?.locked) return false }
    return true
}

const deleteItem = (id: number) => {
    const entity = entities.value.find(e => e.id === id)
    if (entity && isItemDeletable(entity)) {
        entities.value = entities.value.filter(e => e.id !== id); selectedIds.value = selectedIds.value.filter(sId => sId !== id); saveHistory(); render(); return
    }
    const path = paths.value.find(p => p.id === id)
    if (path && isPathDeletable(path)) {
        paths.value = paths.value.filter(p => ! (p.id === id)); selectedIds.value = selectedIds.value.filter(sId => sId !== id); saveHistory(); render()
    }
}

const deleteFolder = (groupId: number) => {
    const group = groups.value.find(g => g.id === groupId)
    if (group?.locked) return
    entities.value = entities.value.filter(e => e.groupId !== groupId); groups.value = groups.value.filter(g => g.id !== groupId); selectedIds.value = selectedIds.value.filter(id => entities.value.some(e => e.id === id)); saveHistory(); render()
}

const moveLayer = (id: number, direction: 'up' | 'down') => {
    const idx = entities.value.findIndex(e => e.id === id)
    if (idx !== -1) {
        const newIdx = direction === 'up' ? idx + 1 : idx - 1
        if (newIdx >= 0 && newIdx < entities.value.length) { const temp = entities.value[idx]; entities.value[idx] = entities.value[newIdx]; entities.value[newIdx] = temp; saveHistory(); render() }
        return
    }
    const pidx = paths.value.findIndex(p => p.id === id)
    if (pidx !== -1) {
        const newIdx = direction === 'up' ? pidx + 1 : pidx - 1
        if (newIdx >= 0 && newIdx < paths.value.length) { const temp = paths.value[pidx]; paths.value[pidx] = paths.value[newIdx]; paths.value[newIdx] = temp; saveHistory(); render() }
    }
}

const moveFolder = (id: number, direction: 'up' | 'down') => {
    const idx = groups.value.findIndex(g => g.id === id)
    if (idx === -1) return
    const newIdx = direction === 'up' ? idx - 1 : idx + 1
    if (newIdx >= 0 && newIdx < groups.value.length) { const temp = groups.value[idx]; groups.value[idx] = groups.value[newIdx]; groups.value[newIdx] = temp; saveHistory(); render() }
}

// --- NAME / RENAME DIALOG ---
// Replaces native prompt() for renaming layers/paths and naming new groups.
const nameDialogOpen = ref(false)
const nameDialogTitle = ref('')
const nameDialogLabel = ref('')
const nameDialogValue = ref('')
let nameDialogConfirm: ((value: string) => void) | null = null

// PrimeVue Dialog (modal) handles its own focus trap, Escape and initial focus.
const openNameDialog = (opts: { title: string; label: string; initial: string; onConfirm: (value: string) => void }) => {
    nameDialogTitle.value = opts.title
    nameDialogLabel.value = opts.label
    nameDialogValue.value = opts.initial
    nameDialogConfirm = opts.onConfirm
    nameDialogOpen.value = true
}

const confirmNameDialog = () => {
    const value = nameDialogValue.value.trim()
    nameDialogOpen.value = false
    if (value && nameDialogConfirm) nameDialogConfirm(value)
    nameDialogConfirm = null
}

const cancelNameDialog = () => {
    nameDialogOpen.value = false
    nameDialogConfirm = null
}

const renameItem = (item: any) => {
    openNameDialog({
        title: 'Rename',
        label: 'Name',
        initial: item.name || item.text || item.kind,
        onConfirm: (newName) => {
            if (item.kind === 'text') item.text = newName; else item.name = newName
            saveHistory(); render()
        },
    })
}

// --- TEXT EDITING LOGIC ---
const startEditingText = (item: any) => {
    editingTextId.value = item.id
    editingTextValue.value = item.text
    nextTick(() => textInputRef.value?.focus())
}

const saveTextEdit = () => {
    if (editingTextId.value !== null) {
        const item = entities.value.find(e => e.id === editingTextId.value)
        if (item) {
            item.text = editingTextValue.value || 'Text Here'
            saveHistory()
        }
        editingTextId.value = null
        render()
    }
}

const createGroup = () => {
    openNameDialog({
        title: 'New Folder',
        label: 'Folder name',
        initial: 'New Group',
        onConfirm: (gName) => {
            const gId = Date.now()
            groups.value.push({ id: gId, name: gName, collapsed: false, locked: false, visible: true })
            if (selectedIds.value.length > 0) { entities.value.forEach(ent => { if (selectedIds.value.includes(ent.id)) ent.groupId = gId }) }
            saveHistory(); render()
        },
    })
}

const onDragStartSidebar = (e: DragEvent, type: 'entity' | 'folder', id: number) => { e.dataTransfer?.setData('sidebarItem', JSON.stringify({ type, id })) }

const onDropSidebar = (e: DragEvent, targetFolderId: number | null) => {
    e.preventDefault(); const dataRaw = e.dataTransfer?.getData('sidebarItem')
    if (!dataRaw) return
    const { type, id } = JSON.parse(dataRaw)
    if (type === 'entity') { const ent = entities.value.find(item => item.id === id); if (ent) { ent.groupId = targetFolderId; saveHistory(); render() } }
}

const eraseAtPoint = (mapX: number, mapY: number) => {
    const threshold = eraserSize.value / scale.value; const toRemove: number[] = []
    paths.value.forEach(p => { if (!isPathDeletable(p)) return; if (isPointNearPath(mapX, mapY, p, threshold)) { toRemove.push(p.id) } })
    if (toRemove.length > 0) { paths.value = paths.value.filter(p => !toRemove.includes(p.id)); selectedIds.value = selectedIds.value.filter(id => !toRemove.includes(id)) }
}

const render = () => {
    if (!canvas.value || !mapImage.value) return
    const ctx = canvas.value.getContext('2d')!
    canvas.value.width = canvas.value.offsetWidth; canvas.value.height = canvas.value.offsetHeight
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
    ctx.save(); ctx.translate(offset.value.x, offset.value.y); ctx.scale(scale.value, scale.value); ctx.drawImage(mapImage.value, 0, 0)

    paths.value.forEach(p => {
        if (!p.visible) return
        const isSelected = selectedIds.value.includes(p.id)
        ctx.strokeStyle = p.color; ctx.lineWidth = isSelected ? 5 / scale.value : 3 / scale.value; ctx.lineCap = 'round'; ctx.setLineDash([]); ctx.beginPath()
        p.points.forEach((pt, i) => i === 0 ? ctx.moveTo(pt.x, pt.y) : ctx.lineTo(pt.x, pt.y)); ctx.stroke()
        if (isSelected) { ctx.strokeStyle = 'rgba(255, 138, 0, 0.6)'; ctx.lineWidth = 9 / scale.value; ctx.setLineDash([8 / scale.value, 4 / scale.value]); ctx.beginPath(); p.points.forEach((pt, i) => i === 0 ? ctx.moveTo(pt.x, pt.y) : ctx.lineTo(pt.x, pt.y)); ctx.stroke(); ctx.setLineDash([]) }
    })

    if (currentPath.value.length > 0) {
        ctx.strokeStyle = activeColor.value; ctx.lineWidth = 3/scale.value; ctx.lineCap = 'round'; ctx.setLineDash([]); ctx.beginPath()
        currentPath.value.forEach((pt, i) => i === 0 ? ctx.moveTo(pt.x, pt.y) : ctx.lineTo(pt.x, pt.y)); ctx.stroke()
    }

    entities.value.forEach(item => {
        const parent = item.groupId ? groups.value.find(g => g.id === item.groupId) : null
        if (!item.visible || (parent && !parent.visible)) return
        
        // Draw the entity (rotated)
        ctx.save(); ctx.translate(item.x, item.y); ctx.rotate(item.rotation || 0); const s = item.size
        ctx.setLineDash([])
        if (item.kind === 'util' || item.kind === 'player') {
            if (item.kind === 'util') drawExplosion(ctx, item)
            let imgHeight = 24; let imgWidth = 18;
            const img = getCachedImage(item.src); const ratio = imgWidth / imgHeight || 1; const dw = ratio >= 1 ? s : s * ratio; const dh = ratio >= 1 ? s / ratio : s; ctx.drawImage(img, -dw/2, -dh/2, dw, dh)
        } else if (item.kind === 'text') {
            if (editingTextId.value !== item.id) {
                ctx.fillStyle = item.color; ctx.font = `bold ${s}px Arial`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(item.text, 0, 0)
            }
        }
        ctx.restore() // Restore context here so the box is NOT rotated

        // Render Selection Box (Unrotated) and Handle (Orbiting)
        if (selectedIds.value.includes(item.id)) {
            ctx.save()
            ctx.translate(item.x, item.y)
            const textW = item.kind === 'text' ? ctx.measureText(item.text).width : s
            const hb = { w: textW + 12, h: s + 12 }; const locked = item.locked || (parent && parent.locked)
            
            // Unrotated Box
            ctx.strokeStyle = locked ? '#ff4444' : '#ff8a00'; ctx.setLineDash([5/scale.value, 5/scale.value]); ctx.lineWidth = 2/scale.value; ctx.strokeRect(-hb.w/2, -hb.h/2, hb.w, hb.h)
            
            if (selectedIds.value.length === 1 && !locked) {
                ctx.setLineDash([]);
                ctx.font = `${14/scale.value}px Arial`; 
                ctx.textAlign = 'center'; 
                ctx.textBaseline = 'middle';
                
                // Delete Point (Fixed Top-Right)
                ctx.fillStyle = '#ff4444'; ctx.beginPath(); ctx.arc(hb.w/2, -hb.h/2, 10/scale.value, 0, Math.PI*2); ctx.fill()
                ctx.fillStyle = 'white'; ctx.fillText('✖', hb.w/2, -hb.h/2 + 1/scale.value)
                
                // Resize Point (Fixed Bottom-Right)
                ctx.fillStyle = '#ff8a00'; ctx.beginPath(); ctx.arc(hb.w/2, hb.h/2, 10/scale.value, 0, Math.PI*2); ctx.fill()
                ctx.fillStyle = 'white'; ctx.fillText('⤡', hb.w/2, hb.h/2)
                
                // Rotate Point (Orbiting based on angle)
                const rotR = hb.h/2 + 20/scale.value;
                const rotX = rotR * Math.sin(item.rotation || 0);
                const rotY = -rotR * Math.cos(item.rotation || 0);
                
                ctx.fillStyle = '#00e5ff'; ctx.beginPath(); ctx.arc(rotX, rotY, 10/scale.value, 0, Math.PI*2); ctx.fill()
                ctx.fillStyle = 'black'; ctx.fillText('↻', rotX, rotY)
            }
            ctx.restore()
        }
    })
    if (marqueeBox.value) {
        ctx.strokeStyle = '#00e5ff'; ctx.setLineDash([4/scale.value, 2/scale.value]); ctx.lineWidth = 1/scale.value
        ctx.strokeRect(marqueeBox.value.start.x, marqueeBox.value.start.y, marqueeBox.value.end.x - marqueeBox.value.start.x, marqueeBox.value.end.y - marqueeBox.value.start.y)
    }
    ctx.restore()

    if (activeMode.value === 'erase' && eraserCursor.value) {
        const screenX = eraserCursor.value.x; const screenY = eraserCursor.value.y; const screenRadius = eraserSize.value
        ctx.save(); ctx.beginPath(); ctx.arc(screenX, screenY, screenRadius, 0, Math.PI * 2); ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)'; ctx.lineWidth = 2; ctx.setLineDash([4, 3]); ctx.stroke(); ctx.fillStyle = 'rgba(255, 255, 255, 0.08)'; ctx.fill(); ctx.restore()
    }
}

const drawExplosion = (ctx: any, icon: any) => {
    const cfg = UTIL_CONFIG[icon.type as keyof typeof UTIL_CONFIG]; if (!cfg) return
    const r = (icon.size / 45) * 40; const g = ctx.createRadialGradient(0, 0, r * 0.1, 0, 0, r)
    g.addColorStop(0, cfg.colors[0]); g.addColorStop(0.5, cfg.colors[1]); g.addColorStop(1, 'transparent'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI*2); ctx.fill()
}

const getMapCoords = (clientX: number, clientY: number) => {
    if (!canvas.value) return { x: 0, y: 0 }
    const rect = canvas.value.getBoundingClientRect(); return { x: (clientX - rect.left - offset.value.x) / scale.value, y: (clientY - rect.top - offset.value.y) / scale.value }
}

const getScreenCoords = (clientX: number, clientY: number) => {
    if (!canvas.value) return { x: 0, y: 0 }
    const rect = canvas.value.getBoundingClientRect(); return { x: clientX - rect.left, y: clientY - rect.top }
}

const handleMouseDown = (e: MouseEvent) => {
    const coords = getMapCoords(e.clientX, e.clientY); isInteracting.value = true; lastMousePos.value = { x: e.clientX, y: e.clientY }
    if (activeMode.value === 'erase') { eraseAtPoint(coords.x, coords.y); render(); return }
    if (e.shiftKey) { isMarqueeActive.value = true; marqueeBox.value = { start: coords, end: coords }; return }
    
    if (selectedIds.value.length === 1) {
        const item = entities.value.find(i => i.id === selectedIds.value[0])
        if (item && isItemDeletable(item)) {
            const ctx = canvas.value!.getContext('2d')!; const textW = item.kind === 'text' ? ctx.measureText(item.text).width : item.size; const hb = { w: textW + 12, h: item.size + 12 }
            
            const dx = coords.x - item.x;
            const dy = coords.y - item.y;

            // Box points are unrotated
            if (Math.hypot(dx - (hb.w/2), dy - (-hb.h/2)) < 15/scale.value) { deleteItem(item.id); return }
            if (Math.hypot(dx - (hb.w/2), dy - (hb.h/2)) < 15/scale.value) { isResizing.value = true; return }
            
            // Rotate point hit detection uses the orbiting math
            const rotR = hb.h/2 + 20/scale.value;
            const rotX = rotR * Math.sin(item.rotation || 0);
            const rotY = -rotR * Math.cos(item.rotation || 0);
            if (Math.hypot(dx - rotX, dy - rotY) < 15/scale.value) { isRotating.value = true; return }
        }
    }
    
    const hit = [...entities.value].reverse().find(i => {
        const parent = i.groupId ? groups.value.find(g => g.id === i.groupId) : null
        if (!i.visible || (parent && !parent.visible)) return false
        const textW = i.kind === 'text' ? canvas.value!.getContext('2d')!.measureText(i.text).width : i.size
        
        // Entity selection body is back to axis-aligned matching the unrotated box
        return Math.abs(coords.x - i.x) < (textW/2 + 8) && Math.abs(coords.y - i.y) < (i.size/2 + 8)
    })
    
    if (hit) { if (!selectedIds.value.includes(hit.id)) selectedIds.value = [hit.id]; if (isItemDeletable(hit)) draggedId.value = hit.id; render(); return }
    if (activeMode.value === 'pan') { const hitPath = [...paths.value].reverse().find(p => p.visible && isPointNearPath(coords.x, coords.y, p, 8 / scale.value)); if (hitPath) { selectedIds.value = [hitPath.id]; render(); return } }
    selectedIds.value = []; if (activeMode.value === 'draw') currentPath.value = [coords]; render()
}

// Detection for Double Click to trigger editing
const handleDoubleClick = (e: MouseEvent) => {
    const coords = getMapCoords(e.clientX, e.clientY)
    const hit = [...entities.value].reverse().find(i => {
        if (i.kind !== 'text' || !i.visible) return false
        const textW = canvas.value!.getContext('2d')!.measureText(i.text).width
        
        // Axis-aligned hit detection
        return Math.abs(coords.x - i.x) < (textW/2 + 8) && Math.abs(coords.y - i.y) < (i.size/2 + 8)
    })
    if (hit) startEditingText(hit)
}

const handleMouseMove = (e: MouseEvent) => {
    if (activeMode.value === 'erase') { const sc = getScreenCoords(e.clientX, e.clientY); eraserCursor.value = sc } else { eraserCursor.value = null }
    if (!isInteracting.value) { if (activeMode.value === 'erase') render(); return }
    const coords = getMapCoords(e.clientX, e.clientY)
    if (activeMode.value === 'erase') { eraseAtPoint(coords.x, coords.y); render(); return }
    if (isMarqueeActive.value && marqueeBox.value) {
        marqueeBox.value.end = coords; const x1 = Math.min(marqueeBox.value.start.x, marqueeBox.value.end.x); const x2 = Math.max(marqueeBox.value.start.x, marqueeBox.value.end.x); const y1 = Math.min(marqueeBox.value.start.y, marqueeBox.value.end.y); const y2 = Math.max(marqueeBox.value.start.y, marqueeBox.value.end.y)
        selectedIds.value = entities.value.filter(ent => ent.x > x1 && ent.x < x2 && ent.y > y1 && ent.y < y2).map(ent => ent.id)
    } else if (draggedId.value) {
        const dx = coords.x - getMapCoords(lastMousePos.value.x, lastMousePos.value.y).x; const dy = coords.y - getMapCoords(lastMousePos.value.x, lastMousePos.value.y).y
        selectedIds.value.forEach(id => { const ent = entities.value.find(e => e.id === id); if (ent && isItemDeletable(ent)) { ent.x += dx; ent.y += dy } })
    } else if (selectedIds.value.length === 1) {
        const item = entities.value.find(i => i.id === selectedIds.value[0]); if (item && isItemDeletable(item)) { if (isRotating.value) item.rotation = Math.atan2(coords.y - item.y, coords.x - item.x) + Math.PI/2; else if (isResizing.value) item.size = Math.max(10, Math.hypot(coords.x - item.x, coords.y - item.y) * 1.5) }
    }
    if (!draggedId.value && !isResizing.value && !isRotating.value && !isMarqueeActive.value) { if (activeMode.value === 'pan') { offset.value.x += e.clientX - lastMousePos.value.x; offset.value.y += e.clientY - lastMousePos.value.y } else if (activeMode.value === 'draw') currentPath.value.push(coords) }
    lastMousePos.value = { x: e.clientX, y: e.clientY }; render()
}

const handleMouseUp = () => {
    if (activeMode.value === 'erase') { saveHistory(); isInteracting.value = false; return }
    if (isInteracting.value) {
        if (draggedId.value || isRotating.value || isResizing.value || isMarqueeActive.value) saveHistory()
        else if (currentPath.value.length > 1) { paths.value.push({ id: Date.now(), name: `Line ${paths.value.length + 1}`, points: [...currentPath.value], color: activeColor.value, visible: true, locked: false }); saveHistory() }
    }
    isInteracting.value = false; draggedId.value = null; isRotating.value = false; isResizing.value = false; isMarqueeActive.value = false; marqueeBox.value = null; currentPath.value = []; render()
}

const handleMouseLeave = () => { eraserCursor.value = null; render() }

const handleWheel = (e: WheelEvent) => {
    e.preventDefault(); const zoomSpeed = 0.0015; const oldScale = scale.value; scale.value = Math.min(Math.max(scale.value - e.deltaY * zoomSpeed, 0.1), 10)
    const rect = canvas.value!.getBoundingClientRect(); const mx = e.clientX - rect.left; const my = e.clientY - rect.top
    offset.value.x = mx - (mx - offset.value.x) * (scale.value / oldScale); offset.value.y = my - (my - offset.value.y) * (scale.value / oldScale); render()
}

const handleKeyDown = (e: KeyboardEvent) => {
    // Escape closes the shortcuts modal. (The name dialog is a PrimeVue
    // modal Dialog and handles its own Escape.)
    if (e.key === 'Escape' && showShortcuts.value) { showShortcuts.value = false; return }
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); return }
    if (e.ctrlKey && e.key === 'y') { e.preventDefault(); redo(); return }
    if (e.key === 'Delete' || e.key === 'Backspace') { selectedIds.value.forEach(id => deleteItem(id)); return }
    if (e.code === 'Space' && !preSpaceMode.value) { e.preventDefault(); preSpaceMode.value = activeMode.value; activeMode.value = 'pan'; return }
    switch(e.key.toLowerCase()) {
        case 'p': activeMode.value = 'pan'; break; case 'd': activeMode.value = 'draw'; break; case 'e': activeMode.value = 'erase'; break; case 't': spawnEntity('text', ''); break; case 's': spawnEntity('util', 'smokes'); break; case 'm': spawnEntity('util', 'mol'); break; case 'h': spawnEntity('util', 'he'); break; case 'f': spawnEntity('util', 'flashbang'); break; case '1': spawnEntity('player', 'ct'); break; case '2': spawnEntity('player', 't'); break; case 'l': isSidebarMinimized.value = !isSidebarMinimized.value; break; case '?': showShortcuts.value = !showShortcuts.value; break
    }
}

const handleKeyUp = (e: KeyboardEvent) => { if (e.code === 'Space' && preSpaceMode.value) { activeMode.value = preSpaceMode.value; preSpaceMode.value = null } }

const spawnEntity = (type: 'util' | 'player' | 'text', key: string, x?: number, y?: number) => {
    if (!canvas.value) return
    let mapPos = (x !== undefined && y !== undefined) ? getMapCoords(x, y) : { x: (canvas.value.offsetWidth / 2 - offset.value.x) / scale.value, y: (canvas.value.offsetHeight / 2 - offset.value.y) / scale.value }
    const newId = Date.now(); let entity: any = { id: newId, x: mapPos.x, y: mapPos.y, kind: type, rotation: 0, size: type === 'player' ? 35 : 45, visible: true, locked: false, name: `${type} ${key}` }
    if (type === 'util') { const cfg = UTIL_CONFIG[key as keyof typeof UTIL_CONFIG]; entity = { ...entity, src: cfg.src, type: key, size: cfg.initialSize } }
    else if (type === 'player') { entity = { ...entity, src: PLAYER_CONFIG[key as keyof typeof PLAYER_CONFIG].src }; const teamGroupName = key.toUpperCase() + ' Players'; let existingGroup = groups.value.find(g => g.name === teamGroupName); if (!existingGroup) { const gId = Date.now() + 1; existingGroup = { id: gId, name: teamGroupName, collapsed: false, locked: false, visible: true }; groups.value.push(existingGroup) }; entity.groupId = existingGroup.id }
    else if (type === 'text') { entity = { ...entity, text: 'Text Here', color: activeColor.value, size: 30 } }
    entities.value.push(entity); selectedIds.value = [newId]; saveHistory(); render()
}

const getCachedImage = (src: string) => { if (assetCache.has(src)) return assetCache.get(src)!; const img = new Image(); img.src = src; assetCache.set(src, img); img.onload = () => render(); return img }

const downloadMap = () => {
    if (!mapImage.value) return; const exportCanvas = document.createElement('canvas'); const ctx = exportCanvas.getContext('2d')!; exportCanvas.width = mapImage.value.naturalWidth; exportCanvas.height = mapImage.value.naturalHeight; ctx.drawImage(mapImage.value, 0, 0)
    paths.value.forEach(p => { if (!p.visible) return; ctx.strokeStyle = p.color; ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.beginPath(); p.points.forEach((pt, i) => i === 0 ? ctx.moveTo(pt.x, pt.y) : ctx.lineTo(pt.x, pt.y)); ctx.stroke() })
    entities.value.forEach(item => { const parent = item.groupId ? groups.value.find(g => g.id === item.groupId) : null; if (!item.visible || (parent && !parent.visible)) return; ctx.save(); ctx.translate(item.x, item.y); ctx.rotate(item.rotation || 0); const s = item.size; if (item.kind === 'util' || item.kind === 'player') { if (item.kind === 'util') drawExplosion(ctx, item); const img = getCachedImage(item.src); const ratio = img.naturalWidth / img.naturalHeight || 1; const dw = ratio >= 1 ? s : s * ratio; const dh = ratio >= 1 ? s / ratio : s; ctx.drawImage(img, -dw/2, -dh/2, dw, dh) } else if (item.kind === 'text') { ctx.fillStyle = item.color; ctx.font = `bold ${s}px Arial`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(item.text, 0, 0) } ctx.restore() })
    const link = document.createElement('a'); link.download = `strat-export-${Date.now()}.png`; link.href = exportCanvas.toDataURL('image/png'); link.click()
}

const handleDrop = (e: DragEvent) => { e.preventDefault(); const raw = e.dataTransfer?.getData('entityData'); if (raw) { const data = JSON.parse(raw); spawnEntity(data.type, data.key, e.clientX, e.clientY) } }

const canvasCursor = computed(() => { if (activeMode.value === 'erase') return 'cursor-none'; if (draggedId.value) return 'cursor-grabbing'; if (activeMode.value === 'pan') return 'cursor-grab'; return 'cursor-crosshair' })

onMounted(() => { window.addEventListener('resize', () => render()); window.addEventListener('keydown', handleKeyDown); window.addEventListener('keyup', handleKeyUp); saveHistory() })
onUnmounted(() => { window.removeEventListener('resize', render); window.removeEventListener('keydown', handleKeyDown); window.removeEventListener('keyup', handleKeyUp) })
</script>

<template>
    <div class="board-root relative w-full h-full overflow-hidden text-[var(--th-text)] font-sans select-none">
        <input 
            v-if="editingTextId !== null"
            ref="textInputRef"
            v-model="editingTextValue"
            @blur="saveTextEdit"
            @keyup.enter="saveTextEdit"
            class="fixed z-[60] bg-[oklch(0.04_0.01_250_/_0.8)] border border-[var(--th-accent)] text-center outline-none p-1 rounded font-bold"
            :style="{
                left: (entities.find(e => e.id === editingTextId).x * scale + offset.x) + 'px',
                top: (entities.find(e => e.id === editingTextId).y * scale + offset.y) + 'px',
                transform: 'translate(-50%, -50%)',
                fontSize: (entities.find(e => e.id === editingTextId).size * scale) + 'px',
                color: entities.find(e => e.id === editingTextId).color,
                minWidth: '50px'
            }"
        />

        <Dialog v-model:visible="showShortcuts" modal header="Keyboard Shortcuts" :style="{ width: '34rem' }">
                <div class="grid grid-cols-2 gap-y-4 text-sm">
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">SPACE</kbd> <span>Hold to Pan</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">Double Click</kbd> <span>Edit Text</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">P</kbd> <span>Pan Tool</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">D</kbd> <span>Draw Tool</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">E</kbd> <span>Eraser Tool</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">T</kbd> <span>Text Tool</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">S</kbd> <span>Smoke</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">M</kbd> <span>Molotov</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">H</kbd> <span>HE Grenade</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">F</kbd> <span>Flashbang</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">1-2</kbd> <span>Spawn Players</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">L</kbd> <span>Toggle Library</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">DEL</kbd> <span>Delete Selection</span></div>
                    <div class="flex items-center gap-3"><kbd class="bg-[var(--th-surface-2)] px-2 py-1 rounded border border-[var(--th-border-2)] font-mono text-[var(--th-accent)]">Ctrl+Z</kbd> <span>Undo</span></div>
                </div>
        </Dialog>

        <div class="absolute top-12 left-6 flex flex-col gap-4 z-10">
            <input class="bg-transparent border-none outline-none text-3xl font-bold space-grotesk-400" v-model="boardName" placeholder="Board Name"/>
            <GroupSelect v-model="tacticId" placeholder="Select a tactic" optionGroupLabel="label" optionGroupChildren="items" :options="tactics" :optionLabel="option => option.label || 'Untitled' + ' - ' + option.map" optionValue="value">
                <template #option="{ option }">
                    <div class="flex flex-col"><span class="font-medium">{{ option.label || 'Untitled' }}</span><span class="text-xs text-[var(--th-text-2)]">Map: {{ option.map }}</span></div>
                </template>
            </GroupSelect>
            <div class="flex gap-2">
                <Button @click="saveToDatabase">{{ isSaving ? 'Saving...' : 'Save Board' }}</Button>
                <CreateBoard @boardCreated="createNewBoard" />
            </div>
        </div>

        <canvas ref="canvas" class="w-full h-full block"
            :class="canvasCursor"
            @mousedown="handleMouseDown" @mousemove="handleMouseMove" @mouseup="handleMouseUp" @mouseleave="handleMouseLeave" @wheel="handleWheel"
            @dblclick="handleDoubleClick"
            @dragover.prevent @drop="handleDrop"
        ></canvas>

        <div class="board-toolbar absolute flex flex-col gap-4 px-8 py-6 bg-[var(--th-surface)] border border-[var(--th-border-2)] rounded-lg z-20 shadow-2xl" :style="{ left: toolbarPos.x + 'px', top: toolbarPos.y + 'px' }">
            <div class="absolute material-symbols top-4 right-4 cursor-move text-[var(--th-text-3)] hover:text-[var(--th-accent)] active:text-[var(--th-accent)]" role="button" tabindex="0" aria-label="Move toolbar" @mousedown.stop="startToolbarDrag" @keydown.enter.prevent @keydown.space.prevent>drag_indicator</div>
            <div class="grid grid-cols-2">
                <button @click="activeMode = 'pan'" :class="{'text-[var(--th-accent)]': activeMode === 'pan'}" class="p-2 rounded hover:cursor-pointer transition-colors" title="Pan (P or Hold Space)" aria-label="Pan tool"><span class="material-symbols">pan_tool</span></button>
                <button @click="activeMode = 'draw'" :class="{'text-[var(--th-accent)]': activeMode === 'draw'}" class="p-2 rounded hover:cursor-pointer transition-colors" title="Draw (D)" aria-label="Draw tool"><span class="material-symbols">edit</span></button>
                <button @click="activeMode = 'erase'" :class="{'text-[var(--th-accent)]': activeMode === 'erase'}" class="p-2 rounded hover:cursor-pointer transition-colors" title="Eraser (E)" aria-label="Eraser tool"><span class="material-symbols">ink_eraser</span></button>
                <button @click="spawnEntity('text', '')" class="p-2 rounded text-[var(--th-ct)] hover:cursor-pointer" title="Add Text (T)" aria-label="Add text"><span class="material-symbols">title</span></button>
            </div>
            <hr class="border-[var(--th-border)]" />
            <div class="flex gap-3">
                <button v-for="(p, k) in PLAYER_CONFIG" :key="k" draggable="true" @dragstart="e => e.dataTransfer?.setData('entityData', JSON.stringify({type:'player', key:k}))" @click="spawnEntity('player', k)" class="w-9 h-9 hover:scale-110 transition-transform" :aria-label="`Add ${k} player`" :title="`Add ${k} player`">
                    <img :src="p.src" alt="" class="w-full h-full object-contain pointer-events-none" />
                </button>
            </div>
            <hr class="border-[var(--th-border)]" />
            <div class="grid grid-cols-2 gap-2">
                <button v-for="(cfg, key) in UTIL_CONFIG" :key="key" draggable="true" @dragstart="e => e.dataTransfer?.setData('entityData', JSON.stringify({type:'util', key:key}))" @click="spawnEntity('util', key as string)" class="w-9 h-9 bg-[var(--th-surface-2)] rounded p-1 hover:border hover:border-[var(--th-accent)]/50 transition-all" :aria-label="`Add ${key}`" :title="`Add ${key}`">
                    <img :src="cfg.src" alt="" class="w-full h-full object-contain pointer-events-none" />
                </button>
            </div>
            <hr class="border-[var(--th-border)]" />
            <div class="flex flex-col items-center gap-2">
                <div class="relative w-8 h-8 rounded-full border border-[var(--th-border-2)] overflow-hidden shadow-inner group transition-transform hover:scale-110">
                    <input type="color" v-model="activeColor" aria-label="Draw color" class="absolute -inset-2 w-[200%] h-[200%] cursor-pointer border-none p-0 outline-none bg-transparent" />
                </div>
                <span class="text-[10px] text-[var(--th-text-3)] uppercase font-mono">{{ activeColor }}</span>
            </div>
        </div>

        <div class="board-layers absolute top-6 w-80 bg-[var(--th-surface)] border border-[var(--th-border-2)] rounded-lg z-20 shadow-2xl flex flex-col max-h-[70vh] transition-all duration-300" :style="{ right: isSidebarMinimized ? '60px' : '490px' }">
            <div class="p-4 border-b border-[var(--th-border)] font-bold text-xs uppercase tracking-widest text-[var(--th-text-2)] flex justify-between items-center">
                <span>Layers</span>
                <button @click="createGroup" class="text-[var(--th-accent)] hover:scale-110 transition-transform" title="New Folder" aria-label="New folder"><span class="material-symbols text-sm">create_new_folder</span></button>
            </div>
            <div class="overflow-y-auto flex-1 custom-scrollbar pb-10" @dragover.prevent @drop="onDropSidebar($event, null)">
                <div v-for="group in groups" :key="group.id" class="border-b border-[var(--th-border)]/50" @dragover.prevent @drop.stop="onDropSidebar($event, group.id)">
                    <div class="flex items-center gap-2 p-3 bg-[var(--th-surface-2)]/30 hover:bg-[var(--th-surface-2)]/60 cursor-pointer group">
                        <button type="button" @click.stop="group.collapsed = !group.collapsed" class="material-symbols text-sm text-[var(--th-text-3)]" :aria-label="group.collapsed ? 'Expand folder' : 'Collapse folder'" :aria-expanded="!group.collapsed">{{ group.collapsed ? 'arrow_right' : 'arrow_drop_down' }}</button>
                        <span class="material-symbols text-sm text-[var(--th-t)]" aria-hidden="true">folder</span>
                        <span class="flex-1 text-[11px] font-bold truncate">{{ group.name }}</span>
                        <div class="flex items-center gap-1">
                            <button @click.stop="group.visible = !group.visible; render()" :class="group.visible ? 'text-[var(--th-text-4)]' : 'text-[var(--th-accent)]'" :aria-label="group.visible ? 'Hide folder' : 'Show folder'"><span class="material-symbols text-sm">{{ group.visible ? 'visibility' : 'visibility_off' }}</span></button>
                            <button @click.stop="group.locked = !group.locked; render()" :class="group.locked ? 'text-[var(--th-accent)]' : 'text-[var(--th-text-4)]'" :aria-label="group.locked ? 'Unlock folder' : 'Lock folder'"><span class="material-symbols text-sm">{{ group.locked ? 'lock' : 'lock_open' }}</span></button>
                            <button @click.stop="moveFolder(group.id, 'up')" class="text-[var(--th-text-4)] hover:text-[var(--th-text)]" aria-label="Move folder up"><span class="material-symbols text-sm">expand_less</span></button>
                            <button @click.stop="moveFolder(group.id, 'down')" class="text-[var(--th-text-4)] hover:text-[var(--th-text)]" aria-label="Move folder down"><span class="material-symbols text-sm">expand_more</span></button>
                            <button v-if="!group.locked" @click.stop="deleteFolder(group.id)" class="text-[var(--th-text-4)] hover:text-[var(--th-loss)]" aria-label="Delete folder"><span class="material-symbols text-sm">delete</span></button>
                        </div>
                    </div>
                    <div v-if="!group.collapsed" class="bg-[oklch(0.04_0.01_250_/_0.1)]">
                        <div v-for="layer in entities.filter(e => e.groupId === group.id)" :key="layer.id" class="board-layer-row flex items-center gap-3 pl-10 p-2 hover:bg-[var(--th-surface-2)]/50 cursor-pointer" :class="{ 'is-selected': selectedIds.includes(layer.id) }" draggable="true" @dragstart="onDragStartSidebar($event, 'entity', layer.id)" @click="selectedIds = [layer.id]; render()">
                            <button @click.stop="layer.visible = !layer.visible; render()" class="text-[var(--th-text-3)]" :aria-label="layer.visible ? 'Hide layer' : 'Show layer'"><span class="material-symbols text-xs">{{ layer.visible ? 'visibility' : 'visibility_off' }}</span></button>
                            <button @click.stop="layer.locked = !layer.locked; render()" class="text-[var(--th-text-3)]" :aria-label="layer.locked ? 'Unlock layer' : 'Lock layer'"><span class="material-symbols text-xs">{{ layer.locked ? 'lock' : 'lock_open' }}</span></button>
                            <span class="flex-1 text-[10px] truncate text-[var(--th-text-2)]">{{ layer.text || layer.name }}</span>
                            <button v-if="isItemDeletable(layer)" @click.stop="deleteItem(layer.id)" class="text-[var(--th-text-4)] hover:text-[var(--th-loss)]" aria-label="Delete layer"><span class="material-symbols text-xs">delete</span></button>
                        </div>
                    </div>
                </div>
                <div class="px-3 py-2 text-[10px] font-bold text-[var(--th-text-4)] border-b border-[var(--th-border)] bg-[var(--th-surface)]/40">Ungrouped Elements</div>
                <div v-for="layer in entities.filter(e => !e.groupId).reverse()" :key="layer.id" class="board-layer-row group flex items-center gap-3 p-3 border-b border-[var(--th-border)]/50 hover:bg-[var(--th-surface-2)]/50 cursor-pointer" :class="{ 'is-selected': selectedIds.includes(layer.id) }" draggable="true" @dragstart="onDragStartSidebar($event, 'entity', layer.id)" @click="selectedIds = [layer.id]; render()">
                    <button @click.stop="layer.visible = !layer.visible; render()" class="text-[var(--th-text-3)]" :aria-label="layer.visible ? 'Hide layer' : 'Show layer'"><span class="material-symbols text-sm">{{ layer.visible ? 'visibility' : 'visibility_off' }}</span></button>
                    <button @click.stop="layer.locked = !layer.locked; render()" class="text-[var(--th-text-3)]" :aria-label="layer.locked ? 'Unlock layer' : 'Lock layer'"><span class="material-symbols text-sm">{{ layer.locked ? 'lock' : 'lock_open' }}</span></button>
                    <span class="flex-1 text-[11px] truncate text-[var(--th-text-2)]">{{ layer.text || layer.name }}</span>
                    <div class="flex gap-1 items-center">
                        <button @click.stop="startEditingText(layer)" v-if="layer.kind === 'text'" class="text-[var(--th-text-4)] hover:text-[var(--th-text)]" aria-label="Edit text"><span class="material-symbols text-sm">edit</span></button>
                        <button @click.stop="renameItem(layer)" v-else class="text-[var(--th-text-4)] hover:text-[var(--th-text)]" aria-label="Rename layer"><span class="material-symbols text-sm">edit</span></button>
                        <button @click.stop="moveLayer(layer.id, 'up')" class="text-[var(--th-text-4)] hover:text-[var(--th-accent)]" aria-label="Move layer up"><span class="material-symbols text-sm">expand_less</span></button>
                        <button @click.stop="moveLayer(layer.id, 'down')" class="text-[var(--th-text-4)] hover:text-[var(--th-accent)]" aria-label="Move layer down"><span class="material-symbols text-sm">expand_more</span></button>
                        <button v-if="isItemDeletable(layer)" @click.stop="deleteItem(layer.id)" class="text-[var(--th-text-4)] hover:text-[var(--th-loss)]" aria-label="Delete layer"><span class="material-symbols text-sm">delete</span></button>
                    </div>
                </div>
                <div class="px-3 py-2 text-[10px] font-bold text-[var(--th-text-4)] border-b border-[var(--th-border)] bg-[var(--th-surface)]/40 flex items-center gap-2"><span class="material-symbols text-xs">gesture</span><span>Drawn Lines</span></div>
                <div v-for="path in [...paths].reverse()" :key="path.id" class="board-layer-row group flex items-center gap-3 p-3 border-b border-[var(--th-border)]/50 hover:bg-[var(--th-surface-2)]/50 cursor-pointer" :class="{ 'is-selected': selectedIds.includes(path.id) }" @click="selectedIds = [path.id]; render()">
                    <span class="w-3 h-3 rounded-full shrink-0 border border-[var(--th-border-2)]" :style="{ backgroundColor: path.color }"></span>
                    <button @click.stop="path.visible = !path.visible; render()" class="text-[var(--th-text-3)]" :aria-label="path.visible ? 'Hide line' : 'Show line'"><span class="material-symbols text-sm">{{ path.visible ? 'visibility' : 'visibility_off' }}</span></button>
                    <button @click.stop="path.locked = !path.locked; render()" class="text-[var(--th-text-3)]" :aria-label="path.locked ? 'Unlock line' : 'Lock line'"><span class="material-symbols text-sm">{{ path.locked ? 'lock' : 'lock_open' }}</span></button>
                    <span class="flex-1 text-[11px] truncate text-[var(--th-text-2)]">{{ path.name }}</span>
                    <div class="flex gap-1 items-center">
                        <button @click.stop="renameItem(path)" class="text-[var(--th-text-4)] hover:text-[var(--th-text)]" aria-label="Rename line"><span class="material-symbols text-sm">edit</span></button>
                        <button @click.stop="moveLayer(path.id, 'up')" class="text-[var(--th-text-4)] hover:text-[var(--th-accent)]" aria-label="Move line up"><span class="material-symbols text-sm">expand_less</span></button>
                        <button @click.stop="moveLayer(path.id, 'down')" class="text-[var(--th-text-4)] hover:text-[var(--th-accent)]" aria-label="Move line down"><span class="material-symbols text-sm">expand_more</span></button>
                        <button v-if="isPathDeletable(path)" @click.stop="deleteItem(path.id)" class="text-[var(--th-text-4)] hover:text-[var(--th-loss)]" aria-label="Delete line"><span class="material-symbols text-sm">delete</span></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="board-undobar absolute bottom-6 left-6 flex bg-[var(--th-surface)] rounded-lg p-2 z-20 shadow-xl h-12 border border-[var(--th-accent)] items-center">
             <button @click="undo" class="hover:text-[var(--th-accent)] px-2 border-r border-[var(--th-border)]" title="Undo (Ctrl+Z)" aria-label="Undo"><span class="material-symbols">undo</span></button>
             <button @click="redo" class="hover:text-[var(--th-accent)] px-2 border-r border-[var(--th-border)]" title="Redo (Ctrl+Y)" aria-label="Redo"><span class="material-symbols">redo</span></button>
             <button @click="downloadMap" class="hover:text-[var(--th-accent)] px-2 border-r border-[var(--th-border)]" title="Download Screenshot" aria-label="Download screenshot"><span class="material-symbols">download</span></button>
             <button @click="showShortcuts = true" class="hover:text-[var(--th-accent)] px-2" title="Keyboard Shortcuts (?)" aria-label="Keyboard shortcuts"><span class="material-symbols">help</span></button>
        </div>

        <ContextPanel
            v-model:collapsed="isSidebarMinimized"
            side="right"
            title="Library"
            icon="video_library"
            width="30rem"
            rail="3rem"
            class="board-library"
        >
            <div class="flex flex-col h-full p-4 gap-4 overflow-hidden">
                <div class="flex self-center gap-4 justify-between w-full">
                    <Select v-model="selectedMap" :options="maps" optionLabel="name" optionValue="id" class="!w-full" />
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto custom-scrollbar pr-1 grid grid-cols-2 gap-3 content-start">
                    <div v-if="boardsQuery.isPending.value" class="col-span-2 py-10 text-center text-[var(--th-text-4)] text-[10px] italic">Loading boards...</div>
                    <div v-for="board in filteredBoards" :key="board.id" class="group relative aspect-video bg-[var(--th-surface-2)]/50 rounded border border-[var(--th-border-2)] hover:border-[var(--th-accent)]/50 overflow-hidden cursor-pointer transition-all" @click="loadFromDatabase(board)">
                        <div class="absolute inset-0 bg-[oklch(0.04_0.01_250_/_0.4)] group-hover:bg-[oklch(0.04_0.01_250_/_0.2)] transition-colors z-0"></div>
                        <img v-if="board.snapshot" :src="board.snapshot.url" class="w-full h-full object-contain z-0" />
                        <div class="absolute inset-x-0 bottom-0 p-2 bg-gradient-to-t from-[oklch(0.04_0.01_250_/_0.85)] to-transparent z-10">
                            <div class="text-[10px] font-bold truncate text-[var(--th-text)] leading-tight uppercase">{{ board.name }}</div>
                            <div class="text-[8px] text-[var(--th-text-2)] truncate">{{ Maps.find(board.map_id)?.name }}</div>
                        </div>
                        <button @click.stop="handleDeleteBoard(board.id)" class="absolute top-2 right-2 p-1 bg-[oklch(0.04_0.01_250_/_0.6)] rounded text-[var(--th-text-3)] hover:text-[var(--th-loss)] opacity-0 group-hover:opacity-100 transition-opacity z-20" aria-label="Delete board"><span class="material-symbols text-[14px]">delete</span></button>
                    </div>
                </div>
            </div>
        </ContextPanel>

        <!-- Rename / name dialog — replaces native prompt() -->
        <Dialog
            v-model:visible="nameDialogOpen"
            modal
            :header="nameDialogTitle"
            :style="{ width: '22rem' }"
            @hide="cancelNameDialog"
        >
            <div class="flex flex-col gap-2">
                <label for="board-name-dialog-input" class="text-[11px] font-bold uppercase tracking-widest text-[var(--th-text-3)]">{{ nameDialogLabel }}</label>
                <InputText
                    id="board-name-dialog-input"
                    v-model="nameDialogValue"
                    class="w-full"
                    autocomplete="off"
                    autofocus
                    @keydown.enter="confirmNameDialog"
                />
            </div>
            <template #footer>
                <Button severity="secondary" variant="text" @click="cancelNameDialog">Cancel</Button>
                <Button :disabled="!nameDialogValue.trim()" @click="confirmNameDialog">Save</Button>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.board-root {
    --th-bg:        var(--app-canvas, oklch(0.125 0.012 250));
    --th-surface:   var(--app-surface, oklch(0.175 0.014 250));
    --th-surface-2: var(--app-surface-2, oklch(0.215 0.016 250));
    --th-surface-3: var(--app-surface-3, oklch(0.26 0.018 250));
    --th-surface-4: var(--app-surface-4, oklch(0.31 0.02 250));
    --th-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --th-border-2:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --th-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --th-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --th-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --th-text-4:    var(--app-text-4, oklch(1 0 0 / 0.50));
    --th-accent:    var(--app-accent, #ff8c00);
    --th-loss:      oklch(0.62 0.20 22);
    --th-t:         oklch(0.76 0.13 75);
    --th-ct:        oklch(0.66 0.13 250);
}
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: var(--th-surface-3); border-radius: 10px; }
input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
input[type="color"]::-webkit-color-swatch { border: none; }
kbd { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }

/* Selected layer / line row: full-row tint instead of an edge stripe. */
.board-layer-row.is-selected {
    background: color-mix(in oklch, var(--th-accent) 12%, transparent);
    border-color: color-mix(in oklch, var(--th-accent) 35%, var(--th-border));
}

/* Narrow viewports: keep the floating panels from overlapping each other
   and from fully covering the canvas. Conservative — same pattern, tighter. */
@media (max-width: 1100px) {
    .board-toolbar {
        padding-left: 1rem;
        padding-right: 1rem;
        gap: 0.75rem;
    }
    .board-layers {
        width: 16rem;
    }
    /* Library width is owned by ContextPanel (--cp-width / rail). It defaults
       collapsed, so it stays a thin rail on narrow screens without an override. */
    .board-undobar {
        height: 2.5rem;
        padding: 0.25rem;
    }
}

/* Respect reduced-motion: neutralize decorative transitions and hover scale. */
@media (prefers-reduced-motion: reduce) {
    .board-root :where(.transition-all, .transition-colors, .transition-transform, .transition-opacity) {
        transition: none !important;
    }
    .board-root :where(.hover\:scale-110):hover {
        transform: none !important;
    }
}
</style>