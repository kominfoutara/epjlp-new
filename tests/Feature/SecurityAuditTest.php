<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\DinasLuar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    /**
     * Test 1: PJLP cannot validate (approve/reject) activities.
     */
    public function test_pjlp_cannot_validate_own_or_others_activity(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $activity = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Testing',
            'lokasi' => 'Testing Room',
            'uraian' => 'Aktivitas yang belum divalidasi',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($pjlp)->post("/aktivitas/{$activity->id}/quick-validate", [
            'status' => 'Disetujui',
            'catatan' => 'Self-validation bypass attempt',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $activity->fresh()->status_validasi);
    }

    /**
     * Test 2: PJLP cannot bulk validate activities.
     */
    public function test_pjlp_cannot_bulk_validate_activities(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $act1 = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'kategori' => 'Testing',
            'lokasi' => 'Room 1',
            'uraian' => 'Tugas 1',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($pjlp)->post('/aktivitas-bulk/validate', [
            'activity_ids' => [$act1->id],
            'status' => 'Disetujui',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $act1->fresh()->status_validasi);
    }

    /**
     * Test 3: PJLP cannot modify or delete already approved activities.
     */
    public function test_pjlp_cannot_modify_or_delete_approved_activity(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $activity = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Testing',
            'lokasi' => 'Testing Room',
            'uraian' => 'Aktivitas resmi yang sudah disetujui',
            'status_validasi' => 'Disetujui',
        ]);

        // Attempt edit
        $updateResponse = $this->actingAs($pjlp)->post("/aktivitas/{$activity->id}", [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '09:00',
            'jam_selesai' => '17:00',
            'kategori' => 'Tampered',
            'lokasi' => 'Tampered',
            'uraian' => 'Manipulasi aktivitas yang sudah approved',
        ]);
        $updateResponse->assertSessionHas('error');
        $this->assertEquals('Testing Room', $activity->fresh()->lokasi);

        // Attempt delete
        $deleteResponse = $this->actingAs($pjlp)->delete("/aktivitas/{$activity->id}");
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('aktivitas', ['id' => $activity->id]);
    }

    /**
     * Test 4: PJLP cannot verify Dinas Luar requests.
     */
    public function test_pjlp_cannot_verify_own_or_others_dinas_luar(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $dl = DinasLuar::create([
            'user_id' => $pjlp->id,
            'tipe' => 'DL PENUH',
            'keterangan' => 'Permohonan DL',
            'status_verifikasi' => 'Menunggu',
            'waktu' => now(),
        ]);

        $response = $this->actingAs($pjlp)->patch("/dinas-luar/{$dl->id}/verify", [
            'status' => 'Disetujui',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $dl->fresh()->status_verifikasi);
    }

    /**
     * Test 5: PJLP cannot bulk verify Dinas Luar requests.
     */
    public function test_pjlp_cannot_bulk_verify_dinas_luar(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $dl = DinasLuar::create([
            'user_id' => $pjlp->id,
            'tipe' => 'DL AWAL',
            'keterangan' => 'Permohonan DL Massal',
            'status_verifikasi' => 'Menunggu',
            'waktu' => now(),
        ]);

        $response = $this->actingAs($pjlp)->post('/dinas-luar/verify-bulk', [
            'ids' => [$dl->id],
            'status' => 'Disetujui',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $dl->fresh()->status_verifikasi);
    }

    /**
     * Test 6: PJLP cannot access or manipulate PJLP Management.
     */
    public function test_pjlp_cannot_access_or_modify_pjlp_management(): void
    {
        $pjlp1 = User::where('role', 'PJLP')->first();
        $pjlp2 = User::where('role', 'PJLP')->skip(1)->first() ?? User::factory()->create(['role' => 'PJLP']);

        // Index
        $this->actingAs($pjlp1)->get('/pjlp')->assertStatus(403);

        // Store
        $this->actingAs($pjlp1)->post('/pjlp', [
            'nama_lengkap' => 'Injected PJLP',
            'username' => 'injected_pjlp',
            'email' => 'injected@pjlp.go.id',
            'seksi' => 'Seksi ASTIK',
            'jam_masuk' => '07:30',
            'jam_pulang' => '16:00',
            'password' => 'secret123',
        ])->assertStatus(403);

        // Update
        $this->actingAs($pjlp1)->put("/pjlp/{$pjlp2->id}", [
            'nama_lengkap' => 'Hijacked PJLP',
            'username' => $pjlp2->username,
            'email' => $pjlp2->email,
            'seksi' => 'Seksi ASTIK',
            'jam_masuk' => '07:30',
            'jam_pulang' => '16:00',
            'status_user' => 'Aktif',
            'password' => 'newpassword123',
        ])->assertStatus(403);

        // Destroy
        $this->actingAs($pjlp1)->delete("/pjlp/{$pjlp2->id}")->assertStatus(403);
    }

    /**
     * Test 7: PJLP cannot create, update, or delete activity categories.
     */
    public function test_pjlp_cannot_manage_activity_categories(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $cat = ActivityCategory::create([
            'nama' => 'Original Category',
            'seksi' => 'Seksi ASTIK',
        ]);

        // Store
        $this->actingAs($pjlp)->post('/categories', [
            'nama' => 'Rogue Category',
            'seksi' => 'Seksi ASTIK',
        ])->assertStatus(403);

        // Update
        $this->actingAs($pjlp)->put("/categories/{$cat->id}", [
            'nama' => 'Altered Category',
            'seksi' => 'Seksi ASTIK',
        ])->assertStatus(403);

        // Destroy
        $this->actingAs($pjlp)->delete("/categories/{$cat->id}")->assertStatus(403);
    }

    /**
     * Test 8: PJLP cannot trigger attendance sync, test-connection, or update attendance keterangan.
     */
    public function test_pjlp_cannot_trigger_attendance_sync_or_edit_keterangan(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $att = Attendance::create([
            'pin' => $pjlp->pin ?? '12345',
            'tanggal' => now()->toDateString(),
            'keterangan' => 'ALPA',
        ]);

        // Sync
        $this->actingAs($pjlp)->post('/absensi/sync')->assertStatus(403);

        // Test Connection
        $this->actingAs($pjlp)->post('/absensi/test-connection', ['ip' => '127.0.0.1'])->assertStatus(403);

        // Update keterangan
        $this->actingAs($pjlp)->patch("/absensi/{$att->id}/keterangan", [
            'keterangan' => 'WFO',
        ])->assertStatus(403);

        $this->assertEquals('ALPA', $att->fresh()->keterangan);
    }

    /**
     * Test 9: Suspended / Inactive account cannot log in.
     */
    public function test_suspended_user_cannot_log_in(): void
    {
        $suspendedUser = User::create([
            'name' => 'Suspended Staff',
            'nama_lengkap' => 'Suspended Staff',
            'username' => 'suspendeduser',
            'email' => 'suspended@pjlp.go.id',
            'password' => Hash::make('password123'),
            'role' => 'PJLP',
            'status_user' => 'Suspend',
            'seksi' => 'Seksi ASTIK',
        ]);

        $response = $this->post('/login', [
            'email' => 'suspendeduser',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Test 10: Malicious photo upload extension in base64 is sanitized to allowed type.
     */
    public function test_dinas_luar_sanitizes_malicious_photo_extension(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        // Attempt payload with data:image/php
        $maliciousPayload = 'data:image/php;base64,' . base64_encode('<?php echo "evil"; ?>');

        $response = $this->actingAs($pjlp)->post('/dinas-luar', [
            'tipe' => 'DL PENUH',
            'keterangan' => 'Testing upload filter',
            'foto' => $maliciousPayload,
        ]);

        $response->assertSessionHas('success');

        $latestDl = DinasLuar::where('user_id', $pjlp->id)->latest()->first();
        $this->assertNotNull($latestDl->foto);
        // Extension must NOT be .php, must be .jpeg
        $this->assertStringEndsWith('.jpeg', $latestDl->foto);
        $this->assertStringNotContainsString('.php', $latestDl->foto);
    }

    /**
     * Test 11: Security Headers are returned on HTTP responses.
     */
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test 12: Regular PJLP cannot delete broadcast notifications.
     */
    public function test_pjlp_cannot_delete_broadcast_notification(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $broadcastNotification = AppNotification::create([
            'title' => 'Broadcast Notice',
            'message' => 'System maintenance notice for all staff',
            'type' => 'info',
            'module' => 'Sistem',
            'target_role' => 'PJLP',
            'user_id' => null, // Broadcast
        ]);

        $response = $this->actingAs($pjlp)->delete("/notifications/{$broadcastNotification->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('app_notifications', ['id' => $broadcastNotification->id]);
    }

    /**
     * Test 13: Login page provides initial CAPTCHA props.
     */
    public function test_login_page_renders_initial_captcha_and_key(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) {
            $page->component('Auth/Login')
                ->has('initialCaptcha')
                ->has('initialCaptchaKey');

            $props = $page->toArray()['props'];
            $captcha = $props['initialCaptcha'];
            $key = $props['initialCaptchaKey'];

            $this->assertEquals(5, strlen($captcha));
            $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($key);
            $this->assertStringStartsWith($captcha . '|', $decrypted);
        });
    }

    /**
     * Test 14: Captcha refresh endpoint returns valid JSON and enforces rate limiting.
     */
    public function test_captcha_refresh_endpoint_returns_json_and_is_rate_limited(): void
    {
        $response = $this->get('/captcha/refresh');
        $response->assertStatus(200);
        $response->assertJsonStructure(['captcha', 'captcha_key']);

        $data = $response->json();
        $this->assertEquals(5, strlen($data['captcha']));

        $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($data['captcha_key']);
        $this->assertStringStartsWith($data['captcha'] . '|', $decrypted);

        // Test rate limiting: 30 requests per minute allowed, 31st triggers 429
        for ($i = 0; $i < 30; $i++) {
            $this->get('/captcha/refresh');
        }

        $throttledResponse = $this->get('/captcha/refresh');
        $throttledResponse->assertStatus(429);
    }

    /**
     * Test 15: Login succeeds with valid credentials and valid CAPTCHA.
     */
    public function test_login_succeeds_with_valid_captcha_and_credentials(): void
    {
        $user = User::where('role', 'PJLP')->first();
        $user->password = \Illuminate\Support\Facades\Hash::make('password123');
        $user->status_user = 'Aktif';
        $user->save();

        $code = 'ABC23';
        $timestamp = time();
        $nonce = \Illuminate\Support\Str::random(16);
        $key = \Illuminate\Support\Facades\Crypt::encryptString("{$code}|{$timestamp}|{$nonce}");

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password123',
            'captcha' => $code,
            'captcha_key' => $key,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test 16: Login fails with invalid CAPTCHA code.
     */
    public function test_login_fails_with_invalid_captcha_code(): void
    {
        $user = User::where('role', 'PJLP')->first();

        $code = 'ABC23';
        $timestamp = time();
        $nonce = \Illuminate\Support\Str::random(16);
        $key = \Illuminate\Support\Facades\Crypt::encryptString("{$code}|{$timestamp}|{$nonce}");

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password123',
            'captcha' => 'WRONG',
            'captcha_key' => $key,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha');
    }

    /**
     * Test 17: Anti-Replay: Reusing a valid CAPTCHA key is blocked.
     */
    public function test_login_blocks_captcha_replay_attack(): void
    {
        $user = User::where('role', 'PJLP')->first();
        $user->password = \Illuminate\Support\Facades\Hash::make('password123');
        $user->status_user = 'Aktif';
        $user->save();

        $code = 'DEF45';
        $timestamp = time();
        $nonce = \Illuminate\Support\Str::random(16);
        $key = \Illuminate\Support\Facades\Crypt::encryptString("{$code}|{$timestamp}|{$nonce}");

        // First attempt with valid key & wrong password (fails credentials, consumes key)
        $firstAttempt = $this->post('/login', [
            'email' => $user->username,
            'password' => 'wrongpassword',
            'captcha' => $code,
            'captcha_key' => $key,
        ]);
        $this->assertGuest();
        $firstAttempt->assertSessionHasErrors('email');

        // Second attempt replaying the exact same captcha_key
        $secondAttempt = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password123',
            'captcha' => $code,
            'captcha_key' => $key,
        ]);

        $this->assertGuest();
        $secondAttempt->assertSessionHasErrors('captcha');
        $this->assertEquals(
            'Kode verifikasi (CAPTCHA) sudah digunakan. Silakan muat ulang kode.',
            session('errors')->first('captcha')
        );
    }

    /**
     * Test 18: Expired CAPTCHA (>300 seconds) is rejected.
     */
    public function test_login_rejects_expired_captcha(): void
    {
        $user = User::where('role', 'PJLP')->first();

        $code = 'GHI67';
        $expiredTimestamp = time() - 305; // 305 seconds ago (> 300s TTL)
        $nonce = \Illuminate\Support\Str::random(16);
        $key = \Illuminate\Support\Facades\Crypt::encryptString("{$code}|{$expiredTimestamp}|{$nonce}");

        $response = $this->post('/login', [
            'email' => $user->username,
            'password' => 'password123',
            'captcha' => $code,
            'captcha_key' => $key,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha');
        $this->assertStringContainsString('kedaluwarsa', session('errors')->first('captcha'));
    }
}
