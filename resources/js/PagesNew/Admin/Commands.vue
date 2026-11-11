<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import axios from 'axios'
import { useStore } from '@/store'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const store = useStore()
const toast = useToast()
const { user } = storeToRefs(store)

interface AdminCommand {
    name: string
    description: string
    supports_dry_run: boolean
    execute_option: string | null
}

const commands = ref<AdminCommand[]>([])
const loading = ref(true)
const running = ref<string | null>(null)
const results = ref<{ command: string; exit_code: number; output: string; success: boolean } | null>(null)

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/admin/commands')
        commands.value = data.commands
    } catch {
        toast.error('Failed to load commands')
    } finally {
        loading.value = false
    }
}

async function runCommand(cmd: AdminCommand, mode: 'dry-run' | 'execute') {
    running.value = cmd.name + (mode === 'dry-run' ? ' (dry run)' : ' (execute)')
    results.value = null
    try {
        const { data } = await axios.post('/api/admin/commands/run', { command: cmd.name, mode })
        results.value = data
        if (data.success) {
            toast.success('Command completed')
        } else {
            toast.error('Command failed', `Exit code: ${data.exit_code}`)
        }
    } catch (e: any) {
        toast.error('Command failed', e?.response?.data?.message ?? 'Unknown error')
    } finally {
        running.value = null
    }
}

onMounted(() => {
    if (user.value && !user.value.is_admin) { router.push('/tactics'); return }
    load()
})
</script>

<template>
    <div class="h-full overflow-y-auto p-6 text-white">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-xl font-bold">System Commands</h1>
                <p class="text-sm text-white/40">Run maintenance and migration commands. Use dry-run first to preview changes.</p>
            </div>

            <div v-if="loading" class="text-white/40 py-8 text-center">Loading commands...</div>

            <div v-else class="space-y-3">
                <div v-for="cmd in commands" :key="cmd.name"
                    class="rounded-lg border border-white/10 bg-white/[0.02] p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="font-mono text-sm font-semibold text-primary">{{ cmd.name }}</h3>
                            <p class="text-sm text-white/50 mt-0.5">{{ cmd.description }}</p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button
                                v-if="cmd.supports_dry_run"
                                @click="runCommand(cmd, 'dry-run')"
                                :disabled="running !== null"
                                class="px-3 py-1.5 text-xs font-semibold rounded-md border border-white/10 bg-white/[0.04] hover:bg-white/[0.08] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >
                                Dry run
                            </button>
                            <button
                                @click="runCommand(cmd, 'execute')"
                                :disabled="running !== null"
                                class="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-black disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >
                                Execute
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Running -->
            <div v-if="running" class="mt-6 p-4 rounded-lg border border-white/10 bg-black/40">
                <div class="flex items-center gap-2 text-sm text-white/60">
                    <span class="material-symbols animate-spin text-base">progress_activity</span>
                    Running: <span class="font-mono text-primary">{{ running }}</span>
                </div>
            </div>

            <!-- Output -->
            <div v-if="results" class="mt-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm font-semibold" :class="results.success ? 'text-green-400' : 'text-red-400'">
                        {{ results.success ? 'Success' : 'Failed' }} (exit {{ results.exit_code }})
                    </span>
                    <span class="font-mono text-xs text-white/40">{{ results.command }}</span>
                </div>
                <pre class="bg-black/60 border border-white/10 rounded-lg p-4 text-xs text-white/70 font-mono whitespace-pre-wrap max-h-96 overflow-y-auto">{{ results.output }}</pre>
            </div>
        </div>
    </div>
</template>
