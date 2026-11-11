<template>
    <div class="otp">

        <!-- Header -->
        <div v-if="opponent" class="otp-header">
            <div class="otp-header__inner">
                <div class="otp-header__lead">
                    <div class="otp-header__title-row">
                        <span v-if="opponent.tag" class="otp-header__tag">{{ opponent.tag }}</span>
                        <h1 class="otp-header__name">{{ opponent.name }}</h1>
                        <span
                            v-if="opponent.ai_insights?.threat_level"
                            class="threat-chip"
                            :class="`threat-chip--${threatTone(opponent.ai_insights.threat_level)}`"
                        >
                            {{ opponent.ai_insights.threat_level.replace('_', ' ') }}
                        </span>
                    </div>
                    <p v-if="opponent.notes" class="otp-header__notes">{{ opponent.notes }}</p>
                    <div class="otp-header__sublinks">
                        <a
                            v-if="opponent.platform_url"
                            :href="opponent.platform_url"
                            target="_blank"
                            class="otp-header__link"
                        >
                            <Icon icon="lucide:external-link" /> Platform profile
                        </a>
                        <!-- Non-owner: read-only sharing line -->
                        <span v-if="!opponent.is_owner && opponent.shared_teams?.length" class="otp-header__shared-by">
                            <Icon icon="lucide:users" /> Shared with: {{ opponent.shared_teams.map(t => t.name).join(', ') }}
                        </span>
                    </div>
                </div>

                <div class="otp-header__actions">
                    <!-- Share control — owner only -->
                    <div v-if="opponent.is_owner" class="otp-share">
                        <MultiSelect
                            v-model="shareTeamIds"
                            :options="userTeams ?? []"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Share with teams"
                            :showToggleAll="false"
                            class="otp-share__select"
                            @change="handleSharesChange"
                        >
                            <template #value="slotProps">
                                <span class="otp-share__value">
                                    <Icon icon="lucide:users" class="otp-share__value-icon" />
                                    <template v-if="slotProps.value?.length">
                                        Shared with {{ slotProps.value.length }} team{{ slotProps.value.length > 1 ? 's' : '' }}
                                    </template>
                                    <template v-else>Share with teams</template>
                                </span>
                            </template>
                        </MultiSelect>
                    </div>
                    <Button severity="secondary" size="sm" @click="showEditModal = true">
                        <Icon icon="lucide:pencil" class="otp-btn-icon" /> Edit
                    </Button>
                    <Button
                        size="sm"
                        :disabled="refreshInsights.isPending.value"
                        @click="handleRefreshInsights"
                    >
                        <Icon
                            icon="lucide:sparkles"
                            class="otp-btn-icon"
                            :class="{ 'otp-btn-icon--spin': refreshInsights.isPending.value }"
                        />
                        {{ refreshInsights.isPending.value ? 'Analysing...' : 'Refresh AI' }}
                    </Button>
                </div>
            </div>

            <!-- Tab bar -->
            <nav class="tab-bar" role="tablist" :aria-label="`${opponent.name} sections`">
                <button
                    v-for="(tab, i) in tabs"
                    :key="tab.id"
                    role="tab"
                    :id="`tab-${tab.id}`"
                    :aria-selected="activeTab === tab.id"
                    :aria-controls="`panel-${tab.id}`"
                    :tabindex="activeTab === tab.id ? 0 : -1"
                    class="tab-btn"
                    :class="{ 'tab-btn--active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                    @keydown.right.prevent="focusTab(1)"
                    @keydown.left.prevent="focusTab(-1)"
                >
                    <span class="tab-btn__idx">{{ String(i + 1).padStart(2, '0') }}</span>
                    <span class="tab-btn__label">{{ tab.label }}</span>
                    <span v-if="tab.badge" class="tab-btn__badge">{{ tab.badge }}</span>
                </button>
            </nav>
        </div>

        <div v-else-if="isPending" class="otp-header">
            <div class="otp-header__inner">
                <div class="otp-skel-stack">
                    <span class="skel skel--name" />
                    <span class="skel skel--sub" />
                </div>
            </div>
        </div>

        <!-- Load error -->
        <div v-else-if="isError" class="otp-error-wrap">
            <div class="otp-error">
                <Icon icon="lucide:alert-triangle" class="otp-error__icon" />
                <p class="otp-error__headline">Could not load this opponent profile</p>
                <p class="otp-error__body">Check your connection and refresh the page.</p>
                <Button severity="secondary" size="sm" class="otp-error__btn" @click="goBackToList">
                    <Icon icon="lucide:arrow-left" class="otp-btn-icon" /> Back to opponents
                </Button>
            </div>
        </div>

        <!-- Content -->
        <div v-if="opponent" ref="contentRef" class="otp-content">
            <div class="otp-content__inner">

                <!-- Stat pills -->
                <div class="otp-stats">
                    <div
                        v-for="pill in statPills"
                        :key="pill.label"
                        class="otp-stat"
                    >
                        <span class="otp-stat__label">
                            <Icon :icon="pill.icon" class="otp-stat__icon" />
                            {{ pill.label }}
                        </span>
                        <span class="otp-stat__value" :class="{ 'otp-stat__value--accent': pill.highlight }">
                            {{ pill.value }}
                        </span>
                    </div>
                </div>

                <!-- Analysis complete flash -->
                <Transition name="fade">
                    <div v-if="showInsightsSuccess" class="otp-banner otp-banner--win">
                        <div class="otp-banner__icon">
                            <Icon icon="lucide:check" />
                        </div>
                        <p class="otp-banner__title">Analysis complete. Insights updated.</p>
                    </div>
                </Transition>

                <!-- Pending banner -->
                <div v-if="insightsStatus?.status === 'pending'" class="otp-banner otp-banner--warn">
                    <div class="otp-banner__spinner" />
                    <div>
                        <p class="otp-banner__title">Analysing in background...</p>
                        <p class="otp-banner__body">This page will update automatically when complete.</p>
                    </div>
                </div>

                <!-- Failed banner -->
                <div v-else-if="insightsStatus?.status === 'failed'" class="otp-banner otp-banner--loss otp-banner--split">
                    <div>
                        <p class="otp-banner__title">Analysis failed</p>
                        <p class="otp-banner__body">{{ insightsStatus.insights_error ?? 'Unknown error' }}</p>
                    </div>
                    <Button severity="secondary" size="sm" @click="handleRefreshInsights">Retry</Button>
                </div>

                <!-- Normal AI summary banner -->
                <div
                    v-else-if="opponent.ai_insights && !opponent.ai_insights.error"
                    class="otp-scout"
                >
                    <div class="otp-scout__gauge">
                        <svg class="otp-scout__ring" width="80" height="80" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="28" fill="none" stroke="oklch(1 0 0 / 0.06)" stroke-width="6" />
                            <circle
                                cx="40"
                                cy="40"
                                r="28"
                                fill="none"
                                :stroke="gaugeColor"
                                stroke-width="6"
                                stroke-linecap="round"
                                :stroke-dasharray="175.9"
                                :stroke-dashoffset="175.9 * (1 - (opponent.ai_insights.danger_score ?? 0) / 100)"
                                class="otp-scout__ring-fill"
                            />
                        </svg>
                        <div class="otp-scout__gauge-text">
                            <p class="otp-scout__gauge-value" :style="{ color: gaugeColor }">
                                {{ opponent.ai_insights.danger_score }}
                            </p>
                            <p class="otp-scout__gauge-label">Danger</p>
                        </div>
                    </div>
                    <div class="otp-scout__body">
                        <p class="otp-scout__kicker">AI Scout Report</p>
                        <p class="otp-scout__summary">
                            <CalloutRenderer :text="opponent.ai_insights.summary ?? ''" />
                        </p>
                        <p class="otp-scout__updated">Updated {{ formatDate(opponent.insights_updated_at) }}</p>
                    </div>
                </div>

                <!-- ══ TAB: OVERVIEW ══ -->
                <section
                    v-show="activeTab === 'overview'"
                    :id="`panel-overview`"
                    role="tabpanel"
                    aria-labelledby="tab-overview"
                    class="otp-tab"
                >
                    <div v-if="analyticsLoading" class="otp-loading-stack">
                        <div class="otp-skel-block otp-skel-block--lg" />
                        <div class="otp-skel-block" />
                    </div>
                    <template v-else-if="analytics">
                        <!-- Player table -->
                        <div class="otp-panel otp-panel--flush">
                            <div class="otp-panel__head">
                                <h2 class="otp-panel__title">Player Performance</h2>
                                <span class="otp-panel__count">{{ analytics.total_demos }} demos · {{ analytics.total_rounds }} rounds</span>
                            </div>
                            <div class="otp-table-wrap">
                                <table class="otp-table">
                                    <thead>
                                        <tr>
                                            <th class="otp-table__th otp-table__th--left">Player</th>
                                            <th class="otp-table__th">Rating</th>
                                            <th class="otp-table__th">ADR</th>
                                            <th class="otp-table__th">KAST%</th>
                                            <th class="otp-table__th">K/D</th>
                                            <th class="otp-table__th">HS%</th>
                                            <th class="otp-table__th">Util DMG</th>
                                            <th class="otp-table__th">Clutches</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="p in analytics.player_stats" :key="p.steamid" class="otp-table__row">
                                            <td class="otp-table__td otp-table__td--left">
                                                <div class="otp-player-cell">
                                                    <div class="otp-player-cell__avatar">{{ p.name[0]?.toUpperCase() }}</div>
                                                    <div>
                                                        <p class="otp-player-cell__name">{{ p.name }}</p>
                                                        <p class="otp-player-cell__role">{{ getAiRole(p.steamid) }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="otp-table__td otp-table__td--num" :class="`otp-rating--${ratingTone(p.avg_rating)}`">{{ p.avg_rating }}</td>
                                            <td class="otp-table__td otp-table__td--num">{{ p.avg_adr }}</td>
                                            <td class="otp-table__td otp-table__td--num">{{ p.avg_kast }}%</td>
                                            <td class="otp-table__td otp-table__td--num" :class="p.kd_ratio >= 1 ? 'otp-rating--win' : 'otp-rating--loss'">{{ p.kd_ratio }}</td>
                                            <td class="otp-table__td otp-table__td--num">{{ p.hs_percent }}%</td>
                                            <td class="otp-table__td otp-table__td--num">{{ p.avg_utility_dmg }}</td>
                                            <td class="otp-table__td otp-table__td--num" :class="totalClutches(p) > 0 ? 'otp-rating--warn' : 'otp-rating--muted'">
                                                {{ totalClutches(p) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Charts row 1 -->
                        <div class="otp-chart-grid">
                            <div class="otp-panel">
                                <h3 class="otp-chart__title">Multi-Kill Rounds</h3>
                                <p class="otp-chart__sub">Total across all demos</p>
                                <div class="otp-mk">
                                    <div v-for="p in analytics.player_stats" :key="p.steamid + 'mk'" class="otp-mk__player">
                                        <p class="otp-mk__name">{{ p.name }}</p>
                                        <div class="otp-mk__bars">
                                            <div v-for="(count, key) in p.multi_kills" :key="key" class="otp-mk__bar-col">
                                                <div class="otp-mk__bar-track">
                                                    <div
                                                        class="otp-mk__bar-fill"
                                                        :class="`otp-mk__bar-fill--${key === '5k' ? 'loss' : key === '4k' ? 'warn' : 'accent'}`"
                                                        :style="{ transform: `scaleY(${multiKillMax > 0 ? count / multiKillMax : 0})` }"
                                                    />
                                                </div>
                                                <p class="otp-mk__bar-label">{{ key }} ({{ count }})</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="otp-panel">
                                <h3 class="otp-chart__title">Utility Impact</h3>
                                <p class="otp-chart__sub">Avg per demo</p>
                                <div class="otp-util">
                                    <div v-for="p in analytics.player_stats" :key="p.steamid + 'ut'" class="otp-util__row">
                                        <div class="otp-util__head">
                                            <span class="otp-util__name">{{ p.name }}</span>
                                            <span class="otp-util__stat">{{ p.avg_utility_dmg }} dmg · {{ p.avg_flashed }} flash</span>
                                        </div>
                                        <div class="otp-util__bars">
                                            <div class="otp-util__bar otp-util__bar--dmg" :style="{ width: (utilMax > 0 ? p.avg_utility_dmg / utilMax * 70 : 0) + '%' }" />
                                            <div class="otp-util__bar otp-util__bar--flash" :style="{ width: (flashMax > 0 ? p.avg_flashed / flashMax * 30 : 0) + '%' }" />
                                        </div>
                                    </div>
                                    <div class="otp-util__legend">
                                        <span class="otp-util__legend-item"><span class="otp-util__legend-dot otp-util__legend-dot--dmg" /> Util DMG</span>
                                        <span class="otp-util__legend-item"><span class="otp-util__legend-dot otp-util__legend-dot--flash" /> Enemies flashed</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts row 2 -->
                        <div class="otp-chart-grid">
                            <div class="otp-panel">
                                <h3 class="otp-chart__title">First Kill Speed</h3>
                                <p class="otp-chart__sub">Avg seconds after freeze-end (lower = more aggressive)</p>
                                <div class="otp-fk">
                                    <div v-for="p in analytics.first_kill_timing" :key="p.steamid + 'fk'" class="otp-fk__row">
                                        <div class="otp-fk__head">
                                            <span class="otp-fk__name">{{ p.name }}</span>
                                            <span class="otp-fk__stat">{{ p.avg_first_kill_seconds }}s</span>
                                        </div>
                                        <div class="otp-fk__track">
                                            <div
                                                class="otp-fk__fill"
                                                :class="`otp-fk__fill--${p.avg_first_kill_seconds < 5 ? 'loss' : p.avg_first_kill_seconds < 10 ? 'warn' : 'accent'}`"
                                                :style="{ transform: `scaleX(${firstKillMax > 0 ? p.avg_first_kill_seconds / firstKillMax : 0})` }"
                                            />
                                        </div>
                                    </div>
                                    <p v-if="!analytics.first_kill_timing?.length" class="otp-chart__empty">No data</p>
                                </div>
                            </div>
                            <div class="otp-panel">
                                <h3 class="otp-chart__title">Weapon Preferences</h3>
                                <p class="otp-chart__sub">Kill count per weapon</p>
                                <div class="otp-weapons">
                                    <div v-for="p in analytics.weapon_usage" :key="p.steamid + 'wp'" class="otp-weapons__row">
                                        <span class="otp-weapons__name">{{ p.name }}</span>
                                        <div class="otp-weapons__chips">
                                            <span v-for="(count, weapon) in p.weapons" :key="String(weapon)" class="otp-weapons__chip">
                                                {{ weapon }} ({{ count }})
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hit distribution -->
                        <div class="otp-panel">
                            <h3 class="otp-chart__title">Hit Distribution</h3>
                            <p class="otp-chart__sub">Where each player lands their kills</p>
                            <div class="otp-hit">
                                <div v-for="p in analytics.player_stats" :key="p.steamid + 'hg'" class="otp-hit__row">
                                    <p class="otp-hit__name">{{ p.name }}</p>
                                    <div class="otp-hit__bar">
                                        <div class="otp-hit__seg otp-hit__seg--head"    :style="{ width: p.hitgroups.head    + '%' }" />
                                        <div class="otp-hit__seg otp-hit__seg--chest"   :style="{ width: p.hitgroups.chest   + '%' }" />
                                        <div class="otp-hit__seg otp-hit__seg--stomach" :style="{ width: p.hitgroups.stomach + '%' }" />
                                        <div class="otp-hit__seg otp-hit__seg--arms"    :style="{ width: p.hitgroups.arms    + '%' }" />
                                        <div class="otp-hit__seg otp-hit__seg--legs"    :style="{ width: p.hitgroups.legs    + '%' }" />
                                    </div>
                                    <div class="otp-hit__legend">
                                        <span class="otp-hit__legend-item otp-hit__legend-item--head">Head {{ p.hitgroups.head }}%</span>
                                        <span class="otp-hit__legend-item otp-hit__legend-item--chest">Chest {{ p.hitgroups.chest }}%</span>
                                        <span class="otp-hit__legend-item otp-hit__legend-item--stomach">Stomach {{ p.hitgroups.stomach }}%</span>
                                        <span class="otp-hit__legend-item otp-hit__legend-item--arms">Arms {{ p.hitgroups.arms }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kill modifiers -->
                        <div class="otp-panel">
                            <h3 class="otp-chart__title">Kill Modifiers</h3>
                            <p class="otp-chart__sub">Special kill types across all demos</p>
                            <div class="otp-mods">
                                <div v-for="p in analytics.grenade_patterns" :key="p.steamid + 'km'" class="otp-mods__row">
                                    <span class="otp-mods__name">{{ p.name }}</span>
                                    <div class="otp-mods__items">
                                        <span :class="p.thru_smoke > 0 ? 'otp-mods__item' : 'otp-mods__item otp-mods__item--off'">{{ p.thru_smoke }} thru smoke</span>
                                        <span :class="p.blind_kills > 0 ? 'otp-mods__item otp-mods__item--warn' : 'otp-mods__item otp-mods__item--off'">{{ p.blind_kills }} blind</span>
                                        <span :class="p.noscopes > 0 ? 'otp-mods__item otp-mods__item--loss' : 'otp-mods__item otp-mods__item--off'">{{ p.noscopes }} noscope</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else-if="!analyticsLoading" class="otp-empty">
                        No analytics data yet. Link demos to this opponent to generate stats.
                    </div>
                </section>

                <!-- ══ TAB: AI INSIGHTS ══ -->
                <section
                    v-show="activeTab === 'insights'"
                    :id="`panel-insights`"
                    role="tabpanel"
                    aria-labelledby="tab-insights"
                    class="otp-tab"
                >
                    <div v-if="!opponent.ai_insights" class="otp-empty otp-empty--icon">
                        <Icon icon="lucide:sparkles" class="otp-empty__icon" />
                        <p>No AI insights yet. Click <strong>Refresh AI</strong> to analyse linked demos.</p>
                    </div>
                    <template v-else-if="!opponent.ai_insights.error">
                        <div class="otp-chart-grid">
                            <div class="otp-panel">
                                <h3 class="otp-panel__kicker">Team Style</h3>
                                <div class="otp-style">
                                    <template v-for="row in teamStyleRows" :key="row.label">
                                        <div v-if="row.value" class="otp-style__row">
                                            <span class="otp-style__label">{{ row.label }}</span>
                                            <span class="otp-style__value" :class="{ 'otp-style__value--cap': row.cap }">
                                                <CalloutRenderer :text="String(row.value)" />
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="otp-panel">
                                <h3 class="otp-panel__kicker">Map Intel</h3>
                                <div class="otp-mapintel">
                                    <div
                                        v-for="(data, map) in (opponent.ai_insights.map_specific ?? {})"
                                        :key="String(map)"
                                        class="otp-mapintel__map"
                                    >
                                        <div class="otp-mapintel__head">
                                            <span class="otp-mapintel__name">{{ map }}</span>
                                            <span class="otp-mapintel__side">{{ data.preferred_side }}</span>
                                        </div>
                                        <ul class="otp-mapintel__habits">
                                            <li v-for="habit in data.exploitable_habits" :key="habit" class="otp-habit">
                                                <Icon icon="lucide:alert-triangle" class="otp-habit__icon" />
                                                <CalloutRenderer :text="habit" />
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="opponent.ai_insights.counter_recommendations?.length" class="otp-panel">
                            <h3 class="otp-panel__kicker">Counter Recommendations</h3>
                            <div class="otp-recs">
                                <div
                                    v-for="(rec, i) in opponent.ai_insights.counter_recommendations"
                                    :key="i"
                                    class="otp-rec"
                                >
                                    <span
                                        class="otp-rec__num"
                                        :class="`otp-rec__num--${rec.priority === 'high' ? 'loss' : rec.priority === 'medium' ? 'warn' : 'muted'}`"
                                    >
                                        {{ i + 1 }}
                                    </span>
                                    <div>
                                        <p class="otp-rec__situation"><CalloutRenderer :text="rec.situation" /></p>
                                        <p class="otp-rec__recommendation"><CalloutRenderer :text="rec.recommendation" /></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="opponent.ai_insights.key_players?.length" class="otp-panel">
                            <h3 class="otp-panel__kicker">Key Players</h3>
                            <div class="otp-keyplayers">
                                <div
                                    v-for="kp in opponent.ai_insights.key_players"
                                    :key="kp.steamid"
                                    class="otp-keyplayer"
                                >
                                    <div class="otp-keyplayer__head">
                                        <p class="otp-keyplayer__name">{{ kp.name }}</p>
                                        <span class="otp-keyplayer__role">{{ kp.role_assessment }}</span>
                                    </div>
                                    <div class="otp-keyplayer__stats">
                                        <span v-if="kp.avg_rating">{{ kp.avg_rating }} rating</span>
                                        <span v-if="kp.avg_adr">{{ kp.avg_adr }} ADR</span>
                                        <span v-if="kp.hs_percent">{{ kp.hs_percent }}% HS</span>
                                    </div>
                                    <p class="otp-keyplayer__watch">
                                        <Icon icon="lucide:alert-triangle" class="otp-keyplayer__watch-icon" />
                                        <CalloutRenderer :text="kp.watch_out_for" />
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-if="opponent.ai_insights.player_matchups?.length" class="otp-panel">
                            <h3 class="otp-panel__kicker">1v1 Matchup Tips</h3>
                            <div class="otp-matchups">
                                <div v-for="m in opponent.ai_insights.player_matchups" :key="m.their_player" class="otp-matchup">
                                    <span class="otp-matchup__arrow">&rarr;</span>
                                    <span>
                                        <span class="otp-matchup__player">{{ m.their_player }}:</span>
                                        <span class="otp-matchup__advice"><CalloutRenderer :text="m.advice" /></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="otp-banner otp-banner--loss">
                        <p class="otp-banner__title">AI error: {{ opponent.ai_insights.error }}</p>
                    </div>
                </section>

                <!-- ══ TAB: PLAYERS ══ -->
                <section
                    v-show="activeTab === 'players'"
                    :id="`panel-players`"
                    role="tabpanel"
                    aria-labelledby="tab-players"
                    class="otp-tab"
                >
                    <div v-if="opponent.players.length" class="otp-players-grid">
                        <PlayerTendencyCard
                            v-for="player in opponent.players"
                            :key="player.steamid"
                            :player="player"
                            :stat="getPlayerStat(player.steamid)"
                            :position-dominance="playerDominanceMap[String(player.steamid)]"
                            :ai-player="getAiKeyPlayer(player.steamid)"
                            :first-kill-timing="playerFirstKillMap[String(player.steamid)]"
                            :weapon-usage="playerWeaponMap[String(player.steamid)]"
                        />
                    </div>
                    <p v-else class="otp-empty">
                        Players will appear here automatically once demos are linked.
                    </p>
                </section>

                <!-- ══ TAB: POSITIONS ══ -->
                <section
                    v-show="activeTab === 'positions'"
                    :id="`panel-positions`"
                    role="tabpanel"
                    aria-labelledby="tab-positions"
                    class="otp-tab otp-tab--map"
                >
                    <OpponentPositionMap :opponent-id="id" />
                </section>

                <!-- ══ TAB: HEATMAPS ══ -->
                <section
                    v-show="activeTab === 'heatmaps'"
                    :id="`panel-heatmaps`"
                    role="tabpanel"
                    aria-labelledby="tab-heatmaps"
                    class="otp-tab otp-tab--map"
                >
                    <OpponentHeatmap
                        :opponent-id="id"
                        :map-name="heatmapMapName"
                        :players="opponent.players ?? []"
                        :zones="allZones"
                    />
                </section>

                <!-- ══ TAB: DEMOS ══ -->
                <section
                    v-show="activeTab === 'demos'"
                    :id="`panel-demos`"
                    role="tabpanel"
                    aria-labelledby="tab-demos"
                    class="otp-tab"
                >
                    <div class="otp-demos__head">
                        <p class="otp-demos__count">{{ opponent.demos?.length ?? 0 }} demos linked</p>
                        <Button size="sm" @click="showAttachDemo = true">
                            <Icon icon="lucide:plus" class="otp-btn-icon" /> Link demo
                        </Button>
                    </div>
                    <div class="otp-demos__list">
                        <div v-for="demo in opponent.demos" :key="demo.id" class="otp-demo">
                            <div class="otp-demo__main">
                                <div class="otp-demo__badge">
                                    {{ (demo.map ?? '??').replace('de_', '').slice(0, 3) }}
                                </div>
                                <div class="otp-demo__identity">
                                    <p class="otp-demo__name">{{ demo.name }}</p>
                                    <p class="otp-demo__meta">{{ demo.map }} · {{ formatDate(demo.created_at) }}</p>
                                </div>
                            </div>
                            <div class="otp-demo__actions">
                                <router-link :to="{ name: 'DemoOverview', params: { id: demo.id } }" class="otp-demo__view">View</router-link>
                                <button class="otp-demo__unlink" @click="handleDetachDemo(demo.id)">Unlink</button>
                            </div>
                        </div>
                        <div v-if="!opponent.demos?.length" class="otp-empty otp-empty--icon">
                            <Icon icon="lucide:film" class="otp-empty__icon" />
                            <p>No demos linked yet.</p>
                            <Button size="sm" class="otp-empty__btn" @click="showAttachDemo = true">
                                <Icon icon="lucide:plus" class="otp-btn-icon" /> Link first demo
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- ══ TAB: PREP ══ -->
                <section
                    v-show="activeTab === 'prep'"
                    :id="`panel-prep`"
                    role="tabpanel"
                    aria-labelledby="tab-prep"
                    class="otp-tab"
                >
                    <MatchPrepTab
                        :opponent="opponent"
                        :analytics="analytics"
                        :position-dominance="positionDominanceData"
                        :strats="opponent.counter_strats ?? []"
                        :zones="allZones"
                        :ai-insights="opponent.ai_insights"
                    />
                </section>

                <!-- ══ TAB: CHAT ══ -->
                <section
                    v-show="activeTab === 'chat'"
                    :id="`panel-chat`"
                    role="tabpanel"
                    aria-labelledby="tab-chat"
                    class="otp-tab otp-tab--chat"
                >
                    <AntiStratChat
                        :opponent-id="id"
                        :opponent-name="opponent.name"
                    />
                </section>

                <!-- ══ TAB: COUNTERS ══ -->
                <section
                    v-show="activeTab === 'counters'"
                    :id="`panel-counters`"
                    role="tabpanel"
                    aria-labelledby="tab-counters"
                    class="otp-tab"
                >
                    <CountersTab
                        :opponent-id="id"
                        :strats="opponent.counter_strats ?? []"
                        :maps="maps ?? []"
                        :zones="allZones"
                        :generated-at="opponent.counter_strats_generated_at"
                        @add="openAddStrat"
                        @delete="handleDeleteStrat"
                    />
                </section>
            </div>
        </div>

        <!-- Link Demo Modal -->
        <Dialog v-model:visible="showAttachDemo" modal header="Link demo" :style="{ width: '520px' }">
            <div class="otp-modal">
                <div class="otp-field">
                    <label class="otp-label">Search demos</label>
                    <InputText v-model="demoSearch" class="otp-input" placeholder="Search by name or map..." autofocus />
                </div>
                <div class="otp-demo-picker">
                    <div v-if="filteredDemos.length === 0" class="otp-demo-picker__empty">No demos found</div>
                    <button
                        v-for="demo in filteredDemos"
                        :key="demo.id"
                        :disabled="isAlreadyLinked(demo.id) || attachDemo.isPending.value"
                        class="otp-demo-pick"
                        :class="{ 'otp-demo-pick--linked': isAlreadyLinked(demo.id) }"
                        @click="handleAttachDemo(demo.id)"
                    >
                        <div class="otp-demo__badge">
                            {{ (demo.map ?? '??').replace('de_', '').slice(0, 3) }}
                        </div>
                        <div class="otp-demo-pick__body">
                            <p class="otp-demo__name">{{ demo.name }}</p>
                            <p class="otp-demo__meta">{{ demo.map }} · {{ formatDate(demo.created_at) }}</p>
                        </div>
                        <span v-if="isAlreadyLinked(demo.id)" class="otp-demo-pick__tag">Linked</span>
                        <Icon v-else icon="lucide:plus" class="otp-demo-pick__icon" />
                    </button>
                </div>
            </div>
            <template #footer>
                <Button severity="secondary" size="sm" @click="showAttachDemo = false">Close</Button>
            </template>
        </Dialog>

        <!-- Add Counter Strat Modal -->
        <Dialog v-model:visible="showAddStrat" modal header="Add counter strat" :style="{ width: '500px' }">
            <div class="otp-modal">
                <div class="otp-field">
                    <label class="otp-label">Name *</label>
                    <InputText v-model="stratForm.name" class="otp-input" placeholder="e.g. Anti fast B rush" autofocus />
                </div>
                <div class="otp-field-row">
                    <div class="otp-field">
                        <label class="otp-label">Map *</label>
                        <Select
                            v-model="stratForm.map_id"
                            :options="maps ?? []"
                            optionLabel="display_name"
                            optionValue="id"
                            placeholder="Select map"
                            class="otp-input"
                        />
                    </div>
                    <div class="otp-field">
                        <label class="otp-label">Side *</label>
                        <Select
                            v-model="stratForm.side"
                            :options="sideOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="otp-input"
                        />
                    </div>
                </div>
                <div class="otp-field">
                    <label class="otp-label">Description</label>
                    <Textarea v-model="stratForm.description" class="otp-input" rows="3" placeholder="Describe the counter approach in detail..." />
                </div>
                <div class="otp-field">
                    <label class="otp-label">Tags</label>
                    <div class="otp-tag-input">
                        <InputText
                            v-model="tagInput"
                            class="otp-tag-input__field"
                            placeholder="Add tag and press Enter"
                            @keyup.enter="addTag"
                            @keyup.comma="addTag"
                        />
                        <Button severity="secondary" size="sm" @click="addTag">Add</Button>
                    </div>
                    <div v-if="stratForm.tags?.length" class="otp-tag-list">
                        <span v-for="tag in stratForm.tags" :key="tag" class="otp-tag">
                            {{ tag }}
                            <button class="otp-tag__remove" aria-label="Remove tag" @click="removeTag(tag)">&times;</button>
                        </span>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button severity="secondary" size="sm" @click="showAddStrat = false">Cancel</Button>
                <Button
                    size="sm"
                    :disabled="!stratForm.name || !stratForm.map_id || addCounterStrat.isPending.value"
                    @click="handleAddStrat"
                >
                    {{ addCounterStrat.isPending.value ? 'Saving...' : 'Save' }}
                </Button>
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { useConfirm } from 'primevue/useconfirm';
import { storeToRefs } from 'pinia';
import { Icon } from '@iconify/vue';
import Dialog from '@/components/volt/Dialog.vue';
import InputText from '@/components/volt/InputText.vue';
import Textarea from 'primevue/textarea';
import Select from '@/components/volt/Select.vue';
import MultiSelect from '@/components/volt/MultiSelect.vue';
import Button from '@/components/volt/Button.vue';
import useOpponentTeams, { type CounterStrat } from '@/composables/useOpponentTeams';
import useOpponentAnalytics from '@/composables/useOpponentAnalytics';
import useMaps from '@/composables/useMaps';
import useOpponentInsightsStatus from '@/composables/useOpponentInsightsStatus';
import useDemos from '@/composables/useDemos';
import useTeams from '@/composables/useTeams';
import { useStore } from '@/store';
import { useCallouts } from '@/composables/useCallouts';
import CalloutRenderer from '@/componentsnew/callouts/CalloutRenderer.vue';
import OpponentPositionMap from '@/componentsnew/antistrat/OpponentPositionMap.vue';
import CountersTab from '@/componentsnew/antistrat/CountersTab.vue';
import PlayerTendencyCard from '@/componentsnew/antistrat/PlayerTendencyCard.vue';
import MatchPrepTab from '@/componentsnew/antistrat/MatchPrepTab.vue';
import OpponentHeatmap from '@/componentsnew/antistrat/OpponentHeatmap.vue';
import AntiStratChat from '@/componentsnew/antistrat/AntiStratChat.vue';
import { AppErrorCode } from '@/constants/errorCodes';
import { usePlan } from '@/composables/usePlan';
import { useUpgradeModal } from '@/composables/useUpgradeModal';

const route   = useRoute();
const router  = useRouter();
const confirm = useConfirm();
const { can } = usePlan();
const { open: openUpgrade } = useUpgradeModal();
const id      = computed(() => Number(route.params.id));

const goBackToList = () => router.push({ name: 'AntiStrat' });

const store              = useStore();
const { selectedTeamId } = storeToRefs(store);

// ── Callouts (provide to entire tree so CalloutRenderer works) ────────────────
const allZones = ref<any[]>([]);

const loadZones = async (teamId: number | null | undefined) => {
    if (!teamId) return;
    try {
        const res = await fetch(`/api/callouts/all?team_id=${teamId}`, {
            headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
        });
        if (res.ok) allZones.value = await res.json();
    } catch {}
};

// useCallouts provides zones via inject so CalloutRenderer works in the tree
const { provideCallouts } = useCallouts(selectedTeamId.value);
provideCallouts();

watch(selectedTeamId, (id) => {
    loadZones(id);
    if (id) {
        const { provideCallouts: rp } = useCallouts(id);
        rp();
    }
}, { immediate: true });

// ── Composables ───────────────────────────────────────────────────────────────
const { OpponentTeams, attachDemo, detachDemo, addCounterStrat, deleteCounterStrat, refreshInsights, updateShares } = useOpponentTeams();
const { data: opponent, isPending, isError }           = OpponentTeams.show(() => id.value);
const { data: analytics, isLoading: analyticsLoading } = useOpponentAnalytics(() => id.value);
const { Maps }       = useMaps();
const { data: maps } = Maps.get();
const { Demos }          = useDemos();
const { data: allDemos } = Demos.get();
const { Teams }          = useTeams();
const { data: userTeams } = Teams.get();

// Insights status + success flash
const insightsStatusQuery = useOpponentInsightsStatus(() => id.value);
const insightsStatus      = insightsStatusQuery.data;
const showInsightsSuccess = insightsStatusQuery.showSuccess;

// Position dominance (for PlayerTendencyCard)
const getCsrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const { data: positionDominanceData } = useQuery({
    queryKey: ['opponent-position-dominance', computed(() => id.value)],
    queryFn:  async () => {
        const res = await fetch(`/api/opponent-teams/${id.value}/position-dominance`, {
            headers: { 'X-CSRF-TOKEN': getCsrf() },
        });
        return res.ok ? res.json() : null;
    },
    enabled:   computed(() => !!id.value),
    staleTime: 1000 * 60 * 5,
});

// ── Share control ─────────────────────────────────────────────────────────────
const shareTeamIds = ref<number[]>([]);

watch(opponent, (opp) => {
    shareTeamIds.value = (opp?.shared_teams ?? []).map((t: any) => t.id);
}, { immediate: true });

const handleSharesChange = () => {
    if (!id.value) return;
    updateShares.mutate({ id: id.value, team_ids: [...shareTeamIds.value] });
};

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref('overview');
const tabs = computed(() => [
    { id: 'overview',  label: 'Overview' },
    { id: 'insights',  label: 'AI Insights' },
    { id: 'players',   label: 'Players',   badge: opponent.value?.players?.length },
    { id: 'positions', label: 'Positions' },
    { id: 'heatmaps',  label: 'Heatmaps' },
    { id: 'demos',     label: 'Demos',     badge: opponent.value?.demos?.length },
    { id: 'counters',  label: 'Counters',  badge: opponent.value?.counter_strats?.length },
    { id: 'prep',      label: 'Match Prep' },
    { id: 'chat',      label: 'AI Chat' },
]);

const focusTab = (direction: 1 | -1) => {
    const list = tabs.value;
    const idx = list.findIndex(t => t.id === activeTab.value);
    const next = (idx + direction + list.length) % list.length;
    activeTab.value = list[next].id;
};

// ── Heatmap map name ──────────────────────────────────────────────────────────
const heatmapMapName = computed(() =>
    (positionDominanceData.value?.maps ?? positionDominanceData.value?.map
        ? [positionDominanceData.value?.map]
        : null
    )?.[0]
    ?? analytics.value?.map?.[0]
    ?? opponent.value?.demos?.[0]?.map
    ?? 'de_ancient'
);

// ── Chart helpers ─────────────────────────────────────────────────────────────
const multiKillMax = computed(() => {
    if (!analytics.value?.player_stats?.length) return 1;
    let m = 0;
    for (const p of analytics.value.player_stats) m = Math.max(m, p.multi_kills['3k'], p.multi_kills['4k'], p.multi_kills['5k']);
    return Math.max(m, 1);
});
const utilMax      = computed(() => Math.max(...(analytics.value?.player_stats ?? []).map((p: any) => p.avg_utility_dmg), 1));
const flashMax     = computed(() => Math.max(...(analytics.value?.player_stats ?? []).map((p: any) => p.avg_flashed), 1));
const firstKillMax = computed(() => Math.max(...(analytics.value?.first_kill_timing ?? []).map((p: any) => p.avg_first_kill_seconds), 1));

// ── Stat pills ────────────────────────────────────────────────────────────────
// Note: the "Danger" score is intentionally not a stat pill — the AI Scout
// gauge below is its canonical home on this page (avoids rendering it 2x here).
const statPills = computed(() => [
    { label: 'Demos',   value: opponent.value?.demos?.length ?? 0,                                      icon: 'lucide:film',   highlight: false },
    { label: 'Players', value: opponent.value?.players?.length ?? 0,                                    icon: 'lucide:users',  highlight: false },
    { label: 'Maps',    value: analytics.value?.map?.length ?? '—',                                     icon: 'lucide:map',    highlight: false },
    { label: 'Rounds',  value: analytics.value?.total_rounds ?? '—',                                    icon: 'lucide:repeat', highlight: false },
    { label: 'T win %', value: analytics.value ? analytics.value.round_outcomes.t_win_rate + '%' : '—', icon: 'lucide:sword',  highlight: false },
]);

// ── Gauge ─────────────────────────────────────────────────────────────────────
const gaugeColor = computed(() => {
    const s = opponent.value?.ai_insights?.danger_score ?? 0;
    if (s >= 76) return 'oklch(0.62 0.20 22)';   // loss / round-lost red
    if (s >= 51) return '#ff8c00';                // accent / saffron
    if (s >= 26) return 'oklch(0.78 0.15 80)';   // warn / amber
    return 'oklch(0.72 0.14 145)';                // win / sage
});

// ── AI insight rows ───────────────────────────────────────────────────────────
const teamStyleRows = computed(() => {
    const t = opponent.value?.ai_insights?.team_tendencies;
    if (!t) return [];
    return [
        { label: 'Aggression',    value: t.aggression_style,      cap: true  },
        { label: 'Why',           value: t.aggression_reasoning,  cap: false },
        { label: 'Utility',       value: t.utility_style,         cap: true  },
        { label: 'Utility why',   value: t.utility_reasoning,     cap: false },
        { label: 'Clutch threat', value: t.clutch_threat,         cap: true  },
        { label: 'Clutch why',    value: t.clutch_reasoning,      cap: false },
        { label: 'Eco',           value: t.eco_behavior,          cap: false },
    ];
});

// ── Player helpers ────────────────────────────────────────────────────────────
const playerDominanceMap = computed(() => {
    const map: Record<string, any> = {};
    for (const entry of (positionDominanceData.value?.dominance ?? [])) map[String(entry.steamid)] = entry;
    return map;
});
const playerWeaponMap = computed(() => {
    const map: Record<string, any> = {};
    for (const entry of (analytics.value?.weapon_usage ?? [])) map[String(entry.steamid)] = entry;
    return map;
});
const playerFirstKillMap = computed(() => {
    const map: Record<string, any> = {};
    for (const entry of (analytics.value?.first_kill_timing ?? [])) map[String(entry.steamid)] = entry;
    return map;
});

// ── Generic helpers ───────────────────────────────────────────────────────────
const totalClutches  = (p: any) => Object.values(p?.clutches ?? {}).reduce((a: any, b: any) => Number(a) + Number(b), 0) as number;
const getPlayerStat  = (sid: string) => analytics.value?.player_stats?.find((p: any) => p.steamid === sid);
const getAiRole      = (sid: string) => opponent.value?.ai_insights?.key_players?.find((p: any) => p.steamid === sid)?.role_assessment ?? '';
const getAiKeyPlayer = (sid: string) => opponent.value?.ai_insights?.key_players?.find((p: any) => p.steamid === sid) ?? null;
const threatTone     = (level: string) => ({ low: 'win', medium: 'warn', high: 'accent', very_high: 'loss' }[level] ?? 'default');
const ratingTone     = (r: number) => r >= 1.1 ? 'win' : r >= 0.9 ? 'default' : 'loss';
const formatDate     = (iso: string | null | undefined) => iso ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

// ── Modal state ───────────────────────────────────────────────────────────────
const showAddStrat   = ref(false);
const showEditModal  = ref(false);
const showAttachDemo = ref(false);
const demoSearch     = ref('');
const tagInput       = ref('');
const sideOptions    = [{ label: 'T-side', value: 'T' }, { label: 'CT-side', value: 'CT' }];

const linkedDemoIds = computed(() => new Set((opponent.value?.demos ?? []).map((d: any) => d.id)));
const filteredDemos = computed(() => {
    const all = (allDemos.value as any[]) ?? [];
    const q   = demoSearch.value.toLowerCase().trim();
    return all.filter((d: any) => !q || d.name?.toLowerCase().includes(q) || d.map?.toLowerCase().includes(q)).slice(0, 50);
});
const isAlreadyLinked = (demoId: number) => linkedDemoIds.value.has(demoId);

const stratForm = ref<Partial<CounterStrat & { id: number }>>({ name: '', map_id: undefined, side: 'T', description: '', tags: [] });

const openAddStrat = () => {
    stratForm.value    = { name: '', map_id: undefined, side: 'T', description: '', tags: [] };
    tagInput.value     = '';
    showAddStrat.value = true;
};
const addTag    = () => { const tag = tagInput.value.replace(',', '').trim(); if (!tag) return; if (!stratForm.value.tags) stratForm.value.tags = []; if (!stratForm.value.tags.includes(tag)) stratForm.value.tags.push(tag); tagInput.value = ''; };
const removeTag = (tag: string) => { stratForm.value.tags = (stratForm.value.tags ?? []).filter(t => t !== tag); };

const handleAttachDemo      = (demoId: number) => { if (!isAlreadyLinked(demoId)) attachDemo.mutate({ id: id.value, demo_id: demoId }); };
const handleDetachDemo      = (demoId: number) => {
    confirm.require({
        message:     'Unlink this demo from the profile? Its stats will no longer count toward this opponent.',
        header:      'Unlink demo',
        icon:        'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Unlink', severity: 'danger' },
        accept:      () => detachDemo.mutate({ id: id.value, demo_id: demoId }),
    });
};
const handleDeleteStrat     = (stratId: number) => deleteCounterStrat.mutate({ opponentId: id.value, stratId });
const handleRefreshInsights = () => {
    if (!id.value) return;
    // Pro-only feature. Pre-empt the 403 before dispatching the AI job.
    if (!can('ai_antistrat')) {
        openUpgrade({ feature: 'ai_antistrat', requiredTier: 'Pro' });
        return;
    }
    refreshInsights.mutate(id.value, {
        onError: (err: any) => {
            // Raw-fetch mutation: the fetcher attaches the parsed body to the
            // error. Pop the upgrade modal on a plan-limit 403.
            if (err?.body?.app_code === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
                openUpgrade({
                    feature: err.body.feature ?? 'ai_antistrat',
                    requiredTier: err.body.required_tier ?? 'Pro',
                });
            }
        },
    });
};
const handleAddStrat        = () => {
    if (!stratForm.value.name || !stratForm.value.map_id) return;
    addCounterStrat.mutate({ ...stratForm.value, id: id.value }, {
        onSuccess: () => { showAddStrat.value = false; stratForm.value = { name: '', map_id: undefined, side: 'T', description: '', tags: [] }; },
    });
};
</script>

<style scoped>
.otp {
    /* ── App-palette tokens ── */
    --th-bg:        var(--app-canvas, oklch(0.125 0.012 250));
    --th-surface:   var(--app-surface, oklch(0.175 0.014 250));
    --th-surface-2: var(--app-surface-2, oklch(0.215 0.016 250));
    --th-surface-3: var(--app-surface-3, oklch(0.26 0.018 250));
    --th-surface-4: var(--app-surface-4, oklch(0.31 0.02 250));

    --th-border:    var(--app-border, oklch(1 0 0 / 0.09));
    --th-border-2:  var(--app-border-2, oklch(1 0 0 / 0.14));
    --th-border-3:  var(--app-border-3, oklch(1 0 0 / 0.22));

    --th-text:      var(--app-text, oklch(1 0 0 / 0.96));
    --th-text-2:    var(--app-text-2, oklch(1 0 0 / 0.70));
    --th-text-3:    var(--app-text-3, oklch(1 0 0 / 0.62));
    --th-text-4:    var(--app-text-4, oklch(1 0 0 / 0.50));

    --th-accent:    var(--app-accent, #ff8c00);
    --th-accent-2:  oklch(0.62 0.20 50);

    --th-win:       oklch(0.72 0.14 145);
    --th-loss:      oklch(0.62 0.20 22);
    --th-warn:      oklch(0.78 0.15 80);

    --th-sans:      'Schibsted Grotesk', 'Helvetica Neue', Arial, sans-serif;
    --th-mono:      'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: var(--th-bg);
    font-family: var(--th-sans);
    color: var(--th-text);
}

/* ── Header ── */
.otp-header {
    flex-shrink: 0;
    background: var(--th-surface);
    border-bottom: 1px solid var(--th-border);
}

.otp-header__inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 20px 28px 16px;
    flex-wrap: wrap;
}

.otp-header__lead { min-width: 0; }

.otp-header__title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.otp-header__tag {
    font-family: var(--th-mono);
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--th-text-3);
    background: var(--th-surface-3);
    border: 1px solid var(--th-border-2);
    padding: 3px 7px;
    border-radius: 3px;
}

.otp-header__name {
    font-family: var(--th-sans);
    font-size: clamp(1.25rem, 2.2vw, 1.75rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.02em;
    color: var(--th-text);
    margin: 0;
}

.otp-header__notes {
    font-size: 13px;
    color: var(--th-text-2);
    margin: 6px 0 0;
    max-width: 60ch;
}

.otp-header__sublinks {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.otp-header__link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--th-accent);
    text-decoration: none;
}

.otp-header__link:hover { text-decoration: underline; }

.otp-header__shared-by {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-3);
}

.otp-header__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    flex-wrap: wrap;
}

.otp-btn-icon { font-size: 14px; }
.otp-btn-icon--spin { animation: otp-spin 1s linear infinite; }

@keyframes otp-spin {
    to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .otp-btn-icon--spin { animation: none; }
}

/* ── Share control ── */
.otp-share { display: flex; }

.otp-share__select { min-width: 200px; }

.otp-share__value {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--th-text-2);
}

.otp-share__value-icon {
    font-size: 13px;
    color: var(--th-text-3);
}

/* ── Tab bar ── */
.tab-bar {
    display: flex;
    gap: 0;
    padding: 0 28px;
    border-top: 1px solid var(--th-border);
    overflow-x: auto;
    scrollbar-width: none;
    background: var(--th-bg);
}

.tab-bar::-webkit-scrollbar { display: none; }

.tab-btn {
    position: relative;
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    padding: 13px 18px 13px 0;
    margin-right: 28px;
    font-family: var(--th-sans);
    font-size: 14px;
    font-weight: 500;
    color: var(--th-text-3);
    background: none;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: color 150ms ease;
}

.tab-btn:last-child { margin-right: 0; }
.tab-btn:hover { color: var(--th-text-2); }
.tab-btn--active { color: var(--th-text); }

.tab-btn--active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 18px;
    height: 2px;
    background: var(--th-accent);
}

.tab-btn:focus-visible {
    outline: 2px solid var(--th-accent);
    outline-offset: -2px;
    border-radius: 2px;
}

.tab-btn__idx {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 500;
    color: var(--th-text-4);
    letter-spacing: 0.04em;
}

.tab-btn--active .tab-btn__idx { color: var(--th-accent); }

.tab-btn__label { letter-spacing: -0.005em; }

.tab-btn__badge {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 500;
    color: var(--th-text-3);
    background: var(--th-surface-3);
    padding: 1px 5px;
    border-radius: 8px;
    font-variant-numeric: tabular-nums;
}

/* ── Content ── */
.otp-content {
    flex: 1;
    overflow-y: auto;
    background: var(--th-bg);
    scrollbar-width: thin;
    scrollbar-color: var(--th-border-2) transparent;
}

.otp-content::-webkit-scrollbar { width: 6px; }
.otp-content::-webkit-scrollbar-thumb {
    background: var(--th-border-2);
    border-radius: 3px;
}
.otp-content::-webkit-scrollbar-thumb:hover { background: var(--th-border-3); }

.otp-content__inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 28px 64px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── Stat pills ── */
/* 5 columns: statPills currently renders 5 entries (the Danger pill lives in
   the AI Scout gauge, not here). Keep this in sync with statPills length. */
.otp-stats {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1px;
    background: var(--th-border);
    border: 1px solid var(--th-border);
    border-radius: 6px;
    overflow: hidden;
}

.otp-stat {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    background: var(--th-surface);
}

.otp-stat__label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: var(--th-mono);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-3);
}

.otp-stat__icon {
    font-size: 12px;
    color: var(--th-text-4);
}

.otp-stat__value {
    font-family: var(--th-mono);
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
    color: var(--th-text);
    font-variant-numeric: tabular-nums;
}

.otp-stat__value--accent { color: var(--th-accent); }

@media (max-width: 900px) {
    .otp-stats { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 560px) {
    .otp-stats { grid-template-columns: repeat(2, 1fr); }
}

/* ── Banners ── */
.otp-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 6px;
    border: 1px solid;
}

.otp-banner--split { justify-content: space-between; }

.otp-banner--win {
    background: color-mix(in oklch, var(--th-win) 8%, var(--th-surface));
    border-color: color-mix(in oklch, var(--th-win) 28%, transparent);
}

.otp-banner--warn {
    background: color-mix(in oklch, var(--th-warn) 8%, var(--th-surface));
    border-color: color-mix(in oklch, var(--th-warn) 28%, transparent);
}

.otp-banner--loss {
    background: color-mix(in oklch, var(--th-loss) 9%, var(--th-surface));
    border-color: color-mix(in oklch, var(--th-loss) 32%, transparent);
}

.otp-banner__icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: color-mix(in oklch, var(--th-win) 20%, transparent);
    color: var(--th-win);
    font-size: 11px;
}

.otp-banner__spinner {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    border-radius: 50%;
    border: 2px solid color-mix(in oklch, var(--th-warn) 30%, transparent);
    border-top-color: var(--th-warn);
    animation: otp-spin 0.8s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .otp-banner__spinner { animation: none; }
}

.otp-banner__title {
    font-size: 13px;
    font-weight: 600;
    margin: 0;
}

.otp-banner--win .otp-banner__title  { color: var(--th-win); }
.otp-banner--warn .otp-banner__title { color: var(--th-warn); }
.otp-banner--loss .otp-banner__title { color: var(--th-loss); }

.otp-banner__body {
    font-size: 11px;
    margin: 3px 0 0;
    color: var(--th-text-3);
}

/* ── AI scout report ── */
.otp-scout {
    display: flex;
    gap: 16px;
    padding: 18px 20px;
    border-radius: 6px;
    background: color-mix(in oklch, var(--th-accent) 6%, var(--th-surface));
    border: 1px solid color-mix(in oklch, var(--th-accent) 22%, transparent);
}

.otp-scout__gauge {
    position: relative;
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.otp-scout__ring {
    position: absolute;
    inset: 0;
    transform: rotate(-90deg);
}

.otp-scout__ring-fill {
    transition: stroke-dashoffset 600ms ease;
}

@media (prefers-reduced-motion: reduce) {
    .otp-scout__ring-fill { transition: none; }
}

.otp-scout__gauge-text {
    text-align: center;
    z-index: 1;
}

.otp-scout__gauge-value {
    font-family: var(--th-mono);
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    margin: 0;
    font-variant-numeric: tabular-nums;
}

.otp-scout__gauge-label {
    font-family: var(--th-mono);
    font-size: 8px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--th-text-4);
    margin: 2px 0 0;
}

.otp-scout__body { min-width: 0; }

.otp-scout__kicker {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--th-accent);
    margin: 0 0 8px;
}

.otp-scout__summary {
    font-size: 13px;
    line-height: 1.6;
    color: var(--th-text-2);
    margin: 0;
}

.otp-scout__updated {
    font-family: var(--th-mono);
    font-size: 10px;
    color: var(--th-text-4);
    margin: 8px 0 0;
}

/* ── Tabs / panels ── */
.otp-tab {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.otp-tab--map {
    border: 1px solid var(--th-border);
    border-radius: 6px;
    overflow: hidden;
    /* min-height (not fixed height) so the panel fits short laptop viewports
       but can still grow on tall screens. */
    min-height: clamp(420px, 60vh, 680px);
}

.otp-tab--chat {
    min-height: clamp(420px, 56vh, 600px);
}

.otp-panel {
    background: var(--th-surface);
    border: 1px solid var(--th-border);
    border-radius: 6px;
    padding: 18px;
}

.otp-panel--flush { padding: 0; overflow: hidden; }

.otp-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--th-border);
    background: var(--th-surface-2);
}

.otp-panel__title {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text);
    margin: 0;
}

.otp-panel__count {
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-3);
}

.otp-panel__kicker {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--th-text-3);
    margin: 0 0 14px;
}

.otp-chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 900px) {
    .otp-chart-grid { grid-template-columns: 1fr; }
}

.otp-chart__title {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text);
    margin: 0;
}

.otp-chart__sub {
    font-size: 12px;
    color: var(--th-text-3);
    margin: 2px 0 16px;
}

.otp-chart__empty {
    font-size: 12px;
    color: var(--th-text-4);
    text-align: center;
    padding: 8px 0;
    margin: 0;
}

/* ── Player table ── */
.otp-table-wrap {
    overflow-x: auto;
}

.otp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.otp-table__th {
    font-family: var(--th-mono);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--th-text-4);
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid var(--th-border);
}

.otp-table__th--left { text-align: left; padding-left: 18px; }

.otp-table__row {
    border-bottom: 1px solid var(--th-border);
    transition: background 150ms ease;
}

.otp-table__row:last-child { border-bottom: none; }
.otp-table__row:hover { background: var(--th-surface-2); }

.otp-table__td {
    padding: 12px;
    text-align: center;
    color: var(--th-text-2);
}

.otp-table__td--left { text-align: left; padding-left: 18px; }

.otp-table__td--num {
    font-family: var(--th-mono);
    font-variant-numeric: tabular-nums;
}

.otp-rating--win   { color: var(--th-win); }
.otp-rating--loss  { color: var(--th-loss); }
.otp-rating--warn  { color: var(--th-warn); }
.otp-rating--muted { color: var(--th-text-4); }

.otp-player-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.otp-player-cell__avatar {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--th-surface-3);
    font-family: var(--th-mono);
    font-size: 11px;
    font-weight: 700;
    color: var(--th-text-3);
}

.otp-player-cell__name {
    font-size: 13px;
    font-weight: 500;
    color: var(--th-text);
    margin: 0;
    line-height: 1.2;
}

.otp-player-cell__role {
    font-family: var(--th-mono);
    font-size: 10px;
    color: var(--th-text-4);
    margin: 0;
}

/* ── Multi-kill chart ── */
.otp-mk {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.otp-mk__player {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.otp-mk__name {
    font-size: 12px;
    color: var(--th-text-2);
    margin: 0;
}

.otp-mk__bars {
    display: flex;
    gap: 6px;
}

.otp-mk__bar-col { flex: 1; }

.otp-mk__bar-track {
    height: 40px;
    background: var(--th-surface-3);
    border-radius: 4px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    padding: 0 4px 2px;
}

.otp-mk__bar-fill {
    width: 100%;
    height: 100%;
    border-radius: 2px;
    transform-origin: bottom;
    transition: transform 300ms cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
    .otp-mk__bar-fill { transition: none; }
}

.otp-mk__bar-fill--accent { background: var(--th-accent); }
.otp-mk__bar-fill--warn   { background: var(--th-warn); }
.otp-mk__bar-fill--loss   { background: var(--th-loss); }

.otp-mk__bar-label {
    font-family: var(--th-mono);
    font-size: 9px;
    color: var(--th-text-4);
    text-align: center;
    margin: 3px 0 0;
}

/* ── Utility chart ── */
.otp-util {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.otp-util__row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.otp-util__head {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.otp-util__name { color: var(--th-text-2); }
.otp-util__stat { color: var(--th-text-4); font-family: var(--th-mono); }

.otp-util__bars {
    display: flex;
    gap: 4px;
    height: 8px;
}

.otp-util__bar { border-radius: 4px; }
.otp-util__bar--dmg   { background: color-mix(in oklch, var(--th-accent) 70%, transparent); }
.otp-util__bar--flash { background: color-mix(in oklch, var(--th-accent-2) 70%, transparent); }

.otp-util__legend {
    display: flex;
    gap: 16px;
    padding-top: 4px;
}

.otp-util__legend-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    color: var(--th-text-3);
}

.otp-util__legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.otp-util__legend-dot--dmg   { background: var(--th-accent); }
.otp-util__legend-dot--flash { background: var(--th-accent-2); }

/* ── First kill chart ── */
.otp-fk {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.otp-fk__row {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.otp-fk__head {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.otp-fk__name { color: var(--th-text-2); }
.otp-fk__stat { color: var(--th-text-3); font-family: var(--th-mono); }

.otp-fk__track {
    height: 6px;
    background: var(--th-surface-3);
    border-radius: 3px;
    overflow: hidden;
}

.otp-fk__fill {
    width: 100%;
    height: 100%;
    border-radius: 3px;
    transform-origin: left;
    transition: transform 300ms cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
    .otp-fk__fill { transition: none; }
}

.otp-fk__fill--accent { background: var(--th-accent); }
.otp-fk__fill--warn   { background: var(--th-warn); }
.otp-fk__fill--loss   { background: var(--th-loss); }

/* ── Weapons chart ── */
.otp-weapons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.otp-weapons__row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.otp-weapons__name {
    font-size: 12px;
    color: var(--th-text-2);
    width: 80px;
    flex-shrink: 0;
    padding-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.otp-weapons__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.otp-weapons__chip {
    font-family: var(--th-mono);
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    background: var(--th-surface-3);
    color: var(--th-text-2);
}

/* ── Hit distribution ── */
.otp-hit {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.otp-hit__row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.otp-hit__name {
    font-size: 12px;
    color: var(--th-text-2);
    margin: 0;
}

.otp-hit__bar {
    display: flex;
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    background: var(--th-surface-3);
}

.otp-hit__seg--head    { background: var(--th-loss); }
.otp-hit__seg--chest   { background: var(--th-accent); }
.otp-hit__seg--stomach { background: var(--th-warn); }
.otp-hit__seg--arms    { background: var(--th-accent-2); }
.otp-hit__seg--legs    { background: var(--th-text-4); }

.otp-hit__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.otp-hit__legend-item {
    font-family: var(--th-mono);
    font-size: 9px;
}

.otp-hit__legend-item--head    { color: var(--th-loss); }
.otp-hit__legend-item--chest   { color: var(--th-accent); }
.otp-hit__legend-item--stomach { color: var(--th-warn); }
.otp-hit__legend-item--arms    { color: var(--th-accent-2); }

/* ── Kill modifiers ── */
.otp-mods {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.otp-mods__row {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 12px;
}

.otp-mods__name {
    color: var(--th-text-2);
    width: 96px;
    flex-shrink: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.otp-mods__items {
    display: flex;
    gap: 16px;
}

.otp-mods__item {
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-2);
}

.otp-mods__item--off   { color: var(--th-text-4); }
.otp-mods__item--warn  { color: var(--th-warn); }
.otp-mods__item--loss  { color: var(--th-loss); }

/* ── AI insights: team style ── */
.otp-style {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.otp-style__row {
    display: flex;
    gap: 10px;
}

.otp-style__label {
    font-size: 12px;
    color: var(--th-text-3);
    width: 112px;
    flex-shrink: 0;
    margin-top: 2px;
}

.otp-style__value {
    font-size: 12px;
    line-height: 1.6;
    color: var(--th-text-2);
}

.otp-style__value--cap { text-transform: capitalize; }

/* ── AI insights: map intel ── */
.otp-mapintel {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.otp-mapintel__head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.otp-mapintel__name {
    font-size: 13px;
    font-weight: 600;
    color: var(--th-text-2);
}

.otp-mapintel__side {
    font-family: var(--th-mono);
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    background: var(--th-surface-3);
    color: var(--th-text-3);
}

.otp-mapintel__habits {
    display: flex;
    flex-direction: column;
    gap: 5px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.otp-habit {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 12px;
    color: var(--th-warn);
}

.otp-habit__icon {
    flex-shrink: 0;
    margin-top: 2px;
    font-size: 10px;
}

/* ── AI insights: recommendations ── */
.otp-recs {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.otp-rec {
    display: flex;
    gap: 12px;
    padding: 12px;
    border-radius: 5px;
    background: var(--th-surface-2);
    border: 1px solid var(--th-border);
}

.otp-rec__num {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-family: var(--th-mono);
    font-size: 11px;
    font-weight: 700;
    margin-top: 2px;
}

.otp-rec__num--loss  { color: var(--th-loss); background: color-mix(in oklch, var(--th-loss) 18%, transparent); }
.otp-rec__num--warn  { color: var(--th-warn); background: color-mix(in oklch, var(--th-warn) 18%, transparent); }
.otp-rec__num--muted { color: var(--th-text-3); background: var(--th-surface-3); }

.otp-rec__situation {
    font-size: 12px;
    color: var(--th-text-3);
    margin: 0 0 4px;
}

.otp-rec__recommendation {
    font-size: 13px;
    color: var(--th-text-2);
    margin: 0;
}

/* ── AI insights: key players ── */
.otp-keyplayers {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

@media (max-width: 900px) {
    .otp-keyplayers { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 560px) {
    .otp-keyplayers { grid-template-columns: 1fr; }
}

.otp-keyplayer {
    padding: 14px;
    border-radius: 5px;
    background: var(--th-surface-2);
    border: 1px solid var(--th-border);
}

.otp-keyplayer__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}

.otp-keyplayer__name {
    font-size: 13px;
    font-weight: 600;
    color: var(--th-text);
    margin: 0;
}

.otp-keyplayer__role {
    font-family: var(--th-mono);
    font-size: 9px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 3px;
    color: var(--th-accent);
    background: color-mix(in oklch, var(--th-accent) 16%, transparent);
}

.otp-keyplayer__stats {
    display: flex;
    gap: 12px;
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-3);
    margin-bottom: 10px;
}

.otp-keyplayer__watch {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 12px;
    color: var(--th-warn);
    border-top: 1px solid var(--th-border);
    padding-top: 8px;
    margin: 0;
}

.otp-keyplayer__watch-icon {
    flex-shrink: 0;
    margin-top: 2px;
    font-size: 11px;
}

/* ── AI insights: matchups ── */
.otp-matchups {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.otp-matchup {
    display: flex;
    gap: 10px;
    font-size: 13px;
    align-items: flex-start;
}

.otp-matchup__arrow {
    color: var(--th-accent);
    flex-shrink: 0;
    margin-top: 2px;
}

.otp-matchup__player {
    font-weight: 600;
    color: var(--th-text-2);
}

.otp-matchup__advice {
    color: var(--th-text-3);
    margin-left: 4px;
}

/* ── Players grid ── */
.otp-players-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 760px) {
    .otp-players-grid { grid-template-columns: 1fr; }
}

/* ── Demos ── */
.otp-demos__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.otp-demos__count {
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-3);
    margin: 0;
}

.otp-demos__list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.otp-demo {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 6px;
    background: var(--th-surface);
    border: 1px solid var(--th-border);
}

.otp-demo__main {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.otp-demo__badge {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    background: var(--th-surface-3);
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--th-text-3);
}

.otp-demo__identity { min-width: 0; }

.otp-demo__name {
    font-size: 13px;
    font-weight: 500;
    color: var(--th-text);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.otp-demo__meta {
    font-family: var(--th-mono);
    font-size: 11px;
    color: var(--th-text-3);
    margin: 3px 0 0;
}

.otp-demo__actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.otp-demo__view {
    display: inline-flex;
    align-items: center;
    font-family: var(--th-mono);
    font-size: 11px;
    padding: 8px 12px;
    min-height: 32px;
    border-radius: 4px;
    background: var(--th-surface-3);
    color: var(--th-text-2);
    text-decoration: none;
    transition: background 150ms ease;
}

.otp-demo__view:hover { background: var(--th-surface-4); }

.otp-demo__unlink {
    display: inline-flex;
    align-items: center;
    font-family: var(--th-mono);
    font-size: 11px;
    padding: 8px 12px;
    min-height: 32px;
    border-radius: 4px;
    background: none;
    border: none;
    color: var(--th-loss);
    cursor: pointer;
    transition: background 150ms ease;
}

.otp-demo__unlink:hover { background: color-mix(in oklch, var(--th-loss) 14%, transparent); }

/* ── Empty states ── */
.otp-empty {
    padding: 48px 32px;
    text-align: center;
    border: 1px dashed var(--th-border-2);
    border-radius: 6px;
    font-size: 13px;
    color: var(--th-text-3);
}

.otp-empty--icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.otp-empty__icon {
    font-size: 30px;
    color: var(--th-text-4);
}

.otp-empty strong { color: var(--th-text); }

.otp-empty__btn { margin-top: 8px; }

/* ── Load error ── */
.otp-error-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 28px;
}

.otp-error {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 4px;
    max-width: 420px;
    padding: 32px;
    border-radius: 6px;
    border: 1px solid color-mix(in oklch, var(--th-loss) 28%, transparent);
    background: color-mix(in oklch, var(--th-loss) 7%, var(--th-surface));
}

.otp-error__icon {
    font-size: 28px;
    color: var(--th-loss);
    margin-bottom: 4px;
}

.otp-error__headline {
    font-size: 15px;
    font-weight: 600;
    color: var(--th-loss);
    margin: 0;
}

.otp-error__body {
    font-size: 13px;
    color: var(--th-text-3);
    margin: 0;
}

.otp-error__btn { margin-top: 12px; }

/* ── Skeletons ── */
.otp-skel-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.skel {
    display: inline-block;
    background: var(--th-surface-3);
    border-radius: 3px;
    animation: otp-pulse 1.6s ease-in-out infinite;
}

.skel--name { width: 256px; height: 28px; }
.skel--sub  { width: 160px; height: 14px; }

.otp-loading-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.otp-skel-block {
    height: 128px;
    border-radius: 6px;
    background: var(--th-surface);
    border: 1px solid var(--th-border);
    animation: otp-pulse 1.6s ease-in-out infinite;
}

.otp-skel-block--lg { height: 192px; }

@keyframes otp-pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 0.8; }
}

@media (prefers-reduced-motion: reduce) {
    .skel, .otp-skel-block { animation: none; opacity: 0.6; }
}

/* ── Modals ── */
.otp-modal {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 4px;
}

.otp-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.otp-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.otp-label {
    font-family: var(--th-mono);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--th-text-3);
}

.otp-input { width: 100%; }
.otp-input :deep(textarea) { width: 100%; }

.otp-demo-picker {
    max-height: 320px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    scrollbar-width: thin;
    scrollbar-color: var(--th-border-2) transparent;
}

.otp-demo-picker::-webkit-scrollbar { width: 4px; }
.otp-demo-picker::-webkit-scrollbar-thumb {
    background: var(--th-border-2);
    border-radius: 3px;
}

.otp-demo-picker__empty {
    text-align: center;
    font-size: 13px;
    color: var(--th-text-4);
    padding: 32px 0;
}

.otp-demo-pick {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 6px;
    background: var(--th-surface);
    border: 1px solid var(--th-border);
    text-align: left;
    cursor: pointer;
    transition: border-color 150ms ease;
}

.otp-demo-pick:hover:not(:disabled) { border-color: var(--th-border-3); }

.otp-demo-pick--linked {
    background: color-mix(in oklch, var(--th-accent) 8%, var(--th-surface));
    border-color: color-mix(in oklch, var(--th-accent) 28%, transparent);
    opacity: 0.7;
    cursor: not-allowed;
}

.otp-demo-pick__body {
    flex: 1;
    min-width: 0;
}

.otp-demo-pick__tag {
    font-family: var(--th-mono);
    font-size: 10px;
    color: var(--th-accent);
    flex-shrink: 0;
}

.otp-demo-pick__icon {
    font-size: 14px;
    color: var(--th-text-4);
    flex-shrink: 0;
}

.otp-tag-input {
    display: flex;
    gap: 8px;
}

.otp-tag-input__field { flex: 1; }

.otp-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.otp-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--th-mono);
    font-size: 10px;
    padding: 3px 7px;
    border-radius: 4px;
    background: var(--th-surface-3);
    color: var(--th-text-2);
    border: 1px solid var(--th-border);
}

.otp-tag__remove {
    background: none;
    border: none;
    color: var(--th-text-4);
    cursor: pointer;
    font-size: 13px;
    line-height: 1;
    padding: 0;
    transition: color 150ms ease;
}

.otp-tag__remove:hover { color: var(--th-loss); }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 300ms ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active, .fade-leave-active { transition: none; }
}

/* ── Responsive ── */
@media (max-width: 900px) {
    .otp-header__inner { padding: 16px 20px 12px; }
    .tab-bar { padding: 0 20px; }
    .otp-content__inner { padding: 18px 20px 48px; }
}

@media (max-width: 560px) {
    .otp-header__inner { padding: 14px 16px 12px; }
    .tab-bar { padding: 0 16px; }
    .tab-btn { margin-right: 20px; padding-right: 14px; font-size: 13px; }
    .otp-content__inner { padding: 14px 16px 32px; }
    .otp-field-row { grid-template-columns: 1fr; }
}
</style>
