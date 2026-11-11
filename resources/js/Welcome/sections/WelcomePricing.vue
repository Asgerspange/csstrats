<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useLandingData } from '../useLandingData'
import { initializePaddle, type Paddle } from '@paddle/paddle-js'
import Container from '../primitives/Container.vue'
import Eyebrow from '../primitives/Eyebrow.vue'
import Button from '../primitives/Button.vue'
import { getReferral } from '@/composables/useReferral'

interface Product {
    name: string
    description: string
    monthly_price: number
    price: number
    priceId: string
    features: string[]
}

interface BillingInfo {
    customer_id: string
    subscription_id: string | null
    had_trial: boolean
    is_on_trial: boolean
}

type Cadence = 'monthly' | 'semi' | 'yearly'
type Tier = 'Starter' | 'Team' | 'Pro'

const { products, user } = useLandingData()

// HLTV referrals get HLTV30 (30% off Monthly for 3 months) auto-applied at
// checkout, and the Monthly tab opens by default so the offer is visible
// immediately. Applies on /hltv and on utm-referred visits.
const HLTV_PROMO_CODE = 'HLTV30'
const isHltv =
    (typeof window !== 'undefined' && window.location.pathname.startsWith('/hltv')) ||
    getReferral() === 'hltv'

// ── Tier + cadence matrix ──────────────────────────────────────────────────
// The controller returns a flat list of 9 products (3 tiers × 3 cadences).
// Group them so each tier card renders the product for the active cadence.
const tierOrder: Tier[] = ['Starter', 'Team', 'Pro']
const cadenceMeta: { key: Cadence; label: string; save: string | null; note: string }[] = [
    { key: 'monthly', label: 'Monthly',       save: null,          note: 'No commitment' },
    { key: 'semi',    label: 'Every 6 months', save: 'Save 20%',    note: '' },
    { key: 'yearly',  label: 'Yearly',         save: 'Save 36.6%',  note: '' },
]

const parseName = (name: string): { tier: Tier; cadence: Cadence } => {
    const [tierPart, cadencePart] = name.split(' - ')
    const tier = tierPart.trim() as Tier
    const c = (cadencePart ?? '').trim().toLowerCase()
    const cadence: Cadence = c === 'monthly' ? 'monthly' : c === 'yearly' ? 'yearly' : 'semi'
    return { tier, cadence }
}

interface TierGroup {
    tier: Tier
    plans: Partial<Record<Cadence, Product>>
}

const tiers = computed<TierGroup[]>(() => {
    const map = new Map<Tier, Partial<Record<Cadence, Product>>>()
    for (const p of products.value) {
        const { tier, cadence } = parseName(p.name)
        if (!map.has(tier)) map.set(tier, {})
        map.get(tier)![cadence] = p
    }
    return tierOrder.map((tier) => ({ tier, plans: map.get(tier) ?? {} }))
})

// Tier-level copy. The controller's per-product description is the cadence
// savings line, not a tier pitch, so these live here.
const tierCopy: Record<Tier, string> = {
    Starter: 'Tactic library and drawing board for a single team.',
    Team: 'The full workbench: tactics, demos, and the round planner.',
    Pro: 'Everything in Team, plus AI antistrat and priority support.',
}

const activeCadence = ref<Cadence>(isHltv ? 'monthly' : 'yearly')

const planFor = (tier: TierGroup): Product | undefined => tier.plans[activeCadence.value]

const choose = (tier: TierGroup) => {
    const p = planFor(tier)
    if (p) openCheckout(p)
}

// ── Geolocated pricing via Paddle ──────────────────────────────────────────
// Paddle.PricePreview IP-geolocates the visitor and returns the correct price
// and currency for their country (where per-country overrides exist). The
// controller ships priceIds only, so the display is empty until it resolves.
interface LocalPrice { currency: string; total: number; formattedTotal: string }
const localized = ref<Record<string, LocalPrice>>({})

const fmt = (amount: number, currency: string) => {
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount)
    } catch {
        return `${amount.toFixed(2)} ${currency}`
    }
}

const local = (p: Product | undefined): LocalPrice | undefined =>
    p ? localized.value[p.priceId] : undefined

const monthsFor = (p: Product) => {
    const part = (p.name.split('-')[1] ?? '').trim().toLowerCase()
    if (part === 'monthly') return 1
    if (part === 'yearly') return 12
    return 6
}

const PLACEHOLDER = '···'

const displayMonthly = (p: Product | undefined) => {
    if (!p) return PLACEHOLDER
    const l = local(p)
    if (l) return fmt(l.total / monthsFor(p), l.currency)
    return PLACEHOLDER
}
const billed = (p: Product | undefined) => {
    if (!p) return PLACEHOLDER
    const l = local(p)
    const total = l ? l.formattedTotal : PLACEHOLDER
    const part = (p.name.split('-')[1] ?? '').trim().toLowerCase()
    if (part === 'monthly') return `Billed ${total} every month`
    if (part === 'yearly') return `Billed ${total} once a year`
    return `Billed ${total} every 6 months`
}
const perPlayer = (p: Product | undefined) => {
    if (!p) return PLACEHOLDER
    const l = local(p)
    if (l) return fmt(l.total / monthsFor(p) / 8, l.currency)
    return PLACEHOLDER
}

const loadPrices = async () => {
    if (!paddle.value || !products.value.length) return
    try {
        const preview = await paddle.value.PricePreview({
            items: products.value.map((p) => ({ priceId: p.priceId, quantity: 1 })),
        })
        const currency = preview.data.currencyCode
        const map: Record<string, LocalPrice> = {}
        for (const li of preview.data.details.lineItems) {
            map[li.price.id] = {
                currency,
                total: Number(li.totals.total) / 100, // minor units -> major
                formattedTotal: li.formattedTotals.total,
            }
        }
        localized.value = map
    } catch {
        // Geolocation/preview unavailable: keep the placeholder display.
    }
}

const promoCode = ref<string>('')
const promoApplied = computed(() => promoCode.value.trim().length > 0)

const paddle = ref<Paddle>()
const status = ref<'idle' | 'loading' | 'subscribed' | 'error'>('idle')
const statusFor = ref<string | null>(null)
const errorMsg = ref<string>('')

const csrf = (): string =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''

onMounted(async () => {
    paddle.value = await initializePaddle({
        environment: window.PADDLE_SANDBOX ? 'sandbox' : 'production',
        token: window.PADDLE_TOKEN,
    })
    loadPrices()
})

// Products load asynchronously from welcome-data; (re)preview once they're in.
watch(products, () => loadPrices())

const fetchBilling = async (): Promise<BillingInfo> => {
    const res = await fetch('/billing/subscription', {
        credentials: 'include',
        headers: { 'X-CSRF-TOKEN': csrf() },
    })
    return res.json()
}

const openCheckout = async (product: Product) => {
    status.value = 'loading'
    statusFor.value = product.priceId
    errorMsg.value = ''

    try {
        const billing = await fetchBilling()
        if (billing?.subscription_id) {
            status.value = 'subscribed'
            return
        }
        const res = await fetch('/billing/customer', {
            method: 'POST',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': csrf() },
        })
        const { customer_id } = await res.json()

        const checkoutConfig: Parameters<NonNullable<Paddle['Checkout']>['open']>[0] = {
            items: [{ priceId: product.priceId, quantity: 1 }],
            customer: { id: customer_id },
            settings: {
                successUrl: window.location.origin,
                theme: 'dark',
                displayMode: 'overlay',
            },
        }
        const code = promoCode.value.trim()
        if (code.length > 0) {
            checkoutConfig.discountCode = code
        } else if (isHltv && product.name === 'Team - Monthly') {
            checkoutConfig.discountCode = HLTV_PROMO_CODE
        }

        paddle.value?.Checkout.open(checkoutConfig)
        status.value = 'idle'
    } catch (err) {
        status.value = 'error'
        errorMsg.value = err instanceof Error ? err.message : 'Checkout failed. Try again.'
    }
}
</script>

<template>
    <section id="pricing" class="relative py-16 sm:py-20 lg:py-24" data-reveal-group>
        <Container>
            <header class="mb-12 flex flex-col gap-6 lg:mb-16">
                <Eyebrow index="05" label="Pricing" />
                <h2
                    class="max-w-[28ch] font-display text-[clamp(2rem,4.5vw,3.25rem)] font-medium leading-[1.08] tracking-[-0.02em] text-chalk"
                    data-reveal
                >
                    Three plans. One workbench. Cancel any time.
                </h2>
                <p class="max-w-[64ch] text-bone" data-reveal>
                    Every plan covers a roster of up to eight. Starter is the library and board, Team is the full workbench, Pro adds AI antistrat and priority support.
                </p>
            </header>
            <!-- Billing cadence. Monthly, semi-annual, or yearly. Switching
                 re-renders the price on every tier card below. -->
            <div class="mb-4 flex justify-center font-mono" data-reveal>
                <div
                    class="inline-flex rounded-full bg-[#e4e4e6] p-1"
                    role="tablist"
                    aria-label="Billing cadence"
                >
                    <button
                        v-for="c in cadenceMeta"
                        :key="c.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeCadence === c.key"
                        class="flex flex-col items-center gap-1 rounded-full px-5 py-2.5 transition-colors duration-[180ms] ease-[var(--ease-quart)] sm:px-7"
                        :class="activeCadence === c.key
                            ? 'bg-white text-black'
                            : 'text-bone hover:bg-smoke/60 hover:text-chalk'"
                        @click="activeCadence = c.key"
                    >
                        <span class="font-inter text-xs font-medium uppercase">
                            {{ c.label }}
                        </span>
                        <!-- <span
                            v-if="c.save"
                            class="font-inter text-[10px] font-medium uppercase tracking-[0.08em]"
                            :class="activeCadence === c.key ? 'text-black/75' : 'text-saffron'"
                        >
                            {{ c.save }}
                        </span>
                        <span
                            v-else
                            class="font-inter text-[10px] font-medium uppercase tracking-[0.08em]"
                            :class="activeCadence === c.key ? 'text-black/75' : 'text-black/70'"
                        >
                            {{ c.note }}
                        </span> -->
                    </button>
                </div>
            </div>

            <div class="grid gap-4 overflow-hidden rounded-[var(--radius-md)] lg:grid-cols-3 max-w-5xl mx-auto" data-reveal>
                <article
                    v-for="tier in tiers"
                    :key="tier.tier"
                    class="relative flex flex-col gap-7 bg-white rounded-2xl p-8 transition-colors  lg:p-10"
                >
                    <header class="flex flex-col gap-3">
                        <div class="min-h-[1.2em]">
                            <p
                                v-if="tier.tier === 'Team'"
                                class="font-mono text-[11px] font-medium uppercase tracking-[0.1em] text-saffron"
                            >
                                <span aria-hidden="true">●</span>
                                <template v-if="isHltv && activeCadence === 'monthly'"> HLTV offer · 30% off for 3 months</template>
                                <template v-else> Most popular</template>
                            </p>
                        </div>
                        <h3 class="font-display text-3xl font-medium tracking-[-0.01em] text-chalk">
                            {{ tier.tier }}
                        </h3>
                        <div class="flex items-baseline gap-2">
                            <span class="font-display text-5xl font-medium tracking-[-0.02em] text-chalk">
                                {{ displayMonthly(planFor(tier)) }}
                            </span>
                            <span class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-bone">
                                / month
                            </span>
                        </div>

                        <p class="font-inter text-[11px] font-medium uppercase tracking-[0.08em] text-bone">
                            {{ billed(planFor(tier)) }}
                        </p>
                        <p class="-mt-4 font-inter text-[11px] font-medium uppercase text-bone">
                            ≈ {{ perPlayer(planFor(tier)) }} / player / month with 8 on the roster
                        </p>

                        <div class="mt-auto">
                            <template v-if="!user">
                                <Button as="a" href="/register" variant="primary" size="md" class="w-full">
                                    Register to choose {{ tier.tier }}
                                </Button>
                            </template>
                            <template v-else>
                                <Button
                                    @click="choose(tier)"
                                    :variant="tier.tier"
                                    size="md"
                                    class="w-full"
                                    :disabled="status === 'loading' && statusFor === planFor(tier)?.priceId"
                                >
                                    <template v-if="status === 'loading' && statusFor === planFor(tier)?.priceId">
                                        Opening checkout…
                                    </template>
                                    <template v-else>
                                        Choose {{ tier.tier }}
                                    </template>
                                </Button>
                            </template>
                        </div>
                    </header>

                    <ul class="flex flex-col gap-3">
                        <li
                            v-for="feature in (planFor(tier)?.features ?? [])"
                            :key="feature"
                            class="flex items-baseline gap-3 text-sm text-black"
                        >
                            <span class="text-[11px] uppercase tracking-[0.08em] text-black" aria-hidden="true">✓</span>
                            <span>{{ feature }}</span>
                        </li>
                    </ul>
                </article>
            </div>

            <!-- Promo code + the auto-charge truth -->
            <div
                class="mt-4 overflow-hidden rounded-2xl border border-t-0 border-ash bg-slate max-w-5xl mx-auto"
                data-reveal
            >
                <div class="flex flex-col gap-6 p-8 lg:flex-row lg:items-end lg:justify-between lg:p-10">
                    <div class="flex flex-col gap-3 lg:max-w-[36ch]">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-saffron">
                            Promo code
                        </p>
                        <p class="text-sm text-bone">
                            Won a code at a tournament? Drop it in here. <span class="text-chalk">After the promo period, the plan auto-charges at the regular rate every billing cycle until you cancel.</span>
                        </p>
                        <p
                            v-if="isHltv && !promoApplied"
                            class="font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-saffron"
                        >
                            <span aria-hidden="true">●</span> HLTV30 applies to Team Monthly at checkout · 30% off for 3 months
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 lg:flex-row lg:items-stretch lg:gap-3 rounded-2xl">
                        <label class="sr-only" for="promo-code">Promotional code</label>
                        <input
                            id="promo-code"
                            v-model="promoCode"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Promo code"
                            class="h-12 w-full min-w-[18ch] rounded-2xl border border-ash bg-concrete px-4 font-mono text-sm text-chalk placeholder:text-ash/80 focus:border-saffron focus:outline-none lg:w-[28ch]"
                        />
                        <p
                            v-if="promoApplied"
                            class="self-center font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-saffron"
                        >
                            <span aria-hidden="true">●</span> Will apply at checkout
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="status === 'subscribed'"
                role="status"
                class="mt-6 flex items-baseline gap-3 rounded-[var(--radius-sm)] border border-ash bg-slate p-5"
                data-reveal
            >
                <span class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-saffron" aria-hidden="true">●</span>
                <p class="text-sm text-bone">
                    You already have an active subscription. Manage it in
                    <a href="/profile" class="text-chalk underline-offset-2 hover:underline">your profile</a>.
                </p>
            </div>

            <div
                v-if="status === 'error'"
                role="alert"
                class="mt-6 flex items-baseline gap-3 rounded-[var(--radius-sm)] border border-ash bg-slate p-5"
                data-reveal
            >
                <span class="font-mono text-xs font-medium uppercase tracking-[0.08em] text-round-lost" aria-hidden="true">●</span>
                <p class="text-sm text-bone">
                    {{ errorMsg || 'Checkout did not open. Try again, or contact us on Discord.' }}
                </p>
            </div>

            <p class="mt-8 font-mono text-[11px] font-medium uppercase tracking-[0.08em] text-bone max-w-5xl mx-auto" data-reveal>
                VAT added where applicable · Powered by Paddle · Cancel any time from your profile
            </p>
        </Container>
    </section>
</template>
