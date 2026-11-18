<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\IssueReport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IssueReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_issue_reports_index(): void
    {
        $response = $this->get('/laporan-kendala');
        $response->assertRedirect('/login');
    }

    public function test_pjlp_can_access_issue_reports_and_submits_report(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        Storage::fake('public');

        $response = $this->actingAs($pjlp)->get('/laporan-kendala');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('IssueReports/Index')
            ->has('reports.data')
            ->has('stats')
            ->where('isSuperadmin', false)
        );

        // Submit new report
        $postResponse = $this->actingAs($pjlp)->post('/laporan-kendala', [
            'judul' => 'Kamera dinas luar gagal deteksi lokasi',
            'kategori' => 'Kamera & Foto Lapangan',
            'prioritas' => 'Tinggi',
            'deskripsi' => 'Ketika mencoba membuka kamera pada form dinas luar di HP Samsung, koordinat GPS macet.',
            'lampiran' => UploadedFile::fake()->image('error_screenshot.png'),
        ]);

        $postResponse->assertSessionHas('success');
        $this->assertDatabaseHas('issue_reports', [
            'user_id' => $pjlp->id,
            'judul' => 'Kamera dinas luar gagal deteksi lokasi',
            'kategori' => 'Kamera & Foto Lapangan',
            'prioritas' => 'Tinggi',
            'status' => 'Menunggu',
        ]);

        // Notification created for Superadmin
        $this->assertDatabaseHas('app_notifications', [
            'role' => 'Superadmin',
        ]);
    }

    public function test_kasi_and_kasudin_can_also_submit_issue_reports(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $kasudin = User::where('role', 'Kasudin')->first();

        // Kasi reports issue
        $resKasi = $this->actingAs($kasi)->post('/laporan-kendala', [
            'judul' => 'Validasi aktivitas staf tidak memicu email/notif',
            'kategori' => 'Aplikasi & Fitur',
            'prioritas' => 'Sedang',
            'deskripsi' => 'Staf mengeluhkan notifikasi validasi aktivitas tidak masuk.',
        ]);
        $resKasi->assertSessionHas('success');
        $this->assertDatabaseHas('issue_reports', [
            'user_id' => $kasi->id,
            'judul' => 'Validasi aktivitas staf tidak memicu email/notif',
        ]);

        // Kasudin reports issue
        $resKasudin = $this->actingAs($kasudin)->post('/laporan-kendala', [
            'judul' => 'Data rekap kehadiran bulanan lambat dimuat',
            'kategori' => 'Presensi & Mesin Fingerprint',
            'prioritas' => 'Darurat',
            'deskripsi' => 'Saat membuka menu rekap presensi seluruh seksi, loading memakan waktu lebih dari 15 detik.',
        ]);
        $resKasudin->assertSessionHas('success');
        $this->assertDatabaseHas('issue_reports', [
            'user_id' => $kasudin->id,
            'judul' => 'Data rekap kehadiran bulanan lambat dimuat',
            'prioritas' => 'Darurat',
        ]);
    }

    public function test_superadmin_can_view_all_reports_and_update_status(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp = User::where('role', 'PJLP')->first();

        // Create a report by PJLP
        $report = IssueReport::create([
            'ticket_number' => IssueReport::generateTicketNumber(),
            'user_id' => $pjlp->id,
            'judul' => 'Tidak bisa simpan aktivitas harian',
            'kategori' => 'Aplikasi & Fitur',
            'prioritas' => 'Tinggi',
            'deskripsi' => 'Tombol simpan disable padahal semua field telah diisi.',
            'status' => 'Menunggu',
        ]);

        // Superadmin views index
        $res = $this->actingAs($superadmin)->get('/laporan-kendala');
        $res->assertStatus(200);
        $res->assertInertia(fn ($page) => $page
            ->component('IssueReports/Index')
            ->where('isSuperadmin', true)
        );

        // Superadmin updates to Diproses
        $updateRes = $this->actingAs($superadmin)->patch("/laporan-kendala/{$report->id}", [
            'status' => 'Diproses',
            'tanggapan_it' => 'Sedang dilakukan pengecekan pada modul aktivitas.',
        ]);

        $updateRes->assertSessionHas('success');
        $this->assertDatabaseHas('issue_reports', [
            'id' => $report->id,
            'status' => 'Diproses',
            'handled_by' => $superadmin->id,
            'tanggapan_it' => 'Sedang dilakukan pengecekan pada modul aktivitas.',
        ]);

        // Superadmin completes ticket
        $completeRes = $this->actingAs($superadmin)->patch("/laporan-kendala/{$report->id}", [
            'status' => 'Selesai',
            'tanggapan_it' => 'Bug validasi telah diperbaiki dan deploy selesai.',
        ]);

        $completeRes->assertSessionHas('success');
        $this->assertDatabaseHas('issue_reports', [
            'id' => $report->id,
            'status' => 'Selesai',
        ]);

        // Reporter receives notification
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $pjlp->id,
            'type' => 'success',
        ]);
    }

    public function test_non_superadmin_cannot_update_ticket_status(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        $kasi = User::where('role', 'Kasi')->first();

        $report = IssueReport::create([
            'ticket_number' => IssueReport::generateTicketNumber(),
            'user_id' => $pjlp->id,
            'judul' => 'Test unauthorized update',
            'kategori' => 'Lainnya',
            'prioritas' => 'Sedang',
            'deskripsi' => 'Deskripsi kendala pengujian otorisasi.',
            'status' => 'Menunggu',
        ]);

        $this->actingAs($kasi)->patch("/laporan-kendala/{$report->id}", [
            'status' => 'Selesai',
        ])->assertStatus(403);

        $this->actingAs($pjlp)->patch("/laporan-kendala/{$report->id}", [
            'status' => 'Selesai',
        ])->assertStatus(403);
    }

    public function test_reporter_can_delete_ticket_only_when_waiting(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $reportWaiting = IssueReport::create([
            'ticket_number' => IssueReport::generateTicketNumber(),
            'user_id' => $pjlp->id,
            'judul' => 'Kendala keliru input',
            'kategori' => 'Lainnya',
            'prioritas' => 'Rendah',
            'deskripsi' => 'Salah lapor, ingin saya batalkan segera.',
            'status' => 'Menunggu',
        ]);

        // PJLP can delete when status is Menunggu
        $delRes = $this->actingAs($pjlp)->delete("/laporan-kendala/{$reportWaiting->id}");
        $delRes->assertSessionHas('success');
        $this->assertDatabaseMissing('issue_reports', [
            'id' => $reportWaiting->id,
        ]);

        // Create a report that is already 'Diproses'
        $reportDiproses = IssueReport::create([
            'ticket_number' => IssueReport::generateTicketNumber(),
            'user_id' => $pjlp->id,
            'judul' => 'Kendala sedang diproses',
            'kategori' => 'Lainnya',
            'prioritas' => 'Sedang',
            'deskripsi' => 'Sudah dalam penanganan tim IT kominfotik.',
            'status' => 'Diproses',
        ]);

        $delFailed = $this->actingAs($pjlp)->delete("/laporan-kendala/{$reportDiproses->id}");
        $delFailed->assertSessionHas('error');
        $this->assertDatabaseHas('issue_reports', [
            'id' => $reportDiproses->id,
        ]);
    }

    public function test_user_sees_superadmin_notes_and_handler_details_in_reports_list(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $pjlp = User::where('role', 'PJLP')->first();

        $report = IssueReport::create([
            'ticket_number' => IssueReport::generateTicketNumber(),
            'user_id' => $pjlp->id,
            'judul' => 'Kendala fingerprint tidak sinkron',
            'kategori' => 'Presensi & Mesin Fingerprint',
            'prioritas' => 'Tinggi',
            'deskripsi' => 'Data presensi pagi ini belum muncul di dashboard.',
            'status' => 'Selesai',
            'handled_by' => $superadmin->id,
            'tanggapan_it' => 'Data mesin telah disinkronkan ulang oleh Tim IT. Silakan periksa kembali.',
            'handled_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($pjlp)->get('/laporan-kendala');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('IssueReports/Index')
            ->has('reports.data.0', fn ($item) => $item
                ->where('id', $report->id)
                ->where('status', 'Selesai')
                ->where('tanggapan_it', 'Data mesin telah disinkronkan ulang oleh Tim IT. Silakan periksa kembali.')
                ->where('handled_by', $superadmin->id)
                ->where('handler.nama_lengkap', $superadmin->nama_lengkap)
                ->etc()
            )
        );
    }

    public function test_realtime_notification_unread_polling_includes_pending_issues_count_and_link_attribute(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        // Create a report with pending status
        $report = IssueReport::create([
            'ticket_number' => IssueReport::generateTicketNumber(),
            'user_id' => $pjlp->id,
            'judul' => 'Kendala Realtime Test',
            'kategori' => 'Aplikasi & Fitur',
            'prioritas' => 'Sedang',
            'deskripsi' => 'Deskripsi kendala pengujian realtime notifikasi.',
            'status' => 'Diproses',
        ]);

        // Create an unread notification with a url
        $notif = AppNotification::send([
            'user_id' => $pjlp->id,
            'title' => "Update Tiket {$report->ticket_number}: Diproses",
            'message' => 'Laporan kendala sedang diproses.',
            'type' => 'info',
            'url' => "/laporan-kendala?search={$report->ticket_number}",
        ]);

        $response = $this->actingAs($pjlp)->getJson('/notifications/unread');
        $response->assertStatus(200);
        $response->assertJson([
            'unread_count' => 1,
            'pending_issues_count' => 1,
        ]);

        $latest = $response->json('latest');
        $this->assertNotEmpty($latest);
        $this->assertEquals("/laporan-kendala?search={$report->ticket_number}", $latest[0]['link']);
        $this->assertEquals("/laporan-kendala?search={$report->ticket_number}", $latest[0]['url']);
    }
}

