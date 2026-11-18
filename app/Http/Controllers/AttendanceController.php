<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Fingerprint\FingerprintSoapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected FingerprintSoapService $soapService
    ) {}

    /**
     * Tampilkan data absensi harian dengan pemantauan realtime.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $date = $request->input('tanggal') ?: $today;
        $month = $request->input('bulan') ?: Carbon::now()->format('Y-m');
        $seksiFilter = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $search = $request->input('search');

        if ($user->isPjlp()) {
            // Absensi pribadi PJLP untuk bulan terpilih (10 entri per halaman)
            $attendances = Attendance::where('pin', $user->pin)
                ->where('tanggal', 'like', "{$month}%")
                ->orderBy('tanggal', 'desc')
                ->paginate(10)
                ->withQueryString();

            $attendances->through(function ($att) use ($user) {
                $jamHadir = '-';
                if ($att->absen_masuk && $att->absen_pulang) {
                    $tMasuk = strtotime($att->absen_masuk);
                    $tPulang = strtotime($att->absen_pulang);
                    if ($tPulang >= $tMasuk) {
                        $jamHadir = gmdate('H:i:s', $tPulang - $tMasuk);
                    }
                }

                return [
                    'id' => $att->id,
                    'tanggal' => $att->tanggal,
                    'jam_masuk' => $user->jam_masuk ?: '07:30:00',
                    'jam_pulang' => $user->jam_pulang ?: '16:00:00',
                    'absen_masuk' => $att->absen_masuk,
                    'absen_pulang' => $att->absen_pulang,
                    'terlambat' => $att->terlambat,
                    'pulang_cepat' => $att->pulang_cepat,
                    'waktu_kompensasi' => $att->waktu_kompensasi,
                    'lembur' => $att->waktu_kompensasi,
                    'jam_hadir' => $jamHadir,
                    'keterangan' => $att->keterangan ?? 'WFO',
                ];
            });

            // Presensi realtime hari ini untuk PJLP login
            $todayAttendance = Attendance::where('pin', $user->pin)
                ->whereDate('tanggal', $today)
                ->first();

            return Inertia::render('Attendance/Index', [
                'isPjlp' => true,
                'attendances' => $attendances,
                'todayAttendance' => $todayAttendance,
                'todayDate' => $today,
                'serverTime' => Carbon::now()->format('H:i:s'),
                'filters' => [
                    'bulan' => $month,
                ],
            ]);
        }

        // Tampilan Admin / Kasi / Kasudin
        $usersQuery = User::pjlp()->active()->with(['attendances' => function ($q) use ($date) {
            $q->where('tanggal', $date);
        }]);

        if ($seksiFilter) {
            $usersQuery->where('seksi', $seksiFilter);
        }

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $list = $usersQuery->orderBy('seksi')->orderBy('nama_lengkap')->paginate(10)->withQueryString();
        $list->setPath(route('attendance.index'));

        // Transformasi agar mudah ditampilkan di tabel & status realtime
        $list->through(function ($pjlp) {
            $att = $pjlp->attendances->first();

            $liveStatus = 'Belum Hadir';
            if ($att?->absen_pulang) {
                $liveStatus = 'Selesai Pulang';
            } elseif ($att?->absen_masuk) {
                $liveStatus = 'Sedang Bekerja (WFO)';
            } elseif ($att?->keterangan && !in_array($att->keterangan, ['Belum Ada Data', 'WFO'])) {
                $liveStatus = $att->keterangan;
            }

            $jamHadir = '-';
            if ($att?->absen_masuk && $att?->absen_pulang) {
                $tMasuk = strtotime($att->absen_masuk);
                $tPulang = strtotime($att->absen_pulang);
                if ($tPulang >= $tMasuk) {
                    $jamHadir = gmdate('H:i:s', $tPulang - $tMasuk);
                }
            }

            return [
                'user_id' => $pjlp->id,
                'nama_lengkap' => $pjlp->nama_lengkap,
                'pin' => $pjlp->pin,
                'seksi' => $pjlp->seksi,
                'posisi' => $pjlp->posisi_pekerjaan,
                'jam_masuk' => $pjlp->jam_masuk ?: '07:30:00',
                'jam_pulang' => $pjlp->jam_pulang ?: '16:00:00',
                'attendance_id' => $att?->id,
                'tanggal' => $att?->tanggal,
                'absen_masuk' => $att?->absen_masuk,
                'absen_pulang' => $att?->absen_pulang,
                'terlambat' => $att?->terlambat,
                'pulang_cepat' => $att?->pulang_cepat,
                'waktu_kompensasi' => $att?->waktu_kompensasi,
                'lembur' => $att?->waktu_kompensasi,
                'jam_hadir' => $jamHadir,
                'keterangan' => $att?->keterangan ?? $liveStatus,
                'live_status' => $liveStatus,
            ];
        });

        $seksiList = User::whereNotNull('seksi')->distinct()->pluck('seksi');
        $stats = $this->attendanceService->getRealtimeStats($date, $seksiFilter);

        return Inertia::render('Attendance/Index', [
            'isPjlp' => false,
            'list' => $list,
            'stats' => $stats,
            'todayDate' => $today,
            'serverTime' => Carbon::now()->format('H:i:s'),
            'filters' => [
                'tanggal' => $date,
                'seksi' => $seksiFilter,
                'search' => $search,
            ],
            'seksiList' => $seksiList,
            'defaultFingerprintIp' => config('services.fingerprint.ip'),
        ]);
    }

    /**
     * Endpoint API JSON untuk pembaruan realtime otomatis di latar belakang.
     */
    public function realtimeFeed(Request $request): JsonResponse
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $date = $request->input('tanggal') ?: $today;
        $seksiFilter = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $search = $request->input('search');

        $serverTime = Carbon::now()->format('H:i:s');

        if ($user->isPjlp()) {
            $todayAttendance = Attendance::where('pin', $user->pin)
                ->whereDate('tanggal', $today)
                ->first();

            return response()->json([
                'server_time' => $serverTime,
                'today_date' => $today,
                'is_pjlp' => true,
                'today_attendance' => $todayAttendance,
                'has_checked_in' => !empty($todayAttendance?->absen_masuk),
                'has_checked_out' => !empty($todayAttendance?->absen_pulang),
            ]);
        }

        $usersQuery = User::pjlp()->active()->with(['attendances' => function ($q) use ($date) {
            $q->where('tanggal', $date);
        }]);

        if ($seksiFilter) {
            $usersQuery->where('seksi', $seksiFilter);
        }

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $list = $usersQuery->orderBy('seksi')->orderBy('nama_lengkap')->paginate(10)->withQueryString();
        $list->setPath(route('attendance.index'));

        $list->through(function ($pjlp) {
            $att = $pjlp->attendances->first();

            $liveStatus = 'Belum Hadir';
            if ($att?->absen_pulang) {
                $liveStatus = 'Selesai Pulang';
            } elseif ($att?->absen_masuk) {
                $liveStatus = 'Sedang Bekerja (WFO)';
            } elseif ($att?->keterangan && !in_array($att->keterangan, ['Belum Ada Data', 'WFO'])) {
                $liveStatus = $att->keterangan;
            }

            $jamHadir = '-';
            if ($att?->absen_masuk && $att?->absen_pulang) {
                $tMasuk = strtotime($att->absen_masuk);
                $tPulang = strtotime($att->absen_pulang);
                if ($tPulang >= $tMasuk) {
                    $jamHadir = gmdate('H:i:s', $tPulang - $tMasuk);
                }
            }

            return [
                'user_id' => $pjlp->id,
                'nama_lengkap' => $pjlp->nama_lengkap,
                'pin' => $pjlp->pin,
                'seksi' => $pjlp->seksi,
                'posisi' => $pjlp->posisi_pekerjaan,
                'jam_masuk' => $pjlp->jam_masuk ?: '07:30:00',
                'jam_pulang' => $pjlp->jam_pulang ?: '16:00:00',
                'attendance_id' => $att?->id,
                'tanggal' => $att?->tanggal,
                'absen_masuk' => $att?->absen_masuk,
                'absen_pulang' => $att?->absen_pulang,
                'terlambat' => $att?->terlambat,
                'pulang_cepat' => $att?->pulang_cepat,
                'waktu_kompensasi' => $att?->waktu_kompensasi,
                'lembur' => $att?->waktu_kompensasi,
                'jam_hadir' => $jamHadir,
                'keterangan' => $att?->keterangan ?? $liveStatus,
                'live_status' => $liveStatus,
            ];
        });

        $stats = $this->attendanceService->getRealtimeStats($date, $seksiFilter);

        return response()->json([
            'server_time' => $serverTime,
            'today_date' => $today,
            'is_pjlp' => false,
            'stats' => $stats,
            'list' => $list,
        ]);
    }

    /**
     * Presensi mandiri realtime untuk pegawai PJLP.
     */
    public function presensiMandiri(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isPjlp()) {
            abort(403, 'Akses ditolak: Hanya pegawai PJLP yang dapat melakukan presensi mandiri.');
        }

        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $scheduledIn = $user->jam_masuk ?: '07:30:00';
        $scheduledOut = $user->jam_pulang ?: '16:00:00';

        $attendance = Attendance::where('pin', $user->pin)
            ->whereDate('tanggal', $today)
            ->first();

        if (!$attendance) {
            $attendance = new Attendance([
                'pin' => $user->pin,
                'tanggal' => $today,
            ]);
        }

        if (empty($attendance->absen_masuk)) {
            $attendance->absen_masuk = $currentTime;
            $attendance->terlambat = $this->attendanceService->getCalculator()->calculateLate($currentTime, $scheduledIn);
            if (empty($attendance->keterangan)) {
                $attendance->keterangan = 'WFO';
            }
            $attendance->save();

            ActivityLog::record(
                'PRESENSI_MASUK',
                'Presensi',
                "Pegawai {$user->nama_lengkap} mencatat presensi masuk realtime pukul {$currentTime} WIB.",
                ['pin' => $user->pin, 'waktu' => $currentTime, 'terlambat' => $attendance->terlambat]
            );

            return back()->with('success', "Presensi masuk berhasil dicatat pada pukul {$currentTime} WIB.");
        }

        if (empty($attendance->absen_pulang)) {
            $attendance->absen_pulang = $currentTime;
            $attendance->pulang_cepat = $this->attendanceService->getCalculator()->calculateEarlyLeave($currentTime, $scheduledOut);
            $attendance->waktu_kompensasi = $this->attendanceService->getCalculator()->calculateCompensation($currentTime, $scheduledOut);
            $attendance->save();

            ActivityLog::record(
                'PRESENSI_PULANG',
                'Presensi',
                "Pegawai {$user->nama_lengkap} mencatat presensi pulang realtime pukul {$currentTime} WIB.",
                ['pin' => $user->pin, 'waktu' => $currentTime, 'waktu_kompensasi' => $attendance->waktu_kompensasi]
            );

            return back()->with('success', "Presensi pulang berhasil dicatat pada pukul {$currentTime} WIB.");
        }

        return back()->with('success', 'Presensi hari ini sudah lengkap tercatat (Masuk dan Pulang).');
    }


    /**
     * Tampilkan rekap bulanan absensi.
     */
    public function recap(Request $request): Response
    {
        $user = $request->user();
        $monthYear = $request->input('periode', Carbon::now()->format('Y-m'));
        $seksi = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $userId = $user->isPjlp() ? $user->id : $request->input('user_id');

        $recap = $this->attendanceService->getMonthlyRecap($monthYear, $userId, $seksi);

        $seksiList = User::whereNotNull('seksi')->distinct()->pluck('seksi');

        return Inertia::render('Attendance/Recap', [
            'recap' => $recap,
            'filters' => [
                'periode' => $monthYear,
                'seksi' => $seksi,
            ],
            'seksiList' => $seksiList,
            'isPjlp' => $user->isPjlp(),
            'defaultFingerprintIp' => $this->soapService->getIp(),
        ]);
    }

    /**
     * Trigger tarik log absensi dari mesin fingerprint.
     */
    public function sync(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperadmin() && !$user->isKasi()) {
            abort(403, 'Akses ditolak: Hanya Superadmin dan Kasi yang dapat melakukan sinkronisasi mesin absensi.');
        }

        $validated = $request->validate([
            'ip' => ['nullable', 'string'],
            'bulan' => ['nullable', 'string'],
            'tahun' => ['nullable', 'string'],
            'periode' => ['nullable', 'string'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $ip = !empty($validated['ip']) ? $validated['ip'] : null;
        $bulan = $validated['bulan'] ?? null;
        $tahun = $validated['tahun'] ?? null;

        // Mendukung input gabungan periode YYYY-MM
        if (empty($bulan) && empty($tahun) && !empty($validated['periode'])) {
            $parts = explode('-', $validated['periode']);
            if (count($parts) === 2) {
                $tahun = $parts[0];
                $bulan = $parts[1];
            }
        }

        $result = $this->attendanceService->syncFromMachine($ip, $bulan, $tahun);

        if ($result['success']) {
            // Jika ada filter bulan dan tahun spesifik
            if (!empty($bulan) && !empty($tahun) && $bulan !== 'all' && $tahun !== 'all') {
                $monthMap = [
                    'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
                    'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
                    'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
                ];
                $mLower = strtolower(trim((string) $bulan));
                $monthNum = is_numeric($bulan) ? str_pad((string) (int) $bulan, 2, '0', STR_PAD_LEFT) : ($monthMap[$mLower] ?? '01');
                $targetPeriode = sprintf('%04d-%02d', (int) $tahun, (int) $monthNum);

                // Jika diakses dari halaman Rekap Absensi, arahkan langsung ke rekap periode tersebut
                if (str_contains($request->header('referer', ''), '/absensi/rekap')) {
                    return redirect()->route('attendance.recap', ['periode' => $targetPeriode])
                        ->with('success', $result['message']);
                }

                // Jika diakses dari halaman Absensi Harian, cari tanggal yang memiliki data di bulan tersebut
                if (str_contains($request->header('referer', ''), '/absensi')) {
                    $selectedDate = $validated['tanggal'] ?? null;
                    $foundDate = Attendance::where('tanggal', 'like', "{$targetPeriode}%")->max('tanggal');
                    $targetDate = $selectedDate && str_starts_with($selectedDate, $targetPeriode . '-')
                        ? $selectedDate
                        : ($foundDate ?: sprintf('%04d-%02d-01', (int) $tahun, (int) $monthNum));
                    return redirect()->route('attendance.index', ['tanggal' => $targetDate])
                        ->with('success', $result['message'] . " (Menampilkan data tanggal {$targetDate})");
                }
            }

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Tes koneksi SOAP ke mesin fingerprint.
     */
    public function testConnection(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperadmin() && !$user->isKasi()) {
            abort(403, 'Akses ditolak: Hanya Superadmin dan Kasi yang dapat menguji koneksi mesin absensi.');
        }

        $validated = $request->validate([
            'ip' => ['nullable', 'ip'],
        ]);

        $ip = $validated['ip'] ?? null;
        $result = $this->soapService->testConnection($ip);

        return response()->json($result);
    }

    /**
     * Update manual keterangan absensi oleh Admin/Kasi.
     */
    public function updateKeterangan(Request $request, Attendance $attendance): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperadmin() && !$user->isKasi()) {
            abort(403, 'Akses ditolak: Hanya Superadmin dan Kasi yang dapat mengubah keterangan absensi.');
        }

        if ($user->isKasi() && $attendance->user && $user->seksi && $attendance->user->seksi !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengubah keterangan absensi untuk seksi Anda.');
        }

        $validated = $request->validate([
            'keterangan' => 'required|string|in:WFO,DL AWAL,DL AKHIR,DL PENUH,SAKIT,CUTI,IZIN AWAL,IZIN AKHIR,IZIN PENUH,ALPA',
        ]);

        $attendance->update([
            'keterangan' => $validated['keterangan'],
        ]);

        return back()->with('success', 'Status keterangan absensi berhasil diperbarui.');
    }

    /**
     * Export data presensi harian ke format Excel (.xlsx).
     */
    public function exportDailyExcel(Request $request): StreamedResponse
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $date = $request->input('tanggal', $today);
        $seksiFilter = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $search = $request->input('search');

        $usersQuery = User::pjlp()->active()->with(['attendances' => function ($q) use ($date) {
            $q->where('tanggal', $date);
        }]);

        if ($user->isKasi() && $user->seksi) {
            $usersQuery->where('seksi', $user->seksi);
        } elseif ($seksiFilter) {
            $usersQuery->where('seksi', $seksiFilter);
        }

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $list = $usersQuery->orderBy('seksi')->orderBy('nama_lengkap')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Presensi ' . substr($date, 0, 10));

        // Judul Laporan
        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'DAFTAR PRESENSI HARIAN PEGAWAI PJLP');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:K2');
        $sheet->setCellValue('A2', 'Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Utara');
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A4', 'Tanggal: ' . $this->formatIndoDate($date));
        $sheet->setCellValue('A5', 'Seksi: ' . ($seksiFilter ?: 'Semua Seksi'));
        $sheet->getStyle('A4:A5')->getFont()->setBold(true);

        // Header Tabel
        $headers = [
            'A7' => 'No',
            'B7' => 'PIN Mesin',
            'C7' => 'Nama Lengkap Pegawai',
            'D7' => 'Seksi / Satker',
            'E7' => 'Posisi Pekerjaan',
            'F7' => 'Absen Masuk',
            'G7' => 'Absen Pulang',
            'H7' => 'Terlambat',
            'I7' => 'Pulang Cepat',
            'J7' => 'Kompensasi',
            'K7' => 'Status Kehadiran',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $rowNum = 8;
        foreach ($list as $idx => $pjlp) {
            $att = $pjlp->attendances->first();
            $liveStatus = 'Belum Hadir';
            if ($att?->absen_pulang) {
                $liveStatus = 'Selesai Pulang';
            } elseif ($att?->absen_masuk) {
                $liveStatus = 'Sedang Bekerja (WFO)';
            } elseif ($att?->keterangan && !in_array($att->keterangan, ['Belum Ada Data', 'WFO'])) {
                $liveStatus = $att->keterangan;
            }

            $sheet->setCellValue('A' . $rowNum, $idx + 1);
            $sheet->setCellValueExplicit('B' . $rowNum, (string) ($pjlp->pin ?: '-'), DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $rowNum, $pjlp->nama_lengkap);
            $sheet->setCellValue('D' . $rowNum, $pjlp->seksi ?: '-');
            $sheet->setCellValue('E' . $rowNum, $pjlp->posisi_pekerjaan ?: 'PJLP');
            $sheet->setCellValue('F' . $rowNum, $att?->absen_masuk ?: '--:--:--');
            $sheet->setCellValue('G' . $rowNum, $att?->absen_pulang ?: '--:--:--');
            $sheet->setCellValue('H' . $rowNum, (!empty($att?->terlambat) && $att->terlambat !== '00:00:00') ? $att->terlambat : '-');
            $sheet->setCellValue('I' . $rowNum, (!empty($att?->pulang_cepat) && $att->pulang_cepat !== '00:00:00') ? $att->pulang_cepat : '-');
            $sheet->setCellValue('J' . $rowNum, (!empty($att?->waktu_kompensasi) && $att->waktu_kompensasi !== '00:00:00') ? $att->waktu_kompensasi : '-');
            $sheet->setCellValue('K' . $rowNum, $att?->keterangan ?: $liveStatus);

            $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $rowNum . ':K' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowNum++;
        }

        if ($rowNum > 8) {
            $sheet->getStyle('A7:K' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'Presensi_Harian_' . $date . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export data presensi harian ke format PDF naskah dinas resmi.
     */
    public function exportDailyPdf(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $date = $request->input('tanggal', $today);
        $seksiFilter = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $search = $request->input('search');

        $usersQuery = User::pjlp()->active()->with(['attendances' => function ($q) use ($date) {
            $q->where('tanggal', $date);
        }]);

        if ($user->isKasi() && $user->seksi) {
            $usersQuery->where('seksi', $user->seksi);
        } elseif ($seksiFilter) {
            $usersQuery->where('seksi', $seksiFilter);
        }

        if ($search) {
            $usersQuery->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->orderBy('seksi')->orderBy('nama_lengkap')->get();

        $records = $users->map(function ($pjlp) {
            $att = $pjlp->attendances->first();
            $liveStatus = 'Belum Hadir';
            if ($att?->absen_pulang) {
                $liveStatus = 'Selesai Pulang';
            } elseif ($att?->absen_masuk) {
                $liveStatus = 'Sedang Bekerja (WFO)';
            } elseif ($att?->keterangan && !in_array($att->keterangan, ['Belum Ada Data', 'WFO'])) {
                $liveStatus = $att->keterangan;
            }

            return [
                'nama_lengkap' => $pjlp->nama_lengkap,
                'pin' => $pjlp->pin,
                'seksi' => $pjlp->seksi,
                'posisi' => $pjlp->posisi_pekerjaan,
                'absen_masuk' => $att?->absen_masuk,
                'absen_pulang' => $att?->absen_pulang,
                'terlambat' => $att?->terlambat,
                'keterangan' => $att?->keterangan,
                'live_status' => $liveStatus,
            ];
        });

        $pdf = Pdf::loadView('pdf.attendance_daily', [
            'records' => $records,
            'tanggal' => $date,
            'tanggalIndo' => $this->formatIndoDate($date),
            'seksiLabel' => $seksiFilter ?: 'Seluruh Seksi',
            'logoBase64' => $this->getLogoBase64(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Presensi_Harian_{$date}.pdf");
    }

    /**
     * Export rekapitulasi bulanan ke format Excel (.xlsx).
     */
    public function exportRecapExcel(Request $request): StreamedResponse
    {
        $user = $request->user();
        $monthYear = $request->input('periode', Carbon::now()->format('Y-m'));
        $seksi = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $userId = $user->isPjlp() ? $user->id : $request->input('user_id');

        $recap = $this->attendanceService->getMonthlyRecap($monthYear, $userId, $seksi);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap ' . $monthYear);

        // Judul Laporan
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'REKAPITULASI PRESENSI BULANAN PEGAWAI PJLP');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Utara');
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A4', 'Periode Bulan: ' . $this->formatIndoPeriod($monthYear));
        $sheet->setCellValue('A5', 'Seksi: ' . ($seksi ?: 'Semua Seksi'));
        $sheet->getStyle('A4:A5')->getFont()->setBold(true);

        // Header
        $headers = [
            'A7' => 'No',
            'B7' => 'PIN Mesin',
            'C7' => 'Nama Lengkap Pegawai',
            'D7' => 'Seksi / Satker',
            'E7' => 'Posisi Pekerjaan',
            'F7' => 'Hadir WFO',
            'G7' => 'Dinas Luar',
            'H7' => 'Cuti',
            'I7' => 'Sakit',
            'J7' => 'Izin',
            'K7' => 'Alpa',
            'L7' => 'Total Hadir',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $rowNum = 8;
        $totWfo = 0; $totDl = 0; $totCuti = 0; $totSakit = 0; $totIzin = 0; $totAlpa = 0; $totGrand = 0;

        foreach ($recap as $idx => $r) {
            $hadirTotal = ($r['total_hadir'] ?? 0) + ($r['total_dl'] ?? 0);
            $totWfo += ($r['total_hadir'] ?? 0);
            $totDl += ($r['total_dl'] ?? 0);
            $totCuti += ($r['total_cuti'] ?? 0);
            $totSakit += ($r['total_sakit'] ?? 0);
            $totIzin += ($r['total_izin'] ?? 0);
            $totAlpa += ($r['total_alpa'] ?? 0);
            $totGrand += $hadirTotal;

            $sheet->setCellValue('A' . $rowNum, $idx + 1);
            $sheet->setCellValueExplicit('B' . $rowNum, (string) ($r['pin'] ?: '-'), DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $rowNum, $r['nama_lengkap']);
            $sheet->setCellValue('D' . $rowNum, $r['seksi'] ?: '-');
            $sheet->setCellValue('E' . $rowNum, $r['posisi'] ?: 'PJLP');
            $sheet->setCellValue('F' . $rowNum, $r['total_hadir'] ?? 0);
            $sheet->setCellValue('G' . $rowNum, $r['total_dl'] ?? 0);
            $sheet->setCellValue('H' . $rowNum, $r['total_cuti'] ?? 0);
            $sheet->setCellValue('I' . $rowNum, $r['total_sakit'] ?? 0);
            $sheet->setCellValue('J' . $rowNum, $r['total_izin'] ?? 0);
            $sheet->setCellValue('K' . $rowNum, $r['total_alpa'] ?? 0);
            $sheet->setCellValue('L' . $rowNum, $hadirTotal);

            $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $rowNum . ':L' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowNum++;
        }

        // Baris Total
        $sheet->mergeCells('A' . $rowNum . ':E' . $rowNum);
        $sheet->setCellValue('A' . $rowNum, 'TOTAL KESELURUHAN');
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');

        $sheet->setCellValue('F' . $rowNum, $totWfo);
        $sheet->setCellValue('G' . $rowNum, $totDl);
        $sheet->setCellValue('H' . $rowNum, $totCuti);
        $sheet->setCellValue('I' . $rowNum, $totSakit);
        $sheet->setCellValue('J' . $rowNum, $totIzin);
        $sheet->setCellValue('K' . $rowNum, $totAlpa);
        $sheet->setCellValue('L' . $rowNum, $totGrand);
        $sheet->getStyle('F' . $rowNum . ':L' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A7:L' . $rowNum)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'Rekap_Presensi_Bulanan_' . $monthYear . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export rekapitulasi bulanan ke format PDF naskah dinas resmi.
     */
    public function exportRecapPdf(Request $request)
    {
        $user = $request->user();
        $monthYear = $request->input('periode', Carbon::now()->format('Y-m'));
        $seksi = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));
        $userId = $user->isPjlp() ? $user->id : $request->input('user_id');

        $recap = $this->attendanceService->getMonthlyRecap($monthYear, $userId, $seksi);

        $pdf = Pdf::loadView('pdf.attendance_recap', [
            'recap' => $recap,
            'periode' => $monthYear,
            'periodeIndo' => $this->formatIndoPeriod($monthYear),
            'seksiLabel' => $seksi ?: 'Seluruh Seksi',
            'logoBase64' => $this->getLogoBase64(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("Rekap_Presensi_Bulanan_{$monthYear}.pdf");
    }

    protected function formatIndoDate(string $date): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $time = strtotime($date);
        if (!$time) return $date;
        $d = date('j', $time);
        $m = (int) date('n', $time);
        $y = date('Y', $time);
        return "{$d} {$months[$m]} {$y}";
    }

    protected function formatIndoPeriod(string $period): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $parts = explode('-', $period);
        if (count($parts) < 2) return $period;
        $y = $parts[0];
        $m = (int) $parts[1];
        return ($months[$m] ?? $parts[1]) . ' ' . $y;
    }

    protected function getLogoBase64(): string
    {
        $path = public_path('images/logo-jakarta-utara.png');
        if (file_exists($path)) {
            $data = file_get_contents($path);
            return 'data:image/png;base64,' . base64_encode($data);
        }
        return '';
    }
}
