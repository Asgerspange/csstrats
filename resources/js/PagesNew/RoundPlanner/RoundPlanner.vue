<template>
    <div class="rp-root relative flex flex-col w-full bg-[var(--th-bg)] text-[var(--th-text)] outline-none overflow-hidden" style="height: 100%" tabindex="0">

    <!-- ═══════════ SETUP ═══════════════════════════════════════════ -->
    <transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
        <div v-if="!setupDone" class="absolute inset-0 z-50 flex items-center justify-center bg-[oklch(0.04_0.01_250_/_0.72)] overflow-y-auto p-8">
            <div class="w-full max-w-xl bg-[var(--th-surface)] border border-[var(--th-border)] rounded-2xl p-8 flex flex-col gap-5" role="dialog" aria-modal="true" aria-labelledby="rp-setup-title">
                <div class="text-center">
                    <span class="material-symbols text-4xl text-[var(--th-accent)]">route</span>
                    <h1 id="rp-setup-title" class="text-2xl font-black text-[var(--th-accent)] mt-1 mb-0">Strat Builder</h1>
                    <p class="text-xs text-[var(--th-text-3)] mt-0.5">Draw paths, schedule utility, and branch variations on the map.</p>
                </div>

                <!-- Tab -->
                <div class="flex gap-1 p-1 bg-[var(--th-surface-2)] rounded-xl">
                    <button class="flex-1 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all" :class="setupTab === 'new' ? 'bg-[var(--th-surface-3)] text-[var(--th-text)]' : 'text-[var(--th-text-3)] hover:text-[var(--th-text-2)]'" @click="setupTab = 'new'">New</button>
                    <button class="flex-1 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all" :class="setupTab === 'import' ? 'bg-[var(--th-surface-3)] text-[var(--th-text)]' : 'text-[var(--th-text-3)] hover:text-[var(--th-text-2)]'" @click="setupTab = 'import'">From demo</button>
                    <button class="flex-1 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all" :class="setupTab === 'load' ? 'bg-[var(--th-surface-3)] text-[var(--th-text)]' : 'text-[var(--th-text-3)] hover:text-[var(--th-text-2)]'" @click="setupTab = 'load'; loadExistingPlans()">Open</button>
                </div>

                <!-- New plan -->
                <template v-if="setupTab === 'new'">
                    <div v-if="draftAvailable" class="flex items-center gap-2 bg-[var(--th-accent)]/8 border border-[var(--th-accent)]/30 rounded-xl px-3 py-2.5">
                        <span class="material-symbols text-[var(--th-accent)]" style="font-size:18px">history</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-[var(--th-text)]">Unsaved draft from {{ new Date(draftAvailable.at).toLocaleString() }}</p>
                            <p class="text-[10px] text-[var(--th-text-3)] truncate">"{{ draftAvailable.name }}"</p>
                        </div>
                        <button class="text-[var(--th-accent)] text-xs font-bold hover:underline flex-shrink-0" @click="restoreDraft">Restore</button>
                        <button class="text-[var(--th-text-4)] hover:text-[var(--th-text-3)] text-xs flex-shrink-0" aria-label="Dismiss draft" @click="clearDraft"><span class="material-symbols" style="font-size:14px">close</span></button>
                    </div>
                    <div class="flex flex-col gap-1.5"><label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Plan Name</label><input v-model="planName" placeholder="e.g. Mirage T – B Split" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-sm px-3 py-2 outline-none focus:border-[var(--th-accent)]/60 transition-colors" /></div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Map</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button v-for="map in maps" :key="map.id" class="flex flex-col items-center gap-1 p-2 rounded-xl border text-[10px] font-bold transition-all overflow-hidden" :class="setupMapId === map.id ? 'border-[var(--th-accent)] bg-[var(--th-accent)]/10 text-[var(--th-text)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)] text-[var(--th-text-3)] hover:border-[var(--th-border-2)] hover:text-[var(--th-text-2)]'" @click="setupMapId = map.id">
                                <img :src="`/images/demo/${map.name}.svg`" :alt="map.display_name" class="w-full aspect-square object-cover rounded-md transition-all" :class="setupMapId === map.id ? 'opacity-100' : 'opacity-50 grayscale'" />
                                <span>{{ map.display_name }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Side</label>
                        <div class="flex gap-3">
                            <button class="flex items-center gap-2 px-5 py-2 rounded-lg border text-sm font-bold transition-all" :class="setupSide === 'T' ? 'border-[var(--th-t)] bg-[var(--th-t)]/10 text-[var(--th-t)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)] text-[var(--th-text-3)]'" @click="setupSide = 'T'"><span class="material-symbols text-sm">person</span> T</button>
                            <button class="flex items-center gap-2 px-5 py-2 rounded-lg border text-sm font-bold transition-all" :class="setupSide === 'CT' ? 'border-[var(--th-ct)] bg-[var(--th-ct)]/10 text-[var(--th-ct)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)] text-[var(--th-text-3)]'" @click="setupSide = 'CT'"><span class="material-symbols text-sm">shield</span> CT</button>
                        </div>
                    </div>
                    <div v-if="teamPlayers.length" class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Players <span class="normal-case font-normal text-[var(--th-text-4)]">(optional)</span></label>
                        <div class="flex flex-col gap-1">
                            <div v-for="(_, i) in slotAssignments" :key="i" class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: PLAYER_COLORS[i] }" />
                                <select v-model="slotAssignments[i]" class="flex-1 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-xs px-3 py-1.5 outline-none"><option :value="null">— Unassigned —</option><option v-for="p in teamPlayers" :key="p.id" :value="p">{{ p.name }}</option></select>
                            </div>
                        </div>
                    </div>
                    <button class="w-full flex items-center justify-center gap-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 disabled:opacity-40 disabled:cursor-not-allowed text-[var(--th-bg)] text-sm font-semibold rounded-xl h-11 transition-all" :disabled="!setupMapId || !setupSide" @click="confirmSetup">Start Building</button>
                </template>

                <!-- Import from demo -->
                <template v-if="setupTab === 'import'">
                    <div v-if="importPreview" class="flex flex-col gap-3">
                        <div class="bg-[var(--th-surface-2)]/60 border border-[var(--th-border-2)] rounded-xl p-3 flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols text-[var(--th-accent)]" style="font-size:18px">route</span>
                                <span class="text-sm font-bold text-[var(--th-text)]">Round {{ importPreview.roundIndex }}</span>
                                <span class="text-[10px] font-black px-2 py-0.5 rounded border uppercase tracking-widest flex-shrink-0" :class="importPreview.side === 'T' ? 'bg-[var(--th-t)]/10 text-[var(--th-t)] border-[var(--th-t)]/30' : 'bg-[var(--th-ct)]/10 text-[var(--th-ct)] border-[var(--th-ct)]/30'">{{ importPreview.side }}</span>
                                <span class="ml-auto text-[10px] text-[var(--th-text-3)]">{{ importPreview.mapName }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="bg-[var(--th-surface-2)] rounded-lg py-2">
                                    <div class="text-base font-black text-[var(--th-text)] tabular-nums">{{ importPreview.playerCount }}</div>
                                    <div class="text-[9px] uppercase tracking-wider text-[var(--th-text-4)]">players</div>
                                </div>
                                <div class="bg-[var(--th-surface-2)] rounded-lg py-2">
                                    <div class="text-base font-black text-[var(--th-text)] tabular-nums">{{ importPreview.grenadeCount }}</div>
                                    <div class="text-[9px] uppercase tracking-wider text-[var(--th-text-4)]">grenades</div>
                                </div>
                                <div class="bg-[var(--th-surface-2)] rounded-lg py-2">
                                    <div class="text-base font-black text-[var(--th-text)] tabular-nums">{{ importPreview.duration }}s</div>
                                    <div class="text-[9px] uppercase tracking-wider text-[var(--th-text-4)]">length</div>
                                </div>
                            </div>
                            <p class="text-[11px] text-[var(--th-text-3)] leading-relaxed">Paths and grenades for the {{ importPreview.side }} side are imported as a starting variation. Edit from there.</p>
                        </div>
                        <button class="w-full flex items-center justify-center gap-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 text-[var(--th-bg)] text-sm font-semibold rounded-xl h-11 transition-all" @click="confirmImport">Import &amp; Edit</button>
                    </div>
                    <div v-else class="flex flex-col gap-3 py-4">
                        <div class="flex items-center justify-center gap-2 text-[var(--th-text-3)]">
                            <span class="material-symbols" style="font-size:20px">route</span>
                            <span class="text-sm">No round staged for import.</span>
                        </div>
                        <p class="text-[11px] text-[var(--th-text-4)] leading-relaxed text-center">Open a demo in the 2D viewer, pick a round, and click <b class="text-[var(--th-text-3)]">Send to Strat Builder</b>. The round's paths and grenades land here as a starting variation.</p>
                        <button class="w-full flex items-center justify-center gap-2 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] hover:border-[var(--th-accent)]/40 hover:text-[var(--th-text)] text-[var(--th-text-2)] text-sm font-bold rounded-xl py-2.5 transition-all" @click="router.push('/demos')">Browse demos</button>
                    </div>
                </template>

                <!-- Load existing -->
                <template v-if="setupTab === 'load'">
                    <div v-if="existingPlansLoading" class="flex items-center justify-center py-8 text-[var(--th-text-3)]"><span class="material-symbols animate-spin mr-2 text-[var(--th-accent)]">autorenew</span> Loading…</div>
                    <div v-else-if="existingPlans.length === 0" class="py-6 text-center text-[var(--th-text-3)] text-sm flex flex-col gap-2">
                        <span>No saved plans yet.</span>
                        <button class="text-[var(--th-accent)] text-xs font-bold hover:underline" @click="setupTab = 'new'">Create one →</button>
                    </div>
                    <div v-else class="flex flex-col gap-1.5 max-h-72 overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--th-surface-3)]">
                        <button v-for="plan in existingPlans" :key="plan.id" class="flex items-center gap-3 p-3 rounded-xl border transition-all text-left" :class="selectedExistingPlanId === plan.id ? 'border-[var(--th-accent)]/50 bg-[var(--th-accent)]/5 text-[var(--th-text)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)] text-[var(--th-text-2)] hover:border-[var(--th-border-2)]'" @click="selectedExistingPlanId = plan.id">
                            <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-[var(--th-surface-3)]"><img v-if="plan.snapshot_url" :src="plan.snapshot_url" class="w-full h-full object-cover" /><div v-else class="w-full h-full flex items-center justify-center"><span class="material-symbols text-[var(--th-text-3)]" style="font-size:18px">route</span></div></div>
                            <div class="flex-1 min-w-0"><p class="text-sm font-bold truncate">{{ plan.name }}</p><p class="text-[10px] text-[var(--th-text-3)]"><span :class="plan.side === 'T' ? 'text-[var(--th-t)]' : 'text-[var(--th-ct)]'">{{ plan.side }}</span> · {{ plan.map }} · {{ new Date(plan.updated_at).toLocaleDateString() }}</p></div>
                            <span v-if="selectedExistingPlanId === plan.id" class="material-symbols text-[var(--th-accent)] flex-shrink-0" style="font-size:18px">check_circle</span>
                        </button>
                    </div>
                    <button class="w-full flex items-center justify-center gap-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 disabled:opacity-40 disabled:cursor-not-allowed text-[var(--th-bg)] text-sm font-semibold rounded-xl h-11 transition-all" :disabled="!selectedExistingPlanId" @click="confirmLoadExisting">Open & Edit</button>
                </template>
            </div>
        </div>
    </transition>

    <!-- ═══════════ MAIN EDITOR ══════════════════════════════════════ -->
    <div v-if="setupDone" class="flex flex-1 overflow-hidden h-full">

        <!-- Sidebar -->
        <ContextPanel
            v-model:collapsed="sidebarCollapsed"
            :overlay="false"
            :body-scroll="false"
            side="left"
            title="Plan"
            icon="route"
            width="268px"
            class="rp-sidebar"
        >
            <!-- Header (plan name + side badge) lives in the panel's title row -->
            <template #title>
                <input v-model="planName" class="flex-1 bg-transparent border-none outline-none text-[var(--th-text)] text-sm font-bold placeholder-[var(--th-text-4)] min-w-0" placeholder="Plan name..." />
                <span class="text-[10px] font-black px-2 py-0.5 rounded border uppercase tracking-widest flex-shrink-0" :class="side === 'T' ? 'bg-[var(--th-t)]/10 text-[var(--th-t)] border-[var(--th-t)]/30' : 'bg-[var(--th-ct)]/10 text-[var(--th-ct)] border-[var(--th-ct)]/30'">{{ side }}</span>
            </template>

            <!-- Variations bar -->
            <div class="border-b border-[var(--th-border)] px-2.5 py-2.5 flex-shrink-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button
                        v-for="v in variations" :key="v.id"
                        class="group flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border relative"
                        :class="activeVariationId === v.id
                            ? 'bg-[var(--th-accent)]/12 border-[var(--th-accent)]/50 text-[var(--th-accent)]'
                            : 'bg-[var(--th-surface-2)]/50 border-[var(--th-border-2)]/50 text-[var(--th-text-3)] hover:border-[var(--th-border-2)] hover:text-[var(--th-text-2)]'"
                        @click="switchVariation(v.id)"
                        @dblclick.stop="startRenameVariation(v.id)"
                    >
                        <span v-if="v.branchedFromVariationId" class="material-symbols mr-0.5" style="font-size:12px">call_split</span>
                        <input
                            v-if="renamingVariationId === v.id"
                            :ref="(el) => { if (el) (el as HTMLInputElement).focus(); }"
                            v-model="renamingVariationName"
                            class="bg-transparent border-none outline-none w-20 text-xs font-semibold"
                            @blur="commitRenameVariation"
                            @keydown.enter="commitRenameVariation"
                            @keydown.escape="renamingVariationId = null"
                            @click.stop
                        />
                        <span v-else>{{ v.name }}</span>
                        <button
                            v-if="variations.length > 1 && activeVariationId === v.id"
                            class="opacity-0 group-hover:opacity-100 ml-1 text-[var(--th-text-3)] hover:text-[var(--th-loss)] transition-all"
                            @click.stop="deleteVariation(v.id)"
                        ><span class="material-symbols" style="font-size:14px">close</span></button>
                    </button>

                    <!-- Add variation -->
                    <button
                        class="flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--th-surface-2)]/50 border border-[var(--th-border-2)]/50 text-[var(--th-text-3)] hover:text-[var(--th-accent)] hover:border-[var(--th-accent)]/40 transition-all"
                        title="New variation"
                        @click="addVariation()"
                    ><span class="material-symbols" style="font-size:16px">add</span></button>
                </div>

                <!-- Branch info -->
                <div v-if="activeVariation.branchedFromVariationId" class="mt-2 flex items-center gap-1 text-[11px] text-[var(--th-text-4)]">
                    <span class="material-symbols" style="font-size:13px">call_split</span>
                    Branched from <b class="text-[var(--th-text-3)]">{{ variations.find(v => v.id === activeVariation.branchedFromVariationId)?.name }}</b>
                    at {{ activeVariation.branchedAtTime?.toFixed(1) }}s
                </div>
            </div>

            <!-- Multi-select bulk toolbar — appears when ≥2 players are selected. -->
            <transition enter-active-class="transition-all duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition-all duration-100" leave-to-class="opacity-0 -translate-y-1">
                <div v-if="hasMulti" class="mx-1.5 mt-1.5 mb-0.5 rounded-xl border border-[var(--th-accent)]/30 bg-[var(--th-accent)]/8 px-2.5 py-2 flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] font-semibold text-[var(--th-accent)] flex items-center gap-1 flex-shrink-0">
                        <span class="material-symbols" style="font-size:12px">group</span>
                        {{ multiSelection.size }} selected
                    </span>
                    <div class="h-3 w-px bg-[var(--th-border-2)]" />
                    <button class="text-[10px] font-semibold text-[var(--th-text-2)] hover:text-[var(--th-text)] transition-colors" @click="bulkSetWalk">All walk</button>
                    <button class="text-[10px] font-semibold text-[var(--th-text-2)] hover:text-[var(--th-text)] transition-colors" @click="bulkSetRun">All run</button>
                    <button class="text-[10px] font-semibold text-[var(--th-text-2)] hover:text-[var(--th-text)] transition-colors" @click="bulkShift(20, 0)">Shift →</button>
                    <button class="text-[10px] font-semibold text-[var(--th-text-2)] hover:text-[var(--th-text)] transition-colors" @click="bulkShift(-20, 0)">← Shift</button>
                    <button class="text-[10px] font-semibold text-[var(--th-text-2)] hover:text-[var(--th-text)] transition-colors" @click="bulkShift(0, 20)">Shift ↓</button>
                    <button class="text-[10px] font-semibold text-[var(--th-text-2)] hover:text-[var(--th-text)] transition-colors" @click="bulkShift(0, -20)">Shift ↑</button>
                    <button class="text-[10px] font-semibold text-[var(--th-loss)] hover:text-[var(--th-loss)]/80 transition-colors ml-auto" @click="bulkClearPaths">Clear paths</button>
                    <button class="text-[10px] font-semibold text-[var(--th-text-4)] hover:text-[var(--th-text-3)] transition-colors" aria-label="Clear selection" @click="clearMulti"><span class="material-symbols" style="font-size:12px">close</span></button>
                </div>
            </transition>

            <!-- Player list -->
            <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-[var(--th-surface-3)] p-1.5 flex flex-col gap-1">
                <div v-for="(player, slot) in players" :key="slot" class="rounded-xl border overflow-hidden cursor-pointer transition-all select-none" :class="[
                    selectedPlayerSlot === slot ? 'border-[var(--th-border-2)] bg-[var(--th-surface-2)]/90' : 'border-transparent bg-[var(--th-surface-2)]/20 hover:bg-[var(--th-surface-2)]/50 hover:border-[var(--th-border)]',
                    multiSelection.has(slot) && selectedPlayerSlot !== slot ? '!border-[var(--th-accent)]/50 !bg-[var(--th-accent)]/8' : ''
                ]" @click="selectPlayerRow(slot, $event.shiftKey)">

                    <div class="flex items-center gap-0 relative">
                        <div class="w-[3px] self-stretch rounded-l-xl flex-shrink-0" :style="{ background: player.color }" />
                        <div class="flex items-center gap-2.5 flex-1 px-3 py-2.5 min-w-0">
                            <div class="relative flex-shrink-0" @click.stop>
                                <button type="button" class="block w-6 h-6 rounded-full border-2 cursor-pointer transition-all hover:scale-110" :style="{ background: player.color, borderColor: player.color + '60' }" :aria-label="`Change color for ${player.name}`" @click="toggleColorPicker(slot)" />
                                <div v-if="colorPickerSlot === slot" class="absolute z-50 top-8 left-0 bg-[var(--th-surface)] border border-[var(--th-border-2)] rounded-xl p-2 shadow-2xl flex flex-wrap gap-1.5 w-[136px]">
                                    <button v-for="c in PLAYER_SWATCHES" :key="c" type="button" class="w-6 h-6 rounded-full border-2 cursor-pointer transition-all hover:scale-110" :style="{ background: c, borderColor: c + '60' }" :aria-label="`Color ${c}`" @click="setPlayerColor(slot, c); colorPickerSlot = null" />
                                </div>
                            </div>
                            <input v-if="editingNameSlot === slot" :ref="(el) => { if (el) (el as HTMLInputElement).focus(); }" v-model="player.name" class="flex-1 bg-[var(--th-surface-3)] border border-[var(--th-accent)]/40 rounded px-2 py-1 text-[var(--th-text)] text-sm font-semibold outline-none min-w-0" @blur="editingNameSlot = null" @keydown.enter="editingNameSlot = null" @click.stop />
                            <span v-else class="flex-1 text-sm font-semibold text-[var(--th-text-2)] truncate" @dblclick.stop="editingNameSlot = slot">{{ player.name }}</span>
                            <span v-if="player.role && player.role !== 'none'" class="text-[11px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0" :style="{ background: player.color + '20', color: player.color, border: '1px solid ' + player.color + '40' }">{{ getRoleLabel(player.role) }}</span>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="flex items-center gap-0.5 text-[11px] font-semibold text-[var(--th-text-3)] tabular-nums"><span class="material-symbols" style="font-size:14px">route</span>{{ player.path.length }}</span>
                                <span class="flex items-center gap-0.5 text-[11px] font-semibold text-[var(--th-text-3)] tabular-nums"><span class="material-symbols" style="font-size:14px">blur_on</span>{{ player.utilities.length }}</span>
                            </div>
                            <span class="material-symbols text-[var(--th-text-4)] flex-shrink-0 transition-transform duration-200" style="font-size:18px" :style="{ transform: selectedPlayerSlot === slot ? 'rotate(180deg)' : 'rotate(0deg)' }">expand_more</span>
                        </div>
                    </div>

                    <div v-if="selectedPlayerSlot === slot" class="border-t border-[var(--th-border-2)]/40 bg-[oklch(0.04_0.01_250_/_0.72)]/25 flex flex-col">
                        <!-- Role -->
                        <div class="px-3 pt-3 pb-2.5">
                            <p class="text-[11px] font-semibold text-[var(--th-text-4)] mb-2">Role</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="r in PLAYER_ROLES" :key="r.key" class="px-2.5 py-1 rounded-md text-[11px] font-semibold border transition-all" :class="player.role === r.key ? 'border-[var(--th-accent)]/60 bg-[var(--th-accent)]/10 text-[var(--th-accent)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)]/50 text-[var(--th-text-3)] hover:text-[var(--th-text-2)] hover:border-[var(--th-border-2)]'" @click.stop="setPlayerRole(slot, r.key)">{{ r.label }}</button>
                            </div>
                        </div>
                        <div class="w-full h-px bg-[var(--th-border-2)]/30" />

                        <!-- Path -->
                        <div class="px-3 pt-3 pb-2.5">
                            <p class="text-[11px] font-semibold text-[var(--th-text-4)] mb-2">Path</p>
                            <div class="flex items-center gap-1.5">
                                <button class="flex items-center justify-center w-9 h-9 rounded-lg border transition-all flex-shrink-0" :class="activeMode === 'path' ? 'bg-[var(--th-accent)]/15 border-[var(--th-accent)]/60 text-[var(--th-accent)]' : 'bg-[var(--th-surface-2)]/60 border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] hover:border-[var(--th-border-2)]'" title="Draw path" @click.stop="togglePathMode"><span class="material-symbols" style="font-size:18px">route</span></button>
                                <button class="flex items-center gap-1.5 h-9 px-2.5 rounded-lg border transition-all flex-shrink-0" :class="lastWaypointSpeed(slot) === 'walk' ? 'border-[var(--th-info)]/50 bg-[var(--th-info)]/10 text-[var(--th-info)]' : 'bg-[var(--th-surface-2)]/60 border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] hover:border-[var(--th-border-2)]'" @click.stop="toggleLastWaypointSpeed(slot)">
                                    <span class="material-symbols" style="font-size:16px">{{ lastWaypointSpeed(slot) === 'walk' ? 'directions_walk' : 'directions_run' }}</span>
                                    <span class="text-[11px] font-semibold">{{ lastWaypointSpeed(slot) }}</span>
                                </button>
                                <div class="flex-1" />
                                <button class="flex items-center justify-center w-9 h-9 rounded-lg border bg-[var(--th-surface-2)]/60 border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] hover:border-[var(--th-border-2)] transition-all flex-shrink-0" title="Undo last waypoint" @click.stop="removeLastWaypoint(slot)"><span class="material-symbols" style="font-size:18px">undo</span></button>
                                <button class="flex items-center justify-center w-9 h-9 rounded-lg border bg-[var(--th-surface-2)]/60 border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-loss)] hover:border-[var(--th-loss)]/40 transition-all flex-shrink-0" title="Clear path" @click.stop="clearPath(slot)"><span class="material-symbols" style="font-size:18px">delete_sweep</span></button>
                            </div>
                        </div>
                        <div class="w-full h-px bg-[var(--th-border-2)]/30" />

                        <!-- Utility -->
                        <div class="px-3 pt-3 pb-2.5">
                            <p class="text-[11px] font-semibold text-[var(--th-text-4)] mb-2">Utility</p>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <button v-for="ut in UTILITY_TYPES" :key="ut.key" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[11px] font-semibold transition-all" :class="activeMode === 'util_land' && pendingUtilType === ut.key ? '' : 'bg-[var(--th-surface-2)]/60 border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] hover:border-[var(--th-border-2)]'" :style="activeMode === 'util_land' && pendingUtilType === ut.key ? { borderColor: ut.color, color: ut.color, background: ut.color + '18' } : {}" @click.stop="startAddingUtil(slot, ut.key)"><div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: ut.color }" />{{ ut.label }}</button>
                            </div>
                            <div v-if="player.utilities.length" class="flex flex-col gap-1.5">
                                <div v-for="util in player.utilities" :key="util.id" class="flex items-center gap-2 bg-[var(--th-bg)]/60 rounded-lg px-2.5 py-2">
                                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: UTIL_COLORS[util.type] }" />
                                    <span class="text-xs font-semibold capitalize flex-1 min-w-0 truncate" :style="{ color: UTIL_COLORS[util.type] }">{{ util.type }}</span>
                                    <label class="flex items-center gap-0.5 bg-[var(--th-accent)]/10 border border-[var(--th-accent)]/25 rounded px-2 py-1 flex-shrink-0"><input type="number" class="w-10 bg-transparent border-none outline-none text-xs font-semibold text-[var(--th-accent)] text-right tabular-nums" :value="util.throwAt" min="0" max="300" step="0.5" @change="(e) => updateUtilThrowAt(slot, util.id, +(e.target as HTMLInputElement).value)" /><span class="text-[11px] text-[var(--th-accent)]/60 font-semibold">s</span></label>
                                    <button class="text-[var(--th-text-4)] hover:text-[var(--th-loss)] transition-colors flex-shrink-0" @click.stop="removeUtil(slot, util.id)"><span class="material-symbols" style="font-size:16px">close</span></button>
                                </div>
                            </div>
                        </div>
                        <div class="w-full h-px bg-[var(--th-border-2)]/30" />

                        <!-- Notes -->
                        <div class="px-3 pt-3 pb-3.5">
                            <p class="text-[11px] font-semibold text-[var(--th-text-4)] mb-2">Notes</p>
                            <div v-if="player.instructions.length" class="flex flex-col gap-1.5 mb-2">
                                <div v-for="instr in player.instructions" :key="instr.id" class="flex items-center gap-2 bg-[var(--th-bg)]/60 rounded-lg px-2.5 py-2">
                                    <span class="text-[11px] font-semibold text-[var(--th-accent)] flex-shrink-0 w-9 tabular-nums">{{ instr.t.toFixed(1) }}s</span>
                                    <input v-model="instr.text" class="flex-1 bg-transparent border-none outline-none text-[var(--th-text-2)] text-xs min-w-0" @blur="updateInstruction(slot, instr.id, { text: instr.text })" />
                                    <button class="text-[var(--th-text-4)] hover:text-[var(--th-loss)] transition-colors flex-shrink-0" @click.stop="removeInstruction(slot, instr.id)"><span class="material-symbols" style="font-size:16px">close</span></button>
                                </div>
                            </div>
                            <div class="flex gap-1.5">
                                <input v-model="newInstrText[slot]" class="flex-1 bg-[var(--th-surface-2)]/60 border border-[var(--th-border-2)] rounded-lg text-xs text-[var(--th-text)] px-2.5 py-2 outline-none focus:border-[var(--th-accent)]/40 placeholder-[var(--th-text-4)] transition-colors" placeholder="Note at current time…" @keydown.enter="submitInstruction(slot)" />
                                <button class="flex items-center justify-center w-9 h-9 bg-[var(--th-surface-2)]/60 border border-[var(--th-border-2)] hover:border-[var(--th-accent)]/40 hover:text-[var(--th-accent)] text-[var(--th-text-2)] rounded-lg transition-all flex-shrink-0" @click="submitInstruction(slot)"><span class="material-symbols" style="font-size:18px">add</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-3 border-t border-[var(--th-border)] flex-shrink-0 flex flex-col gap-2">
                <button class="w-full flex items-center justify-center gap-2 h-10 rounded-xl border font-semibold text-sm transition-all" :class="isDirty ? 'bg-[var(--th-accent)]/10 border-[var(--th-accent)]/40 text-[var(--th-accent)] hover:bg-[var(--th-accent)]/20' : 'bg-[var(--th-surface-2)]/50 border-[var(--th-border-2)] text-[var(--th-text-3)] hover:border-[var(--th-border-2)] hover:text-[var(--th-text-2)]'" :disabled="isSaving" @click="showSaveModal = true">
                    <span class="material-symbols" style="font-size:18px">{{ isEditMode ? 'save' : 'add_circle' }}</span>
                    {{ isSaving ? 'Saving…' : (isEditMode ? 'Save changes' : 'Save as tactic') }}
                </button>
                <button v-if="isEditMode" class="w-full flex items-center justify-center gap-2 h-9 rounded-xl border text-xs font-semibold transition-all" :class="shareState.enabled ? 'bg-[var(--th-accent)]/8 border-[var(--th-accent)]/30 text-[var(--th-accent)] hover:bg-[var(--th-accent)]/15' : 'border-[var(--th-border-2)] text-[var(--th-text-3)] hover:text-[var(--th-text-2)] hover:border-[var(--th-border-2)]'" @click="toggleShare">
                    <span class="material-symbols" style="font-size:16px">{{ shareState.enabled ? 'link' : 'share' }}</span>
                    {{ shareState.busy ? '…' : (shareState.enabled ? 'Copy link' : 'Share read-only') }}
                </button>
            </div>
        </ContextPanel>

        <!-- Canvas -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            <!-- Status bar -->
            <div class="h-10 bg-[var(--th-surface)] border-b border-[var(--th-border)] flex items-center px-4 gap-2.5 flex-shrink-0">
                <template v-if="activeMode === 'path'">
                    <span class="material-symbols text-[var(--th-accent)]" style="font-size:16px">route</span>
                    <span class="text-sm text-[var(--th-text-3)]">Placing <b class="text-[var(--th-text-2)]">{{ selectedPlayer?.name }}</b>
                        <span class="text-[var(--th-text-4)] ml-2 hidden sm:inline">
                            <kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1.5 py-0.5 text-[10px] font-mono">W</kbd> walk ·
                            <kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1.5 py-0.5 text-[10px] font-mono">RMB</kbd> undo ·
                            <kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1.5 py-0.5 text-[10px] font-mono">B</kbd> branch
                        </span>
                    </span>
                </template>
                <template v-else-if="activeMode === 'util_land'">
                    <span class="material-symbols text-[var(--th-warn)]" style="font-size:16px">gps_fixed</span>
                    <span class="text-sm text-[var(--th-text-3)]">Click landing for <b class="text-[var(--th-warn)]">{{ pendingUtilType }}</b> <kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-1.5 py-0.5 text-[10px] font-mono ml-1">Esc</kbd></span>
                </template>
                <span v-else class="text-sm text-[var(--th-text-4)]">Select a player to start</span>
                <div class="ml-auto flex items-center gap-1">
                    <button class="w-8 h-8 flex items-center justify-center text-[var(--th-text-4)] hover:text-[var(--th-text)] transition-colors rounded-lg" aria-label="Undo" title="Undo (Ctrl+Z)" @click="undo"><span class="material-symbols" style="font-size:18px">undo</span></button>
                    <button class="w-8 h-8 flex items-center justify-center text-[var(--th-text-4)] hover:text-[var(--th-text)] transition-colors rounded-lg" aria-label="Redo" title="Redo (Ctrl+Y)" @click="redo"><span class="material-symbols" style="font-size:18px">redo</span></button>
                    <button class="h-8 flex items-center gap-1.5 px-2.5 text-[var(--th-text-4)] hover:text-[var(--th-text)] transition-colors rounded-lg border border-[var(--th-border-2)]" aria-label="Command palette" title="Command palette (Ctrl+K)" @click="showPalette = true">
                        <span class="material-symbols" style="font-size:16px">search</span>
                        <kbd class="text-[10px] font-mono hidden sm:inline">⌘K</kbd>
                    </button>
                    <button class="w-8 h-8 flex items-center justify-center text-[var(--th-text-4)] hover:text-[var(--th-text)] transition-colors rounded-lg text-sm font-bold" aria-label="Keyboard shortcuts" title="Keyboard shortcuts (Shift+?)" @click="showShortcuts = true">?</button>
                </div>
            </div>

            <!-- Canvas container -->
            <div id="canvas-container" class="flex-1 relative overflow-hidden bg-[var(--th-bg)]" :style="canvasCursor" @mousedown="onCanvasMouseDown" @mousemove="onCanvasMouseMove" @mouseup="onCanvasMouseUp" @mouseleave="onCanvasMouseLeave" @contextmenu.prevent="onCanvasRightClick" @wheel.prevent="onCanvasWheel">
                <canvas id="mapCanvas"  class="absolute inset-0 w-full h-full" style="z-index:1" />
                <canvas id="gameCanvas" class="absolute inset-0 w-full h-full" style="z-index:2" />

                <!-- First-run empty state: teaches the first waypoint.
                     Dismissed on first interaction or via the close button.
                     Respects prefers-reduced-motion (handled by the rp-root
                     transition override in <style>). -->
                <transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
                    <div v-if="showEmptyHint" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none flex flex-col items-center gap-2 text-center" style="z-index:15">
                        <div class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[var(--th-surface)]/90 border border-[var(--th-border-2)] backdrop-blur-sm">
                            <span class="material-symbols text-[var(--th-accent)]" style="font-size:18px">route</span>
                            <span class="text-sm text-[var(--th-text-2)]">Click the map to place <b class="text-[var(--th-text)]">{{ selectedPlayer?.name ?? 'Player 1' }}</b>'s first waypoint</span>
                            <button class="pointer-events-auto ml-1 text-[var(--th-text-4)] hover:text-[var(--th-text)] transition-colors" aria-label="Dismiss hint" @click.stop="dismissEmptyHint"><span class="material-symbols" style="font-size:16px">close</span></button>
                        </div>
                        <span class="text-[10px] text-[var(--th-text-4)]">Right-click a waypoint to branch, wait, or throw utility.</span>
                    </div>
                </transition>

                <!-- Instruction overlays -->
                <template v-for="(player, slot) in players" :key="'instr-' + slot">
                    <transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition-all duration-150" leave-to-class="opacity-0 -translate-y-1">
                        <div v-if="getPlayerInstructionAtTime(slot, currentSecond)" class="absolute pointer-events-none" style="z-index:20; transform:translateX(-50%)" :style="getInstrOverlayStyle(slot)">
                            <div class="bg-[var(--th-bg)]/90 border rounded-xl px-3 py-1.5 backdrop-blur-sm flex flex-col gap-0.5 max-w-[160px]" :style="{ borderColor: player.color }">
                                <span class="text-[9px] font-black uppercase tracking-widest" :style="{ color: player.color }">{{ player.name }}</span>
                                <span class="text-[11px] text-[var(--th-text-2)] leading-snug">{{ getPlayerInstructionAtTime(slot, currentSecond)?.text }}</span>
                            </div>
                        </div>
                    </transition>
                </template>

                <!-- Context menu -->
                <div v-if="contextMenu" class="absolute z-50 bg-[var(--th-surface)] border border-[var(--th-border-2)] rounded-xl shadow-2xl overflow-hidden min-w-[180px]" :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }" @mousedown.stop @contextmenu.prevent.stop>
                    <button v-for="item in contextMenu.items" :key="item.label" class="w-full flex items-center gap-2 px-4 py-2.5 text-xs font-bold transition-all hover:bg-[var(--th-surface-2)] text-left" :class="item.danger ? 'text-[var(--th-loss)]' : 'text-[var(--th-text-2)]'" @mousedown.stop @click.stop="item.action(); contextMenu = null">
                        <span class="material-symbols flex-shrink-0" style="font-size:14px">{{ item.icon }}</span>
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <!-- Timeline -->
            <div class="h-24 bg-[var(--th-surface)] border-t border-[var(--th-border)] flex items-center gap-3 px-4 flex-shrink-0">
                <button class="w-11 h-11 rounded-full bg-[var(--th-surface-2)] border border-[var(--th-border-2)] text-[var(--th-accent)] flex items-center justify-center hover:bg-[var(--th-surface-3)] transition-colors flex-shrink-0" :aria-label="isPlaying ? 'Pause' : 'Play'" @click="togglePlayback"><span class="material-symbols" style="font-size:24px">{{ isPlaying ? 'pause' : 'play_arrow' }}</span></button>
                <button class="text-sm font-semibold text-[var(--th-text-3)] hover:text-[var(--th-text)] bg-transparent border border-[var(--th-border-2)] rounded-md cursor-pointer w-12 h-8 flex items-center justify-center flex-shrink-0 transition-colors tabular-nums" aria-label="Cycle playback speed" @click="togglePlaybackSpeed">{{ playbackSpeed }}x</button>
                <div ref="scrubRef" class="flex-1 relative h-16 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg overflow-visible cursor-pointer" @mousedown="startScrubDrag" @mousemove="onScrubHover" @mouseleave="scrubHoverX = null">
                    <div class="absolute inset-0 overflow-hidden rounded-lg">
                        <!-- Ruler row: ticks + labels at the top -->
                        <div class="absolute top-0 left-0 right-0 h-[16px] pointer-events-none border-b border-[var(--th-border)]/40">
                            <template v-for="(tick, i) in timelineTicks" :key="i">
                                <div class="absolute top-0 bg-[var(--th-border-2)]" style="transform:translateX(-50%)" :style="{ left: `${(tick.t / duration) * 100}%`, height: tick.major ? '10px' : '5px', opacity: tick.major ? 0.7 : 0.35 }" />
                                <span v-if="tick.major" class="absolute top-[10px] text-[10px] font-mono text-[var(--th-text-4)] tabular-nums whitespace-nowrap" style="transform:translateX(-50%)" :style="{ left: `${(tick.t / duration) * 100}%` }">{{ tick.label }}</span>
                            </template>
                        </div>
                        <!-- Per-player path bars -->
                        <div class="absolute left-0 right-0 bottom-0 flex flex-col justify-evenly py-1.5" style="top:16px">
                            <div v-for="(player, slot) in players" :key="slot" class="relative h-[8px]">
                                <div v-if="player.path.length >= 2" class="absolute top-0 h-full rounded-sm border min-w-[2px]" :style="{ left: `${(player.path[0].t / duration) * 100}%`, width: `${((player.path[player.path.length - 1].t - player.path[0].t) / duration) * 100}%`, background: player.color + '44', borderColor: player.color }" />
                                <span v-for="util in player.utilities" :key="util.id" class="absolute text-[9px] leading-none -top-1 pointer-events-none select-none" style="transform:translateX(-50%)" :style="{ left: `${(util.throwAt / duration) * 100}%`, color: UTIL_COLORS[util.type] }">▼</span>
                                <div v-for="instr in player.instructions" :key="instr.id" class="absolute top-0 w-px h-full opacity-60 pointer-events-none" style="transform:translateX(-50%)" :style="{ left: `${(instr.t / duration) * 100}%`, background: player.color }" />
                            </div>
                        </div>
                        <!-- Branch markers -->
                        <div v-for="v in variations.filter(v => v.branchedAtTime !== null)" :key="v.id" class="absolute top-0 w-px h-full pointer-events-none" style="z-index:4; transform:translateX(-50%)" :style="{ left: `${((v.branchedAtTime ?? 0) / duration) * 100}%`, background: '#FF8C00', opacity: activeVariation.id === v.branchedFromVariationId ? '0.5' : '0' }">
                            <div class="absolute top-1 left-1/2 -translate-x-1/2 w-2 h-2 rounded-full bg-[var(--th-accent)]" />
                        </div>
                        <!-- Playhead -->
                        <div class="absolute top-0 w-0.5 h-full bg-[var(--th-accent)] pointer-events-none" style="transform:translateX(-50%); z-index:5; box-shadow:0 0 6px #FF8C00" :style="{ left: `${(currentSecond / duration) * 100}%` }" />
                    </div>
                    <div v-if="scrubHoverX !== null" class="absolute -top-8 pointer-events-none z-20 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-2 py-1 text-[11px] font-semibold text-[var(--th-text)] whitespace-nowrap tabular-nums" style="transform:translateX(-50%)" :style="{ left: scrubHoverX + 'px' }">{{ fmt(scrubHoverSeconds) }}</div>
                </div>
                <span class="text-sm font-semibold text-[var(--th-text-3)] tabular-nums min-w-[44px] flex-shrink-0">{{ fmt(currentSecond) }}</span>
            </div>
        </div>
    </div>

    <!-- ═══════════ SAVE / TACTIC MODAL ══════════════════════════════ -->
    <Dialog v-model:visible="showSaveModal" modal :header="isEditMode ? 'Save Changes' : 'Save as Tactic'" :style="{ width: '42rem' }">
            <div class="flex flex-col gap-4">

                <!-- Basic info -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5 col-span-2"><label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Plan / Tactic Name *</label><input v-model="saveName" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-sm px-3 py-2 outline-none focus:border-[var(--th-accent)]/50 transition-colors" placeholder="e.g. B Split double flash" /></div>
                    <div class="flex flex-col gap-1.5 col-span-2"><label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Description</label><textarea v-model="saveDescription" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-sm px-3 py-2 outline-none focus:border-[var(--th-accent)]/50 transition-colors resize-none" rows="2" placeholder="General flow of the round…" /></div>
                </div>

                <!-- New plan only: stratbook + type + tags -->
                <template v-if="!isEditMode">
                    <div class="flex flex-col gap-1.5"><label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Stratbook *</label><select v-model="saveStratbookId" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-sm px-3 py-2 outline-none"><option :value="null">— Select —</option><option v-for="sb in currentTeam?.stratbooks" :key="sb.id" :value="sb.id">{{ sb.stratbook_name }}</option></select></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5"><label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Type</label><TypeDropdown :tacticType="saveType" :teamTypes="currentTeam?.settings?.['types']" @changed="v => saveType = v ?? 'Execute'" /></div>
                        <div class="flex flex-col gap-1.5"><label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Tags</label><TagsDropdown :tacticTags="saveTags" :teamTags="currentTeam?.settings?.['tags']" @changed="v => saveTags = v" /></div>
                    </div>
                </template>

                <!-- Players section -->
                <div class="border-t border-[var(--th-border)] pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Players</label>
                        <span class="text-[10px] text-[var(--th-text-4)]">Names & roles pre-filled from your plan. Descriptions and loadouts optional.</span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div v-for="(sp, i) in savePlayers" :key="i" class="bg-[var(--th-surface-2)]/50 border border-[var(--th-border-2)]/50 rounded-xl p-3 flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: players[i]?.color ?? PLAYER_COLORS[i] }" />
                                <input v-model="sp.name" class="flex-1 bg-transparent border-b border-[var(--th-border-2)] text-[var(--th-text)] text-sm font-bold outline-none focus:border-[var(--th-accent)] pb-0.5" placeholder="Player name" />
                                <select v-model="sp.role" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-md text-[var(--th-text-2)] text-xs px-2 py-1 outline-none">
                                    <option v-for="r in PLAYER_ROLES" :key="r.key" :value="r.key">{{ r.label }}</option>
                                </select>
                            </div>
                            <textarea v-model="sp.description" class="bg-[var(--th-surface)]/60 border border-[var(--th-border-2)]/50 rounded-lg text-[var(--th-text-2)] text-[11px] px-2 py-1.5 outline-none focus:border-[var(--th-accent)]/30 transition-colors resize-none placeholder-[var(--th-text-4)]" rows="2" :placeholder="`${sp.name} role / instructions (optional)`" />
                        </div>
                    </div>
                </div>

                <p v-if="isEditMode" class="text-[11px] text-[var(--th-text-3)] bg-[var(--th-surface-2)]/50 rounded-lg px-3 py-2">
                    <span class="material-symbols text-[var(--th-accent)] mr-1" style="font-size:13px; vertical-align:middle">edit</span>
                    Updating existing plan. Stratbook / type settings are unchanged.
                </p>
            </div>
        <template #footer>
            <button class="px-4 py-2 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] hover:border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] text-xs font-bold rounded-xl transition-all" @click="showSaveModal = false">Cancel</button>
            <button class="flex items-center gap-2 px-4 py-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 disabled:opacity-40 disabled:cursor-not-allowed text-[var(--th-bg)] text-xs font-black uppercase tracking-widest rounded-xl transition-all" :disabled="!saveName || (!isEditMode && !saveStratbookId) || isSaving" @click="confirmSave">
                <span v-if="isSaving" class="material-symbols text-sm animate-spin">autorenew</span>
                {{ isSaving ? 'Saving…' : (isEditMode ? 'Save Changes' : 'Create Tactic') }}
            </button>
        </template>
    </Dialog>

    <!-- Shortcuts -->
    <Dialog v-model:visible="showShortcuts" modal header="Shortcuts" :style="{ width: '22rem' }">
        <div class="flex flex-col gap-2">
            <div v-for="sc in SHORTCUTS" :key="sc.key" class="flex items-center justify-between gap-4"><span class="text-xs text-[var(--th-text-2)]">{{ sc.label }}</span><kbd class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded px-2 py-0.5 text-[10px] font-black text-[var(--th-text-2)] whitespace-nowrap">{{ sc.key }}</kbd></div>
        </div>
    </Dialog>

    <!-- Click outside context menu -->
    <div v-if="contextMenu" class="fixed inset-0 z-40" @mousedown="contextMenu = null" />

    <!-- Wait time dialog -->
    <Dialog v-model:visible="waitDialog.open" modal header="Wait time at waypoint" :style="{ width: '22rem' }">
        <div class="flex flex-col gap-4">
            <p class="text-xs text-[var(--th-text-3)]">Player holds position here before continuing.</p>
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <input type="range" v-model.number="waitDialog.seconds" min="0" max="15" step="0.5" class="flex-1 accent-[var(--th-accent)]" />
                    <span class="text-lg font-black text-[var(--th-accent)] tabular-nums w-12 text-right">{{ waitDialog.seconds.toFixed(1) }}s</span>
                </div>
                <div class="flex gap-1.5 flex-wrap">
                    <button v-for="s in [0, 1, 2, 3, 5, 8]" :key="s" class="px-2.5 py-1 rounded-lg border text-[10px] font-black transition-all" :class="waitDialog.seconds === s ? 'border-[var(--th-accent)]/60 bg-[var(--th-accent)]/10 text-[var(--th-accent)]' : 'border-[var(--th-border-2)] bg-[var(--th-surface-2)] text-[var(--th-text-3)] hover:border-[var(--th-border-2)] hover:text-[var(--th-text-2)]'" @click="waitDialog.seconds = s">{{ s === 0 ? 'None' : s + 's' }}</button>
                </div>
            </div>
        </div>
        <template #footer>
            <button class="px-4 py-2 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] text-xs font-bold rounded-xl" @click="waitDialog.open = false">Cancel</button>
            <button class="px-4 py-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 text-[var(--th-bg)] text-xs font-black uppercase tracking-widest rounded-xl" @click="confirmWait">Apply</button>
        </template>
    </Dialog>

    <!-- Branch variation dialog -->
    <Dialog v-model:visible="branchDialog.open" modal :header="`Branch from ${branchDialog.t.toFixed(1)}s`" :style="{ width: '24rem' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs text-[var(--th-text-3)]">All player paths up to this point are copied into the new variation.</p>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Variation name</label>
                <input v-model="branchDialog.name" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-sm px-3 py-2 outline-none focus:border-[var(--th-accent)]/50" placeholder="e.g. B rush fallback" @keydown.enter="confirmBranch" @keydown.escape="branchDialog.open = false" />
            </div>
        </div>
        <template #footer>
            <button class="px-4 py-2 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] text-xs font-bold rounded-xl" @click="branchDialog.open = false">Cancel</button>
            <button class="px-4 py-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 text-[var(--th-bg)] text-xs font-black uppercase tracking-widest rounded-xl" @click="confirmBranch">Create Branch</button>
        </template>
    </Dialog>

    <!-- Add note dialog -->
    <Dialog v-model:visible="noteDialog.open" modal :header="`Note at ${noteDialog.t.toFixed(1)}s`" :style="{ width: '24rem' }">
        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black uppercase tracking-widest text-[var(--th-text-3)]">Note for {{ players[noteDialog.slot]?.name }}</label>
            <input v-model="noteDialog.text" class="bg-[var(--th-surface-2)] border border-[var(--th-border-2)] rounded-lg text-[var(--th-text)] text-sm px-3 py-2 outline-none focus:border-[var(--th-accent)]/50" placeholder="e.g. Flash before pushing" @keydown.enter="confirmNote" @keydown.escape="noteDialog.open = false" />
        </div>
        <template #footer>
            <button class="px-4 py-2 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] text-xs font-bold rounded-xl" @click="noteDialog.open = false">Cancel</button>
            <button class="px-4 py-2 bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 disabled:opacity-40 text-[var(--th-bg)] text-xs font-black uppercase tracking-widest rounded-xl" :disabled="!noteDialog.text.trim()" @click="confirmNote">Add Note</button>
        </template>
    </Dialog>

    <!-- Util type picker -->
    <Dialog v-model:visible="showUtilPicker" modal :header="`Throw utility at ${utilFromWaypointTime.toFixed(1)}s`" :style="{ width: '22rem' }">
        <div class="grid grid-cols-2 gap-2">
            <button v-for="ut in UTILITY_TYPES" :key="ut.key" class="flex items-center gap-3 px-4 py-3 rounded-xl border border-[var(--th-border-2)] bg-[var(--th-surface-2)]/60 hover:border-[var(--th-border-2)] transition-all group" @click="selectUtilFromWaypoint(ut.key)">
                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: ut.color }" />
                <span class="text-sm font-bold text-[var(--th-text-2)] group-hover:text-[var(--th-text)] transition-colors">{{ ut.label }}</span>
            </button>
        </div>
        <p class="mt-3 text-[10px] text-[var(--th-text-4)]">Then click the landing position on the map.</p>
    </Dialog>


    <!-- Confirm modal -->
    <Dialog :visible="!!confirmModal?.open" modal :header="confirmModal?.title ?? ''" :style="{ width: '24rem' }" @update:visible="(v) => { if (!v) confirmModal = null }">
        <div class="text-sm text-[var(--th-text-2)]">{{ confirmModal?.message }}</div>
        <template #footer>
            <button class="px-4 py-2 bg-[var(--th-surface-2)] border border-[var(--th-border-2)] hover:border-[var(--th-border-2)] text-[var(--th-text-2)] hover:text-[var(--th-text)] text-xs font-bold rounded-xl" @click="confirmModal = null">Cancel</button>
            <button
                class="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-xl"
                :class="confirmModal?.danger ? 'bg-[var(--th-loss)] hover:bg-[var(--th-loss)]/85 text-[var(--th-text)]' : 'bg-[var(--th-accent)] hover:bg-[var(--th-accent)]/90 text-[var(--th-bg)]'"
                @click="confirmModal?.onConfirm?.(); confirmModal = null"
            >Confirm</button>
        </template>
    </Dialog>

    <!-- Command palette -->
    <CommandPalette :open="showPalette" :commands="paletteCommands" @close="showPalette = false" />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from '@/store';
import useMaps  from '@/composables/useMaps';
import useDemos from '@/composables/useDemos';
import useTeams from '@/composables/useTeams';
import { useCanvas }   from '@/PagesNew/Demo/2D/composables/useCanvas';
import { getTickView } from '@/PagesNew/Demo/2D/composables/useRoundData';
import { useToast }    from '@/composables/useToast';
import { usePlan }    from '@/composables/usePlan';
import { useUpgradeModal } from '@/composables/useUpgradeModal';
import { AppErrorCode } from '@/constants/errorCodes';
import TagsDropdown from '@/componentsnew/TagsDropdown.vue';
import TypeDropdown from '@/componentsnew/TypeDropdown.vue';
import ContextPanel from '@/componentsnew/ContextPanel.vue';
import Dialog from '@/components/volt/Dialog.vue';
import { useRoundPlanner, PLAYER_COLORS, PLAYER_ROLES, UTILITY_TYPES } from './composables/useRoundPlanner';
import { useRoundPlannerRenderer }                                       from './composables/useRoundPlannerRenderer';
import { consumeImportPayload, peekImportPayload, type ImportPayload }    from './composables/useImportHandoff';
import CommandPalette, { type CommandItem }                              from './CommandPalette.vue';
import type { RoleKey, UtilType, ActiveMode, Vec2, MapPosition, DragState, ContextMenu, Variation } from './composables/types';

// ── Sidebar collapse (persisted) ────────────────────────────────────────────────
const SIDEBAR_KEY = 'rp-sidebar-collapsed';
const sidebarCollapsed = ref<boolean>(localStorage.getItem(SIDEBAR_KEY) === 'true');
watch(sidebarCollapsed, (v) => localStorage.setItem(SIDEBAR_KEY, String(v)));

const props = withDefaults(defineProps<{ planId?: number | null }>(), { planId: null });
const emit  = defineEmits<{ close: [] }>();

// Modal focus trap, Escape handling and restore-focus are all handled by the
// Volt Dialog wrapper that every RoundPlanner modal now uses.

const store = useStore();
const { teams, selectedTeamId } = storeToRefs(store);
const { Maps }  = useMaps();
const { Demos } = useDemos();
const { Teams } = useTeams();
const toast     = useToast();
const { can }   = usePlan();
const { open: openUpgrade } = useUpgradeModal();
const { data: maps }     = Maps.get();
const { data: demosRaw } = Demos.get();
const { data: team }     = Teams.find();
const currentTeam  = computed(() => (teams.value as any[])?.find((t: any) => t.id === selectedTeamId.value));
// The team API (TeamRepository::show) returns `users` with {name, steam_id},
// not `players`. The old `?.players` read always returned [] so the roster
// prefill block in setup never rendered.
const teamPlayers  = computed<any[]>(() => (team.value as any)?.users ?? []);

const route = useRoute();
const router = useRouter();

// ── Edit mode ─────────────────────────────────────────────────────────────────
// Re-entry: /strat-builder?planId=N opens the plan directly in the editor,
// skipping the setup modal entirely. TacticCard's "Edit" affordance uses this.
const queryPlanId = route.query.planId ? Number(route.query.planId) : null;
const editingPlanId = ref<number | null>(props.planId ?? queryPlanId);
const isEditMode    = computed(() => editingPlanId.value !== null);

// ── Setup ─────────────────────────────────────────────────────────────────────
const setupDone               = ref(false);
const setupMapId              = ref<number | null>(null);
const setupSide               = ref<'T' | 'CT'>('T');
const slotAssignments         = ref<any[]>([null, null, null, null, null]);
const setupTab                = ref<'new' | 'import' | 'load'>('new');
const existingPlans           = ref<any[]>([]);
const existingPlansLoading    = ref(false);
const selectedExistingPlanId  = ref<number | null>(null);

// Import-from-demo tab state
const importPayload = ref<ImportPayload | null>(peekImportPayload());
const importPreview = computed(() => {
    const p = importPayload.value;
    if (!p?.round?.tickNumbers?.length || !p?.round?.players?.count) return null;
    const sideTeam = p.side === 'T' ? 't' : 'ct';
    const { steamids, teams, count } = p.round.players;
    // Players on the chosen side
    let playerCount = 0;
    const sideSteamids = new Set<string>();
    for (let s = 0; s < count; s++) {
        const team = teams[s] === 0 ? 'ct' : 't';
        if (team === sideTeam) { playerCount++; sideSteamids.add(steamids[s]); }
    }
    // Grenades thrown by players on the chosen side (skip per-frame effect entries)
    const grenadeList: any[] = p.round.grenades ? Object.values(p.round.grenades) : [];
    const grenadeCount = grenadeList.filter((g: any) => !g?.isEffectOnly && sideSteamids.has(g.thrower_steamid)).length;
    const duration = (p.round.tickNumbers[p.round.tickNumbers.length - 1] - p.round.tickNumbers[0]) / 64;
    return {
        mapName:     p.mapName ?? 'Unknown',
        roundIndex:  p.roundIndex,
        side:        p.side,
        playerCount,
        grenadeCount,
        duration:    Math.round(duration),
        tickCount:    p.round.tickNumbers.length,
    };
});

async function loadExistingPlans() {
    existingPlansLoading.value = true;
    try { const r = await fetch('/api/round-plans'); if (r.ok) existingPlans.value = await r.json(); } catch {}
    existingPlansLoading.value = false;
}

async function confirmLoadExisting() {
    if (!selectedExistingPlanId.value) return;
    editingPlanId.value = selectedExistingPlanId.value;
    await _loadAndMountPlan(selectedExistingPlanId.value);
}

async function _loadAndMountPlan(planId: number) {
    try {
        const r = await fetch('/api/round-plans/' + planId);
        const data = await r.json();
        const planData = data.data ?? data;
        const mapName  = data.map ?? planData.map;
        let mapList = maps.value as any[] | null;
        if (!mapList?.length) { try { const mr = await fetch('/api/maps'); if (mr.ok) mapList = await mr.json(); } catch {} }
        if (mapName && mapList?.length) {
            const match = mapList.find((m: any) => m.display_name === mapName || m.name === mapName || m.display_name?.toLowerCase() === mapName.toLowerCase());
            if (match) setupMapId.value = match.id;
        }
        side.value = ((data.side ?? planData.side ?? 'T') as string).toUpperCase() as 'T' | 'CT';
        deserialize(planData);
        saveName.value        = data.name ?? planData.name ?? '';
        saveDescription.value = data.tactic?.description ?? '';
        // Reflect the plan's current share state in the footer.
        if (data.share_enabled && data.share_token) {
            shareState.value = { enabled: true, token: data.share_token, url: url('/plan/' + data.share_token), busy: false };
        } else {
            shareState.value = { enabled: false, token: null, url: null, busy: false };
        }
        setupDone.value = true;
        await nextTick();
        if (setupMapId.value) await loadAndDrawMap(setupMapId.value);
        triggerRender();
    } catch (e) {
        console.error('Failed to load plan for editing', e);
        toast.error('Plan did not load', 'The plan data could not be read from the server. Reload the page and try again; if it persists, the plan may be corrupted.');
    }
}

// ── Canvas ────────────────────────────────────────────────────────────────────
const mapMetadataRef = ref<any>(null);
const mapNameRef     = ref<string | null>(null);
const demoProxy      = ref<any>({ map_metadata: null });
const { cachedImage, mapPosition, getCanvases, toWorldCoords, toGameCoords } =
    useCanvas({ mapMetadata: mapMetadataRef, demo: demoProxy });

async function loadAndDrawMap(mapId: number) {
    try {
        const r = await fetch('/api/maps/' + mapId); if (!r.ok) return;
        const data = await r.json();
        mapMetadataRef.value = data.map_metadata ?? null;
        demoProxy.value.map_metadata = data.map_metadata ?? null;
        mapNameRef.value = data.name ?? null;
    } catch { return; }
    await new Promise<void>(resolve => {
        const img = new Image(); img.crossOrigin = 'anonymous';
        img.src = '/images/demo/' + mapNameRef.value + '.svg';
        img.onerror = () => resolve();
        img.onload  = () => { cachedImage.value = img; _drawCachedMap(); resolve(); };
    });
}
function _drawCachedMap() {
    const { mapCanvas, mapCtx, gameCanvas } = getCanvases();
    const container = document.getElementById('canvas-container');
    if (!container || !mapCanvas || !mapCtx || !cachedImage.value) return;
    mapCanvas.width = container.clientWidth; mapCanvas.height = container.clientHeight;
    if (gameCanvas) { gameCanvas.width = container.clientWidth; gameCanvas.height = container.clientHeight; }
    const img = cachedImage.value;
    const x = Math.floor((container.offsetWidth - img.width) / 2), y = Math.floor((container.offsetHeight - img.height) / 2);
    mapPosition.value = { x, y, scale: (mapPosition.value as any).scale || 1 };
    mapCtx.setTransform(1,0,0,1,0,0); mapCtx.clearRect(0,0,mapCanvas.width,mapCanvas.height);
    mapCtx.setTransform(1,0,0,1,x,y); mapCtx.drawImage(img,0,0);
}

// ── Round planner ─────────────────────────────────────────────────────────────
const {
    planName, side, duration, isDirty,
    players, selectedPlayerSlot, selectedPlayer,
    activeMode, pendingUtilType, pendingUtilThrow, draggingWaypoint,
    variations, activeVariationId, activeVariation,
    addVariation, renameVariation, deleteVariation, switchVariation, branchVariationFromTime,
    setPlayerColor, setPlayerRole, setPlayerFromTeam,
    addWaypoint, removeLastWaypoint, clearPath, toggleLastWaypointSpeed, setWaypointWait,
    startDragWaypoint, moveDragWaypoint, endDragWaypoint,
    setSpeedForSlots, clearPathsForSlots, shiftPathsForSlots,
    startAddingUtil, setUtilLandPos, removeUtil, updateUtilThrowAt, cancelUtil, pendingUtilThrowAt,
    addInstruction, removeInstruction, updateInstruction,
    currentSecond, isPlaying, playbackSpeed,
    togglePlayback, togglePlaybackSpeed, seekTo,
    getPlayerPosAtTime, getPlayerInstructionAtTime,
    undo, redo, serialize, deserialize,
    importFromDemoRound,
} = useRoundPlanner({ mapMetadata: mapMetadataRef });

const { render: rendererRender } = useRoundPlannerRenderer({
    getCanvases,
    toGameCoords: (x: number, y: number) => toGameCoords(x, y) as any,
    mapPosition:  mapPosition as any,
    cachedImage,
});

// ── Variation rename ──────────────────────────────────────────────────────────
const renamingVariationId   = ref<number | null>(null);
const renamingVariationName = ref('');

function startRenameVariation(id: number) {
    renamingVariationId.value   = id;
    renamingVariationName.value = variations.value.find(v => v.id === id)?.name ?? '';
}
function commitRenameVariation() {
    if (renamingVariationId.value && renamingVariationName.value.trim()) {
        renameVariation(renamingVariationId.value, renamingVariationName.value.trim());
    }
    renamingVariationId.value = null;
}

// ── UI state ──────────────────────────────────────────────────────────────────
const editingNameSlot  = ref<number | null>(null);
const newInstrText     = ref<string[]>(['', '', '', '', '']);
const cursorGamePos    = ref<Vec2 | null>(null);
const currentSpeed     = ref<'run' | 'walk'>('run');
const showSaveModal    = ref(false);
const showShortcuts    = ref(false);
const showPalette      = ref(false);
// Multi-select: a Set of slots for bulk operations. The primary
// selectedPlayerSlot is the "active" player (for drawing); multiSelection is
// the transform target. Shift-click toggles membership.
const multiSelection = ref<Set<number>>(new Set());
const hasMulti = computed(() => multiSelection.value.size >= 2);
const hoveredWaypoint  = ref<DragState | null>(null);
const isDraggingLocal  = ref(false);
const colorPickerSlot  = ref<number | null>(null);
const contextMenu           = ref<ContextMenu | null>(null);
const showUtilPicker        = ref(false);

// Curated swatch palette — the 5 default player colors plus 4 neutrals that
// read well on the map. Replaces the native <input type="color"> dialog,
// which is exactly the default-browser styling that reads as cheap.
const PLAYER_SWATCHES: readonly string[] = [
    '#FF8C00', '#4FC3F7', '#81C784', '#F06292', '#CE93D8',
    '#FFFFFF', '#FFD54F', '#4DB6AC', '#EF5350',
];

// First-run empty-state coachmark. Shows when the plan is genuinely empty
// (no paths, no utils across any player) and the user hasn't dismissed it.
// Auto-dismisses on the first waypoint or any canvas click.
const emptyHintDismissed = ref(false);
const planIsEmpty = computed(() =>
    players.value.every((p: any) => p.path.length === 0 && p.utilities.length === 0));
const showEmptyHint = computed(() =>
    setupDone.value && planIsEmpty.value && !emptyHintDismissed.value);
function dismissEmptyHint() { emptyHintDismissed.value = true; }
const utilFromWaypointSlot  = ref(0);
const utilFromWaypointTime  = ref(0);
const branchDialog = ref<{ open: boolean; t: number; name: string }>({ open: false, t: 0, name: '' });
const waitDialog   = ref<{ open: boolean; slot: number; index: number; seconds: number }>({ open: false, slot: 0, index: 0, seconds: 0 });
const noteDialog   = ref<{ open: boolean; slot: number; t: number; text: string }>({ open: false, slot: 0, t: 0, text: '' });
function openBranchDialog(t: number) { branchDialog.value = { open: true, t, name: 'Branch @' + t.toFixed(1) + 's' }; }
function confirmBranch() { branchVariationFromTime(branchDialog.value.t, branchDialog.value.name || undefined); branchDialog.value.open = false; }
function openWaitDialog(slot: number, index: number, cur: number) { waitDialog.value = { open: true, slot, index, seconds: cur }; }
function confirmWait() { setWaypointWait(waitDialog.value.slot, waitDialog.value.index, waitDialog.value.seconds); waitDialog.value.open = false; triggerRender(); }
function openNoteDialog(slot: number, t: number) { seekTo(t); noteDialog.value = { open: true, slot, t, text: '' }; }
function confirmNote() { const { slot, t, text } = noteDialog.value; if (text.trim()) addInstruction(slot, text.trim(), t); noteDialog.value.open = false; }
const confirmModal = ref<{ open: boolean; title: string; message: string; danger: boolean; onConfirm: () => void } | null>(null);
function showConfirmModal(title: string, message: string, danger: boolean, onConfirm: () => void) {
    confirmModal.value = { open: true, title, message, danger, onConfirm };
}

function selectUtilFromWaypoint(utilType: string) {
    showUtilPicker.value = false;
    const t = utilFromWaypointTime.value;
    seekTo(t);
    selectedPlayerSlot.value = utilFromWaypointSlot.value;
    startAddingUtil(utilFromWaypointSlot.value, utilType as any, t);
    nextTick(() => triggerRender());
}

// Save form
const saveName        = ref('');
const saveStratbookId = ref<number | null>(null);
const saveType        = ref('Execute');
const saveDescription = ref('');
const saveTags = ref<string[]>([]);
const isSaving        = ref(false);

// ── Share (read-only link) ───────────────────────────────────────────────────
// Share state for the current plan. Enabled via POST /share; revoked via
// DELETE /share. Rotating the token on re-enable revokes all prior links.
const shareState = ref<{ enabled: boolean; token: string | null; url: string | null; busy: boolean }>({ enabled: false, token: null, url: null, busy: false });

async function toggleShare() {
    if (!editingPlanId.value) return;
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    shareState.value.busy = true;
    try {
        if (shareState.value.enabled && shareState.value.url) {
            // Already enabled → copy the link. If copy fails, surface the URL so the user can copy manually.
            await navigator.clipboard.writeText(shareState.value.url);
            toast.success('Link copied', 'The read-only link is in your clipboard.');
        } else {
            // Enable sharing.
            const res = await fetch('/api/round-plans/' + editingPlanId.value + '/share', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            });
            if (!res.ok) throw new Error(String(res.status));
            const body = await res.json();
            shareState.value = { enabled: true, token: body.share_token, url: body.share_url, busy: false };
            await navigator.clipboard.writeText(body.share_url);
            toast.success('Share link ready', 'Read-only link copied. Anyone with the link can view, not edit.');
        }
    } catch (e) {
        toast.error('Share failed', 'Could not toggle sharing. Try again; if it persists, the plan may not be saved yet.');
    } finally {
        shareState.value.busy = false;
    }
}

async function revokeShare() {
    if (!editingPlanId.value || !shareState.value.enabled) return;
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    try {
        const res = await fetch('/api/round-plans/' + editingPlanId.value + '/share', {
            method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf },
        });
        if (!res.ok) throw new Error(String(res.status));
        shareState.value = { enabled: false, token: null, url: null, busy: false };
        toast.success('Sharing revoked', 'The link no longer works.');
    } catch {
        toast.error('Could not revoke', 'Try again; if it persists, refresh the page.');
    }
}

// When entering edit mode, fetch the current share state so the footer
// reflects reality (the plan may already be shared).
async function refreshShareState() {
    if (!editingPlanId.value) { shareState.value = { enabled: false, token: null, url: null, busy: false }; return; }
    try {
        const r = await fetch('/api/round-plans/' + editingPlanId.value);
        if (!r.ok) return;
        const data = await r.json();
        if (data.share_enabled && data.share_token) {
            shareState.value = { enabled: true, token: data.share_token, url: url('/plan/' + data.share_token), busy: false };
        }
    } catch { /* non-critical */ }
}

// Players in save form — pre-filled from round plan players
const savePlayers = ref<{ name: string; role: string; description: string; loadout: string[] }[]>([]);
watch(showSaveModal, (open) => {
    if (open) {
        savePlayers.value = players.value.map(p => ({ name: p.name, role: p.role, description: '', loadout: [] }));
        if (!saveName.value) saveName.value = planName.value;
    }
});

const UTIL_COLORS: Record<string, string> = Object.fromEntries(UTILITY_TYPES.map(u => [u.key, u.color]));
function toggleColorPicker(slot: number) { colorPickerSlot.value = colorPickerSlot.value === slot ? null : slot; }
function getRoleLabel(key: string) { return PLAYER_ROLES.find(r => r.key === key)?.label ?? key; }
function lastWaypointSpeed(slot: number): 'run' | 'walk' { const p = players.value[slot].path; return p.length ? p[p.length - 1].speed as any : 'run'; }
function selectPlayer(slot: number) {
    selectedPlayerSlot.value = slot;
    if (activeMode.value !== 'util_land') activeMode.value = 'path';
}

// Player-row click with optional shift. Shift-click toggles membership in the
// multi-selection; a plain click clears multi-selection and sets the active
// slot (so drawing resumes on a single player).
function selectPlayerRow(slot: number, shift: boolean) {
    if (shift) {
        const s = new Set(multiSelection.value);
        if (s.has(slot)) s.delete(slot); else s.add(slot);
        multiSelection.value = s;
        // The active slot follows the most recently added member so drawing
        // targets a visible player.
        if (s.has(slot)) selectedPlayerSlot.value = slot;
    } else {
        multiSelection.value = new Set();
        selectPlayer(slot);
    }
}

// Bulk actions — apply to the multi-selection (or just the active slot when
// nothing is multi-selected, so the buttons remain useful).
function bulkSlots(): number[] {
    return hasMulti.value ? Array.from(multiSelection.value) : [selectedPlayerSlot.value];
}
function bulkSetWalk()    { setSpeedForSlots(bulkSlots(), 'walk'); triggerRender(); }
function bulkSetRun()     { setSpeedForSlots(bulkSlots(), 'run');  triggerRender(); }
function bulkClearPaths() {
    if (!confirm('Clear paths and utility for the selected players? This cannot be undone past Ctrl+Z.')) return;
    clearPathsForSlots(bulkSlots());
    multiSelection.value = new Set();
    triggerRender();
}
function bulkShift(dx: number, dy: number) { shiftPathsForSlots(bulkSlots(), dx, dy); triggerRender(); }
function selectAllPlayers() {
    multiSelection.value = new Set(players.value.map((_: any, i: number) => i));
}
function clearMulti() { multiSelection.value = new Set(); }

// Close the swatch popover on any click outside its container. The popover's
// parent wraps the click with @click.stop, so clicks inside it never reach
// the document — any click that reaches here is an outside click.
function onDocClickCloseColor() {
    if (colorPickerSlot.value !== null) colorPickerSlot.value = null;
}
function togglePathMode() { activeMode.value = activeMode.value === 'path' ? 'none' : 'path'; }
function submitInstruction(slot: number) { const t = newInstrText.value[slot]?.trim(); if (!t) return; addInstruction(slot, t, currentSecond.value); newInstrText.value[slot] = ''; }

const canvasCursor = computed(() => {
    if (isDraggingLocal.value) return { cursor: 'grabbing' };
    if (hoveredWaypoint.value) return { cursor: 'grab' };
    if (activeMode.value === 'path') return { cursor: 'crosshair' };
    if (activeMode.value === 'util_land') return { cursor: 'cell' };
    return { cursor: 'default' };
});

const SHORTCUTS = [
    { key: 'Ctrl+K',     label: 'Command palette' },
    { key: 'Space',       label: 'Play / pause' },
    { key: '1–5',         label: 'Select player' },
    { key: 'Shift+click', label: 'Add player to multi-select' },
    { key: 'Ctrl+Shift+A', label: 'Select all players' },
    { key: 'Arrows',      label: 'Nudge selected paths' },
    { key: 'B',           label: 'Branch from playhead' },
    { key: 'W',           label: 'Toggle walk on last waypoint' },
    { key: 'Hold H',      label: 'Pan map (or middle-drag)' },
    { key: 'Ctrl+S',      label: 'Save' },
    { key: 'Esc',         label: 'Cancel / clear selection' },
    { key: 'RMB',         label: 'Remove last waypoint / branch from dot' },
    { key: 'Drag dot',    label: 'Reposition waypoint' },
    { key: 'Dbl-click var', label: 'Rename variation' },
    { key: 'Ctrl+Z',      label: 'Undo' },
    { key: 'Ctrl+Y',      label: 'Redo' },
    { key: 'Shift+?',     label: 'This help' },
];

// ── Command palette ──────────────────────────────────────────────────────────
// One affordance for every power action. The palette is the depth surface —
// power users operate the tool from here; new users discover capabilities by
// typing. Actions are grouped: Plan, Edit, Playback, View.
const paletteCommands = computed<CommandItem[]>(() => {
    const cmds: CommandItem[] = [
        // Plan
        { id: 'new',        label: 'New plan',        icon: 'add_circle', group: 'Plan', hint: '', run: () => { setupDone.value = false; setupTab.value = 'new'; editingPlanId.value = null; } },
        { id: 'open',       label: 'Open plan',       icon: 'folder_open', group: 'Plan', run: () => { setupDone.value = false; setupTab.value = 'load'; loadExistingPlans(); } },
        { id: 'import',     label: 'Import from demo', icon: 'route',     group: 'Plan', run: () => { setupDone.value = false; setupTab.value = 'import'; } },
        { id: 'save',       label: 'Save plan',       icon: 'save',       group: 'Plan', hint: 'Ctrl+S', run: () => { showSaveModal.value = true; }, show: () => setupDone.value },
        { id: 'share',      label: 'Copy share link', icon: 'share',      group: 'Plan', run: () => toggleShare(), show: () => isEditMode.value && shareState.value.enabled },
        { id: 'enableshare', label: 'Enable sharing', icon: 'link',       group: 'Plan', run: () => toggleShare(), show: () => isEditMode.value && !shareState.value.enabled },
        { id: 'revokeshare', label: 'Revoke share link', icon: 'link_off', group: 'Plan', run: () => revokeShare(), show: () => isEditMode.value && shareState.value.enabled },
        // Edit
        { id: 'undo',       label: 'Undo',            icon: 'undo',       group: 'Edit', hint: 'Ctrl+Z', run: () => undo() },
        { id: 'redo',       label: 'Redo',            icon: 'redo',       group: 'Edit', hint: 'Ctrl+Y', run: () => redo() },
        { id: 'branch',     label: 'Branch from playhead', icon: 'call_split', group: 'Edit', hint: 'B', run: () => openBranchDialog(currentSecond.value), show: () => setupDone.value },
        { id: 'addvar',     label: 'Add variation',   icon: 'post_add',   group: 'Edit', run: () => addVariation(), show: () => setupDone.value },
        // Player select
        { id: 'p1', label: 'Select Player 1', icon: 'person', group: 'Player', hint: '1', run: () => { selectPlayer(0); triggerRender(); }, show: () => setupDone.value },
        { id: 'p2', label: 'Select Player 2', icon: 'person', group: 'Player', hint: '2', run: () => { selectPlayer(1); triggerRender(); }, show: () => setupDone.value },
        { id: 'p3', label: 'Select Player 3', icon: 'person', group: 'Player', hint: '3', run: () => { selectPlayer(2); triggerRender(); }, show: () => setupDone.value },
        { id: 'p4', label: 'Select Player 4', icon: 'person', group: 'Player', hint: '4', run: () => { selectPlayer(3); triggerRender(); }, show: () => setupDone.value },
        { id: 'p5', label: 'Select Player 5', icon: 'person', group: 'Player', hint: '5', run: () => { selectPlayer(4); triggerRender(); }, show: () => setupDone.value },
        // Selection (multi-select bulk ops)
        { id: 'selectall',  label: 'Select all players', icon: 'group', group: 'Selection', hint: 'Ctrl+Shift+A', run: () => selectAllPlayers(), show: () => setupDone.value },
        { id: 'clearmulti', label: 'Clear selection',    icon: 'deselect', group: 'Selection', run: () => clearMulti(), show: () => hasMulti.value },
        { id: 'bulkwalk',   label: 'Set selected to walk', icon: 'directions_walk', group: 'Selection', run: () => bulkSetWalk(), show: () => setupDone.value },
        { id: 'bulkrun',    label: 'Set selected to run',  icon: 'directions_run',  group: 'Selection', run: () => bulkSetRun(), show: () => setupDone.value },
        { id: 'bulkclear',  label: 'Clear selected paths', icon: 'delete_sweep',     group: 'Selection', run: () => bulkClearPaths(), show: () => setupDone.value },
        // Playback
        { id: 'play',  label: 'Play / pause',  icon: 'play_arrow', group: 'Playback', hint: 'Space', run: () => togglePlayback(), show: () => setupDone.value },
        { id: 'speed', label: 'Cycle speed',   icon: 'speed',     group: 'Playback', run: () => togglePlaybackSpeed(), show: () => setupDone.value },
        // View
        { id: 'sidebar', label: 'Toggle sidebar', icon: 'side_navigation', group: 'View', run: () => sidebarCollapsed.value = !sidebarCollapsed.value, show: () => setupDone.value },
        { id: 'shortcuts', label: 'Show keyboard shortcuts', icon: 'keyboard', group: 'View', hint: 'Shift+?', run: () => showShortcuts.value = true },
    ];
    return cmds;
});

// ── Canvas interaction ────────────────────────────────────────────────────────
let _isPanning = false, _draggingSlot = -1, _draggingIndex = -1;

function clientToGame(cx: number, cy: number): Vec2 {
    const el = document.getElementById('canvas-container');
    if (!el) return { x: 0, y: 0 };
    const rect = el.getBoundingClientRect(), w = toWorldCoords(cx - rect.left, cy - rect.top) as any;
    const m = mapMetadataRef.value;
    if (!m) return { x: w.x, y: w.y };
    return { x: w.x * m.scale + m.pos_x, y: m.pos_y - w.y * m.scale };
}

function onCanvasMouseDown(e: MouseEvent) {
    _isPanning = false;
    contextMenu.value = null;
    if (activeMode.value === 'path' && e.button === 0) emptyHintDismissed.value = true;

    if (activeMode.value === 'path' && e.button === 0) {
        const el = document.getElementById('canvas-container'), rect = el?.getBoundingClientRect();
        if (rect) {
            const mx = e.clientX - rect.left, my = e.clientY - rect.top;
            const mp = mapPosition.value as any;
            const slot = selectedPlayerSlot.value, path = players.value[slot]?.path ?? [];
            for (let i = 0; i < path.length; i++) {
                const gc = toGameCoords(path[i].x, path[i].y) as any;
                if (Math.hypot(mx - (gc.x * mp.scale + mp.x), my - (gc.y * mp.scale + mp.y)) <= 10) {
                    _draggingSlot = slot; _draggingIndex = i; isDraggingLocal.value = true;
                    startDragWaypoint(slot, i);
                    window.addEventListener('mousemove', _onWindowDragMove);
                    window.addEventListener('mouseup', _onWindowDragUp);
                    return;
                }
            }
        }
    }
    if (e.button === 2) return;
    if (e.button === 1 || activeMode.value === 'none') { _isPanning = true; return; }
    const gp = clientToGame(e.clientX, e.clientY);
    if (activeMode.value === 'path')      { addWaypoint(selectedPlayerSlot.value, gp.x, gp.y, currentSpeed.value); triggerRender(); return; }
    if (activeMode.value === 'util_land') { setUtilLandPos(gp.x, gp.y); triggerRender(); return; }
    _isPanning = true;
}

function onCanvasMouseMove(e: MouseEvent) {
    cursorGamePos.value = clientToGame(e.clientX, e.clientY);
    if (_draggingSlot >= 0) { moveDragWaypoint(cursorGamePos.value.x, cursorGamePos.value.y); triggerRender(); return; }
    if (_isPanning) { const mp = mapPosition.value as any; mapPosition.value = { ...mp, x: mp.x + e.movementX, y: mp.y + e.movementY }; }
    if (activeMode.value === 'path') {
        const el = document.getElementById('canvas-container'), rect = el?.getBoundingClientRect();
        if (rect) {
            const mx = e.clientX - rect.left, my = e.clientY - rect.top, mp = mapPosition.value as any;
            const slot = selectedPlayerSlot.value, path = players.value[slot]?.path ?? [];
            let found: DragState | null = null;
            for (let i = 0; i < path.length; i++) {
                const gc = toGameCoords(path[i].x, path[i].y) as any;
                if (Math.hypot(mx - (gc.x * mp.scale + mp.x), my - (gc.y * mp.scale + mp.y)) <= 10) { found = { slot, index: i }; break; }
            }
            if (hoveredWaypoint.value?.index !== found?.index || hoveredWaypoint.value?.slot !== found?.slot) hoveredWaypoint.value = found;
        }
    } else { hoveredWaypoint.value = null; }
    triggerRender();
}

function onCanvasMouseUp() {
    _isPanning = false;
    if (_draggingSlot >= 0) { _draggingSlot = -1; _draggingIndex = -1; isDraggingLocal.value = false; endDragWaypoint(); triggerRender(); }
}
function onCanvasMouseLeave() {
    hoveredWaypoint.value = null; cursorGamePos.value = null;
    if (_draggingSlot >= 0) { _draggingSlot = -1; _draggingIndex = -1; isDraggingLocal.value = false; endDragWaypoint(); }
    triggerRender();
}

function onCanvasRightClick(e: MouseEvent) {
    const el = document.getElementById('canvas-container'), rect = el?.getBoundingClientRect();
    if (rect && activeMode.value === 'path') {
        const mx = e.clientX - rect.left, my = e.clientY - rect.top, mp = mapPosition.value as any;
        const slot = selectedPlayerSlot.value, path = players.value[slot]?.path ?? [];
        for (let i = 0; i < path.length; i++) {
            const gc = toGameCoords(path[i].x, path[i].y) as any;
            if (Math.hypot(mx - (gc.x * mp.scale + mp.x), my - (gc.y * mp.scale + mp.y)) <= 12) {
                const t = path[i].t;
                const menuX = Math.min(mx, rect.width - 200), menuY = Math.min(my, rect.height - 180);
                const waypointIdx = i;
                contextMenu.value = { x: menuX, y: menuY, items: [
                    { label: 'Branch from ' + t.toFixed(1) + 's', icon: 'call_split', action: () => openBranchDialog(t) },
                    { label: 'Add note here', icon: 'comment', action: () => openNoteDialog(slot, t) },
                    { label: (path[waypointIdx]?.waitAfter ?? 0) > 0 ? 'Wait: ' + (path[waypointIdx].waitAfter ?? 0).toFixed(1) + 's (edit)' : 'Set wait time', icon: 'timer', action: () => openWaitDialog(slot, waypointIdx, path[waypointIdx]?.waitAfter ?? 0) },
                    { label: 'Throw utility from here', icon: 'blur_on', action: () => { utilFromWaypointSlot.value = slot; utilFromWaypointTime.value = t; showUtilPicker.value = true; } },
                    { label: 'Remove waypoint', icon: 'delete', danger: true, action: () => {
                        showConfirmModal('Remove waypoint', 'Remove this waypoint from the path?', true, () => {
                            const player = players.value[slot];
                            player.utilities = player.utilities.filter((u: any) => u.fromWaypointIndex !== waypointIdx);
                            player.utilities.forEach((u: any) => { if (u.fromWaypointIndex !== null && u.fromWaypointIndex > waypointIdx) u.fromWaypointIndex--; });
                            player.path.splice(waypointIdx, 1);
                            for (let j = Math.max(1, waypointIdx); j < player.path.length; j++) {
                                const prev = player.path[j-1], curr = player.path[j];
                                curr.t = prev.t + (prev.waitAfter ?? 0) + Math.hypot(curr.x-prev.x, curr.y-prev.y) / (curr.speed === 'walk' ? 130 : 250);
                            }
                            triggerRender();
                        });
                    }},
                ]};
                return;
            }
        }
    }
    if (activeMode.value === 'util_land') { cancelUtil(); triggerRender(); }
}
function onCanvasWheel(e: WheelEvent) {
    const d = e.deltaY > 0 ? -0.08 : 0.08, mp = mapPosition.value as any;
    const next = Math.min(4, Math.max(0.3, mp.scale + d)), z = next / mp.scale;
    const el = document.getElementById('canvas-container'), rect = el?.getBoundingClientRect();
    const ox = e.clientX - (rect?.left ?? 0), oy = e.clientY - (rect?.top ?? 0);
    mapPosition.value = { x: ox - (ox - mp.x) * z, y: oy - (oy - mp.y) * z, scale: next };
    triggerRender();
}

function _onWindowDragMove(e: MouseEvent) { if (_draggingSlot < 0) return; const gp = clientToGame(e.clientX, e.clientY); cursorGamePos.value = gp; moveDragWaypoint(gp.x, gp.y); triggerRender(); }
function _onWindowDragUp() { window.removeEventListener('mousemove', _onWindowDragMove); window.removeEventListener('mouseup', _onWindowDragUp); if (_draggingSlot >= 0) { _draggingSlot = -1; _draggingIndex = -1; isDraggingLocal.value = false; endDragWaypoint(); triggerRender(); } _isPanning = false; }

// ── Keyboard ──────────────────────────────────────────────────────────────────
// Pan model: hold `H` to temporarily suspend path-drawing and drag the map
// (middle-mouse still pans regardless). `Space` is play/pause — the timeline
// is the primary surface here, matching After Effects / Premiere.
let _holdPanActive = false, _modeBeforePan: any = 'path';
function onKeyDown(e: KeyboardEvent) {
    // Command palette — global, works even from inside an input. Toggle on
    // Ctrl+K / Cmd+K so a second press closes it.
    if (e.code === 'KeyK' && (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
        e.preventDefault();
        showPalette.value = !showPalette.value;
        return;
    }
    // If the palette is open, Escape closes it — regardless of focus. This
    // must run before the INPUT/TEXTAREA guard so a field-focused Escape
    // still closes the palette instead of being swallowed.
    if (showPalette.value && e.code === 'Escape') {
        e.preventDefault();
        showPalette.value = false;
        return;
    }
    if ((e.target as HTMLElement).tagName === 'INPUT' || (e.target as HTMLElement).tagName === 'TEXTAREA') return;
    if (e.repeat) return;
    if (e.code === 'Escape') {
        // Esc clears in priority order: util placement → drawing mode → multi-
        // selection → context menu. One press peels one layer.
        if (activeMode.value === 'util_land') { cancelUtil(); return; }
        if (activeMode.value !== 'none') { activeMode.value = 'none'; contextMenu.value = null; return; }
        if (hasMulti.value) { clearMulti(); return; }
        contextMenu.value = null;
        return;
    }
    // Select all players (Ctrl+Shift+A) — bulk-select for the toolbar.
    if (e.code === 'KeyA' && (e.ctrlKey || e.metaKey) && e.shiftKey) {
        e.preventDefault();
        selectAllPlayers();
        return;
    }
    // Nudge selected paths with arrow keys (no modifiers). 20 game-units
    // per press — matches the bulk toolbar buttons.
    if (hasMulti.value && e.code.startsWith('Arrow')) {
        e.preventDefault();
        const d = 20;
        if (e.code === 'ArrowRight') bulkShift(d, 0);
        else if (e.code === 'ArrowLeft') bulkShift(-d, 0);
        else if (e.code === 'ArrowDown') bulkShift(0, d);
        else if (e.code === 'ArrowUp') bulkShift(0, -d);
        return;
    }
    // Play/pause — timeline-first. Ignore when a modifier is held so it never
    // shadows Ctrl+Space / Shift+Space shortcuts in other tools.
    if (e.code === 'Space' && !e.ctrlKey && !e.metaKey && !e.shiftKey) { e.preventDefault(); togglePlayback(); return; }
    // Hold-to-pan (H). Suspends path mode while held so drag pans, not draws.
    if (e.code === 'KeyH' && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        if (!_holdPanActive) { _holdPanActive = true; _modeBeforePan = activeMode.value; activeMode.value = 'none'; }
        return;
    }
    // Player select 1-5.
    if (e.code >= 'Digit1' && e.code <= 'Digit5' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
        const slot = Number(e.code.slice(5)) - 1;
        if (slot < players.value.length) { selectPlayer(slot); triggerRender(); }
        return;
    }
    // Branch from current playhead.
    if (e.code === 'KeyB' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
        e.preventDefault();
        openBranchDialog(currentSecond.value);
        return;
    }
    // Save — pre-empt the browser's "Save Page" and open the save modal.
    if (e.code === 'KeyS' && (e.ctrlKey || e.metaKey) && !e.shiftKey) {
        e.preventDefault();
        showSaveModal.value = true;
        return;
    }
    if (e.code === 'KeyZ' && (e.ctrlKey || e.metaKey) && !e.shiftKey) { undo(); return; }
    if ((e.code === 'KeyZ' && e.shiftKey) || (e.code === 'KeyY' && (e.ctrlKey || e.metaKey))) { redo(); return; }
    if (e.code === 'KeyW') { const slot = selectedPlayerSlot.value, path = players.value[slot]?.path; if (path?.length) { toggleLastWaypointSpeed(slot); currentSpeed.value = path[path.length-1].speed as any; } else { currentSpeed.value = currentSpeed.value === 'run' ? 'walk' : 'run'; } triggerRender(); return; }
    if (e.code === 'Slash' && e.shiftKey) showShortcuts.value = !showShortcuts.value;
}
function onKeyUp(e: KeyboardEvent) { if ((e.target as HTMLElement).tagName === 'INPUT' || (e.target as HTMLElement).tagName === 'TEXTAREA') return; if (e.code === 'KeyH' && _holdPanActive) { _holdPanActive = false; activeMode.value = _modeBeforePan; _isPanning = false; } }

// ── Render ────────────────────────────────────────────────────────────────────
let _rafId: number | null = null;
let _snapshotMode = false;
function triggerRender() {
    if (_rafId !== null) return;
    _rafId = requestAnimationFrame(() => {
        _rafId = null;
        rendererRender({ players: players.value, selectedSlot: selectedPlayerSlot.value, currentSecond: currentSecond.value, getPlayerPosAtTime, getActiveUtilsAtTime: () => [], activeMode: activeMode.value, pendingUtilThrow: pendingUtilThrow.value, pendingCursorPos: cursorGamePos.value, pendingUtilThrowAt: pendingUtilThrowAt.value, isPlayback: false, draggingWaypoint: draggingWaypoint.value, hoveredWaypoint: hoveredWaypoint.value, planName: planName.value, side: side.value, mapMetadata: mapMetadataRef.value, captureForSnapshot: _snapshotMode });
    });
}
watch(currentSecond, triggerRender);

// Collapsing the in-flow sidebar animates the canvas container width over
// ~280ms. The idle render loop is paused, so pump renders across the transition
// to keep the canvas tracking the changing width.
watch(sidebarCollapsed, () => {
    const start = performance.now();
    const pump = () => {
        triggerRender();
        if (performance.now() - start < 340) requestAnimationFrame(pump);
    };
    requestAnimationFrame(pump);
});

// ── Setup ─────────────────────────────────────────────────────────────────────
async function confirmSetup() {
    if (!setupMapId.value) return;
    side.value = setupSide.value; setupDone.value = true;
    slotAssignments.value.forEach((a: any, i: number) => { if (a) setPlayerFromTeam(i, a); });
    await nextTick();
    await loadAndDrawMap(setupMapId.value);
    triggerRender();
}

// Resolve a map by display_name / name / slug (case-insensitive) against the
// loaded maps list, falling back to a fresh /api/maps fetch if needed.
async function resolveMapByName(name: string | null): Promise<number | null> {
    if (!name) return null;
    let list = (maps.value as any[]) ?? null;
    if (!list?.length) {
        try { const mr = await fetch('/api/maps'); if (mr.ok) list = await mr.json(); } catch {}
    }
    if (!list?.length) return null;
    const n = name.toLowerCase();
    const match = list.find((m: any) =>
        m.display_name?.toLowerCase() === n ||
        m.name?.toLowerCase() === n);
    return match?.id ?? null;
}

// Import the stashed demo round into the active variation, resolve the map,
// and drop straight into the editor. The fastest first-run path: a team's
// own round, paths already drawn.
async function confirmImport() {
    const p = importPayload.value;
    if (!p?.round?.tickNumbers?.length) return;
    // Consume (clears the handoff so a refresh doesn't re-trigger).
    consumeImportPayload();

    const mapId = await resolveMapByName(p.mapName);
    setupMapId.value = mapId;
    setupSide.value = p.side;
    side.value = p.side;

    // Prefill roster slots from the team by SteamID where possible.
    const roster = (p.roster ?? []).map(r => ({ name: r.name, steamid: r.steamid }));
    setupDone.value = true;
    await nextTick();
    if (mapId) {
        await loadAndDrawMap(mapId);
    } else {
        toast.info('Map not found', `"${p.mapName ?? 'the demo map'}" is not in the map list. Pick one from the sidebar to draw on it.`);
    }

    importFromDemoRound(p.round, roster, { getTickView, side: p.side });

    // Name the plan from the source.
    if (!planName.value || planName.value === 'New Round Plan') {
        planName.value = `R${p.roundIndex} · ${p.mapName ?? 'Map'} (${p.side})`;
    }
    selectedPlayerSlot.value = 0;
    triggerRender();
}

function getInstrOverlayStyle(slot: number) {
    const pos = getPlayerPosAtTime(slot, currentSecond.value);
    if (!pos) return { display: 'none' };
    const gc = toGameCoords(pos.x, pos.y) as any, mp = mapPosition.value as any;
    return { left: (gc.x * mp.scale + mp.x) + 'px', top: (gc.y * mp.scale + mp.y - 60) + 'px' };
}

// ── Scrub ─────────────────────────────────────────────────────────────────────
const scrubRef = ref<HTMLDivElement | null>(null), scrubHoverX = ref<number | null>(null);
const scrubHoverSeconds = computed(() => { if (scrubHoverX.value === null || !scrubRef.value) return 0; const rect = scrubRef.value.getBoundingClientRect(); return Math.max(0, Math.min(duration.value, (scrubHoverX.value / rect.width) * duration.value)); });
// Ruler ticks for the timeline. Adapt the spacing to the duration so the
// ruler never crowds: 5s grid for short rounds, 10s for long ones.
const timelineTicks = computed(() => {
    const d = duration.value || 1;
    const major = d <= 60 ? 5 : 10;
    const ticks: { t: number; major: boolean; label: string }[] = [];
    for (let t = 0; t <= d; t += major) {
        ticks.push({ t, major: true, label: fmt(t) });
    }
    // Minor ticks at the midpoint of each major span.
    for (let t = major / 2; t < d; t += major) {
        ticks.push({ t, major: false, label: '' });
    }
    return ticks.sort((a, b) => a.t - b.t);
});
function startScrubDrag(e: MouseEvent) { _seek(e); const move = (ev: MouseEvent) => _seek(ev); const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); }; window.addEventListener('mousemove', move); window.addEventListener('mouseup', up); }
function _seek(e: MouseEvent) { if (!scrubRef.value) return; const rect = scrubRef.value.getBoundingClientRect(); seekTo(((e.clientX - rect.left) / rect.width) * duration.value); }
function onScrubHover(e: MouseEvent) { if (!scrubRef.value) return; scrubHoverX.value = e.clientX - scrubRef.value.getBoundingClientRect().left; }

// ── Save ──────────────────────────────────────────────────────────────────────
async function _captureSnapshot(): Promise<Blob | null> {
    const gc = document.getElementById('gameCanvas') as HTMLCanvasElement | null, mc = document.getElementById('mapCanvas') as HTMLCanvasElement | null;
    if (!mc || !gc) return null;
    // Re-render without the watermark so the saved thumbnail is clean.
    await new Promise<void>(resolve => {
        _snapshotMode = true;
        requestAnimationFrame(() => {
            rendererRender({
                players: players.value, selectedSlot: selectedPlayerSlot.value, currentSecond: currentSecond.value,
                getPlayerPosAtTime, getActiveUtilsAtTime: () => [], activeMode: activeMode.value,
                pendingUtilThrow: pendingUtilThrow.value, pendingCursorPos: cursorGamePos.value,
                pendingUtilThrowAt: pendingUtilThrowAt.value, isPlayback: false,
                draggingWaypoint: draggingWaypoint.value, hoveredWaypoint: hoveredWaypoint.value,
                planName: planName.value, side: side.value, mapMetadata: mapMetadataRef.value,
                captureForSnapshot: true,
            });
            _snapshotMode = false;
            resolve();
        });
    });
    const ec = document.createElement('canvas'); ec.width = mc.width; ec.height = mc.height;
    const cx = ec.getContext('2d')!; cx.drawImage(mc,0,0); cx.drawImage(gc,0,0);
    // Restore the live watermark on the next frame.
    triggerRender();
    return new Promise(r => ec.toBlob(r, 'image/png'));
}

async function confirmSave() {
    // Team+ gate. Pre-empt the 403 before the upload + Bunny snapshot work.
    if (!can('round_planner')) {
        openUpgrade({ feature: 'round_planner', requiredTier: 'Team' });
        return;
    }
    isSaving.value = true;
    try {
        const tags = saveTags.value;
        const pd  = serialize();
        const currentMap = (maps.value as any[])?.find((m: any) => m.id === setupMapId.value);
        (pd as any).map  = currentMap?.display_name ?? '';
        const blob = await _captureSnapshot();
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        if (isEditMode.value && editingPlanId.value) {
            const fd = new FormData();
            fd.append('name',        saveName.value);
            fd.append('description', saveDescription.value);
            fd.append('round_plan',  JSON.stringify(pd));
            if (blob) fd.append('snapshot', blob, 'round_plan.png');
            const res = await fetch('/api/round-plans/' + editingPlanId.value, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-HTTP-Method-Override': 'PATCH' }, body: fd });
            if (!res.ok) {
                let body: any = null; try { body = await res.json(); } catch { /* non-JSON */ }
                if (body?.app_code === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
                    openUpgrade({ feature: body.feature ?? 'round_planner', requiredTier: body.required_tier ?? 'Team' });
                    return;
                }
                throw new Error();
            }
            toast.success('Plan updated', '"' + saveName.value + '" has been saved.');
        } else {
            if (!saveName.value || !saveStratbookId.value) return;
            const fd = new FormData();
            fd.append('name',         saveName.value);
            fd.append('stratbook_id', String(saveStratbookId.value));
            fd.append('type',         saveType.value);
            fd.append('description',  saveDescription.value);
            fd.append('map', currentMap?.display_name ?? '');
            fd.append('side',         side.value);
            fd.append('round_plan',   JSON.stringify(pd));
            fd.append('tags',         JSON.stringify(tags));
            // Players for the tactic
            fd.append('players',      JSON.stringify(savePlayers.value));
            if (blob) fd.append('snapshot', blob, 'round_plan.png');
            const res = await fetch('/api/round-plans', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd });
            if (!res.ok) {
                let body: any = null; try { body = await res.json(); } catch { /* non-JSON */ }
                if (body?.app_code === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
                    openUpgrade({ feature: body.feature ?? 'round_planner', requiredTier: body.required_tier ?? 'Team' });
                    return;
                }
                throw new Error();
            }
            const created = await res.json();
            editingPlanId.value = created.round_plan?.id ?? null;
            toast.success('Tactic saved', '"' + saveName.value + '" saved to your stratbook.');
        }
        showSaveModal.value = false; isDirty.value = false;
        // Now that the plan is persisted server-side, the local draft is stale — clear it.
        localStorage.removeItem(DRAFT_KEY);
        draftSavedAt.value = null;
    } catch { toast.error('Save failed', 'The server rejected the save. Check your connection and try again; if it persists, you may have hit the plan limit for your tier.'); } finally { isSaving.value = false; }
}

function fmt(s: number) { return Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0'); }

// ── Dirty guard + draft autosave ─────────────────────────────────────────────
// Trust floor: warn before unloading unsaved work. Premium ceiling: silently
// autosave a draft of *new* (unsaved) plans to localStorage so a tab crash or
// accidental close recovers to the exact state. Existing plans use the server
// as the source of truth — explicit Save is the contract there.
const DRAFT_KEY = 'rp-draft-new';
const draftSavedAt = ref<number | null>(null);
let _draftTimer: number | null = null;

function onBeforeUnload(e: BeforeUnloadEvent) {
    if (!isDirty.value) return;
    // For new plans with a draft, we don't need the browser prompt — the
    // draft is safe. Only prompt when work would be lost (existing plan,
    // or new plan not yet draft-saved).
    if (editingPlanId.value || !draftSavedAt.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

// Debounced draft save — fires 1.5s after the last change. Cheap: serialize()
// is ~2KB JSON for a typical plan.
function scheduleDraftSave() {
    if (editingPlanId.value) return; // only new plans
    if (_draftTimer !== null) window.clearTimeout(_draftTimer);
    _draftTimer = window.setTimeout(() => {
        if (!isDirty.value || !setupDone.value) return;
        try {
            const pd = serialize();
            localStorage.setItem(DRAFT_KEY, JSON.stringify({
                at: Date.now(),
                plan: pd,
                mapId: setupMapId.value,
                name: planName.value,
            }));
            draftSavedAt.value = Date.now();
        } catch { /* quota / private mode — silent */ }
    }, 1500);
}

watch(isDirty, scheduleDraftSave);

// Restore: if a draft exists on mount and the user isn't deep-linking into
// edit/import, offer a one-click restore from the setup modal. Non-blocking,
// non-surprising — the user is on the New tab anyway.
const draftAvailable = ref<{ at: number; name: string } | null>(null);
function checkForDraft() {
    if (editingPlanId.value || route.query.import === '1' || props.planId || queryPlanId) return;
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const d = JSON.parse(raw);
        if (d?.at && d?.plan) draftAvailable.value = { at: d.at, name: d.plan.name ?? 'Untitled' };
    } catch { /* corrupt — ignore */ }
}
function restoreDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const d = JSON.parse(raw);
        if (!d?.plan) return;
        setupMapId.value = d.mapId ?? null;
        setupSide.value = (d.plan.side ?? 'T') as 'T' | 'CT';
        setupDone.value = true;
        deserialize(d.plan);
        nextTick(async () => {
            if (setupMapId.value) await loadAndDrawMap(setupMapId.value);
            triggerRender();
        });
        toast.success('Draft recovered', `Restored "${d.plan.name ?? 'Untitled'}" from ${new Date(d.at).toLocaleTimeString()}.`);
        draftAvailable.value = null;
    } catch { toast.error('Draft could not be restored', 'The saved draft is unreadable. It will be cleared.'); clearDraft(); }
}
function clearDraft() {
    localStorage.removeItem(DRAFT_KEY);
    draftAvailable.value = null;
}

onMounted(async () => {
    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('keyup',   onKeyUp);
    window.addEventListener('beforeunload', onBeforeUnload);
    document.addEventListener('click', onDocClickCloseColor);
    checkForDraft();

    // Deep-link re-entry: /strat-builder?planId=N → open the plan directly.
    if (props.planId) { await _loadAndMountPlan(props.planId); return; }
    if (queryPlanId)  { await _loadAndMountPlan(queryPlanId); return; }

    // Deep-link import: /strat-builder?import=1 → jump to the Import tab.
    // If a payload is present, show the preview; the user confirms to run
    // the import. (We don't auto-run because the user may want to pick the
    // side first.)
    if (route.query.import === '1' && importPayload.value) {
        setupTab.value = 'import';
    } else if (route.query.import === '1') {
        // Arrived with ?import=1 but no payload (e.g. refreshed). Surface the
        // gap honestly instead of silently landing on the New tab.
        toast.info('No round to import', 'Open a demo round and click "Send to Strat Builder" to import it.');
    }
});

// Smart defaults: preselect the team's most-recently-used map (inferred from
// the existing plans list — the heaviest map by plan count) and prefill the
// 5 slots from the roster by name. Users can still change everything; this
// just removes the "blank form" tax on every first run.
watch([maps, teamPlayers], async () => {
    if (setupDone.value || setupMapId.value) return;
    const mapList = (maps.value as any[]) ?? [];
    if (!mapList.length) return;

    // If an import payload already fixed the map, respect it.
    if (importPayload.value?.mapName) {
        const id = await resolveMapByName(importPayload.value.mapName);
        if (id) { setupMapId.value = id; return; }
    }

    // Default to Mirage if present — the most-played competitive map and the
    // one the landing-page demo uses, so the first canvas is familiar.
    const mirage = mapList.find((m: any) => m.name === 'de_mirage' || /mirage/i.test(m.display_name ?? ''));
    setupMapId.value = mirage?.id ?? mapList[0]?.id ?? null;

    // Prefill slots from the roster by name (first 5 members). SteamID link
    // stays optional — the user can reassign in the sidebar later.
    const roster = teamPlayers.value.slice(0, 5);
    roster.forEach((u: any, i: number) => {
        if (u?.name) slotAssignments.value[i] = { name: u.name, steamid: u.steam_id ?? null };
    });
}, { immediate: true });
onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown);
    document.removeEventListener('keyup',   onKeyUp);
    window.removeEventListener('beforeunload', onBeforeUnload);
    document.removeEventListener('click', onDocClickCloseColor);
    window.removeEventListener('mousemove', _onWindowDragMove);
    window.removeEventListener('mouseup',   _onWindowDragUp);
    if (_rafId !== null) cancelAnimationFrame(_rafId);
    if (_draftTimer !== null) window.clearTimeout(_draftTimer);
});
</script>

<style scoped>
.rp-root {
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
    --th-warn:      oklch(0.78 0.15 80);   /* warning / pending — amber */
    --th-info:      oklch(0.70 0.12 235);  /* info / walk speed — cool blue */
    --th-t:         oklch(0.76 0.13 75);   /* T side — warm gold */
    --th-ct:        oklch(0.66 0.13 250);  /* CT side — cool, in the blue-violet family */
}

/* Sidebar width / collapse is owned by ContextPanel (--cp-width / rail). On
   narrow viewports the user can collapse it to the rail to free the canvas. */

/* Respect reduced-motion: neutralize non-essential transitions and hover scale.
   Loading spinners (.animate-spin) keep a minimal spin so they still read as busy. */
@media (prefers-reduced-motion: reduce) {
    .rp-root :where(.transition-all, .transition-colors, .transition-transform, .transition-opacity) {
        transition: none !important;
    }
    .rp-root :where(.hover\:scale-110):hover {
        transform: none !important;
    }
    .rp-root .animate-spin {
        animation-duration: 2s !important;
    }
}
</style>