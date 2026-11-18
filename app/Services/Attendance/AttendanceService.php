<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Fingerprint\FingerprintSoapService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    public function __construct(
        protected FingerprintSoapService $soapService,
        protected AttendanceCalculationService $calculator
    ) {}

    /**
     * Sinkronisasi data log mesin ke tabel absensi lokal dengan filter bulan dan tahun opsional.
     */
    public function syncFromMachine(?string $ip = null, ?string $bulan = null, ?string $tahun = null): array
    {
        try {
            $rawLogs = $this->soapService->fetchAttendanceLogs($ip);
        } catch (\Exception $e) {
            Log::error('Sinkronisasi absensi error saat mengambil data dari mesin: ' . $e->getMessage());

            return [
                'success' => false,
                'count' => 0,
                'message' => 'Gagal terhubung ke mesin fingerprint: ' . $e->getMessage(),
            ];
        }

        if (empty($rawLogs)) {
            return [
                'success' => true,
                'count' => 0,
                'message' => 'Tidak ada data log baru dari mesin fingerprint.',
            ];
        }

        // Normalisasi filter Bulan & Tahun (Mendukung nama bulan dan angka)
        $monthMap = [
            'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
            'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
            'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'may' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08',
            'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12',
        ];

        $filterMonth = null;
        if (!empty($bulan) && $bulan !== 'all' && $bulan !== 'semua') {
            $lowerBulan = strtolower(trim((string) $bulan));
            if (isset($monthMap[$lowerBulan])) {
                $filterMonth = $monthMap[$lowerBulan];
            } elseif (is_numeric($bulan)) {
                $intMonth = (int) $bulan;
                if ($intMonth >= 1 && $intMonth <= 12) {
                    $filterMonth = str_pad((string) $intMonth, 2, '0', STR_PAD_LEFT);
                }
            }
        }

        $filterYear = null;
        if (!empty($tahun) && $tahun !== 'all' && $tahun !== 'semua') {
            if (is_numeric($tahun)) {
                $filterYear = (string) (int) $tahun;
            }
        }

        // Ambil mapping PIN user PJLP
        $validPjlpUsers = User::pjlp()->whereNotNull('pin')->get()->keyBy(function ($u) {
            return trim((string) $u->pin);
        });

        // Grouping berdasarkan PIN dan Tanggal dengan klasifikasi jam masuk & pulang
        $groupedLogs = [];
        foreach ($rawLogs as $log) {
            $pin = trim((string) $log['pin']);
            if (! isset($validPjlpUsers[$pin])) {
                continue; // Lewati PIN yang bukan PJLP terdaftar
            }

            $timestamp = strtotime($log['datetime']);
            if ($timestamp === false) {
                continue;
            }

            $logYear = date('Y', $timestamp);
            $logMonth = date('m', $timestamp);

            // Filter berdasarkan Tahun jika ditentukan
            if ($filterYear !== null && $logYear !== $filterYear) {
                continue;
            }

            // Filter berdasarkan Bulan jika ditentukan
            if ($filterMonth !== null && $logMonth !== $filterMonth) {
                continue;
            }

            $date = date('Y-m-d', $timestamp);
            $time = date('H:i:s', $timestamp);
            $key = $pin . '_' . $date;

            if (! isset($groupedLogs[$key])) {
                $groupedLogs[$key] = [
                    'pin' => $pin,
                    'tanggal' => $date,
                    'masuk' => null,
                    'pulang' => null,
                ];
            }

            // Standar ZKTeco/Solution: klasifikasi jam masuk & pulang (diadopsi dari epjlp-main)
            $rawStatus = strtolower(trim((string) ($log['status'] ?? '')));
            $isExplicitExit = in_array($rawStatus, ['1', '2', '5', 'out', 'keluar'], true);
            $isExplicitEntry = in_array($rawStatus, ['0', 'in', 'masuk'], true);

            if ($isExplicitExit && $time >= '10:00:00') {
                if ($groupedLogs[$key]['pulang'] === null || $time > $groupedLogs[$key]['pulang']) {
                    $groupedLogs[$key]['pulang'] = $time;
                }
            } elseif ($isExplicitEntry && $time < '14:00:00') {
                if ($groupedLogs[$key]['masuk'] === null || $time < $groupedLogs[$key]['masuk']) {
                    $groupedLogs[$key]['masuk'] = $time;
                }
            } elseif ($time < '12:00:00') {
                if ($groupedLogs[$key]['masuk'] === null || $time < $groupedLogs[$key]['masuk']) {
                    $groupedLogs[$key]['masuk'] = $time;
                }
            } else {
                if ($groupedLogs[$key]['pulang'] === null || $time > $groupedLogs[$key]['pulang']) {
                    $groupedLogs[$key]['pulang'] = $time;
                }
            }
        }

        $monthNames = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        $periodLabel = 'seluruh periode';
        if ($filterMonth !== null && $filterYear !== null) {
            $namaBulan = $monthNames[$filterMonth] ?? $filterMonth;
            $periodLabel = "periode {$namaBulan} {$filterYear}";
        } elseif ($filterMonth !== null) {
            $namaBulan = $monthNames[$filterMonth] ?? $filterMonth;
            $periodLabel = "periode bulan {$namaBulan}";
        } elseif ($filterYear !== null) {
            $periodLabel = "periode tahun {$filterYear}";
        }

        if (empty($groupedLogs)) {
            return [
                'success' => true,
                'count' => 0,
                'message' => "Tidak ada log yang cocok dengan PIN PJLP terdaftar untuk {$periodLabel}. Periksa periode, PIN pegawai, dan ketersediaan log pada mesin.",
            ];
        }

        $processedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($groupedLogs as $record) {
                $pin = $record['pin'];
                $date = $record['tanggal'];
                $firstScan = $record['masuk'];
                $lastScan = $record['pulang'];

                $user = $validPjlpUsers[$pin];
                $scheduledIn = $user->jam_masuk ?: '07:30:00';
                $scheduledOut = $user->jam_pulang ?: '16:00:00';

                $attendance = Attendance::firstOrNew([
                    'pin' => $pin,
                    'tanggal' => $date,
                ]);

                // Tentukan absen masuk & pulang
                if ($firstScan && (! $attendance->absen_masuk || $firstScan < $attendance->absen_masuk)) {
                    $attendance->absen_masuk = $firstScan;
                }

                if ($lastScan && (! $attendance->absen_pulang || $lastScan > $attendance->absen_pulang)) {
                    $attendance->absen_pulang = $lastScan;
                }

                // Kalkulasi keterlambatan & pulang cepat jika hadir
                if ($attendance->absen_masuk) {
                    $attendance->terlambat = $this->calculator->calculateLate($attendance->absen_masuk, $scheduledIn);
                }

                if ($attendance->absen_pulang) {
                    $attendance->pulang_cepat = $this->calculator->calculateEarlyLeave($attendance->absen_pulang, $scheduledOut);
                    $attendance->waktu_kompensasi = $this->calculator->calculateCompensation($attendance->absen_pulang, $scheduledOut);
                }

                if (empty($attendance->keterangan)) {
                    $attendance->keterangan = 'WFO';
                }

                $attendance->save();
                $processedCount++;
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $processedCount,
                'message' => "Berhasil memproses {$processedCount} rekaman absensi untuk {$periodLabel}.",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sinkronisasi absensi error: ' . $e->getMessage());

            return [
                'success' => false,
                'count' => 0,
                'message' => 'Gagal menyimpan data absensi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Hitung rekap bulanan untuk seorang user atau seluruh seksi.
     */
    public function getMonthlyRecap(string $monthYear, ?int $userId = null, ?string $seksi = null): Collection
    {
        $query = User::pjlp()->active()->with(['attendances' => function ($q) use ($monthYear) {
            $q->where('tanggal', 'like', "{$monthYear}%");
        }]);

        if ($userId) {
            $query->where('id', $userId);
        }

        if ($seksi) {
            $query->where('seksi', $seksi);
        }

        return $query->get()->map(function ($user) {
            $attendances = $user->attendances;

            return [
                'user_id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'pin' => $user->pin,
                'seksi' => $user->seksi,
                'posisi' => $user->posisi_pekerjaan,
                'total_hadir' => $attendances->where('keterangan', 'WFO')->count(),
                'total_dl' => $attendances->filter(fn($a) => in_array($a->keterangan, ['DL AWAL', 'DL AKHIR', 'DL PENUH']))->count(),
                'total_sakit' => $attendances->where('keterangan', 'SAKIT')->count(),
                'total_cuti' => $attendances->where('keterangan', 'CUTI')->count(),
                'total_izin' => $attendances->filter(fn($a) => in_array($a->keterangan, ['IZIN AWAL', 'IZIN AKHIR', 'IZIN PENUH']))->count(),
                'total_alpa' => $attendances->where('keterangan', 'ALPA')->count(),
                'records' => $attendances,
            ];
        });
    }

    public function getCalculator(): AttendanceCalculationService
    {
        return $this->calculator;
    }

    /**
     * Dapatkan statistik ringkasan kehadiran realtime untuk tanggal tertentu.
     */
    public function getRealtimeStats(string $date, ?string $seksi = null): array
    {
        $usersQuery = User::pjlp()->active();
        if ($seksi) {
            $usersQuery->where('seksi', $seksi);
        }
        $totalPjlp = $usersQuery->count();
        $pins = $usersQuery->whereNotNull('pin')->pluck('pin');

        $attendances = Attendance::whereIn('pin', $pins)->whereDate('tanggal', $date)->get();

        $hadirMasuk = $attendances->whereNotNull('absen_masuk')->count();
        $hadirPulang = $attendances->whereNotNull('absen_pulang')->count();
        $sedangBekerja = $attendances->filter(fn($a) => !empty($a->absen_masuk) && empty($a->absen_pulang))->count();
        $terlambat = $attendances->filter(fn($a) => !empty($a->terlambat) && $a->terlambat !== '00:00:00')->count();
        $belumHadir = max(0, $totalPjlp - $hadirMasuk);

        return [
            'total_pjlp' => $totalPjlp,
            'hadir_masuk' => $hadirMasuk,
            'hadir_pulang' => $hadirPulang,
            'sedang_bekerja' => $sedangBekerja,
            'terlambat' => $terlambat,
            'belum_hadir' => $belumHadir,
        ];
    }
}

