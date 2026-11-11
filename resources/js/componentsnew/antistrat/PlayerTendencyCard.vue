<template>
    <div class="ptc">

        <!-- Player header -->
        <div class="ptc__head">
            <div class="ptc__avatar">
                {{ (player.alias ?? player.name)[0]?.toUpperCase() }}
            </div>
            <div class="ptc__identity">
                <div class="ptc__name-row">
                    <p class="ptc__name">{{ player.alias ?? player.name }}</p>
                    <span v-if="player.alias" class="ptc__realname">({{ player.name }})</span>
                    <span v-if="aiPlayer?.role_assessment" class="ptc__role">
                        {{ aiPlayer.role_assessment }}
                    </span>
                </div>
                <p class="ptc__steamid">{{ player.steamid }}</p>
            </div>
            <div v-if="stat" class="ptc__rating">
                <p class="ptc__rating-value" :class="`ptc__rating-value--${ratingTone(stat.avg_rating)}`">
                    {{ stat.avg_rating }}
                </p>
                <p class="ptc__rating-label">Rating</p>
            </div>
        </div>

        <div class="ptc__body">

            <!-- Primary positions (T and CT) -->
            <div v-if="hasDominanceData" class="ptc__pos-grid">
                <div
                    v-for="side in ['CT', 'T']"
                    :key="side"
                    class="ptc-side"
                    :class="`ptc-side--${side.toLowerCase()}`"
                >
                    <p class="ptc-side__label">{{ side }}-side positions</p>
                    <div class="ptc-side__zones">
                        <div v-for="zone in topZones(side)" :key="zone.zone_id" class="ptc-zone">
                            <span class="ptc-zone__dot" :style="{ background: zone.color }" />
                            <span class="ptc-zone__name">{{ zone.zone_name }}</span>
                            <span class="ptc-zone__pct" :style="{ color: zone.color }">{{ zone.pct }}%</span>
                        </div>
                        <p v-if="!topZones(side).length" class="ptc-side__empty">No data</p>
                    </div>
                </div>
            </div>

            <!-- No position data yet -->
            <div v-else class="ptc__no-pos">
                <p>Position data will appear after demo processing</p>
            </div>

            <!-- Stat row -->
            <div v-if="stat" class="ptc__stats">
                <div class="ptc-stat">
                    <p class="ptc-stat__value">{{ stat.avg_adr }}</p>
                    <p class="ptc-stat__label">ADR</p>
                </div>
                <div class="ptc-stat">
                    <p class="ptc-stat__value" :class="stat.kd_ratio >= 1 ? 'ptc-stat__value--win' : 'ptc-stat__value--loss'">
                        {{ stat.kd_ratio }}
                    </p>
                    <p class="ptc-stat__label">K/D</p>
                </div>
                <div class="ptc-stat">
                    <p class="ptc-stat__value">{{ stat.hs_percent }}%</p>
                    <p class="ptc-stat__label">HS%</p>
                </div>
                <div class="ptc-stat">
                    <p class="ptc-stat__value" :class="totalClutches > 0 ? 'ptc-stat__value--warn' : 'ptc-stat__value--muted'">
                        {{ totalClutches }}
                    </p>
                    <p class="ptc-stat__label">Clutches</p>
                </div>
            </div>

            <!-- Aggression indicator -->
            <div v-if="stat && firstKillTiming" class="ptc-aggro">
                <div class="ptc-aggro__head">
                    <span class="ptc-aggro__label">Aggression (first kill speed)</span>
                    <span class="ptc-aggro__verdict" :class="`ptc-aggro__verdict--${aggressionTone}`">{{ aggressionLabel }}</span>
                </div>
                <div class="ptc-aggro__track">
                    <div
                        class="ptc-aggro__fill"
                        :class="`ptc-aggro__fill--${aggressionTone}`"
                        :style="{ transform: `scaleX(${aggressionPct / 100})` }"
                    />
                </div>
                <p class="ptc-aggro__note">{{ firstKillTiming.avg_first_kill_seconds }}s avg after freeze-end</p>
            </div>

            <!-- Top weapon -->
            <div v-if="topWeapon" class="ptc-weapon">
                <Icon icon="lucide:crosshair" class="ptc-weapon__icon" />
                <span class="ptc-weapon__label">Primary weapon:</span>
                <span class="ptc-weapon__name">{{ topWeapon }}</span>
                <span class="ptc-weapon__kills">({{ topWeaponKills }} kills)</span>
            </div>

            <!-- Utility -->
            <div v-if="stat && (stat.avg_utility_dmg > 0 || stat.avg_flashed > 0)" class="ptc-util">
                <span class="ptc-util__item">
                    <span class="ptc-util__dot ptc-util__dot--dmg" />
                    {{ stat.avg_utility_dmg }} util dmg
                </span>
                <span class="ptc-util__item">
                    <span class="ptc-util__dot ptc-util__dot--flash" />
                    {{ stat.avg_flashed }} flashed
                </span>
            </div>

            <!-- AI watch out -->
            <div v-if="aiPlayer?.watch_out_for" class="ptc-watch">
                <Icon icon="lucide:alert-triangle" class="ptc-watch__icon" />
                <p class="ptc-watch__text">
                    <CalloutRenderer :text="aiPlayer.watch_out_for" />
                </p>
            </div>

        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Icon } from '@iconify/vue';
import CalloutRenderer from '@/componentsnew/callouts/CalloutRenderer.vue';

const props = defineProps<{
    player:            any;   // OpponentTeamPlayer
    stat:              any;   // from analytics.player_stats
    positionDominance: any;   // from positionDominance endpoint, this player's entry
    aiPlayer:          any;   // from ai_insights.key_players
    firstKillTiming:   any;   // from analytics.first_kill_timing
    weaponUsage:       any;   // from analytics.weapon_usage
}>();

// ── Position dominance ────────────────────────────────────────────────────────
const hasDominanceData = computed(() =>
    props.positionDominance &&
    ((props.positionDominance.CT?.length > 0) || (props.positionDominance.T?.length > 0))
);

const topZones = (side: string) =>
    (props.positionDominance?.[side] ?? []).slice(0, 3);

// ── Stats ──────────────────────────────────────────────────────────────────────
const totalClutches = computed(() =>
    props.stat
        ? Object.values(props.stat.clutches ?? {}).reduce((a: any, b: any) => Number(a) + Number(b), 0)
        : 0
);

// ── Aggression ────────────────────────────────────────────────────────────────
const aggressionPct = computed(() => {
    const secs = props.firstKillTiming?.avg_first_kill_seconds ?? 15;
    // Invert: lower seconds = more aggressive = higher bar
    // Range: 0s (100%) to 20s+ (0%)
    return Math.max(0, Math.min(100, Math.round((1 - secs / 20) * 100)));
});

const aggressionLabel = computed(() => {
    const pct = aggressionPct.value;
    if (pct >= 75) return 'Very aggressive';
    if (pct >= 50) return 'Aggressive';
    if (pct >= 25) return 'Default';
    return 'Passive';
});

const aggressionTone = computed(() => {
    const pct = aggressionPct.value;
    if (pct >= 75) return 'loss';
    if (pct >= 50) return 'warn';
    if (pct >= 25) return 'accent';
    return 'muted';
});

// ── Top weapon ────────────────────────────────────────────────────────────────
const topWeapon = computed(() => {
    const wu = props.weaponUsage;
    if (!wu?.weapons) return null;
    return wu.top_weapon ?? Object.keys(wu.weapons)[0] ?? null;
});

const topWeaponKills = computed(() => {
    const wu = props.weaponUsage;
    if (!wu?.weapons || !topWeapon.value) return 0;
    return wu.weapons[topWeapon.value] ?? 0;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const ratingTone = (r: number) =>
    r >= 1.3 ? 'win' : r >= 1.1 ? 'win' : r >= 0.9 ? 'default' : 'loss';
</script>

<style scoped>
.ptc {
    border-radius: 6px;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    overflow: hidden;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Header ── */
.ptc__head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: var(--th-surface-2, oklch(0.215 0.016 250));
    border-bottom: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
}

.ptc__avatar {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    border: 1px solid var(--th-border-2, oklch(1 0 0 / 0.14));
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 16px;
    font-weight: 700;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.ptc__identity {
    flex: 1;
    min-width: 0;
}

.ptc__name-row {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-wrap: wrap;
}

.ptc__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    margin: 0;
}

.ptc__realname {
    font-size: 11px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

.ptc__role {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 3px;
    color: var(--th-accent, #ff8c00);
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 16%, transparent);
}

.ptc__steamid {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 3px 0 0;
}

.ptc__rating {
    text-align: right;
    flex-shrink: 0;
}

.ptc__rating-value {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    font-variant-numeric: tabular-nums;
    margin: 0;
}

.ptc__rating-value--win  { color: var(--th-win, oklch(0.72 0.14 145)); }
.ptc__rating-value--loss { color: var(--th-loss, oklch(0.62 0.20 22)); }

.ptc__rating-label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 2px 0 0;
}

/* ── Body ── */
.ptc__body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* ── Positions ── */
.ptc__pos-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.ptc-side {
    border-radius: 5px;
    padding: 10px;
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    background: var(--th-surface-2, oklch(0.215 0.016 250));
}

.ptc-side--ct { border-color: color-mix(in oklch, var(--th-accent-2, oklch(0.62 0.20 50)) 22%, transparent); }
.ptc-side--t  { border-color: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 22%, transparent); }

.ptc-side__label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin: 0 0 8px;
}

.ptc-side--ct .ptc-side__label { color: var(--th-accent-2, oklch(0.62 0.20 50)); }
.ptc-side--t  .ptc-side__label { color: var(--th-warn, oklch(0.78 0.15 80)); }

.ptc-side__zones {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.ptc-zone {
    display: flex;
    align-items: center;
    gap: 7px;
}

.ptc-zone__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ptc-zone__name {
    flex: 1;
    font-size: 12px;
    font-weight: 500;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ptc-zone__pct {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.ptc-side__empty {
    font-size: 10px;
    font-style: italic;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 0;
}

.ptc__no-pos {
    border-radius: 5px;
    background: var(--th-surface-2, oklch(0.215 0.016 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    padding: 12px;
    text-align: center;
}

.ptc__no-pos p {
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    margin: 0;
}

/* ── Stat row ── */
.ptc__stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--th-border, oklch(1 0 0 / 0.09));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    border-radius: 5px;
    overflow: hidden;
}

.ptc-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 8px 6px;
    background: var(--th-surface-2, oklch(0.215 0.016 250));
}

.ptc-stat__value {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 14px;
    font-weight: 700;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    font-variant-numeric: tabular-nums;
    margin: 0;
}

.ptc-stat__value--win   { color: var(--th-win, oklch(0.72 0.14 145)); }
.ptc-stat__value--loss  { color: var(--th-loss, oklch(0.62 0.20 22)); }
.ptc-stat__value--warn  { color: var(--th-warn, oklch(0.78 0.15 80)); }
.ptc-stat__value--muted { color: var(--th-text-4, oklch(1 0 0 / 0.50)); }

.ptc-stat__label {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 8px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 0;
}

/* ── Aggression ── */
.ptc-aggro {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ptc-aggro__head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.ptc-aggro__label {
    font-size: 10px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.ptc-aggro__verdict {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
}

.ptc-aggro__verdict--loss   { color: var(--th-loss, oklch(0.62 0.20 22)); }
.ptc-aggro__verdict--warn   { color: var(--th-warn, oklch(0.78 0.15 80)); }
.ptc-aggro__verdict--accent { color: var(--th-accent, #ff8c00); }
.ptc-aggro__verdict--muted  { color: var(--th-text-3, oklch(1 0 0 / 0.62)); }

.ptc-aggro__track {
    height: 6px;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    border-radius: 3px;
    overflow: hidden;
}

.ptc-aggro__fill {
    width: 100%;
    height: 100%;
    border-radius: 3px;
    transform-origin: left;
    transition: transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
}

.ptc-aggro__fill--loss   { background: var(--th-loss, oklch(0.62 0.20 22)); }
.ptc-aggro__fill--warn   { background: var(--th-warn, oklch(0.78 0.15 80)); }
.ptc-aggro__fill--accent { background: var(--th-accent, #ff8c00); }
.ptc-aggro__fill--muted  { background: var(--th-text-4, oklch(1 0 0 / 0.50)); }

.ptc-aggro__note {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 0;
}

@media (prefers-reduced-motion: reduce) {
    .ptc-aggro__fill { transition: none; }
}

/* ── Top weapon ── */
.ptc-weapon {
    display: flex;
    align-items: center;
    gap: 7px;
}

.ptc-weapon__icon {
    font-size: 14px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    flex-shrink: 0;
}

.ptc-weapon__label {
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.ptc-weapon__name {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    padding: 2px 6px;
    border-radius: 3px;
}

.ptc-weapon__kills {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
}

/* ── Utility ── */
.ptc-util {
    display: flex;
    gap: 16px;
}

.ptc-util__item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
}

.ptc-util__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.ptc-util__dot--dmg   { background: var(--th-accent, #ff8c00); }
.ptc-util__dot--flash { background: var(--th-accent-2, oklch(0.62 0.20 50)); }

/* ── AI watch ── */
.ptc-watch {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 5px;
    background: color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 7%, transparent);
    border: 1px solid color-mix(in oklch, var(--th-warn, oklch(0.78 0.15 80)) 18%, transparent);
}

.ptc-watch__icon {
    font-size: 12px;
    color: var(--th-warn, oklch(0.78 0.15 80));
    flex-shrink: 0;
    margin-top: 2px;
}

.ptc-watch__text {
    font-size: 12px;
    line-height: 1.5;
    color: var(--th-warn, oklch(0.78 0.15 80));
    margin: 0;
}
</style>
