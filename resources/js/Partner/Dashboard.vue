<script setup>
import { ref, computed, onMounted } from 'vue';

const loading = ref(true);
const error = ref('');
const data = ref(null);
const copied = ref('');
const displayCurrency = ref('EUR');

const origin = typeof window !== 'undefined' ? window.location.origin : '';
const csrf = typeof document !== 'undefined'
    ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    : '';

const COMMON_CURRENCIES = ['EUR', 'USD', 'GBP', 'DKK', 'SEK', 'NOK', 'PLN'];

// EUR-base rates from the API: 1 EUR = rates[X] units of X.
const rates = computed(() => data.value?.fx?.rates ?? null);
const byCurrency = computed(() => data.value?.earnings?.byCurrency ?? []);

// Currencies offered in the selector: those present in earnings + common ones,
// limited to currencies we actually have a rate for (EUR always allowed).
const currencyOptions = computed(() => {
    const present = byCurrency.value.map(b => b.currency);
    const set = new Set(['EUR', ...present, ...COMMON_CURRENCIES]);
    const r = rates.value;
    return [...set].filter(c => c === 'EUR' || (r && r[c] != null));
});

function convert(amount, from, to) {
    if (from === to) return amount;
    const r = rates.value;
    if (!r || r[from] == null || r[to] == null) return null;
    return (amount / r[from]) * r[to];
}

// Grand total of all earnings converted to the selected display currency.
const convertedEarned = computed(() => {
    let sum = 0;
    for (const b of byCurrency.value) {
        const c = convert(b.earned, b.currency, displayCurrency.value);
        if (c == null) return null; // missing rate -> can't form a single total
        sum += c;
    }
    return sum;
});

const mixedCurrencies = computed(() => byCurrency.value.length > 1);

function money(value, cur) {
    const currency = cur || displayCurrency.value;
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(value ?? 0);
    } catch {
        return `${(value ?? 0).toFixed(2)} ${currency}`;
    }
}

function num(value) {
    return new Intl.NumberFormat().format(value ?? 0);
}

function date(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

const commissionLabel = computed(() => {
    const r = data.value?.referrer;
    if (!r) return '';
    if (r.commission_type === 'first') return `${r.commission_rate}% of first order`;
    const base = `${r.commission_rate}% of every order`;
    if (r.recurring_months) return `${base}, first ${r.recurring_months} months`;
    return base;
});

const cards = computed(() => {
    const s = data.value?.summary ?? {};
    const earned = convertedEarned.value;
    return [
        { label: 'Visits', value: num(s.visits), sub: `${num(s.unique_visitors)} unique` },
        { label: 'Signups', value: num(s.signups), sub: 'accounts created' },
        { label: 'Subscriptions', value: num(s.paying_customers), sub: `${num(s.orders)} orders` },
        {
            label: 'Earned',
            value: earned == null ? '—' : money(earned),
            sub: earned == null ? 'rates unavailable' : (mixedCurrencies.value ? `converted to ${displayCurrency.value}` : 'total commission'),
            accent: true,
        },
    ];
});

// ── Visits sparkline geometry ──────────────────────────────────────────────
const chart = computed(() => {
    const series = data.value?.visitsByDay ?? [];
    const w = 720, h = 140, pad = 4;
    const max = Math.max(1, ...series.map(d => d.count));
    const bw = series.length ? (w - pad * 2) / series.length : 0;
    const bars = series.map((d, i) => {
        const bh = (d.count / max) * (h - pad * 2);
        return {
            x: pad + i * bw,
            y: h - pad - bh,
            w: Math.max(1, bw - 2),
            h: Math.max(d.count > 0 ? 2 : 0, bh),
            day: d.day,
            count: d.count,
        };
    });
    return { w, h, max, bars };
});

function refLink(code) {
    return `${origin}/r/${code}`;
}
function utmLink(code) {
    return `${origin}/?utm_source=${encodeURIComponent(code)}`;
}

async function copy(text, key) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => { if (copied.value === key) copied.value = ''; }, 1500);
    } catch { /* clipboard unavailable */ }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await fetch('/partners/data', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.status === 401 || res.status === 419 || res.redirected) {
            window.location.href = '/partners/login';
            return;
        }
        if (!res.ok) throw new Error(`Request failed (${res.status})`);
        data.value = await res.json();
    } catch (e) {
        error.value = e?.message || 'Could not load your dashboard.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="min-h-screen">
        <!-- Header -->
        <header class="border-b border-[var(--p-border)]">
            <div class="mx-auto max-w-6xl px-6 py-5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img src="/images/logo.webp" alt="CsStrats" class="h-7 w-7 rounded" />
                    <div>
                        <p class="text-sm font-bold leading-tight">Partner dashboard</p>
                        <p class="text-xs text-[var(--p-muted)] leading-tight">{{ data?.referrer?.name }}</p>
                    </div>
                </div>
                <form method="POST" action="/partners/logout">
                    <input type="hidden" name="_token" :value="csrf" />
                    <button type="submit"
                        class="text-xs font-semibold text-[var(--p-muted)] hover:text-[var(--p-text)] border border-[var(--p-border)] rounded-md px-3 py-1.5 transition-colors">
                        Log out
                    </button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-8">
            <div v-if="loading" class="text-[var(--p-muted)] text-sm py-20 text-center">Loading…</div>

            <div v-else-if="error"
                class="rounded-lg border border-red-500/30 bg-red-500/10 text-red-200 text-sm px-4 py-3">
                {{ error }}
            </div>

            <template v-else-if="data">
                <div class="flex items-end justify-between gap-4 mb-6 flex-wrap">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-[var(--p-muted)] font-bold mb-1">Commission</p>
                        <p class="text-sm">{{ commissionLabel }}</p>
                    </div>
                    <label class="text-xs text-[var(--p-muted)] flex items-center gap-2">
                        Display currency
                        <select v-model="displayCurrency"
                            class="bg-[var(--p-surface-2)] border border-[var(--p-border)] rounded-md px-2 py-1.5 text-[var(--p-text)] text-sm">
                            <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </label>
                </div>

                <!-- Summary cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
                    <div v-for="c in cards" :key="c.label"
                        class="rounded-lg border border-[var(--p-border)] bg-[var(--p-surface)] px-4 py-4">
                        <p class="text-xs uppercase tracking-wider text-[var(--p-muted)] font-bold">{{ c.label }}</p>
                        <p class="text-2xl font-extrabold mt-2 tabular"
                            :class="c.accent ? 'text-[var(--color-primary)]' : ''">{{ c.value }}</p>
                        <p class="text-xs text-[var(--p-muted)] mt-1">{{ c.sub }}</p>
                    </div>
                </div>

                <!-- Earnings by currency -->
                <section v-if="byCurrency.length" class="mb-8">
                    <p class="text-xs uppercase tracking-wider text-[var(--p-muted)] font-bold mb-3">Earnings by currency</p>
                    <div class="rounded-lg border border-[var(--p-border)] overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-[var(--p-surface-2)] text-[var(--p-muted)] text-xs">
                                <tr>
                                    <th class="text-left font-semibold px-3 py-2">Currency</th>
                                    <th class="text-right font-semibold px-3 py-2">Orders</th>
                                    <th class="text-right font-semibold px-3 py-2">Gross</th>
                                    <th class="text-right font-semibold px-3 py-2">Earned</th>
                                    <th class="text-right font-semibold px-3 py-2">≈ {{ displayCurrency }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in byCurrency" :key="b.currency" class="border-t border-[var(--p-border)]">
                                    <td class="px-3 py-2 font-semibold">{{ b.currency }}</td>
                                    <td class="px-3 py-2 text-right tabular">{{ b.orders }}</td>
                                    <td class="px-3 py-2 text-right tabular">{{ money(b.gross, b.currency) }}</td>
                                    <td class="px-3 py-2 text-right tabular font-semibold">{{ money(b.earned, b.currency) }}</td>
                                    <td class="px-3 py-2 text-right tabular text-[var(--p-muted)]">
                                        {{ convert(b.earned, b.currency, displayCurrency) == null ? '—' : money(convert(b.earned, b.currency, displayCurrency)) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="convertedEarned != null">
                                <tr class="border-t border-[var(--p-border)] bg-[var(--p-surface-2)]">
                                    <td class="px-3 py-2 font-bold" colspan="4">Total ({{ displayCurrency }})</td>
                                    <td class="px-3 py-2 text-right tabular font-bold text-[var(--color-primary)]">{{ money(convertedEarned) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-[11px] text-[var(--p-muted)] mt-2">
                        Converted at current ECB reference rates. Actual payout is per native currency.
                    </p>
                </section>

                <!-- Visits chart -->
                <section class="rounded-lg border border-[var(--p-border)] bg-[var(--p-surface)] p-4 mb-8">
                    <p class="text-xs uppercase tracking-wider text-[var(--p-muted)] font-bold mb-3">
                        Visits, last 30 days
                    </p>
                    <svg :viewBox="`0 0 ${chart.w} ${chart.h}`" class="w-full h-32" preserveAspectRatio="none">
                        <rect v-for="(b, i) in chart.bars" :key="i"
                            :x="b.x" :y="b.y" :width="b.w" :height="b.h" rx="1"
                            fill="var(--color-primary)" :opacity="b.count ? 0.85 : 0.15">
                            <title>{{ b.day }}: {{ b.count }}</title>
                        </rect>
                    </svg>
                </section>

                <!-- Sources -->
                <section class="mb-8">
                    <p class="text-xs uppercase tracking-wider text-[var(--p-muted)] font-bold mb-3">Your links</p>
                    <div v-if="!data.sources.length" class="text-sm text-[var(--p-muted)]">
                        No referral codes assigned yet. Contact us to get set up.
                    </div>
                    <div v-for="s in data.sources" :key="s.id"
                        class="rounded-lg border border-[var(--p-border)] bg-[var(--p-surface)] px-4 py-3 mb-2">
                        <p class="text-sm font-semibold">{{ s.label || s.code }}
                            <span class="text-[var(--p-muted)] font-mono text-xs">({{ s.code }})</span>
                        </p>
                        <div class="mt-2 grid sm:grid-cols-2 gap-2">
                            <button v-for="link in [refLink(s.code), utmLink(s.code)]" :key="link"
                                @click="copy(link, link)"
                                class="text-left text-xs font-mono bg-[var(--p-surface-2)] border border-[var(--p-border)] rounded-md px-3 py-2 hover:border-[var(--color-primary)]/50 transition-colors truncate">
                                <span class="text-[var(--p-muted)]">{{ copied === link ? 'Copied!' : 'Copy' }} · </span>{{ link }}
                            </button>
                        </div>
                    </div>
                </section>

                <div class="grid lg:grid-cols-2 gap-8">
                    <!-- Commissions -->
                    <section>
                        <p class="text-xs uppercase tracking-wider text-[var(--p-muted)] font-bold mb-3">
                            Subscriptions &amp; earnings
                        </p>
                        <div class="rounded-lg border border-[var(--p-border)] overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-[var(--p-surface-2)] text-[var(--p-muted)] text-xs">
                                    <tr>
                                        <th class="text-left font-semibold px-3 py-2">Date</th>
                                        <th class="text-right font-semibold px-3 py-2">Order</th>
                                        <th class="text-right font-semibold px-3 py-2">Rate</th>
                                        <th class="text-right font-semibold px-3 py-2">You earn</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in data.recentCommissions" :key="c.id"
                                        class="border-t border-[var(--p-border)]">
                                        <td class="px-3 py-2">{{ date(c.billed_at) }}</td>
                                        <td class="px-3 py-2 text-right tabular">{{ money(Number(c.order_total), c.currency) }}</td>
                                        <td class="px-3 py-2 text-right tabular">{{ c.commission_rate }}%</td>
                                        <td class="px-3 py-2 text-right tabular font-semibold text-[var(--color-primary)]">
                                            {{ money(Number(c.commission_amount), c.currency) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!data.recentCommissions.length">
                                        <td colspan="4" class="px-3 py-6 text-center text-[var(--p-muted)]">
                                            No subscriptions yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Signups -->
                    <section>
                        <p class="text-xs uppercase tracking-wider text-[var(--p-muted)] font-bold mb-3">Recent signups</p>
                        <div class="rounded-lg border border-[var(--p-border)] overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-[var(--p-surface-2)] text-[var(--p-muted)] text-xs">
                                    <tr>
                                        <th class="text-left font-semibold px-3 py-2">User</th>
                                        <th class="text-right font-semibold px-3 py-2">Referred</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(u, i) in data.recentSignups" :key="i"
                                        class="border-t border-[var(--p-border)]">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[var(--p-surface-2)] text-xs font-bold mr-2">{{ u.initials }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ date(u.referred_at) }}</td>
                                    </tr>
                                    <tr v-if="!data.recentSignups.length">
                                        <td colspan="2" class="px-3 py-6 text-center text-[var(--p-muted)]">
                                            No signups yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </template>
        </main>
    </div>
</template>
