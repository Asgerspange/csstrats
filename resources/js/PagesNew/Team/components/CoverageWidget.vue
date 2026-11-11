<script setup lang="ts">
import { computed } from 'vue';
import { useTeamTactics, type TeamTactic } from '@/composables/useTeamTactics';
import useMaps from '@/composables/useMaps';

const { data: tactics, isLoading } = useTeamTactics();
const { Maps } = useMaps();
const { data: mapsData } = Maps.get();

const activeMaps = computed<string[]>(() => {
    const list = mapsData.value ?? [];
    return list.filter((m) => m.active).map((m) => m.display_name);
});

interface CoverageCell {
    map: string;
    t: number;
    ct: number;
}

const coverage = computed<CoverageCell[]>(() => {
    const list = tactics.value ?? [];
    const mapNames = activeMaps.value.length > 0
        ? activeMaps.value
        : [...new Set(list.map((t) => t.map))];

    return mapNames.map((mapName) => {
        const mapTactics = list.filter((t) => t.map === mapName);
        return {
            map: mapName,
            t: mapTactics.filter((t) => t.side === 'T').length,
            ct: mapTactics.filter((t) => t.side === 'CT').length,
        };
    });
});
</script>

<template>
    <div class="widget">
        <h2 class="widget__title">Tactic coverage by map</h2>
        <p v-if="isLoading" class="widget__loading">Loading...</p>
        <table v-else-if="coverage.length > 0" class="coverage-table">
            <thead>
                <tr>
                    <th scope="col" class="coverage-table__th">Map</th>
                    <th scope="col" class="coverage-table__th coverage-table__th--num">T</th>
                    <th scope="col" class="coverage-table__th coverage-table__th--num">CT</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="cell in coverage" :key="cell.map">
                    <td class="coverage-table__cell coverage-table__cell--map">{{ cell.map }}</td>
                    <td class="coverage-table__cell coverage-table__cell--num tabular-nums" :class="{ 'coverage-table__cell--gap': cell.t === 0 }">{{ cell.t }}</td>
                    <td class="coverage-table__cell coverage-table__cell--num tabular-nums" :class="{ 'coverage-table__cell--gap': cell.ct === 0 }">{{ cell.ct }}</td>
                </tr>
            </tbody>
        </table>
        <p v-else class="widget__empty">No tactics yet.</p>
    </div>
</template>

<style scoped>
.widget__title {
    font-size: 0.75rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.widget__empty {
    font-size: 0.75rem;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
}

.coverage-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
}

.coverage-table__th {
    text-align: left;
    font-weight: 600;
    color: var(--app-text-3, oklch(1 0 0 / 0.50));
    padding: 0.375rem 0.5rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.09));
}

.coverage-table__th--num {
    text-align: right;
    width: 3rem;
}

.coverage-table__cell {
    padding: 0.375rem 0.5rem;
    border-bottom: 1px solid var(--app-border, oklch(1 0 0 / 0.06));
    color: var(--app-text-2, oklch(1 0 0 / 0.70));
}

.coverage-table__cell--map {
    font-weight: 500;
    color: var(--app-text, oklch(1 0 0 / 0.96));
}

.coverage-table__cell--num {
    text-align: right;
}

.coverage-table__cell--gap {
    color: oklch(0.58 0.18 22 / 0.6);
}
</style>
