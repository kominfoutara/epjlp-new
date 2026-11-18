<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SeksiSeeder::class);
        $this->call(PjlpUserSeeder::class);

        $defaultPassword = Hash::make('password');

        // Superadmin
        User::updateOrCreate(
            ['username' => 'admin1'],
            [
                'name' => 'Admin 1',
                'nama_lengkap' => 'Admin Superadmin',
                'nik' => 'ADMIN001',
                'no_wa' => '08120000001',
                'email' => 'admin1@epjlp.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'Superadmin',
                'seksi' => 'Subbagian Tata Usaha',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Superadmin Sistem',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Superadmin',
                'nama_lengkap' => 'Super Administrator',
                'nik' => 'ADMIN000',
                'nrk' => 'ADMIN000',
                'no_wa' => '08120000000',
                'email' => 'superadmin@epjlp.go.id',
                'password' => Hash::make('superadmin1234'),
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'Superadmin',
                'seksi' => 'Subbagian Tata Usaha',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Super Administrator Sistem',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // Kasudin
        User::updateOrCreate(
            ['username' => 'kasudin'],
            [
                'name' => 'R. Fauzi',
                'nama_lengkap' => 'R. Fauzi, S.Kom',
                'nik' => 'KASUDIN001',
                'no_wa' => '08120000002',
                'email' => 'kasudin@epjlp.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'Kasudin',
                'seksi' => 'Pimpinan',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Kepala Suku Dinas Kominfotik JU',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // Kasi ASTIK
        User::updateOrCreate(
            ['username' => 'kasiastik'],
            [
                'name' => 'Rentina Romauli',
                'nama_lengkap' => 'Rentina Romauli, S.Sos',
                'nik' => 'KASIASTIK01',
                'no_wa' => '08120000003',
                'email' => 'kasiastik@epjlp.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Perempuan',
                'role' => 'Kasi',
                'seksi' => 'Seksi ASTIK',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Kepala Seksi ASTIK',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // Kasi KIP
        User::updateOrCreate(
            ['username' => 'kasikip'],
            [
                'name' => 'Ruki Cita Munggaran',
                'nama_lengkap' => 'Ruki Cita Munggaran, S.Kom',
                'nik' => 'KASIKIP01',
                'no_wa' => '08120000004',
                'email' => 'kasikip@epjlp.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'Kasi',
                'seksi' => 'Seksi KIP',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Kepala Seksi KIP',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // Kasi ID
        User::updateOrCreate(
            ['username' => 'kasiid'],
            [
                'name' => 'Hadi R. Soemarto',
                'nama_lengkap' => 'Hadi R. Soemarto, ST',
                'nik' => 'KASIID01',
                'no_wa' => '08120000005',
                'email' => 'kasiid@epjlp.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'Kasi',
                'seksi' => 'Seksi ID',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Kepala Seksi ID',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // PJLP 1 - ASTIK
        User::updateOrCreate(
            ['username' => 'ikhwan'],
            [
                'name' => 'Ikhwan Prasetya',
                'nama_lengkap' => 'Ikhwan Prasetya',
                'nik' => '3314011306920003',
                'no_wa' => '085642174795',
                'email' => 'ichwanprasetya@gmail.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'PJLP',
                'seksi' => 'Seksi ASTIK',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Application Support Kominfotik/PJLP E-Government',
                'pin' => '80790520',
                'no_registrasi' => '80790520',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // PJLP 2 - KIP
        User::updateOrCreate(
            ['username' => 'ferry'],
            [
                'name' => 'Ferry Satrio Wibowo',
                'nama_lengkap' => 'Ferry Satrio Wibowo',
                'nik' => '3174040701840007',
                'no_wa' => '0816651197',
                'email' => 'fsatriowibowo@gmail.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'PJLP',
                'seksi' => 'Seksi KIP',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'PPPK_PW',
                'pin' => '80748379',
                'no_registrasi' => '80748379',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // PJLP 3 - KIP
        User::updateOrCreate(
            ['username' => 'louise'],
            [
                'name' => 'Louise Mardiliyanti',
                'nama_lengkap' => 'Louise Mardiliyanti',
                'nik' => '3172045403820004',
                'no_wa' => '085714113114',
                'email' => 'louisemania82@gmail.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Perempuan',
                'role' => 'PJLP',
                'seksi' => 'Seksi KIP',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'PJLP Reporter Kominfotik',
                'pin' => '80748604',
                'no_registrasi' => '80748604',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
            ]
        );

        // Kategori Aktivitas
        $categories = [
            'Seksi ASTIK' => [
                'Update modul/website/microsite',
                'Monitoring Server',
                'Dukungan Teknis Aplikasi',
                'Fasilitasi Zoom Meeting / Live Streaming',
                'Statistik Sektoral',
                'Satu Data Indonesia',
                'Penerbitan Sertifikat Elektronik',
                'Instalasi Antivirus',
                'Insiden Siber',
                'Kontra Pengindraan',
                'Fasilitasi Email JakGo',
                'Diseminasi Keamanan Informasi',
                'Survey Indeks Kepuasan Layanan Persandian',
                'Tugas dan Arahan Pimpinan',
            ],
            'Seksi KIP' => [
                'Dokumentasi',
                'Liputan',
                'Edit Video',
                'Desain Grafis',
                'Membuat Konten',
                'Edit Berita',
                'Tugas dan Arahan Pimpinan',
            ],
            'Seksi ID' => [
                'Melakukan pengecekan dan pemeliharaan sistem komputer dan jaringan',
                'Mendiagnosis dan memecahkan masalah (troubleshooting) sistem, jaringan dan hardware yang rumit/kompleks',
                'Melakukan instalasi/re-instalasi jaringan (crimping, splicing dan testing)',
                'Melaksanakan penataan sarana dan prasarana perangkat jaringan',
                'Tugas dan Arahan Pimpinan',
            ],
        ];

        foreach ($categories as $seksi => $list) {
            $cleanSeksi = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $seksi));
            $variants = array_unique([$seksi, $cleanSeksi, 'Seksi ' . $cleanSeksi, ucfirst(strtolower($cleanSeksi))]);
            foreach ($variants as $variant) {
                foreach ($list as $nama) {
                    ActivityCategory::firstOrCreate([
                        'nama' => $nama,
                        'seksi' => $variant,
                    ]);
                }
            }
        }
    }
}
