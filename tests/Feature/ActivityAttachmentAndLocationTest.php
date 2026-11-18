<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityAttachmentAndLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    public function test_pjlp_can_submit_activity_with_optional_lokasi_null(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Monitoring Server',
            'lokasi' => null, // Optional
            'uraian' => 'Melakukan pengecekan rutin server tanpa input lokasi',
            'lampiran' => [
                UploadedFile::fake()->image('monitoring.png'),
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('aktivitas', [
            'user_id' => $pjlp->id,
            'uraian' => 'Melakukan pengecekan rutin server tanpa input lokasi',
            'lokasi' => null,
        ]);
    }

    public function test_pjlp_can_submit_activity_with_excel_and_pdf_attachments(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $fotoFile = UploadedFile::fake()->image('dokumentasi.png');
        $excelFile = UploadedFile::fake()->create('laporan_rekap.xlsx', 150, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdfFile = UploadedFile::fake()->create('dokumen_pendukung.pdf', 300, 'application/pdf');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'kategori' => 'Statistik Sektoral',
            'lokasi' => 'Kantor Walikota',
            'uraian' => 'Mengolah data sektoral dan menyusun rekap excel serta panduan pdf',
            'lampiran' => [
                $fotoFile,
                $excelFile,
                $pdfFile,
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $activity = Activity::where('user_id', $pjlp->id)
            ->where('uraian', 'Mengolah data sektoral dan menyusun rekap excel serta panduan pdf')
            ->first();

        $this->assertNotNull($activity);
        $this->assertNotNull($activity->lampiran);

        $files = explode(',', $activity->lampiran);
        $this->assertCount(3, $files);
    }

    public function test_activity_store_redirects_to_month_and_appears_in_log_activities(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        $targetMonth = now()->format('Y-m');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '17:00',
            'kategori' => 'Monitoring Server',
            'lokasi' => 'Kantor Sudin Kominfotik',
            'uraian' => 'Aktivitas pengujian otomatis tersimpan di log aktivitas harian',
            'lampiran' => [
                UploadedFile::fake()->image('test_log.png'),
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('activity.index', ['bulan' => $targetMonth]));
        $response->assertSessionHas('success', 'Aktivitas berhasil ditambahkan ke Log Aktivitas Harian.');

        // Pastikan saat lembar aktivitas diakses, aktivitas muncul di log
        $pageResponse = $this->actingAs($pjlp)->get(route('activity.index', ['bulan' => $targetMonth]));
        $pageResponse->assertOk();
        $pageResponse->assertInertia(fn ($page) => $page
            ->component('Activity/Index')
            ->where('activities.data.0.uraian', 'Aktivitas pengujian otomatis tersimpan di log aktivitas harian')
        );
    }

    public function test_activity_sheet_overflow_page_redirects_to_page_one(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        $targetMonth = now()->format('Y-m');

        // Mengakses halaman 99 saat jumlah data sedikit harus diarahkan ke page 1
        $response = $this->actingAs($pjlp)->get(route('activity.index', [
            'bulan' => $targetMonth,
            'page' => 99,
        ]));

        $response->assertRedirect(route('activity.index', [
            'bulan' => $targetMonth,
            'page' => 1,
        ]));
    }

    public function test_pjlp_can_upload_up_to_5_photos_and_1_pdf(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $photos = [
            UploadedFile::fake()->image('foto1.jpg'),
            UploadedFile::fake()->image('foto2.jpg'),
            UploadedFile::fake()->image('foto3.jpg'),
            UploadedFile::fake()->image('foto4.jpg'),
            UploadedFile::fake()->image('foto5.jpg'),
        ];
        $pdf = UploadedFile::fake()->create('laporan.pdf', 500, 'application/pdf');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'lokasi' => 'Gedung Walikota',
            'uraian' => 'Upload 5 foto dokumentasi dan 1 file PDF lampiran',
            'lampiran' => array_merge($photos, [$pdf]),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $activity = Activity::where('user_id', $pjlp->id)
            ->where('uraian', 'Upload 5 foto dokumentasi dan 1 file PDF lampiran')
            ->first();

        $this->assertNotNull($activity);
        $files = explode(',', $activity->lampiran);
        $this->assertCount(6, $files);
    }

    public function test_pjlp_cannot_upload_more_than_5_photos(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $photos = [
            UploadedFile::fake()->image('foto1.jpg'),
            UploadedFile::fake()->image('foto2.jpg'),
            UploadedFile::fake()->image('foto3.jpg'),
            UploadedFile::fake()->image('foto4.jpg'),
            UploadedFile::fake()->image('foto5.jpg'),
            UploadedFile::fake()->image('foto6.jpg'), // 6th photo exceeds limit
        ];

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Mencoba upload 6 foto',
            'lampiran' => $photos,
        ]);

        $response->assertSessionHasErrors('lampiran');
    }

    public function test_pjlp_cannot_upload_more_than_1_pdf(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $pdfs = [
            UploadedFile::fake()->create('dokumen1.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->create('dokumen2.pdf', 300, 'application/pdf'), // 2nd PDF exceeds limit
        ];

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Mencoba upload 2 PDF',
            'lampiran' => $pdfs,
        ]);

        $response->assertSessionHasErrors('lampiran');
    }

    public function test_pjlp_cannot_upload_pdf_over_10mb(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $oversizedPdf = UploadedFile::fake()->create('dokumen_besar.pdf', 10241, 'application/pdf'); // > 10MB (10240 KB)

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Mencoba upload PDF melebihi 10MB',
            'lampiran' => [$oversizedPdf],
        ]);

        $response->assertSessionHasErrors(['lampiran.0']);
    }

    public function test_pjlp_must_upload_at_least_1_image(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        // Hanya upload PDF tanpa ada foto kegiatan sama sekali
        $pdfOnly = UploadedFile::fake()->create('dokumen.pdf', 200, 'application/pdf');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Mencoba submit tanpa foto dokumentasi kegiatan',
            'lampiran' => [$pdfOnly],
        ]);

        $response->assertSessionHasErrors('lampiran');
    }

    public function test_pjlp_can_upload_various_image_formats(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $webpImage = UploadedFile::fake()->image('bukti.webp');
        $bmpImage = UploadedFile::fake()->image('bukti.bmp');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Upload foto format bebas webp dan bmp',
            'lampiran' => [$webpImage, $bmpImage],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $activity = Activity::where('user_id', $pjlp->id)
            ->where('uraian', 'Upload foto format bebas webp dan bmp')
            ->first();

        $this->assertNotNull($activity);
        $files = explode(',', $activity->lampiran);
        $this->assertCount(2, $files);
    }

    public function test_pjlp_cannot_upload_image_over_10mb(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        // Image file over 10MB (10241 KB)
        $oversizedImage = UploadedFile::fake()->create('foto_raksasa.jpg', 10245, 'image/jpeg');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Mencoba upload foto melebihi 10MB',
            'lampiran' => [$oversizedImage],
        ]);

        $response->assertSessionHasErrors(['lampiran.0']);
    }

    public function test_pjlp_can_edit_rejected_activity_and_it_reverts_to_menunggu(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();

        $activity = Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Tugas Seksi',
            'lokasi' => 'Sudin',
            'uraian' => 'Uraian sebelum ditolak',
            'lampiran' => 'foto_lama.jpg',
            'status_validasi' => 'Ditolak',
            'catatan' => 'Uraian kurang rinci, mohon diperbaiki',
        ]);

        $response = $this->actingAs($pjlp)->post("/aktivitas/{$activity->id}", [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:30',
            'jam_selesai' => '16:30',
            'kategori' => 'Tugas Seksi',
            'lokasi' => 'Sudin Jakarta Utara',
            'uraian' => 'Uraian yang telah diperbaiki secara lengkap dan rinci',
            'lampiran' => [
                UploadedFile::fake()->image('foto_revisi.jpg'),
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $fresh = $activity->fresh();
        $this->assertEquals('Uraian yang telah diperbaiki secara lengkap dan rinci', $fresh->uraian);
        $this->assertEquals('Menunggu', $fresh->status_validasi, 'Status aktivitas yang ditolak harus berubah kembali menjadi Menunggu setelah diedit.');
        $this->assertNull($fresh->catatan, 'Catatan penolakan sebelumnya harus di-reset setelah perbaikan diserahkan.');
    }
}
