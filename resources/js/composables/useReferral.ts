/**
 * useReferral.ts
 *
 * Captures partner referrals from utm_source and persists them in
 * localStorage so later visits (register, return to pricing) can still
 * apply partner offers. localStorage rather than sessionStorage on
 * purpose: an HLTV visitor who registers and comes back days later
 * should still get the HLTV checkout offer.
 *
 * Persistence only ever happens from a utm_source, and it expires: a
 * visitor who was never referred should never see partner copy on the
 * regular pages.
 *
 * Usage:
 *   captureReferral()              // on landing, before children mount
 *   getReferral() === 'hltv'       // anywhere in the welcome bundle
 */

const STORAGE_KEY = 'csstrats:referral'
const PARTNER_SOURCES = ['hltv']
const TTL_MS = 30 * 24 * 60 * 60 * 1000 // 30 days

export function setReferral(source: string): void {
    if (typeof window === 'undefined') return
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ source, at: Date.now() }))
    } catch {}
}

export function captureReferral(): void {
    if (typeof window === 'undefined') return
    const source = new URLSearchParams(window.location.search)
        .get('utm_source')
        ?.toLowerCase()
    if (!source || !PARTNER_SOURCES.includes(source)) return
    setReferral(source)
}

export function getReferral(): string | null {
    if (typeof window === 'undefined') return null
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY)
        if (!raw) return null
        const parsed = JSON.parse(raw)
        if (
            typeof parsed?.source !== 'string' ||
            typeof parsed?.at !== 'number' ||
            Date.now() - parsed.at > TTL_MS
        ) {
            window.localStorage.removeItem(STORAGE_KEY)
            return null
        }
        return parsed.source
    } catch {
        // Legacy plain-string value (or corrupted entry): drop it so a
        // browser that merely visited /hltv once is not branded forever.
        try {
            window.localStorage.removeItem(STORAGE_KEY)
        } catch {}
        return null
    }
}
