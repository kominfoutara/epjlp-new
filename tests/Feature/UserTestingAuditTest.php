<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\DinasLuar;
use App\Models\IssueReport;
use App\Models\Seksi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserTestingAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_flows(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertStatus(200);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/aktivitas')->assertRedirect('/login');
        $this->get('/dinas-luar')->assertRedirect('/login');
        $this->get('/laporan-kendala')->assertRedirect('/login');
    }

    public function test_pjlp_full_user_journey(): void
    {
        Storage::fake('public');
        $pjlp = User::where('role', 'PJLP')->first();
        $this->assertNotNull($pjlp);

        // 1. Dashboard
        $response = $this->actingAs($pjlp)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('role')
            ->where('role', 'PJLP')
        );

        // 2. Aktivitas Saya
        $response = $this->actingAs($pjlp)->get('/aktivitas');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Index')
            ->has('activities')
            ->has('categories')
        );

        // 3. Tambah Aktivitas Harian dengan lampiran PDF & Gambar
        $fileJpg = UploadedFile::fake()->image('bukti.jpg');
        $filePdf = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $response = $this->actingAs($pjlp)->post('/aktivitas', [
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'kategori' => 'Monitoring Server',
            'lokasi' => 'Command Center',
            'uraian' => 'Uraian pekerjaan pengujian oleh user-testing.',
            'lampiran' => [$fileJpg, $filePdf],
        ]);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('aktivitas', [
            'user_id' => $pjlp->id,
            'uraian' => 'Uraian pekerjaan pengujian oleh user-testing.',
        ]);

        // 4. Dinas Luar
        $response = $this->actingAs($pjlp)->get('/dinas-luar');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('DinasLuar/Index')
            ->has('list')
        );

        // Submit Dinas Luar
        $response = $this->actingAs($pjlp)->post('/dinas-luar', [
            'tipe' => 'DL AWAL',
            'alamat' => 'Kantor Walikota Jakarta Utara',
            'latitude' => -6.12,
            'longitude' => 106.89,
            'keterangan' => 'Tugas luar audit',
            'foto' => UploadedFile::fake()->image('selfie.jpg'),
        ]);
        $response->assertSessionHas('success');

        // 5. Laporan Kendala
        $response = $this->actingAs($pjlp)->get('/laporan-kendala');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('IssueReports/Index')
            ->has('reports')
        );

        // Submit Ticket
        $response = $this->actingAs($pjlp)->post('/laporan-kendala', [
            'judul' => 'Kendala Uji Coba User Testing',
            'kategori' => 'Aplikasi & Fitur',
            'prioritas' => 'Sedang',
            'deskripsi' => 'Testing form laporan kendala user-testing.',
        ]);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('issue_reports', [
            'user_id' => $pjlp->id,
            'judul' => 'Kendala Uji Coba User Testing',
        ]);
    }

    public function test_kasi_full_user_journey(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $this->assertNotNull($kasi);

        // 1. Dashboard Kasi
        $response = $this->actingAs($kasi)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', 'Kasi')
        );

        // 2. Monitoring / Validasi Aktivitas
        $response = $this->actingAs($kasi)->get('/aktivitas');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/PjlpList')
        );

        // 3. Aktivitas Saya milik Kasi
        $response = $this->actingAs($kasi)->get('/aktivitas/saya');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Index')
        );

        // 4. Dinas Luar Kasi
        $response = $this->actingAs($kasi)->get('/dinas-luar');
        $response->assertStatus(200);
    }

    public function test_superadmin_full_user_journey(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $this->assertNotNull($superadmin);

        // 1. Dashboard Superadmin
        $response = $this->actingAs($superadmin)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('role', 'Superadmin')
        );

        // 2. User Management
        $response = $this->actingAs($superadmin)->get('/users');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users')
        );

        // 3. Seksi Management
        $response = $this->actingAs($superadmin)->get('/seksi');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seksi/Index')
            ->has('seksiList')
        );

        // 4. Categories Management
        $response = $this->actingAs($superadmin)->get('/categories');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Index')
            ->has('categories')
        );

        // 5. Activity Logs
        $response = $this->actingAs($superadmin)->get('/log-aktivitas');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ActivityLog/Index')
            ->has('logs')
        );

        // 6. Laporan Kendala (Superadmin view)
        $response = $this->actingAs($superadmin)->get('/laporan-kendala');
        $response->assertStatus(200);
    }

    public function test_pjlp_reminder_evaluasi_status_filtering(): void
    {
        $pjlp = User::where('role', 'PJLP')->first();
        $this->assertNotNull($pjlp);

        // Buat aktivitas dengan status berbeda
        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'kategori' => 'Monitoring Server',
            'uraian' => 'Aktivitas yang disetujui',
            'status_validasi' => 'Disetujui',
        ]);

        Activity::create([
            'user_id' => $pjlp->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '10:00',
            'jam_selesai' => '12:00',
            'kategori' => 'Monitoring Server',
            'uraian' => 'Aktivitas yang masih menunggu review',
            'status_validasi' => 'Menunggu',
        ]);

        // Akses route dengan filter status=Menunggu (link Reminder Evaluasi)
        $response = $this->actingAs($pjlp)->get('/aktivitas?status=Menunggu');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Index')
            ->where('filters.status', 'Menunggu')
            ->has('activities.data')
            ->where('activities.data.0.status_validasi', 'Menunggu')
        );
    }

    public function test_supervisor_reminder_evaluasi_status_filtering(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $this->assertNotNull($kasi);

        $pjlp1 = User::where('role', 'PJLP')->where('seksi', $kasi->seksi)->first();
        $this->assertNotNull($pjlp1);

        // Buat aktivitas menunggu untuk pjlp1
        Activity::create([
            'user_id' => $pjlp1->id,
            'tanggal' => now()->toDateString(),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'kategori' => 'Tugas Seksi',
            'uraian' => 'Aktivitas perlu review kasi',
            'status_validasi' => 'Menunggu',
        ]);

        $response = $this->actingAs($kasi)->get('/aktivitas?status=Menunggu');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Activity/PjlpList')
            ->where('filters.status', 'Menunggu')
            ->has('pjlpUsers')
        );
    }

    public function test_pjlp_management_status_user_update(): void
    {
        $kasi = User::where('role', 'Kasi')->first();
        $this->assertNotNull($kasi);

        $pjlp = User::where('role', 'PJLP')->where('seksi', $kasi->seksi)->first();
        $this->assertNotNull($pjlp);

        // Test update status menjadi Suspend
        $response = $this->actingAs($kasi)->put("/pjlp/{$pjlp->id}", [
            'nama_lengkap' => $pjlp->nama_lengkap,
            'username' => $pjlp->username,
            'email' => $pjlp->email,
            'nik' => $pjlp->nik,
            'pin' => $pjlp->pin,
            'seksi' => $pjlp->seksi,
            'posisi_pekerjaan' => $pjlp->posisi_pekerjaan,
            'jam_masuk' => $pjlp->jam_masuk ?? '07:30',
            'jam_pulang' => $pjlp->jam_pulang ?? '16:00',
            'status_user' => 'Suspend',
        ]);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $pjlp->id,
            'status_user' => 'Suspend',
        ]);

        // Test update status Nonaktif (otomatis dinormalisasi ke Suspend sesuai skema DB)
        $response = $this->actingAs($kasi)->put("/pjlp/{$pjlp->id}", [
            'nama_lengkap' => $pjlp->nama_lengkap,
            'username' => $pjlp->username,
            'email' => $pjlp->email,
            'nik' => $pjlp->nik,
            'pin' => $pjlp->pin,
            'seksi' => $pjlp->seksi,
            'posisi_pekerjaan' => $pjlp->posisi_pekerjaan,
            'jam_masuk' => $pjlp->jam_masuk ?? '07:30',
            'jam_pulang' => $pjlp->jam_pulang ?? '16:00',
            'status_user' => 'Nonaktif',
        ]);
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'id' => $pjlp->id,
            'status_user' => 'Suspend',
        ]);
    }

    public function test_pjlp_index_displays_all_users_except_superadmin(): void
    {
        $superadmin = User::where('role', 'Superadmin')->first();
        $this->assertNotNull($superadmin);

        $response = $this->actingAs($superadmin)->get('/pjlp');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pjlp/Index')
            ->has('list.data')
            ->where('list.data', function ($users) {
                // Pastikan tidak ada Superadmin di dalam list
                foreach ($users as $u) {
                    if ($u['role'] === 'Superadmin') {
                        return false;
                    }
                }
                // Pastikan ada PJLP dan Kasi
                $roles = collect($users)->pluck('role')->unique()->all();
                return in_array('PJLP', $roles) && in_array('Kasi', $roles);
            })
        );
    }
}
