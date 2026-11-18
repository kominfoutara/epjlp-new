<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_users_index(): void
    {
        $response = $this->get('/users');
        $response->assertRedirect('/login');
    }

    public function test_non_superadmin_gets_forbidden_on_users_index(): void
    {
        $kasi = User::where('username', 'kasiastik')->first();
        $pjlp = User::where('username', 'ikhwan')->first();

        $this->actingAs($kasi)->get('/users')->assertStatus(403);
        $this->actingAs($pjlp)->get('/users')->assertStatus(403);
    }

    public function test_superadmin_can_access_users_index(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->get('/users');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->has('seksiList')
            ->has('roles')
        );
    }

    public function test_superadmin_can_create_new_pjlp_user(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->post('/users', [
            'nama_lengkap' => 'Budi Santoso',
            'username' => 'budisantoso',
            'email' => 'budisantoso@epjlp.com',
            'password' => 'secret1234',
            'role' => 'PJLP',
            'seksi' => 'Astik',
            'nik' => '3172010101900001',
            'no_wa' => '081234567890',
            'jabatan' => 'Tenaga Teknis Jaringan',
            'nrk' => 'PJLP-0099',
            'pin' => '80799999',
            'status_user' => 'Aktif',
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
            'jenis_kelamin' => 'Laki-laki',
            'status_pernikahan' => 'Menikah',
            'pendidikan_terakhir' => 'Sarjana Terapan / Sarjana (D4 / S1)',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'username' => 'budisantoso',
            'email' => 'budisantoso@epjlp.com',
            'role' => 'PJLP',
            'seksi' => 'Astik',
            'nik' => '3172010101900001',
            'status_user' => 'Aktif',
            'jenis_kelamin' => 'Laki-laki',
            'status_pernikahan' => 'Menikah',
            'pendidikan_terakhir' => 'Sarjana Terapan / Sarjana (D4 / S1)',
        ]);

        $newUser = User::where('username', 'budisantoso')->first();
        $this->assertTrue(Hash::check('secret1234', $newUser->password));
    }

    public function test_superadmin_can_create_new_kasi_user(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->post('/users', [
            'nama_lengkap' => 'Drs. Hendra Setiawan',
            'username' => 'kasiid_new',
            'email' => 'hendra@epjlp.com',
            'password' => 'password123',
            'role' => 'Kasi',
            'seksi' => 'ID',
            'nik' => '3172010101750002',
            'no_wa' => '081298765432',
            'jabatan' => 'Kepala Seksi Infrastruktur Digital',
            'nrk' => '197501012000031001',
            'status_user' => 'Aktif',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'username' => 'kasiid_new',
            'role' => 'Kasi',
            'seksi' => 'ID',
        ]);
    }

    public function test_user_creation_validates_uniqueness(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        // Existing username & email
        $response = $this->actingAs($superadmin)->post('/users', [
            'nama_lengkap' => 'Duplikat User',
            'username' => 'ikhwan', // Already taken
            'email' => 'ichwanprasetya@gmail.com', // Already taken
            'password' => 'secret123',
            'role' => 'PJLP',
            'seksi' => 'Astik',
            'status_user' => 'Aktif',
        ]);

        $response->assertSessionHasErrors(['username', 'email']);
    }

    public function test_superadmin_can_update_user(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $pjlp = User::where('username', 'ikhwan')->first();

        $response = $this->actingAs($superadmin)->put("/users/{$pjlp->id}", [
            'nama_lengkap' => 'Ikhwan Prasetya, S.Kom',
            'username' => 'ikhwan',
            'email' => 'ikhwan.update@jakarta.go.id',
            'role' => 'PJLP',
            'seksi' => 'Astik',
            'status_user' => 'Aktif',
            'jabatan' => 'Senior Application Engineer PJLP',
            'jenis_kelamin' => 'Laki-laki',
            'status_pernikahan' => 'Belum Menikah',
            'pendidikan_terakhir' => 'Sarjana Terapan / Sarjana (D4 / S1)',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $pjlp->id,
            'nama_lengkap' => 'Ikhwan Prasetya, S.Kom',
            'email' => 'ikhwan.update@jakarta.go.id',
            'jabatan' => 'Senior Application Engineer PJLP',
            'jenis_kelamin' => 'Laki-laki',
            'status_pernikahan' => 'Belum Menikah',
            'pendidikan_terakhir' => 'Sarjana Terapan / Sarjana (D4 / S1)',
        ]);
    }

    public function test_superadmin_can_reset_user_password(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $pjlp = User::where('username', 'ikhwan')->first();

        $response = $this->actingAs($superadmin)->patch("/users/{$pjlp->id}/reset-password", [
            'password' => 'newPassword2026',
        ]);

        $response->assertSessionHas('success');

        $pjlp->refresh();
        $this->assertTrue(Hash::check('newPassword2026', $pjlp->password));

        // Verify login works with new password
        $loginResponse = $this->post('/login', [
            'email' => 'ikhwan',
            'password' => 'newPassword2026',
        ]);
        $loginResponse->assertRedirect('/dashboard');
    }

    public function test_superadmin_can_toggle_user_status(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $pjlp = User::where('username', 'ikhwan')->first();

        $this->assertEquals('Aktif', $pjlp->status_user);

        // Toggle to Suspend
        $response = $this->actingAs($superadmin)->patch("/users/{$pjlp->id}/toggle-status");
        $response->assertSessionHas('success');

        $pjlp->refresh();
        $this->assertEquals('Suspend', $pjlp->status_user);

        // Toggle back to Aktif
        $this->actingAs($superadmin)->patch("/users/{$pjlp->id}/toggle-status");
        $pjlp->refresh();
        $this->assertEquals('Aktif', $pjlp->status_user);
    }

    public function test_superadmin_cannot_toggle_own_status(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->patch("/users/{$superadmin->id}/toggle-status");
        $response->assertSessionHas('error');

        $superadmin->refresh();
        $this->assertEquals('Aktif', $superadmin->status_user);
    }

    public function test_superadmin_can_delete_user(): void
    {
        $superadmin = User::where('username', 'admin1')->first();
        $tempUser = User::create([
            'name' => 'Temp Account',
            'nama_lengkap' => 'Temp Account',
            'username' => 'tempuser',
            'email' => 'temp@epjlp.com',
            'password' => Hash::make('password'),
            'role' => 'PJLP',
            'seksi' => 'Seksi ID',
            'status_user' => 'Aktif',
        ]);

        $response = $this->actingAs($superadmin)->delete("/users/{$tempUser->id}");
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $tempUser->id,
        ]);
    }

    public function test_superadmin_can_access_dedicated_governance_dashboard(): void
    {
        $superadmin = User::where('username', 'admin1')->first();

        $response = $this->actingAs($superadmin)->get('/dashboard');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertEquals('Superadmin', $props['role']);
        $this->assertArrayHasKey('superadminData', $props);
        $this->assertArrayHasKey('userBreakdown', $props['superadminData']);
        $this->assertArrayHasKey('id', $props['superadminData']['userBreakdown']);
        $this->assertArrayHasKey('astik', $props['superadminData']['userBreakdown']);
        $this->assertArrayHasKey('kip', $props['superadminData']['userBreakdown']);
        $this->assertArrayHasKey('seksiOverview', $props['superadminData']);
        $this->assertArrayHasKey('todayPresence', $props['superadminData']);
        $this->assertArrayHasKey('activityStats', $props['superadminData']);
        $this->assertArrayHasKey('systemInfo', $props['superadminData']);
    }
}
