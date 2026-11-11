<?php

namespace App\Services;

use App\Models\Map;
use Illuminate\Support\Collection;

/**
 * Hardened map-name-to-ID resolution. Builds a cached lookup from the maps
 * table that handles display_name ("Mirage"), console name ("de_mirage"),
 * and known aliases ("Dust 2" -> Dust II, "dust2" -> Dust II).
 *
 * Case-insensitive. O(1) lookup. No SQL at check time.
 *
 * Used by EntitlementService and CheckMapAccess middleware to resolve the
 * map from request data (which may be a display_name string on tactics or a
 * console name on demos).
 */
class MapService
{
    /** @var array<string, int>|null */
    private ?array $lookup = null;

    /** @var Collection<int, Map>|null */
    private ?Collection $activeMaps = null;

    /**
     * Resolve a map string (display_name, console name, or alias) to a map ID.
     * Returns null if the string doesn't match any known map.
     */
    public function nameToId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        return $this->lookup()[strtolower(trim($name))] ?? null;
    }

    /**
     * Resolve a map ID to its display_name. Returns null if the ID doesn't exist.
     */
    public function idToDisplayName(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return $this->allMaps()->firstWhere('id', $id)?->display_name;
    }

    /**
     * All active maps, ordered by ID. Cached for the request.
     *
     * @return Collection<int, Map>
     */
    public function activeMaps(): Collection
    {
        if ($this->activeMaps === null) {
            $this->activeMaps = Map::active()->orderBy('id')->get();
        }

        return $this->activeMaps;
    }

    /**
     * The first N active map IDs by ID order. Used for default selection.
     */
    public function defaultMapIds(int $count): array
    {
        return $this->activeMaps()
            ->take($count)
            ->pluck('id')
            ->toArray();
    }

    /**
     * All maps (active + inactive). Cached for the request.
     *
     * @return Collection<int, Map>
     */
    public function allMaps(): Collection
    {
        return Map::orderBy('id')->get();
    }

    /**
     * Build the case-insensitive lookup table. Keys are lowercase strings,
     * values are map IDs. Includes display_name, console name, and aliases.
     *
     * @return array<string, int>
     */
    private function lookup(): array
    {
        if ($this->lookup !== null) {
            return $this->lookup;
        }

        $this->lookup = [];
        foreach ($this->allMaps() as $map) {
            $this->lookup[strtolower($map->name)] = $map->id;
            $this->lookup[strtolower($map->display_name)] = $map->id;
        }

        // Known aliases — maps that have been called different things.
        $aliases = [
            'dust 2' => 2,   // "Dust 2" (seeder's old name) -> Dust II
            'dust2' => 2,   // compact form used in some frontend code
        ];

        foreach ($aliases as $alias => $id) {
            if (! isset($this->lookup[$alias])) {
                $this->lookup[$alias] = $id;
            }
        }

        return $this->lookup;
    }
}
