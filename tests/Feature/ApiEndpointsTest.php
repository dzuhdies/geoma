<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use App\Services\GeoJsonImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(GeoJsonImportService::class)->import();

        $this->user = User::factory()->create([
            'username' => 'admin_test',
        ]);
        $this->actingAs($this->user);
    }

    /**
     * Test Dashboard page renders successfully
     */
    public function test_dashboard_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('GeoMA');
        $response->assertSee('Validasi Batas RT/RW');
    }

    /**
     * Test Filter Options API returns cascading values
     */
    public function test_filter_options_api(): void
    {
        $firstRegion = Region::whereNotNull('nmkec')->first();
        $this->assertNotNull($firstRegion);

        // 1. Get Desas for a Kecamatan
        $response = $this->getJson("/api/filter-options?kecamatan=" . urlencode($firstRegion->nmkec));
        $response->assertStatus(200);
        $response->assertJsonStructure(['desas', 'rws', 'rts']);
        $this->assertNotEmpty($response->json('desas'));

        // 2. Get RWs for Kecamatan + Desa
        $response = $this->getJson("/api/filter-options?kecamatan=" . urlencode($firstRegion->nmkec) . "&desa=" . urlencode($firstRegion->nmdesa));
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('rws'));
    }

    /**
     * Test GeoJSON API returns valid FeatureCollection
     */
    public function test_geojson_api_returns_valid_features(): void
    {
        $firstRegion = Region::whereNotNull('nmkec')->first();

        $response = $this->getJson("/api/regions?kecamatan=" . urlencode($firstRegion->nmkec));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'type',
            'features' => [
                '*' => [
                    'type',
                    'id',
                    'properties' => ['id', 'nmsls', 'idsubsls', 'is_changed'],
                    'geometry' => ['type', 'coordinates'],
                ]
            ]
        ]);
    }

    /**
     * Test Region detail and neighbors endpoints
     */
    public function test_region_detail_and_neighbors_endpoints(): void
    {
        $region = Region::has('neighbors')->first();
        $this->assertNotNull($region);

        // Detail endpoint
        $detailResponse = $this->getJson("/api/regions/{$region->id}");
        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonStructure([
            'id',
            'idsubsls',
            'nmsls',
            'is_changed',
            'neighbors',
            'related_region_ids',
            'warnings',
            'geometry'
        ]);

        // Neighbors endpoint
        $neighborsResponse = $this->getJson("/api/regions/{$region->id}/neighbors");
        $neighborsResponse->assertStatus(200);
        $neighborsResponse->assertJsonStructure([
            'region_id',
            'region_name',
            'neighbors' => [
                '*' => ['id', 'idsubsls', 'nmsls', 'is_changed', 'is_relation']
            ]
        ]);
    }

    /**
     * Test Validation summary endpoint
     */
    public function test_validation_summary_endpoint(): void
    {
        $response = $this->getJson('/api/validation');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => [
                'total_regions',
                'total_changed',
                'total_relations',
                'total_warnings',
            ],
            'warnings'
        ]);
    }

    /**
     * Test Search by ID / subsls and name endpoint
     */
    public function test_search_by_id_and_name_endpoint(): void
    {
        $firstRegion = Region::first();
        $this->assertNotNull($firstRegion);

        // Search by idsubsls
        $response = $this->getJson('/api/search?q=' . urlencode($firstRegion->idsubsls));
        $response->assertStatus(200);
        $results = $response->json();
        $this->assertNotEmpty($results);
        $this->assertEquals($firstRegion->id, $results[0]['id']);

        // Search by numeric primary ID
        $responseId = $this->getJson('/api/search?q=' . $firstRegion->id);
        $responseId->assertStatus(200);
        $this->assertNotEmpty($responseId->json());
    }

    /**
     * Test Changed Regions endpoint
     */
    public function test_changed_regions_endpoint(): void
    {
        $region = Region::first();
        $this->assertNotNull($region);

        // Mark as changed
        $this->postJson("/api/regions/{$region->id}/change", ['notes' => 'Catatan uji']);

        $response = $this->getJson('/api/changed-regions');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total',
            'data' => [
                '*' => [
                    'id',
                    'region_id',
                    'idsubsls',
                    'name',
                    'nmdesa',
                    'nmkec',
                    'notes',
                    'status',
                    'partners_count',
                    'partners',
                ]
            ]
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('total'));
        $found = collect($response->json('data'))->firstWhere('region_id', $region->id);
        $this->assertNotNull($found);
        $this->assertEquals('Catatan uji', $found['notes']);
    }

    /**
     * Test Validation Export CSV endpoint
     */
    public function test_validation_export_csv_endpoint(): void
    {
        $region = Region::first();
        $this->assertNotNull($region);

        // Mark region changed without relation to produce a warning
        $this->postJson("/api/regions/{$region->id}/change", ['notes' => 'Tanpa pasangan']);

        $response = $this->get('/api/validation/export');
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('"ID SLS",Kecamatan,Kelurahan,RW,RT,Status,"Dengan ID SLS","Dengan RW","Dengan RT","Dengan Kelurahan","Warning Message"', $content);
        $this->assertStringContainsString($region->nmkec, $content);
        $this->assertStringContainsString('Belum Pilih Pasangan', $content);
    }

    /**
     * Test Changed Regions Export CSV endpoint
     */
    public function test_changed_regions_export_csv_endpoint(): void
    {
        $region = Region::first();
        $this->assertNotNull($region);

        // Mark as changed
        $this->postJson("/api/regions/{$region->id}/change", ['notes' => 'Catatan uji export']);

        $response = $this->get('/api/changed-regions/export');
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('"ID SLS",Kecamatan,Kelurahan,"RT RW Berubah","RT RW Berubah Dengan",Status', $content);
        $this->assertStringContainsString($region->nmkec, $content);
        $this->assertStringContainsString($region->nmdesa, $content);
        $this->assertStringContainsString('-', $content); // No partner -> '-'
    }
}
