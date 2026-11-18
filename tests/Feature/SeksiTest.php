<?php

namespace Tests\Feature;

use App\Models\Seksi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeksiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_seksi_index(): void
    {
        $response = $this->get('/seksi');
        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_gets_forbidden_on_seksi_index(): void
    {
        $kasi = User::where('username', 'kasiastik')->first();
        $pjlp = User::where('username', 'ikhwan')->first();

        $this->actingAs($kasi)->get('/seksi')->assertStatus(403);
        $this->actingAs($pjlp)->get('/seksi')->assertStatus(403);
    }

    public function test_superadmin_can_access_seksi_index(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->get('/seksi');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Seksi/Index')
            ->has('seksiList.data')
        );
    }

    public function test_superadmin_can_create_new_seksi(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->post('/seksi', [
            'nama' => 'Seksi Perencanaan & Pengembangan',
            'kode' => 'SEKSI-BANG',
            'deskripsi' => 'Unit pengembangan sistem & inovasi.',
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('seksi', [
            'nama' => 'Seksi Perencanaan & Pengembangan',
            'kode' => 'SEKSI-BANG',
            'is_active' => 1,
        ]);
    }

    public function test_validation_prevents_duplicate_seksi_kode(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        // Kode ASTIK already seeded
        $response = $this->actingAs($superadmin)->post('/seksi', [
            'nama' => 'Seksi Duplikat',
            'kode' => 'ASTIK',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['kode']);
    }

    public function test_superadmin_can_update_seksi(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $seksi = Seksi::where('kode', 'ASTIK')->first();

        $response = $this->actingAs($superadmin)->put("/seksi/{$seksi->id}", [
            'nama' => 'Seksi ASTIK & Persandian Terpadu',
            'kode' => 'ASTIK',
            'deskripsi' => 'Deskripsi baru seksi ASTIK.',
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('seksi', [
            'id' => $seksi->id,
            'nama' => 'Seksi ASTIK & Persandian Terpadu',
        ]);
    }

    public function test_superadmin_can_toggle_seksi_status(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $seksi = Seksi::where('kode', 'ASTIK')->first();
        $initialStatus = $seksi->is_active;

        $response = $this->actingAs($superadmin)->patch("/seksi/{$seksi->id}/toggle");
        $response->assertSessionHas('success');

        $seksi->refresh();
        $this->assertEquals(!$initialStatus, (bool) $seksi->is_active);
    }

    public function test_superadmin_can_soft_delete_seksi(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $seksi = Seksi::create([
            'nama' => 'Unit Sementara',
            'kode' => 'UNIT-TEMPORER',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superadmin)->delete("/seksi/{$seksi->id}");
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('seksi', [
            'id' => $seksi->id,
            'kode' => 'UNIT-TEMPORER',
        ]);
    }
}
