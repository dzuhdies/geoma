<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use App\Services\GeoJsonImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(GeoJsonImportService::class)->import();
    }

    /**
     * Test Guest can access dashboard and view components without login
     */
    public function test_guest_can_view_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Login');
        $response->assertSee('Daftar Perubahan');
        $response->assertSee('warning');
    }

    /**
     * Test Guest can read data from all public read-only endpoints
     */
    public function test_guest_can_access_read_endpoints(): void
    {
        $region = Region::first();
        $this->assertNotNull($region);

        // Filter options
        $this->getJson('/api/filter-options')->assertStatus(200);

        // Search
        $this->getJson('/api/search?q=Sukun')->assertStatus(200);

        // Region geojson
        $this->getJson('/api/regions')->assertStatus(200);

        // Region details & neighbors
        $this->getJson("/api/regions/{$region->id}")->assertStatus(200);
        $this->getJson("/api/regions/{$region->id}/neighbors")->assertStatus(200);

        // Changed regions list
        $this->getJson('/api/changed-regions')->assertStatus(200);

        // Validation list
        $this->getJson('/api/validation')->assertStatus(200);

        // Exports
        $this->get('/api/changed-regions/export')->assertStatus(200);
        $this->get('/api/validation/export')->assertStatus(200);
    }

    /**
     * Test Guest is prevented from modifying region data (returns 401)
     */
    public function test_guest_cannot_mutate_region_data(): void
    {
        $region = Region::first();
        $this->assertNotNull($region);

        // Try marking changed without login
        $res1 = $this->postJson("/api/regions/{$region->id}/change", ['notes' => 'Hack']);
        $res1->assertStatus(401);

        // Try removing change without login
        $res2 = $this->deleteJson("/api/regions/{$region->id}/change");
        $res2->assertStatus(401);

        // Try adding relation without login
        $res3 = $this->postJson("/api/regions/{$region->id}/relations", ['target_region_id' => 999]);
        $res3->assertStatus(401);

        // Try deleting relation without login
        $res4 = $this->deleteJson("/api/regions/{$region->id}/relations", ['target_region_id' => 999]);
        $res4->assertStatus(401);
    }

    /**
     * Test User Login with username and password
     */
    public function test_user_login_flow(): void
    {
        $user = User::create([
            'username' => 'operator1',
            'name' => 'Operator Satu',
            'email' => 'operator1@geoma.local',
            'password' => Hash::make('secret123'),
        ]);

        // Failed login
        $failRes = $this->postJson('/api/auth/login', [
            'username' => 'operator1',
            'password' => 'wrongpassword',
        ]);
        $failRes->assertStatus(422);
        $failRes->assertJson([
            'success' => false,
            'message' => 'Username atau password salah.',
        ]);

        // Successful login
        $successRes = $this->postJson('/api/auth/login', [
            'username' => 'operator1',
            'password' => 'secret123',
        ]);
        $successRes->assertStatus(200);
        $successRes->assertJson([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => 'operator1',
            ],
        ]);
        $this->assertAuthenticatedAs($user);

        // Check me endpoint
        $meRes = $this->getJson('/api/auth/me');
        $meRes->assertStatus(200);
        $meRes->assertJson([
            'authenticated' => true,
            'user' => [
                'username' => 'operator1',
            ],
        ]);
    }

    /**
     * Test Authenticated User can mark changed and manage relations
     */
    public function test_authenticated_user_can_modify_data(): void
    {
        $user = User::factory()->create(['username' => 'editor_user']);
        $this->actingAs($user);

        $region = Region::first();
        $this->assertNotNull($region);

        // Mark changed
        $res = $this->postJson("/api/regions/{$region->id}/change", ['notes' => 'Diubah']);
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseHas('changed_regions', [
            'region_id' => $region->id,
        ]);
    }

    /**
     * Test Authenticated User can add another account (Tambah Akun)
     */
    public function test_authenticated_user_can_register_new_account(): void
    {
        $admin = User::factory()->create(['username' => 'admin_utama']);
        $this->actingAs($admin);

        $res = $this->postJson('/api/auth/register', [
            'username' => 'editor_baru',
            'name' => 'Editor Baru Malang',
            'password' => 'password123',
        ]);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'user' => [
                'username' => 'editor_baru',
                'name' => 'Editor Baru Malang',
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'editor_baru',
        ]);

        // Cannot create duplicate username
        $dupRes = $this->postJson('/api/auth/register', [
            'username' => 'editor_baru',
            'password' => 'password123',
        ]);
        $dupRes->assertStatus(422);
    }

    /**
     * Test Guest cannot call register endpoint directly
     */
    public function test_guest_cannot_register_account(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'username' => 'hacker',
            'password' => 'password123',
        ]);
        $res->assertStatus(401);
    }

    /**
     * Test User Logout
     */
    public function test_user_logout(): void
    {
        $user = User::factory()->create(['username' => 'logout_user']);
        $this->actingAs($user);

        $res = $this->postJson('/api/auth/logout');
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertGuest();
    }
}
