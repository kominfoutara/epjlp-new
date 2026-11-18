<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AppNotification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KasiActivityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    /**
     * Test 1: Kasi can view their own activity sheet.
     */
    public function test_kasi_can_view_my_activities_page(): void
    {
        $kasi = User::where('role', 'Kasi')->first();

        $response = $this->actingAs($kasi)->get('/aktivitas/saya');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Activity/Index')
            ->where('isOwner', true)
            ->where('targetUser.id', $kasi->id)
        );
    }

    /**
     * Test 2: Kasi can submit activity and it notifies Kasudin (target_role => 'Kasudin').
     */
    public function test_kasi_can_submit_activity_and_notifies_kasudin(): void
    {
        $kasi = User::where('role', 'Kasi')->first();

        $response = $this->actingAs($kasi)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Koordinasi dan Pengawasan',
            'lokasi' => 'Ruang Rapat Sudin Kominfotik',
            'uraian' => 'Rapat koordinasi teknis tim operasional jaringan',
            'lampiran' => [
                UploadedFile::fake()->image('bukti_rapat.jpg'),
            ],
        ]);

        $response->assertSessionHas('success');

        $activity = Activity::where('user_id', $kasi->id)->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertEquals('Menunggu', $activity->status_validasi);
        $this->assertEquals('Rapat koordinasi teknis tim operasional jaringan', $activity->uraian);

        // Assert notification targeted to Kasudin
        $notification = AppNotification::where('role', 'Kasudin')->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString($kasi->nama_lengkap, $notification->message);
        $this->assertEquals('info', $notification->type);
    }

    /**
     * Test 3: Kasi cannot validate their own activity (self-validation blocked).
     */
    public function test_kasi_cannot_validate_own_activity(): void
    {
        $kasi = User::where('role', 'Kasi')->first();

        $activity = Activity::create([
            'user_id' => $kasi->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Koordinasi',
            'lokasi' => 'Kantor',
            'uraian' => 'Aktivitas Kasi untuk ditest self-validation',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->post("/aktivitas/{$activity->id}/quick-validate", [
            'status' => 'Disetujui',
            'catatan' => 'Self-approve attempt',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $activity->fresh()->status_validasi);
    }

    /**
     * Test 4: Kasi cannot validate another Kasi's activity.
     */
    public function test_kasi_cannot_validate_another_kasi_activity(): void
    {
        $kasi1 = User::where('role', 'Kasi')->first();
        $kasi2 = User::where('role', 'Kasi')->skip(1)->first();

        if (!$kasi2) {
            $kasi2 = User::create([
                'username' => 'kasi2',
                'name' => 'Kasi Dua',
                'nama_lengkap' => 'Kasi Dua, S.Kom',
                'email' => 'kasi2@example.com',
                'password' => bcrypt('password'),
                'role' => 'Kasi',
                'seksi' => 'Seksi Jaringan Komunikasi Data',
                'status_user' => 'Aktif',
            ]);
        }

        $activity = Activity::create([
            'user_id' => $kasi2->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Koordinasi',
            'lokasi' => 'Kantor',
            'uraian' => 'Aktivitas Kasi 2',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi1)->post("/aktivitas/{$activity->id}/quick-validate", [
            'status' => 'Disetujui',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $activity->fresh()->status_validasi);
    }

    /**
     * Test 5: Kasudin can see Kasi in validation list.
     */
    public function test_kasudin_can_see_kasi_in_validation_list(): void
    {
        $kasudin = User::where('role', 'Kasudin')->first();
        $kasi = User::where('role', 'Kasi')->first();

        $response = $this->actingAs($kasudin)->get('/aktivitas');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Activity/PjlpList')
            ->has('pjlpUsers')
        );

        $props = $response->getOriginalContent()->getData()['page']['props'];
        $userList = collect($props['pjlpUsers']);
        $this->assertTrue($userList->contains('id', $kasi->id), 'Kasudin must see Kasi in validation list.');
    }

    /**
     * Test 6: Kasudin can validate Kasi's activity.
     */
    public function test_kasudin_can_validate_kasi_activity(): void
    {
        $kasudin = User::where('role', 'Kasudin')->first();
        $kasi = User::where('role', 'Kasi')->first();

        $activity = Activity::create([
            'user_id' => $kasi->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Koordinasi',
            'lokasi' => 'Kantor',
            'uraian' => 'Aktivitas Kasi yang siap divalidasi Kasudin',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasudin)->post("/aktivitas/{$activity->id}/quick-validate", [
            'status' => 'Disetujui',
            'catatan' => 'Laporan kerja Kasi disetujui pimpinan',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('Disetujui', $activity->fresh()->status_validasi);

        // Check notification sent to Kasi
        $notification = AppNotification::where('user_id', $kasi->id)->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Disetujui', $notification->title);
    }

    /**
     * Test 7: Kasi cannot edit approved activity.
     */
    public function test_kasi_cannot_edit_approved_activity(): void
    {
        $kasi = User::where('role', 'Kasi')->first();

        $activity = Activity::create([
            'user_id' => $kasi->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Koordinasi',
            'lokasi' => 'Kantor',
            'uraian' => 'Aktivitas Kasi yang sudah Disetujui',
            'status_validasi' => 'Disetujui',
        ]);

        $response = $this->actingAs($kasi)->post("/aktivitas/{$activity->id}", [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '09:00',
            'jam_selesai' => '17:00',
            'kategori' => 'Koordinasi',
            'lokasi' => 'Kantor Lain',
            'uraian' => 'Mencoba mengubah aktivitas approved',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('Kantor', $activity->fresh()->lokasi);
    }

    /**
     * Test 8: Kasi cannot bulk validate own or other Kasi's activities.
     */
    public function test_kasi_cannot_bulk_validate_own_activity(): void
    {
        $kasi = User::where('role', 'Kasi')->first();

        $activity = Activity::create([
            'user_id' => $kasi->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Koordinasi',
            'lokasi' => 'Kantor',
            'uraian' => 'Aktivitas Kasi',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->post('/aktivitas-bulk/validate', [
            'activity_ids' => [$activity->id],
            'status' => 'Disetujui',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $activity->fresh()->status_validasi);
    }

    /**
     * Test 9: Kasi can see PJLP in same seksi even with prefix difference (e.g. 'Astik' vs 'Seksi ASTIK').
     */
    public function test_kasi_sees_pjlp_with_normalized_seksi_and_activities_in_pjlp_list(): void
    {
        $kasi = User::factory()->create([
            'role' => 'Kasi',
            'seksi' => 'Seksi ASTIK',
            'status_user' => 'Aktif',
        ]);

        $pjlp = User::factory()->create([
            'role' => 'PJLP',
            'seksi' => 'Astik',
            'status_user' => 'Aktif',
        ]);

        $activity = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'lokasi' => 'Sudin',
            'uraian' => 'Aktivitas pengujian realtime seksi',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->get('/aktivitas?bulan=' . now()->format('Y-m'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Activity/PjlpList')
            ->has('pjlpUsers')
        );

        $props = $response->getOriginalContent()->getData()['page']['props'];
        $userList = collect($props['pjlpUsers']);
        $matched = $userList->firstWhere('id', $pjlp->id);

        $this->assertNotNull($matched, 'PJLP with Seksi Astik must appear for Kasi with Seksi ASTIK.');
        $this->assertEquals('Menunggu', $matched['status_validasi']);
        $this->assertNotEmpty($matched['activities']);
        $this->assertEquals($activity->id, $matched['activities'][0]['id']);

        // Kasi can quick validate this activity
        $valResponse = $this->actingAs($kasi)->postJson("/aktivitas/{$activity->id}/quick-validate", [
            'status' => 'Disetujui',
            'catatan' => 'Kerja bagus',
        ]);

        $valResponse->assertStatus(200);
        $this->assertEquals('Disetujui', $activity->fresh()->status_validasi);
    }

    /**
     * Test 10: Decision cannot be changed once validated (only one choice: either approved or rejected, cannot be flipped).
     */
    public function test_kasi_cannot_change_validation_status_once_decided(): void
    {
        $kasi = User::factory()->create([
            'role' => 'Kasi',
            'seksi' => 'Astik',
            'status_user' => 'Aktif',
        ]);

        $pjlp = User::factory()->create([
            'role' => 'PJLP',
            'seksi' => 'Astik',
            'status_user' => 'Aktif',
        ]);

        // Skenario 1: Disetujui -> tidak bisa diubah jadi Ditolak
        $actApproved = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Aktivitas yang sudah disetujui',
            'status_validasi' => 'Disetujui',
        ]);

        $response1 = $this->actingAs($kasi)->postJson("/aktivitas/{$actApproved->id}/quick-validate", [
            'status' => 'Ditolak',
            'catatan' => 'Mencoba membalikkan keputusan',
        ]);

        $response1->assertStatus(422);
        $this->assertEquals('Disetujui', $actApproved->fresh()->status_validasi);

        // Skenario 2: Ditolak -> tidak bisa diubah jadi Disetujui langsung oleh Kasi
        $actRejected = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Aktivitas yang sudah ditolak',
            'status_validasi' => 'Ditolak',
        ]);

        $response2 = $this->actingAs($kasi)->postJson("/aktivitas/{$actRejected->id}/quick-validate", [
            'status' => 'Disetujui',
        ]);

        $response2->assertStatus(422);
        $this->assertEquals('Ditolak', $actRejected->fresh()->status_validasi);
    }
}
