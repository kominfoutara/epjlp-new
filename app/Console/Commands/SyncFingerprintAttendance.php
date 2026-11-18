<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use App\Services\Fingerprint\FingerprintSoapService;
use Illuminate\Console\Command;

class SyncFingerprintAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync-machine 
                            {--ip= : Alamat IP spesifik mesin fingerprint untuk override}
                            {--bulan= : Filter bulan tertentu (contoh: 10 atau Oktober)}
                            {--tahun= : Filter tahun tertentu (contoh: 2026)}
                            {--test : Uji koneksi saja tanpa menyimpan log absensi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tarik log presensi pegawai dari mesin fingerprint (SOAP) dan sinkronkan ke database berdasarkan bulan dan tahun';

    public function handle(AttendanceService $attendanceService, FingerprintSoapService $soapService): int
    {
        $ip = $this->option('ip') ?: $soapService->getIp();
        $port = $soapService->getPort();
        $key = $soapService->getKey();
        $bulan = $this->option('bulan');
        $tahun = $this->option('tahun');

        $this->info("Konfigurasi Mesin Fingerprint:");
        $this->line(" - IP Target : <comment>{$ip}</comment>");
        $this->line(" - Port      : <comment>{$port}</comment>");
        $this->line(" - Comm Key  : <comment>{$key}</comment>");
        if ($bulan || $tahun) {
            $this->line(" - Filter    : <comment>Bulan: " . ($bulan ?: 'Semua') . ", Tahun: " . ($tahun ?: 'Semua') . "</comment>");
        }

        if ($this->option('test')) {
            $this->comment("Menguji koneksi ke mesin...");
            $testResult = $soapService->testConnection($ip);
            if ($testResult['success']) {
                $this->info("[OK] " . $testResult['message']);
                return Command::SUCCESS;
            }

            $this->error("[GAGAL] " . $testResult['message']);
            return Command::FAILURE;
        }

        $this->comment("Menarik dan menyinkronkan data presensi dari mesin...");
        $result = $attendanceService->syncFromMachine($ip, $bulan, $tahun);

        if ($result['success']) {
            $this->info("[BERHASIL] " . $result['message']);
            $this->line("Jumlah rekaman presensi diproses: " . ($result['count'] ?? 0));
            return Command::SUCCESS;
        }

        $this->error("[GAGAL] " . $result['message']);
        return Command::FAILURE;
    }
}

