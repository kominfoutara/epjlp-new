<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\DinasLuar;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EpjlpFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_username(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin1',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_dashboard_accessible_by_authenticated_user(): void
    {
        $user = User::where('username', 'admin1')->first();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_pjlp_can_create_activity(): void
    {
        Storage::fake('public');
        $pjlp = User::where('username', 'ikhwan')->first();

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Monitoring Server',
            'lokasi' => 'Ruang Server Kominfotik',
            'uraian' => 'Melakukan monitoring rutin suhu dan resource server.',
            'lampiran' => [
                UploadedFile::fake()->image('bukti_kegiatan.jpg'),
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('aktivitas', [
            'user_id' => $pjlp->id,
            'kategori' => 'Monitoring Server',
            'status_validasi' => 'Menunggu',
        ]);
    }

    public function test_activity_creation_requires_uraian_and_lampiran(): void
    {
        Storage::fake('public');
        $pjlp = User::where('username', 'ikhwan')->first();

        // 1. Validasi Uraian Tugas wajib diisi
        $responseNoUraian = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Monitoring Server',
            'lokasi' => 'Ruang Server Kominfotik',
            'uraian' => '',
            'lampiran' => [
                UploadedFile::fake()->image('bukti.jpg'),
            ],
        ]);
        $responseNoUraian->assertSessionHasErrors(['uraian']);

        // 2. Validasi Lampiran wajib diisi
        $responseNoLampiran = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Monitoring Server',
            'lokasi' => 'Ruang Server Kominfotik',
            'uraian' => 'Melakukan pemeliharaan server.',
            'lampiran' => [],
        ]);
        $responseNoLampiran->assertSessionHasErrors(['lampiran']);
    }

    public function test_pjlp_can_submit_dinas_luar(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $pjlp = User::where('username', 'ikhwan')->first();
        $fakePhoto = \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg');

        $response = $this->actingAs($pjlp)->post('/dinas-luar', [
            'tipe' => 'DL PENUH',
            'alamat' => 'Gedung Walikota Jakarta Utara',
            'latitude' => -6.1214,
            'longitude' => 106.8926,
            'keterangan' => 'Penugasan live streaming rapat koordinasi',
            'foto' => $fakePhoto,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('dinas_luar', [
            'user_id' => $pjlp->id,
            'tipe' => 'DL PENUH',
            'status_verifikasi' => 'Menunggu',
        ]);
    }

    public function test_pjlp_cannot_submit_dinas_luar_without_photo(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();

        $response = $this->actingAs($pjlp)->post('/dinas-luar', [
            'tipe' => 'DL PENUH',
            'alamat' => 'Gedung Walikota Jakarta Utara',
            'latitude' => -6.1214,
            'longitude' => 106.8926,
            'keterangan' => 'Mencoba kirim tanpa foto',
        ]);

        $response->assertSessionHasErrors(['foto']);
    }

    public function test_kasi_can_verify_dinas_luar(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();
        $kasi = User::where('username', 'kasiastik')->first();

        $dl = DinasLuar::create([
            'user_id' => $pjlp->id,
            'tipe' => 'DL AWAL',
            'keterangan' => 'Liputan pagi',
            'status_verifikasi' => 'Menunggu',
            'waktu' => now(),
        ]);

        $response = $this->actingAs($kasi)->patch("/dinas-luar/{$dl->id}/verify", [
            'status' => 'Disetujui',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('dinas_luar', [
            'id' => $dl->id,
            'status_verifikasi' => 'Disetujui',
        ]);
    }

    public function test_superadmin_cannot_verify_or_bulk_verify_dinas_luar(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();
        $superadmin = User::where('username', 'admin1')->first();

        $dl = DinasLuar::create([
            'user_id' => $pjlp->id,
            'tipe' => 'DL AWAL',
            'keterangan' => 'Liputan pagi superadmin audit',
            'status_verifikasi' => 'Menunggu',
            'waktu' => now(),
        ]);

        // Verifikasi satuan oleh Superadmin ditolak (403)
        $response = $this->actingAs($superadmin)->patch("/dinas-luar/{$dl->id}/verify", [
            'status' => 'Disetujui',
        ]);
        $response->assertStatus(403);
        $this->assertEquals('Menunggu', $dl->fresh()->status_verifikasi);

        // Verifikasi massal oleh Superadmin ditolak (403)
        $bulkResponse = $this->actingAs($superadmin)->post('/dinas-luar/verify-bulk', [
            'ids' => [$dl->id],
            'status' => 'Disetujui',
        ]);
        $bulkResponse->assertStatus(403);
        $this->assertEquals('Menunggu', $dl->fresh()->status_verifikasi);
    }

    public function test_kasi_can_view_integrated_dashboard_with_kpi_and_monthly_analytics(): void
    {
        $kasi = User::where('username', 'kasiastik')->first();

        $response = $this->actingAs($kasi)->get('/dashboard');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertArrayHasKey('kpiStats', $props);
        $this->assertArrayHasKey('monthlyStats', $props);
        $this->assertArrayHasKey('todayStats', $props);
        $this->assertArrayHasKey('activities', $props);
        $this->assertCount(12, $props['monthlyStats']);
    }

    public function test_kasi_can_quick_validate_activity(): void
    {
        $kasi = User::where('username', 'kasiastik')->first();
        $pjlp = User::where('username', 'ikhwan')->first();

        $act = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'kategori' => 'Infrastruktur IT',
            'lokasi' => 'Server Room',
            'uraian' => 'Pemeriksaan jaringan switch',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->post("/aktivitas/{$act->id}/quick-validate", [
            'status' => 'Disetujui',
            'catatan' => 'Pekerjaan terverifikasi sesuai KAK',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('aktivitas', [
            'id' => $act->id,
            'status_validasi' => 'Disetujui',
            'catatan' => 'Pekerjaan terverifikasi sesuai KAK',
        ]);
    }

    public function test_daily_activities_are_paginated_max_ten_per_page(): void
    {
        $pjlp = User::where('username', 'ikhwan')->first();

        // Create 15 activities for this month
        for ($i = 1; $i <= 15; $i++) {
            Activity::create([
                'user_id' => $pjlp->id,
                'tanggal' => now()->toDateString(),
                'jam_mulai' => sprintf('%02d:00', ($i % 8) + 8),
                'jam_selesai' => '16:00',
                'kategori' => 'Monitoring Server',
                'lokasi' => "Lokasi {$i}",
                'uraian' => "Uraian tugas aktivitas ke-{$i}",
                'status_validasi' => 'Menunggu',
            ]);
        }

        $response = $this->actingAs($pjlp)->get('/aktivitas');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertArrayHasKey('activities', $props);
        $this->assertEquals(15, $props['activities']['total']);
        $this->assertEquals(10, $props['activities']['per_page']);
        $this->assertCount(10, $props['activities']['data']);
        $this->assertEquals(2, $props['activities']['last_page']);

        // Check page 2
        $page2Response = $this->actingAs($pjlp)->get('/aktivitas?page=2');
        $page2Response->assertStatus(200);
        $page2Props = $page2Response->original->getData()['page']['props'];
        $this->assertCount(5, $page2Props['activities']['data']);
    }

    public function test_superadmin_can_view_rekap_seluruh_aktivitas_for_monitoring(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();

        $response = $this->actingAs($superadmin)->get('/aktivitas');
        $response->assertStatus(200);

        $inertiaPage = $response->original->getData()['page'];
        $this->assertEquals('Activity/RekapAktivitas', $inertiaPage['component']);
        $this->assertArrayHasKey('activities', $inertiaPage['props']);
        $this->assertArrayHasKey('stats', $inertiaPage['props']);
    }

    public function test_pjlp_dashboard_renders_with_pjlp_specific_props(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $response = $this->actingAs($pjlp)->get('/dashboard');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertEquals('PJLP', $props['role']);
        $this->assertArrayHasKey('attendanceStats', $props);
        $this->assertArrayHasKey('activityStats', $props);
        $this->assertArrayHasKey('pjlpMonthlyStats', $props);
        $this->assertArrayHasKey('filters', $props);
        $this->assertArrayHasKey('availableYears', $props);
        $this->assertArrayHasKey('recentActivities', $props);
    }

    public function test_pjlp_can_filter_dashboard_by_tahun_and_bulan(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        // Buat aktivitas pada bulan Oktober 2026
        \App\Models\Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => '2026-10-15',
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Jaringan',
            'lokasi' => 'Sudin',
            'uraian' => 'Uji coba filter diagram batang',
            'status_validasi' => 'Disetujui',
            'lampiran' => 'photos/test.jpg',
        ]);

        $response = $this->actingAs($pjlp)->get('/dashboard?tahun=2026&bulan=10');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertEquals(2026, $props['filters']['tahun']);
        $this->assertEquals('10', $props['filters']['bulan']);
        $this->assertGreaterThanOrEqual(1, $props['activityStats']['total']);
        $this->assertGreaterThanOrEqual(1, $props['activityStats']['disetujui']);
        $this->assertCount(12, $props['pjlpMonthlyStats']);
    }

    public function test_superadmin_dashboard_renders_with_governance_props(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();

        $response = $this->actingAs($superadmin)->get('/dashboard');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertEquals('Superadmin', $props['role']);
        $this->assertArrayHasKey('superadminData', $props);
        $this->assertArrayHasKey('userBreakdown', $props['superadminData']);
        $this->assertArrayHasKey('seksiOverview', $props['superadminData']);
    }

    public function test_kasudin_dashboard_renders_with_admin_props(): void
    {
        $kasudin = User::where('role', 'Kasudin')->first();

        $response = $this->actingAs($kasudin)->get('/dashboard');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertEquals('Kasudin', $props['role']);
        $this->assertArrayHasKey('kpiStats', $props);
        $this->assertArrayHasKey('monthlyStats', $props);
        $this->assertArrayHasKey('todayStats', $props);
    }

    public function test_attendance_realtime_feed_returns_data_for_admin_and_pjlp(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $response = $this->actingAs($superadmin)->get('/absensi/realtime-feed');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'server_time',
            'today_date',
            'is_pjlp',
            'stats' => ['total_pjlp', 'hadir_masuk', 'hadir_pulang', 'sedang_bekerja', 'terlambat', 'belum_hadir'],
            'list' => ['data', 'total'],
        ]);

        $pjlp = User::where('role', 'PJLP')->first();
        $responsePjlp = $this->actingAs($pjlp)->get('/absensi/realtime-feed');
        $responsePjlp->assertStatus(200);
        $responsePjlp->assertJsonStructure([
            'server_time',
            'today_date',
            'is_pjlp',
            'has_checked_in',
            'has_checked_out',
        ]);
    }

    public function test_pjlp_can_submit_presensi_mandiri_realtime(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        $today = now()->toDateString();
        \App\Models\Attendance::where('pin', $pjlp->pin)->whereDate('tanggal', $today)->delete();

        // 1. Presensi Masuk
        $responseMasuk = $this->actingAs($pjlp)->post('/absensi/presensi-mandiri');
        $responseMasuk->assertStatus(302);
        $responseMasuk->assertSessionHas('success');

        $att = \App\Models\Attendance::where('pin', $pjlp->pin)->whereDate('tanggal', $today)->first();
        $this->assertNotNull($att);
        $this->assertNotNull($att->absen_masuk);
        $this->assertNull($att->absen_pulang);

        // 2. Presensi Pulang
        $responsePulang = $this->actingAs($pjlp)->post('/absensi/presensi-mandiri');
        $responsePulang->assertStatus(302);
        $responsePulang->assertSessionHas('success');

        $attFresh = $att->fresh();
        $this->assertNotNull($attFresh->absen_pulang);
    }
}

