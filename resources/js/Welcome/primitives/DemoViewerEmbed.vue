<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

// Embeds the external CSDemoViewer widget (api.csstrats.dk) read-only.
// The viewer script is only fetched once the embed scrolls into view, so
// the marketing pages stay light for visitors who never reach it.

declare global {
    interface Window {
        CSDemoViewer?: {
            init: (opts: Record<string, unknown>) => { destroy?: () => void }
        }
        CSDV_KEY?: string
        CSDV_DEMO_ID?: number
        CSDV_API?: string
        CSDV_SRC?: string
    }
}

const props = withDefaults(defineProps<{ height?: number }>(), { height: 560 })
const emit = defineEmits<{ (e: 'ready'): void; (e: 'unavailable'): void }>()

const host = ref<HTMLDivElement | null>(null)
const state = ref<'idle' | 'loading' | 'ready' | 'unavailable'>('idle')
let viewer: { destroy?: () => void } | null = null
let observer: IntersectionObserver | null = null

const loadScript = (src: string): Promise<void> =>
    new Promise((resolve, reject) => {
        if (window.CSDemoViewer) return resolve()
        const s = document.createElement('script')
        s.src = src
        s.async = true
        s.onload = () => resolve()
        s.onerror = () => reject(new Error(`Failed to load ${src}`))
        document.head.appendChild(s)
    })

const boot = async () => {
    if (state.value !== 'idle') return
    state.value = 'loading'

    const key = window.CSDV_KEY
    const api = window.CSDV_API || 'https://api.csstrats.dk'
    const src = window.CSDV_SRC || 'https://demoviewer.b-cdn.net/viewer.js'
    if (!key) {
        state.value = 'unavailable'
        emit('unavailable')
        return
    }

    try {
        await loadScript(src)

        let demoId = window.CSDV_DEMO_ID || 0
        if (!demoId) {
            const res = await fetch(`${api}/v1/demos?limit=1`, {
                headers: { Authorization: `Bearer ${key}` },
            })
            const json = await res.json()
            demoId = json.items?.[0]?.id ?? 0
        }
        if (!demoId || !host.value || !window.CSDemoViewer) {
            state.value = 'unavailable'
            emit('unavailable')
            return
        }

        viewer = window.CSDemoViewer.init({
            el: host.value,
            apiBase: api,
            demoId,
            clientKey: key,
            skipFreezetime: true,
            autoplay: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            autoAdvance: true,
            theme: {
                background: 'oklch(20% 0.006 53)',
                controlBg: 'oklch(15% 0.005 53 / 0.85)',
                controlColor: 'oklch(95% 0.005 53)',
                accent: 'oklch(73% 0.17 53)',
            },
        })
        state.value = 'ready'
        emit('ready')
    } catch {
        state.value = 'unavailable'
        emit('unavailable')
    }
}

onMounted(() => {
    if (!host.value) return
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                observer?.disconnect()
                observer = null
                boot()
            }
        },
        { rootMargin: '200px' },
    )
    observer.observe(host.value)
})

onBeforeUnmount(() => {
    observer?.disconnect()
    viewer?.destroy?.()
})
</script>

<template>
    <div class="artifact-dark overflow-hidden rounded-[var(--radius-md)] border border-ash bg-slate">

        <div class="relative w-full" :style="{ height: `${props.height}px` }">
            <div ref="host" class="h-full w-full"></div>

            <div
                v-if="state !== 'ready'"
                class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate"
            >
                <template v-if="state === 'unavailable'">
                    <p class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone">
                        Sample replay is taking a break
                    </p>
                    <p class="max-w-[40ch] text-center text-sm text-bone">
                        The full demoviewer is waiting inside. Every round of your scrims, parsed and scrubbable.
                    </p>
                </template>
                <template v-else>
                    <p class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone">
                        <span aria-hidden="true">●</span> Loading replay
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>
