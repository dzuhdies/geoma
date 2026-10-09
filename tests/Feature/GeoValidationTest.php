<?php

namespace Tests\Feature;

use App\Models\ChangedRegion;
use App\Models\ChangeRelation;
use App\Models\Region;
use App\Models\User;
use App\Services\ChangeValidationService;
use App\Services\GeoJsonImportService;
use App\Services\NeighborService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);
    }

    /**
     * Test 1 & 2: GeoJSON Import & Idempotency (no duplicates on re-run)
     */
    public function test_geojson_import_and_idempotency(): void
    {
        $importer = app(GeoJsonImportService::class);
        $result1 = $importer->import();

        $this->assertGreaterThan(0, $result1['total_regions']);
        $this->assertGreaterThan(0, $result1['neighbor_pairs']);

        $totalRegions1 = Region::count();

        // Run import second time
        $result2 = $importer->import();
        $totalRegions2 = Region::count();

        $this->assertEquals($totalRegions1, $totalRegions2, 'Importing twice should not create duplicate regions');
    }

    /**
     * Test 3: Region can be queried by idsubsls
     */
    public function test_region_can_be_found_by_idsubsls(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $firstRegion = Region::first();
        $this->assertNotNull($firstRegion);

        $found = Region::where('idsubsls', $firstRegion->idsubsls)->first();
        $this->assertNotNull($found);
        $this->assertEquals($firstRegion->id, $found->id);
    }

    /**
     * Test 4: Neighbor detection works based on geometry
     */
    public function test_neighbor_detection_works(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $neighborService = app(NeighborService::class);

        // Find a region with neighbors
        $regionWithNeighbors = Region::has('neighbors')->first();
        $this->assertNotNull($regionWithNeighbors, 'There should be regions with neighbors');

        $neighbors = $neighborService->getNeighbors($regionWithNeighbors);
        $this->assertNotEmpty($neighbors);

        $firstNeighborId = $neighbors[0]['id'];
        $this->assertTrue($neighborService->areNeighbors($regionWithNeighbors->id, $firstNeighborId));
    }

    /**
     * Test 5 & 6: Region can be marked changed, and unchanged regions have NO record in changed_regions
     */
    public function test_region_changed_status_storage(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $regionA = Region::first();
        $regionB = Region::skip(1)->first();

        // Initially changed_regions is empty
        $this->assertEquals(0, ChangedRegion::count());

        // Mark region A as changed via API
        $response = $this->postJson("/api/regions/{$regionA->id}/change", [
            'notes' => 'Pemekaran RT baru',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Verify only 1 record in changed_regions table
        $this->assertEquals(1, ChangedRegion::count());
        $this->assertTrue(ChangedRegion::where('region_id', $regionA->id)->exists());
        $this->assertFalse(ChangedRegion::where('region_id', $regionB->id)->exists());
    }

    /**
     * Test 7: Relation can only be created between direct geometric neighbors
     */
    public function test_relation_can_only_be_created_between_neighbors(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $regionA = Region::has('neighbors')->first();
        $neighborB = $regionA->neighbors()->first();

        // Find a non-neighbor region
        $neighborIds = $regionA->neighbors()->pluck('regions.id')->all();
        $neighborIds[] = $regionA->id;
        $nonNeighbor = Region::whereNotIn('id', $neighborIds)->first();

        // 1. Linking neighbor should succeed
        $validResponse = $this->postJson("/api/regions/{$regionA->id}/relations", [
            'target_region_id' => $neighborB->id,
        ]);
        $validResponse->assertStatus(200)->assertJson(['success' => true]);

        // 2. Linking non-neighbor should be rejected with 422
        if ($nonNeighbor) {
            $invalidResponse = $this->postJson("/api/regions/{$regionA->id}/relations", [
                'target_region_id' => $nonNeighbor->id,
            ]);
            $invalidResponse->assertStatus(422)
                ->assertJson(['success' => false]);
        }
    }

    /**
     * Test 8: Directional relations and unreciprocated / asymmetric mismatch warning
     */
    public function test_directional_relations_and_unreciprocated_mismatch_warning(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $validationService = app(ChangeValidationService::class);

        $regionA = Region::has('neighbors')->first();
        $neighborB = $regionA->neighbors()->first();

        // Both marked as changed
        ChangedRegion::create(['region_id' => $regionA->id]);
        ChangedRegion::create(['region_id' => $neighborB->id]);

        // 1. Only A declares relation with B (A -> B)
        $this->postJson("/api/regions/{$regionA->id}/relations", [
            'target_region_id' => $neighborB->id,
        ])->assertStatus(200);

        // A has relation to B, but B has NOT declared relation to A
        $this->assertTrue(ChangeRelation::where('region_id_a', $regionA->id)->where('region_id_b', $neighborB->id)->exists());
        $this->assertFalse(ChangeRelation::where('region_id_a', $neighborB->id)->where('region_id_b', $regionA->id)->exists());

        // System should flag unreciprocated mismatch warning
        $report1 = $validationService->getSystemValidationReport();
        $this->assertTrue(collect($report1['warnings'])->contains(fn($w) => ($w['type'] ?? '') === 'unreciprocated_relation' || str_contains($w['message'], 'belum memilih')));

        // 2. Now B also reciprocates and declares relation with A (B -> A)
        $this->postJson("/api/regions/{$neighborB->id}/relations", [
            'target_region_id' => $regionA->id,
        ])->assertStatus(200);

        // Both sides are changed and mutually declared -> 0 warnings!
        $report2 = $validationService->getSystemValidationReport();
        $this->assertEquals(0, $report2['summary']['total_warnings']);

        $warningsA = $validationService->getWarningsForRegion($regionA);
        $this->assertEmpty($warningsA);
    }

    /**
     * Test 9: Warning appears if A changed with B, but B is not marked as changed
     */
    public function test_warning_when_partner_not_marked_as_changed(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $validationService = app(ChangeValidationService::class);

        $regionA = Region::has('neighbors')->first();
        $neighborB = $regionA->neighbors()->first();

        // Mark only Region A as changed
        ChangedRegion::create(['region_id' => $regionA->id]);

        // A declares relation with B
        ChangeRelation::createDirectional($regionA->id, $neighborB->id);

        // Region A warnings should flag partner B not changed
        $warnings = $validationService->getWarningsForRegion($regionA);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('belum ditandai sebagai berubah', $warnings[0]['message']);

        // System validation report should contain warning
        $report = $validationService->getSystemValidationReport();
        $this->assertEquals(1, $report['summary']['total_warnings']);
    }

    /**
     * Test 10: Warning when a region is marked changed but has selected 0 relations
     */
    public function test_warning_when_changed_with_no_relations(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $validationService = app(ChangeValidationService::class);

        $regionA = Region::has('neighbors')->first();

        // Mark A as changed with NO relations
        ChangedRegion::create(['region_id' => $regionA->id]);

        $warningsA = $validationService->getWarningsForRegion($regionA);
        $this->assertNotEmpty($warningsA);
        $this->assertStringContainsString('belum menentukan RT', $warningsA[0]['message']);

        $report = $validationService->getSystemValidationReport();
        $this->assertEquals(1, $report['summary']['total_warnings']);
    }

    /**
     * Test 11: Unmarking change status handles relations correctly
     */
    public function test_unmarking_change_status(): void
    {
        $importer = app(GeoJsonImportService::class);
        $importer->import();

        $regionA = Region::has('neighbors')->first();
        $neighborB = $regionA->neighbors()->first();

        ChangedRegion::create(['region_id' => $regionA->id]);
        ChangeRelation::createDirectional($regionA->id, $neighborB->id);

        // Unmark A as changed with cleanup_relations = true
        $response = $this->deleteJson("/api/regions/{$regionA->id}/change", [
            'cleanup_relations' => true,
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertFalse(ChangedRegion::where('region_id', $regionA->id)->exists());
        $this->assertFalse(ChangeRelation::where('region_id_a', $regionA->id)->where('region_id_b', $neighborB->id)->exists());
    }
}
