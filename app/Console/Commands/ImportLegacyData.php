<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class ImportLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:import 
                            {--legacy-db=epjlp_legacy_temp : Database nama sistem lama di MySQL}
                            {--skip-files : Lewati penyalinan berkas lampiran dan foto}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data user dan aktivitas dari project lama, hapus data dummy, dan hubungkan berkas lampiran';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $legacyDb = $this->option('legacy-db');
        $this->info("=== Memulai Migrasi Data dari Project Lama ({$legacyDb}) ===");

        // 1. Verifikasi koneksi ke legacy database
        try {
            $legacyPdo = new \PDO("mysql:host=127.0.0.1;port=3306;dbname={$legacyDb}", 'root', '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Exception $e) {
            $this->error("Gagal terhubung ke database legacy '{$legacyDb}': " . $e->getMessage());
            return 1;
        }

        $legacyUserCount = (int) $legacyPdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn();
        $legacyActCount = (int) $legacyPdo->query("SELECT COUNT(*) FROM `aktivitas`")->fetchColumn();
        $this->info("Ditemukan pada {$legacyDb}:");
        $this->line(" - User: {$legacyUserCount} orang");
        $this->line(" - Aktivitas: {$legacyActCount} entri");

        if ($legacyUserCount === 0 || $legacyActCount === 0) {
            $this->error("Data user atau aktivitas kosong pada database legacy.");
            return 1;
        }

        // 2. Sinkronisasi Kategori Aktivitas
        $this->info("Sinkronisasi Kategori Aktivitas...");
        $legacyCategories = $legacyPdo->query("SELECT DISTINCT nama, seksi FROM `kategori_aktivitas`")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($legacyCategories as $cat) {
            DB::table('kategori_aktivitas')->updateOrInsert(
                ['nama' => $cat['nama']],
                [
                    'seksi' => $cat['seksi'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Bersihkan data dummy pada database baru
        $this->info("Menghapus seluruh data dummy (user, aktivitas, dll)...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('aktivitas')->truncate();
        DB::table('dinas_luar')->truncate();
        DB::table('activity_logs')->truncate();
        DB::table('app_notifications')->truncate();
        DB::table('issue_reports')->truncate();
        DB::table('laporan_bulanan_aktivitas')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 4. Migrasi User
        $this->info("Mengimpor data user dari project lama...");
        $legacyUsers = $legacyPdo->query("SELECT * FROM `user` ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $defaultPassword = Hash::make('password');
        $importedUsers = 0;

        foreach ($legacyUsers as $u) {
            $jamMasuk = (!empty($u['jam_masuk']) && $u['jam_masuk'] !== '00:00:07') ? $u['jam_masuk'] : '07:30:00';
            $jamPulang = (!empty($u['jam_pulang']) && $u['jam_pulang'] !== '00:00:16') ? $u['jam_pulang'] : '16:00:00';

            // Mapping role
            $role = in_array($u['role'], ['Superadmin', 'Kasudin', 'Kasi', 'PJLP']) ? $u['role'] : 'PJLP';

            DB::table('users')->insert([
                'id' => $u['id'],
                'name' => $u['nama_lengkap'],
                'nama_lengkap' => $u['nama_lengkap'],
                'username' => $u['username'],
                'nik' => $u['nik'] ?? null,
                'no_wa' => $u['no_wa'] ?? null,
                'email' => $u['email'] ?? null,
                'password' => $defaultPassword,
                'jenis_kelamin' => $u['jenis_kelamin'] ?? null,
                'tanggal_lahir' => !empty($u['tanggal_lahir']) && $u['tanggal_lahir'] !== '0000-00-00' ? $u['tanggal_lahir'] : null,
                'tempat_lahir' => $u['tempat_lahir'] ?? null,
                'mulai_bekerja' => !empty($u['mulai_bekerja']) && $u['mulai_bekerja'] !== '0000-00-00' ? $u['mulai_bekerja'] : null,
                'pendidikan_terakhir' => $u['pendidikan_terakhir'] ?? null,
                'nama_instansi' => $u['nama_instansi'] ?? 'SUKU DINAS KOMINFOTIK JAKARTA UTARA',
                'status_pernikahan' => $u['status_pernikahan'] ?? null,
                'seksi' => $u['seksi'] ?? null,
                'posisi_pekerjaan' => $u['posisi_pekerjaan'] ?? null,
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
                'foto' => $u['foto'] ?? null,
                'role' => $role,
                'status_user' => 'Aktif',
                'no_registrasi' => $u['no_registrasi'] ?? null,
                'pin' => $u['pin'] ?? null,
                'jabatan' => $u['jabatan'] ?? null,
                'nrk' => $u['nrk'] ?? null,
                'created_at' => $u['created_at'] ?? now(),
                'updated_at' => $u['updated_at'] ?? now(),
            ]);

            $importedUsers++;
        }

        // Tambahkan user khusus pendukung yang sudah ada di sistem jika belum tercakup
        // Irwan Masuri (PIN: 80752134)
        if (!DB::table('users')->where('pin', '80752134')->exists()) {
            DB::table('users')->insert([
                'id' => 25,
                'name' => 'Irwan Masuri',
                'nama_lengkap' => 'Irwan Masuri',
                'username' => 'irwan',
                'nik' => '2134819231123457',
                'no_wa' => '082137456671',
                'email' => 'irwan12@gmail.com',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'seksi' => 'Tata Usaha',
                'posisi_pekerjaan' => 'Tenaga Ahli Tata Usaha',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
                'role' => 'PJLP',
                'status_user' => 'Aktif',
                'no_registrasi' => '80752134',
                'pin' => '80752134',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $importedUsers++;
        }

        // Superadmin sistem cadangan
        if (!DB::table('users')->where('username', 'superadmin')->exists()) {
            DB::table('users')->insert([
                'id' => 26,
                'name' => 'Superadmin',
                'nama_lengkap' => 'Super Administrator',
                'username' => 'superadmin',
                'nik' => 'ADMIN000',
                'nrk' => 'ADMIN000',
                'no_wa' => '08120000000',
                'email' => 'superadmin@epjlp.go.id',
                'password' => $defaultPassword,
                'jenis_kelamin' => 'Laki-Laki',
                'role' => 'Superadmin',
                'seksi' => 'Subbagian Tata Usaha',
                'status_user' => 'Aktif',
                'posisi_pekerjaan' => 'Super Administrator Sistem',
                'jam_masuk' => '07:30:00',
                'jam_pulang' => '16:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $importedUsers++;
        }

        $this->info("Berhasil mengimpor {$importedUsers} user!");

        // 5. Migrasi Aktivitas
        $this->info("Mengimpor data aktivitas ({$legacyActCount} entri) dalam batch...");
        $bar = $this->output->createProgressBar($legacyActCount);
        $bar->start();

        $chunkSize = 500;
        $offset = 0;
        $totalImportedAct = 0;

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        while (true) {
            $stmt = $legacyPdo->prepare("SELECT * FROM `aktivitas` ORDER BY id ASC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $chunkSize, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) {
                break;
            }

            $insertData = [];
            foreach ($rows as $r) {
                // Map status validasi
                $status = 'Menunggu';
                if ($r['status_validasi'] === 'Diterima') {
                    $status = 'Disetujui';
                } elseif ($r['status_validasi'] === 'Ditolak') {
                    $status = 'Ditolak';
                }

                $insertData[] = [
                    'id' => $r['id'],
                    'user_id' => $r['user_id'],
                    'no' => $r['no'] ? substr($r['no'], 0, 50) : null,
                    'tanggal' => $r['tanggal'],
                    'jam_mulai' => $r['jam_mulai'],
                    'jam_selesai' => $r['jam_selesai'],
                    'kategori' => $r['kategori'] ?? 'Aktivitas Harian',
                    'lokasi' => $r['lokasi'] ? substr($r['lokasi'], 0, 255) : null,
                    'nama_unit' => $r['nama_unit'] ? substr($r['nama_unit'], 0, 255) : null,
                    'uraian' => $r['uraian'] ?? '-',
                    'lampiran' => $r['lampiran'] ? substr($r['lampiran'], 0, 255) : null,
                    'status_validasi' => $status,
                    'catatan' => $r['catatan'] ?? null,
                    'created_at' => $r['created_at'] ?? now(),
                    'updated_at' => $r['updated_at'] ?? now(),
                ];
            }

            DB::table('aktivitas')->insert($insertData);
            $countThisBatch = count($insertData);
            $totalImportedAct += $countThisBatch;
            $bar->advance($countThisBatch);

            $offset += $chunkSize;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $bar->finish();
        $this->newLine();
        $this->info("Berhasil mengimpor {$totalImportedAct} aktivitas!");

        // 6. Sinkronisasi berkas lampiran dan foto jika tidak dilewati
        if (!$this->option('skip-files')) {
            $this->info("Menyalin berkas lampiran aktivitas dan foto profil...");
            $sourceLampiran = 'C:\\Users\\andika\\epjlp-main\\upload\\lampiran';
            $targetLampiran = storage_path('app/public/lampiran_aktivitas');

            if (File::isDirectory($sourceLampiran)) {
                if (!File::isDirectory($targetLampiran)) {
                    File::makeDirectory($targetLampiran, 0755, true);
                }
                $lampiranFiles = File::files($sourceLampiran);
                $this->line("Menyalin " . count($lampiranFiles) . " file lampiran...");
                foreach ($lampiranFiles as $file) {
                    $dest = $targetLampiran . DIRECTORY_SEPARATOR . $file->getFilename();
                    if (!File::exists($dest)) {
                        File::copy($file->getPathname(), $dest);
                    }
                }
                $this->info("File lampiran berhasil disinkronisasi ke {$targetLampiran}");
            }

            $sourceFoto = 'C:\\Users\\andika\\epjlp-main\\upload\\foto';
            $targetFoto = storage_path('app/public/foto');

            if (File::isDirectory($sourceFoto)) {
                if (!File::isDirectory($targetFoto)) {
                    File::makeDirectory($targetFoto, 0755, true);
                }
                $fotoFiles = File::files($sourceFoto);
                $this->line("Menyalin " . count($fotoFiles) . " foto profil...");
                foreach ($fotoFiles as $file) {
                    $dest = $targetFoto . DIRECTORY_SEPARATOR . $file->getFilename();
                    if (!File::exists($dest)) {
                        File::copy($file->getPathname(), $dest);
                    }
                }
                $this->info("Foto profil berhasil disinkronisasi ke {$targetFoto}");
            }
        }

        $this->info("=== Migrasi Data Selesai dengan Sukses! ===");
        $this->line("Password default seluruh akun: 'password'");

        return 0;
    }
}

