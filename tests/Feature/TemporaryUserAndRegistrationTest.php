<?php

namespace Tests\Feature;

use App\Models\RegistrationSubmission;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TemporaryUserAndRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $pjlp;
    protected Seksi $seksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'role' => 'Superadmin',
            'status_user' => 'Aktif',
            'nama_lengkap' => 'Super Administrator',
            'username' => 'superadmin_test',
        ]);

        $this->pjlp = User::factory()->create([
            'role' => 'PJLP',
            'status_user' => 'Aktif',
            'nama_lengkap' => 'Pegawai PJLP Test',
            'username' => 'pjlp_test',
        ]);

        $this->seksi = Seksi::create([
            'kode' => 'ASTIK',
            'nama' => 'Aplikasi Siber dan Statistik',
            'is_active' => true,
        ]);
    }

    public function test_non_superadmin_forbidden_from_temporary_users_index(): void
    {
        $response = $this->actingAs($this->pjlp)->get(route('temporary-users.index'));
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_temporary_users_index(): void
    {
        $response = $this->actingAs($this->superadmin)->get(route('temporary-users.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('TemporaryUsers/Index')
            ->has('activeTemporaryUsers')
            ->has('submissions')
            ->has('stats')
        );
    }

    public function test_superadmin_can_create_temporary_user_with_duration(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('temporary-users.store'), [
            'username' => 'tamu_pelamar1',
            'password' => 'Rahasia123',
            'duration_type' => '15',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('createdCredentials');

        $this->assertDatabaseHas('users', [
            'username' => 'tamu_pelamar1',
            'role' => 'Temporary',
            'is_temporary' => true,
        ]);

        $tempUser = User::where('username', 'tamu_pelamar1')->first();
        $this->assertTrue($tempUser->isTemporary());
        $this->assertNotNull($tempUser->temporary_expires_at);
        $this->assertFalse($tempUser->isTemporaryExpired());
    }

    public function test_superadmin_can_create_temporary_user_with_custom_minutes(): void
    {
        $response = $this->actingAs($this->superadmin)->post(route('temporary-users.store'), [
            'username' => 'tamu_custom',
            'password' => 'Rahasia123',
            'duration_type' => 'custom',
            'custom_minutes' => 45,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'username' => 'tamu_custom',
            'is_temporary' => true,
        ]);
    }

    public function test_superadmin_can_revoke_active_temporary_user(): void
    {
        $tempUser = User::create([
            'name' => 'Akun Sementara',
            'nama_lengkap' => 'Akun Sementara',
            'username' => 'tamu_cabut',
            'password' => Hash::make('password123'),
            'role' => 'Temporary',
            'status_user' => 'Aktif',
            'is_temporary' => true,
            'temporary_expires_at' => now()->addMinutes(10),
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
        ]);

        $response = $this->actingAs($this->superadmin)->delete(route('temporary-users.destroy', $tempUser->id));
        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $tempUser->id]);
    }

    public function test_temporary_user_logs_in_and_is_redirected_to_pendaftaran(): void
    {
        $tempUser = User::create([
            'name' => 'Akun Sementara',
            'nama_lengkap' => 'Akun Sementara',
            'username' => 'tamu_login',
            'password' => Hash::make('password123'),
            'role' => 'Temporary',
            'status_user' => 'Aktif',
            'is_temporary' => true,
            'temporary_expires_at' => now()->addMinutes(10),
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'tamu_login',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('pendaftaran.index'));
        $this->assertAuthenticatedAs($tempUser);
    }

    public function test_temporary_user_cannot_access_dashboard_and_is_redirected_to_pendaftaran(): void
    {
        $tempUser = User::create([
            'name' => 'Akun Sementara',
            'nama_lengkap' => 'Akun Sementara',
            'username' => 'tamu_portal',
            'password' => Hash::make('password123'),
            'role' => 'Temporary',
            'status_user' => 'Aktif',
            'is_temporary' => true,
            'temporary_expires_at' => now()->addMinutes(10),
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
        ]);

        $response = $this->actingAs($tempUser)->get(route('dashboard'));
        $response->assertRedirect(route('pendaftaran.index'));
    }

    public function test_expired_temporary_user_login_fails_and_account_is_deleted(): void
    {
        $tempUser = User::create([
            'name' => 'Akun Sementara Expired',
            'nama_lengkap' => 'Akun Sementara Expired',
            'username' => 'tamu_expired',
            'password' => Hash::make('password123'),
            'role' => 'Temporary',
            'status_user' => 'Aktif',
            'is_temporary' => true,
            'temporary_expires_at' => now()->subMinutes(5), // Kedaluwarsa 5 menit lalu
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'tamu_expired',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Akun tidak di temukan',
        ]);
        $this->assertDatabaseMissing('users', ['id' => $tempUser->id]);
    }

    public function test_temporary_user_submits_registration_form_and_temporary_account_is_immediately_deleted(): void
    {
        $tempUser = User::create([
            'name' => 'Akun Sementara Pelamar',
            'nama_lengkap' => 'Akun Sementara Pelamar',
            'username' => 'tamu_registrasi',
            'password' => Hash::make('password123'),
            'role' => 'Temporary',
            'status_user' => 'Aktif',
            'is_temporary' => true,
            'temporary_expires_at' => now()->addMinutes(10),
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
        ]);

        $tempUserId = $tempUser->id;

        $response = $this->actingAs($tempUser)->post(route('pendaftaran.store'), [
            'nama_lengkap' => 'Ahmad Rifai',
            'username' => 'ahmad.rifai',
            'password' => 'P@ssw0rd123',
            'password_confirmation' => 'P@ssw0rd123',
            'email' => 'ahmad.rifai@gmail.com',
            'nik' => '3175012345678901',
            'no_wa' => '081234567890',
            'seksi' => 'Aplikasi Siber dan Statistik',
            'posisi_pekerjaan' => 'PJLP Tenaga Ahli IT',
            'jenis_kelamin' => 'Laki-laki',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1995-05-12',
            'status_pernikahan' => 'Belum Menikah',
            'pendidikan_terakhir' => 'S1',
            'alamat_domisili' => 'Jl. Pemuda No. 10 Jakarta Timur',
        ]);

        $response->assertRedirect();

        // 1. Pastikan formulir masuk ke tabel registration_submissions
        $this->assertDatabaseHas('registration_submissions', [
            'nama_lengkap' => 'Ahmad Rifai',
            'username' => 'ahmad.rifai',
            'status' => 'pending',
            'seksi' => 'Aplikasi Siber dan Statistik',
            'jabatan' => 'PJLP',
        ]);

        // 2. REQUIREMENT PENTING: Akun sementara langsung HILANG / TERHAPUS
        $this->assertDatabaseMissing('users', ['id' => $tempUserId]);

        // 3. Pengguna telah ter-logout
        $this->assertGuest();
    }

    public function test_superadmin_can_approve_submission_and_permanent_user_is_created(): void
    {
        $submission = RegistrationSubmission::create([
            'registration_code' => 'REG-20261104-TEST1',
            'nama_lengkap' => 'Dewi Sartika',
            'username' => 'dewi.sartika',
            'password' => Hash::make('DewiPassword123'),
            'email' => 'dewi@gmail.com',
            'nik' => '3175098765432100',
            'no_wa' => '08987654321',
            'seksi' => 'Aplikasi Siber dan Statistik',
            'posisi_pekerjaan' => 'PJLP Layanan Jaringan',
            'jabatan' => 'PJLP Layanan Jaringan',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)->post(route('temporary-users.approve', $submission->id), [
            'seksi' => 'Aplikasi Siber dan Statistik',
            'posisi_pekerjaan' => 'PJLP Layanan Jaringan',
            'pin' => '80790999',
            'catatan_admin' => 'Berkas valid dan lengkap.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Status submission berubah menjadi approved
        $submission->refresh();
        $this->assertEquals('approved', $submission->status);
        $this->assertEquals($this->superadmin->id, $submission->approved_by);
        $this->assertNotNull($submission->registered_user_id);

        // Akun resmi permanen terbit di tabel users
        $this->assertDatabaseHas('users', [
            'username' => 'dewi.sartika',
            'nama_lengkap' => 'Dewi Sartika',
            'role' => 'PJLP',
            'status_user' => 'Aktif',
            'is_temporary' => false,
            'pin' => '80790999',
        ]);

        $newUser = User::where('username', 'dewi.sartika')->first();
        $this->assertTrue(Hash::check('DewiPassword123', $newUser->password));
    }

    public function test_superadmin_can_reject_submission_with_admin_notes(): void
    {
        $submission = RegistrationSubmission::create([
            'registration_code' => 'REG-20261104-TEST2',
            'nama_lengkap' => 'Pelamar Ditolak',
            'username' => 'pelamar.tolak',
            'password' => Hash::make('password123'),
            'seksi' => 'Aplikasi Siber dan Statistik',
            'posisi_pekerjaan' => 'PJLP Operator',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->superadmin)->post(route('temporary-users.reject', $submission->id), [
            'catatan_admin' => 'Kuota formasi untuk seksi ini telah terpenuhi.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $submission->refresh();
        $this->assertEquals('rejected', $submission->status);
        $this->assertEquals('Kuota formasi untuk seksi ini telah terpenuhi.', $submission->catatan_admin);
        $this->assertNull($submission->registered_user_id);
    }

    public function test_candidate_can_check_registration_status_by_code(): void
    {
        $submission = RegistrationSubmission::create([
            'registration_code' => 'REG-20261104-TRACK1',
            'nama_lengkap' => 'Pelamar Lacak',
            'username' => 'pelamar.lacak',
            'password' => Hash::make('password123'),
            'seksi' => 'Aplikasi Siber dan Statistik',
            'posisi_pekerjaan' => 'PJLP Tenaga Ahli IT',
            'jabatan' => 'PJLP',
            'status' => 'pending',
        ]);

        // 1. Render halaman status kosong
        $response = $this->get(route('pendaftaran.status'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/PendaftaranStatus')
            ->where('searched', false)
        );

        // 2. Lacak dengan kode yang valid
        $responseWithCode = $this->get(route('pendaftaran.status', ['code' => 'REG-20261104-TRACK1']));
        $responseWithCode->assertStatus(200);
        $responseWithCode->assertInertia(fn ($page) => $page
            ->component('Auth/PendaftaranStatus')
            ->where('searched', true)
            ->where('submission.registration_code', 'REG-20261104-TRACK1')
            ->where('submission.status', 'pending')
            ->where('submission.jabatan', 'PJLP')
        );

        // 3. Lacak dengan kode yang salah
        $responseInvalid = $this->get(route('pendaftaran.status', ['code' => 'KODE-SALAH']));
        $responseInvalid->assertStatus(200);
        $responseInvalid->assertInertia(fn ($page) => $page
            ->component('Auth/PendaftaranStatus')
            ->where('searched', true)
            ->where('submission', null)
        );
    }
}
