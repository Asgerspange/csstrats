<?php

namespace Tests\Unit;

use App\Enums\EntitlementMode;
use App\Models\Map;
use App\Models\MapSelectionAudit;
use App\Models\Stratbook;
use App\Models\Team;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\MapProvisioner;
use App\Services\MapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementService $entitlement;
    private MapService $mapService;
    private MapProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entitlement = app(EntitlementService::class);
        $this->mapService = app(MapService::class);
        $this->provisioner = app(MapProvisioner::class);
    }

    public function test_map_name_to_id_resolves_display_names(): void
    {
        $this->createTestMaps();

        $this->assertEquals(1, $this->mapService->nameToId('Mirage'));
        $this->assertEquals(2, $this->mapService->nameToId('Dust II'));
        $this->assertEquals(3, $this->mapService->nameToId('Inferno'));
    }

    public function test_map_name_to_id_resolves_console_names(): void
    {
        $this->createTestMaps();

        $this->assertEquals(1, $this->mapService->nameToId('de_mirage'));
        $this->assertEquals(2, $this->mapService->nameToId('de_dust2'));
    }

    public function test_map_name_to_id_handles_aliases(): void
    {
        $this->createTestMaps();

        $this->assertEquals(2, $this->mapService->nameToId('Dust 2'));
        $this->assertEquals(2, $this->mapService->nameToId('dust2'));
    }

    public function test_map_name_to_id_is_case_insensitive(): void
    {
        $this->createTestMaps();

        $this->assertEquals(1, $this->mapService->nameToId('MIRAGE'));
        $this->assertEquals(2, $this->mapService->nameToId('dust ii'));
    }

    public function test_map_name_to_id_returns_null_for_unknown(): void
    {
        $this->createTestMaps();

        $this->assertNull($this->mapService->nameToId('nonexistent'));
        $this->assertNull($this->mapService->nameToId(null));
        $this->assertNull($this->mapService->nameToId(''));
    }

    public function test_default_map_ids_returns_first_n_active_by_id(): void
    {
        $this->createTestMaps();

        $ids = $this->mapService->defaultMapIds(4);
        $this->assertCount(4, $ids);
        $this->assertEquals([1, 2, 3, 4], $ids);
    }

    public function test_provisioning_creates_one_stratbook_per_active_map(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();

        $this->provisioner->provision($team);

        $count = $team->stratbooks()->whereNotNull('map_id')->count();
        $activeCount = Map::active()->count();
        $this->assertEquals($activeCount, $count);
    }

    public function test_provisioning_is_idempotent(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();

        $this->provisioner->provision($team);
        $before = $team->stratbooks()->count();

        $this->provisioner->provision($team);
        $after = $team->stratbooks()->count();

        $this->assertEquals($before, $after);
    }

    public function test_unique_constraint_on_team_id_map_id(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();
        $this->provisioner->provision($team);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Stratbook::create([
            'team_id' => $team->id,
            'map_id' => 1,
            'stratbook_name' => 'Duplicate',
            'created_by' => $team->owned_by,
        ]);
    }

    public function test_legacy_team_has_access_to_all_maps(): void
    {
        $this->createTestMaps();
        $team = Team::factory()->create([
            'entitlement_mode' => EntitlementMode::Legacy->value,
        ]);

        foreach (Map::active()->get() as $map) {
            $this->assertTrue($this->entitlement->canAccessMap($team, $map->id));
            $this->assertTrue($this->entitlement->canEditMap($team, $map->id));
        }
    }

    public function test_map_based_team_with_pro_plan_has_all_maps(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();

        // Pro plan (max_maps = null) — all maps accessible
        foreach (Map::active()->get() as $map) {
            $this->assertTrue($this->entitlement->canAccessMap($team, $map->id));
            $this->assertTrue($this->entitlement->canEditMap($team, $map->id));
        }
    }

    public function test_accessible_map_ids_returns_all_for_pro(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();

        $accessible = $this->entitlement->accessibleMapIds($team);
        $this->assertCount(Map::active()->count(), $accessible);
    }

    public function test_accessible_map_ids_returns_all_for_legacy(): void
    {
        $this->createTestMaps();
        $team = Team::factory()->create([
            'entitlement_mode' => EntitlementMode::Legacy->value,
        ]);

        $accessible = $this->entitlement->accessibleMapIds($team);
        $this->assertCount(Map::active()->count(), $accessible);
    }

    public function test_stratbook_map_relationship_works(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();
        $this->provisioner->provision($team);

        $sb = $team->stratbooks()->whereNotNull('map_id')->first();
        $this->assertNotNull($sb->map);
        $this->assertEquals($sb->map_id, $sb->map->id);
    }

    public function test_team_selected_maps_relationship(): void
    {
        $this->createTestMaps();
        $team = $this->createMapBasedTeam();
        $defaultIds = $this->mapService->defaultMapIds(4);
        $team->selectedMaps()->sync($defaultIds);

        $selected = $team->selectedMaps()->get();
        $this->assertCount(4, $selected);
        $this->assertEquals($defaultIds, $selected->pluck('id')->toArray());
    }

    // ── Helpers ──

    private function createTestMaps(): void
    {
        $maps = [
            ['name' => 'de_mirage', 'display_name' => 'Mirage', 'active' => true],
            ['name' => 'de_dust2', 'display_name' => 'Dust II', 'active' => true],
            ['name' => 'de_inferno', 'display_name' => 'Inferno', 'active' => true],
            ['name' => 'de_overpass', 'display_name' => 'Overpass', 'active' => true],
            ['name' => 'de_ancient', 'display_name' => 'Ancient', 'active' => true],
        ];

        foreach ($maps as $map) {
            Map::create(array_merge($map, [
                'slug' => null,
                'image_path' => '/images/demo/' . $map['name'] . '.svg',
                'width' => 1024,
                'height' => 1024,
            ]));
        }
    }

    private function createMapBasedTeam(): Team
    {
        $user = User::factory()->create();

        $team = Team::create([
            'name' => 'Test Team',
            'owned_by' => $user->id,
            'entitlement_mode' => EntitlementMode::MapBased->value,
            'map_lock_expires_at' => now()->addDays(30),
        ]);

        return $team;
    }
}
