<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperadminRekapAktivitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_superadmin_can_access_rekap_aktivitas_and_see_all_user_activities(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $this->assertNotNull($superadmin);

        $pjlp1 = User::where('role', 'PJLP')->first();
        $this->assertNotNull($pjlp1);

        Activity::create([
            'user_id' => $pjlp1->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Maintenance server utama kantor',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($superadmin)->get('/aktivitas');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/RekapAktivitas')
            ->has('activities.data')
            ->has('stats')
            ->has('seksiList')
            ->has('usersList')
            ->has('filters')
        );
    }

    public function test_superadmin_can_filter_activities_by_status(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp1 = User::where('role', 'PJLP')->first();

        Activity::create([
            'user_id' => $pjlp1->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Jaringan',
            'uraian' => 'Aktivitas khusus status disetujui',
            'status_validasi' => 'Disetujui',
        ]);

        $response = $this->actingAs($superadmin)->get('/aktivitas?status=Disetujui');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/RekapAktivitas')
            ->where('filters.status', 'Disetujui')
        );
    }

    public function test_superadmin_can_search_activities_by_keyword(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp1 = User::where('role', 'PJLP')->first();

        Activity::create([
            'user_id' => $pjlp1->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Jaringan',
            'uraian' => 'Konfigurasi switch core cisco lantai 4',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($superadmin)->get('/aktivitas?search=cisco');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/RekapAktivitas')
            ->where('filters.search', 'cisco')
            ->has('activities.data', 1)
        );
    }

    public function test_kasi_still_sees_pjlp_list_for_validation(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $this->assertNotNull($kasi);

        $response = $this->actingAs($kasi)->get('/aktivitas');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/PjlpList')
            ->has('pjlpUsers')
        );
    }

    public function test_pjlp_still_sees_own_activity_sheet(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        $this->assertNotNull($pjlp);

        $response = $this->actingAs($pjlp)->get('/aktivitas');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Index')
            ->where('isOwner', true)
        );
    }

    public function test_superadmin_can_export_activities_to_excel_with_attachment_links(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp = User::where('role', 'PJLP')->first();

        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Penyiapan berkas dan dokumentasi server',
            'lampiran' => 'foto_kegiatan_1.jpg,dokumen_laporan.pdf',
            'status_validasi' => 'Disetujui',
        ]);

        $response = $this->actingAs($superadmin)->get('/aktivitas/export/excel?bulan=' . now()->format('Y-m'));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('content-type') ?? '', 'spreadsheetml.sheet') ||
            str_contains($response->headers->get('content-disposition') ?? '', '.xlsx')
        );
        $this->assertStringContainsString('Rekap_Aktivitas_', $response->headers->get('content-disposition') ?? '');

        // Verify that the Excel content has the numbered attachment layout inside Column J
        ob_start();
        $response->sendContent();
        $excelBinary = ob_get_clean();

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_');
        file_put_contents($tempPath, $excelBinary);

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $loadedSheet = $reader->load($tempPath)->getActiveSheet();

        // Check header column J
        $this->assertEquals('Uraian Aktivitas & Capaian (Lampiran)', $loadedSheet->getCell('J7')->getValue());

        // Find the row for this activity
        $foundCell = false;
        for ($r = 8; $r <= $loadedSheet->getHighestRow(); $r++) {
            $val = $loadedSheet->getCell('J' . $r)->getValue();
            if (str_contains($val, 'Penyiapan berkas dan dokumentasi server')) {
                $foundCell = true;
                $this->assertStringContainsString('(1). ', $val);
                $this->assertStringContainsString('(2). ', $val);
                $this->assertStringContainsString('/aktivitas/lampiran/view/foto_kegiatan_1.jpg', $val);
                $this->assertStringContainsString('/aktivitas/lampiran/view/dokumen_laporan.pdf', $val);
                break;
            }
        }
        $this->assertTrue($foundCell, 'Activity row was not found in exported Excel sheet.');

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    public function test_superadmin_can_export_activities_to_pdf_with_attachment_links(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp = User::where('role', 'PJLP')->first();

        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Dokumentasi perbaikan kabel FO',
            'lampiran' => 'foto_1.jpg,foto_2.jpg,laporan_fo.pdf',
            'status_validasi' => 'Disetujui',
        ]);

        $response = $this->actingAs($superadmin)->get('/aktivitas/export/pdf?bulan=' . now()->format('Y-m'));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Rekap_Aktivitas_', $response->headers->get('content-disposition') ?? '');
    }

    public function test_kasi_can_export_activities_to_excel_for_their_seksi(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $this->assertNotNull($kasi);

        $pjlp = User::where('role', 'PJLP')->where('seksi', $kasi->seksi)->first();
        if (!$pjlp) {
            $pjlp = User::where('role', 'PJLP')->first();
            $pjlp->update(['seksi' => $kasi->seksi]);
        }

        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Uji coba export excel dari akun Kasi',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->get('/aktivitas/export/excel?bulan=' . now()->format('Y-m'));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('content-type') ?? '', 'spreadsheetml.sheet') ||
            str_contains($response->headers->get('content-disposition') ?? '', '.xlsx')
        );
        $this->assertStringContainsString('Rekap_Aktivitas_', $response->headers->get('content-disposition') ?? '');
    }

    public function test_kasi_can_export_activities_to_pdf_for_their_seksi(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $this->assertNotNull($kasi);

        $pjlp = User::where('role', 'PJLP')->where('seksi', $kasi->seksi)->first();
        if (!$pjlp) {
            $pjlp = User::where('role', 'PJLP')->first();
            $pjlp->update(['seksi' => $kasi->seksi]);
        }

        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Uji coba export pdf dari akun Kasi',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->get('/aktivitas/export/pdf?bulan=' . now()->format('Y-m'));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Rekap_Aktivitas_', $response->headers->get('content-disposition') ?? '');
    }

    public function test_pjlp_cannot_export_superadmin_activities(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $excelResponse = $this->actingAs($pjlp)->get('/aktivitas/export/excel');
        $excelResponse->assertStatus(403);

        $pdfResponse = $this->actingAs($pjlp)->get('/aktivitas/export/pdf');
        $pdfResponse->assertStatus(403);
    }

    public function test_attachment_viewer_renders_image_for_authenticated_user(): void
    {
        $user = User::where('role', 'Superadmin')->first();

        // Buat file dummy di storage/app/public/lampiran_aktivitas
        $testDir = storage_path('app/public/lampiran_aktivitas');
        if (!file_exists($testDir)) {
            mkdir($testDir, 0755, true);
        }
        $dummyFile = 'test_sample_image.jpg';
        file_put_contents($testDir . '/' . $dummyFile, 'dummy-image-content');

        $response = $this->actingAs($user)->get('/aktivitas/lampiran/view/' . $dummyFile);

        $response->assertStatus(200);
        $response->assertSee('test_sample_image.jpg');
        $response->assertSee('Kembali ke Rekap PDF');
        $response->assertSee('Unduh ⤓');
        $response->assertDontSee('Buka di Tab Baru');

        // Cleanup
        if (file_exists($testDir . '/' . $dummyFile)) {
            unlink($testDir . '/' . $dummyFile);
        }
    }

    public function test_attachment_viewer_returns_404_when_file_not_found(): void
    {
        $user = User::where('role', 'Superadmin')->first();

        $response = $this->actingAs($user)->get('/aktivitas/lampiran/view/non_existent_file_12345.jpg');

        $response->assertStatus(404);
    }

    public function test_exported_pdf_contains_new_window_attributes_and_viewer_links(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp = User::where('role', 'PJLP')->first();

        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Dokumentasi dengan link foto NewWindow',
            'lampiran' => 'kegiatan_uji_newwindow.jpg',
            'status_validasi' => 'Disetujui',
        ]);

        $response = $this->actingAs($superadmin)->get('/aktivitas/export/pdf?bulan=' . now()->format('Y-m'));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('/NewWindow true', $content);
    }
}
