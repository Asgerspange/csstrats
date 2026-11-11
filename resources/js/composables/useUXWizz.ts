/**
 * useUXWizz.ts
 *
 * Drop this in resources/js/composables/useUXWizz.ts
 *
 * Provides a thin wrapper around UXWizz's UST.addTag() API
 * to track funnel events from Vue components.
 *
 * Usage:
 *   import { useUXWizz } from '@/composables/useUXWizz'
 *   const { track } = useUXWizz()
 *   track('team:created')
 *
 * The events below map directly to UXWizz funnel steps you
 * configure in the UXWizz dashboard under "Funnels".
 *
 * Recommended funnel in UXWizz:
 *   Step 1: page:/tactics          (landed on tactics page)
 *   Step 2: event:onboarding:done  (completed or skipped onboarding)
 *   Step 3: event:team:created     (created a team)
 *   Step 4: event:tactic:added     (added tactic to stratbook)
 *   Step 5: event:stratbook:export (exported a stratbook PDF)
 */

type UXWizzEvent =
    | 'onboarding:started'
    | 'onboarding:done'
    | 'onboarding:skipped'
    | 'team:created'
    | 'team:member_added'
    | 'tactic:added'
    | 'tactic:viewed'
    | 'stratbook:created'
    | 'stratbook:export'
    | 'demo:uploaded'
    | 'demo:viewed'
    | 'board:opened'
    | 'trial:seen_paywall'
    | string  // allow arbitrary tags

declare global {
    interface Window {
        UST?: {
            addTag: (tag: string) => void
            addEvent: (tag: string) => void
        }
    }
}

export function useUXWizz() {
    function track(event: UXWizzEvent) {
        try {
            if (window.UST?.addTag) {
                window.UST.addTag(`event:${event}`)
            }
        } catch {
            // Never let tracking break the app
        }
    }

    function trackPage(page: string) {
        try {
            if (window.UST?.addTag) {
                window.UST.addTag(`page:${page}`)
            }
        } catch {}
    }

    return { track, trackPage }
}