import { onMounted, onBeforeUnmount } from 'vue'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

gsap.registerPlugin(ScrollTrigger)

interface RevealOptions {
    selector?: string
    stagger?: number
    duration?: number
    y?: number
    start?: string
    once?: boolean
}

interface RevealGroup {
    groupEl: HTMLElement
    els: HTMLElement[]
    registeredAt: number
    tween: gsap.core.Tween | null
    done: boolean
}

export function useScrollReveal(opts: RevealOptions = {}) {
    const {
        selector = '[data-reveal]',
        stagger = 0.08,
        duration = 0.9,
        y = 24,
        start = 'top 85%',
        once = true,
    } = opts

    let triggers: ScrollTrigger[] = []
    let observer: MutationObserver | null = null
    let sweeper: number | null = null
    const groups: RevealGroup[] = []
    // Sections render async (TanStack Query), so [data-reveal] nodes keep
    // appearing after mount. Track what we've registered and watch the DOM
    // for the rest; without this, late nodes stay at the CSS opacity 0.
    const seen = new WeakSet<HTMLElement>()

    const collect = (root: ParentNode): HTMLElement[] => {
        const els: HTMLElement[] = []
        if (root instanceof HTMLElement && root.matches(selector)) els.push(root)
        root.querySelectorAll<HTMLElement>(selector).forEach((el) => els.push(el))
        return els.filter((el) => {
            if (seen.has(el)) return false
            seen.add(el)
            return true
        })
    }

    const finish = (group: RevealGroup) => {
        if (group.tween) {
            group.tween.progress(1)
        } else {
            gsap.set(group.els, { clearProps: 'opacity,transform' })
        }
        group.els.forEach((el) => el.classList.add('is-revealed'))
        group.done = true
    }

    const register = (els: HTMLElement[], prefersReduced: boolean) => {
        if (!els.length) return

        if (prefersReduced) {
            els.forEach((el) => el.classList.add('is-revealed'))
            return
        }

        const byGroup = new Map<HTMLElement, HTMLElement[]>()
        els.forEach((el) => {
            const groupEl = (el.closest<HTMLElement>('[data-reveal-group]') ?? el.parentElement) as HTMLElement
            if (!byGroup.has(groupEl)) byGroup.set(groupEl, [])
            byGroup.get(groupEl)!.push(el)
        })

        byGroup.forEach((groupEls, groupEl) => {
            gsap.set(groupEls, { opacity: 0, y })

            const group: RevealGroup = {
                groupEl,
                els: groupEls,
                registeredAt: Date.now(),
                tween: null,
                done: false,
            }
            groups.push(group)

            const trigger = ScrollTrigger.create({
                trigger: groupEl,
                start,
                once,
                onEnter: () => {
                    group.tween = gsap.to(groupEls, {
                        opacity: 1,
                        y: 0,
                        duration,
                        stagger,
                        ease: 'expo.out',
                        onStart: () => {
                            groupEls.forEach((el) => el.classList.add('is-revealed'))
                        },
                        onComplete: () => {
                            group.done = true
                        },
                    })
                },
            })
            triggers.push(trigger)
        })
    }

    onMounted(() => {
        if (typeof window === 'undefined') return

        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

        register(collect(document), prefersReduced)

        observer = new MutationObserver((mutations) => {
            const added: HTMLElement[] = []
            mutations.forEach((m) => {
                m.addedNodes.forEach((node) => {
                    if (node instanceof HTMLElement) added.push(...collect(node))
                })
            })
            register(added, prefersReduced)
        })
        observer.observe(document.body, { childList: true, subtree: true })

        // Guarantee pass. ScrollTrigger and gsap both ride rAF, which
        // background or throttled tabs may starve indefinitely; without
        // this, in-viewport content can sit at opacity 0 for as long as
        // the tab is hidden. Every second, any group that has been
        // registered for a while, sits in the viewport, and is still not
        // fully visible gets snapped to its end state. The interval keeps
        // running because async sections register new groups over time.
        sweeper = window.setInterval(() => {
            groups.forEach((group) => {
                if (group.done) return
                if (Date.now() - group.registeredAt < 2000) return
                const rect = group.groupEl.getBoundingClientRect()
                if (rect.top >= window.innerHeight || rect.bottom <= 0) return
                const settled = group.els.every(
                    (el) => parseFloat(getComputedStyle(el).opacity) >= 0.99,
                )
                if (settled) {
                    group.done = true
                    return
                }
                finish(group)
            })
        }, 1000)
    })

    onBeforeUnmount(() => {
        observer?.disconnect()
        observer = null
        if (sweeper !== null) window.clearInterval(sweeper)
        sweeper = null
        triggers.forEach((t) => t.kill())
        triggers = []
        groups.length = 0
    })
}
