<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\MonthlyActivityReport;
use App\Models\Seksi;
use App\Models\User;
use App\Services\Activity\ActivityService;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function __construct(
        protected ActivityService $activityService
    ) {}

    /**
     * Tampilan lembar aktivitas harian mandiri untuk user saat ini (Kasi atau PJLP).
     */
    public function myActivities(Request $request): Response|RedirectResponse
    {
        return $this->renderActivitySheet($request, $request->user(), true);
    }

    /**
     * Tampilkan daftar aktivitas harian atau rekap validasi.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $targetUserId = $request->input('user_id');

        // Jika user adalah Superadmin: tampilkan rekap seluruh aktivitas yang dikirim oleh users (monitoring read-only)
        if ($user->isSuperadmin()) {
            return $this->superadminRekapAktivitas($request);
        }

        // Jika user adalah Admin/Kasi tanpa targetUserId, tampilkan list pegawai untuk validasi
        if (!$user->isPjlp() && !$targetUserId) {
            return $this->indexPjlpListForAdmin($request, $user);
        }

        // Tampilan aktivitas untuk user tertentu (PJLP sendiri atau user yang dipilih oleh Admin/Kasudin/Kasi)
        $targetUser = $user->isPjlp() ? $user : User::findOrFail($targetUserId);

        // Kasi tidak boleh melihat/memvalidasi aktivitas seksi lain
        if ($user->isKasi() && $targetUser->id !== $user->id) {
            if ($user->seksi && !User::isSameSeksi($user->seksi, $targetUser->seksi)) {
                abort(403, 'Akses ditolak: Anda hanya dapat mengakses aktivitas di seksi Anda.');
            }
        }

        return $this->renderActivitySheet($request, $targetUser, $user->id === $targetUser->id);
    }

    /**
     * Tampilan rekap seluruh aktivitas yang dikirimkan oleh users khusus untuk Superadmin (Monitoring Read-only).
     */
    protected function superadminRekapAktivitas(Request $request): Response
    {
        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $seksi = $request->input('seksi');
        $status = $request->input('status');
        $search = $request->input('search');
        $targetUserId = $request->input('user_id');

        // Query dasar aktivitas dengan eager loading relasi user
        $baseQuery = Activity::query()
            ->with(['user:id,nama_lengkap,pin,seksi,posisi_pekerjaan,role,foto']);

        // Filter periode tanggal
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('tanggal', [$startDate, $endDate]);
        } elseif ($month) {
            $baseQuery->where('tanggal', 'like', "{$month}%");
        }

        // Filter seksi jika dipilih
        if ($seksi && $seksi !== 'all') {
            $baseQuery->whereHas('user', function ($q) use ($seksi) {
                $q->where('seksi', $seksi);
            });
        }

        // Filter user jika dipilih
        if ($targetUserId && $targetUserId !== 'all') {
            $baseQuery->where('user_id', $targetUserId);
        }

        // Hitung ringkasan statistik sebelum filter status dan pencarian kata kunci
        $allStatsActivities = (clone $baseQuery)->get();
        $stats = [
            'total' => $allStatsActivities->count(),
            'disetujui' => $allStatsActivities->where('status_validasi', 'Disetujui')->count(),
            'menunggu' => $allStatsActivities->where('status_validasi', 'Menunggu')->count(),
            'ditolak' => $allStatsActivities->where('status_validasi', 'Ditolak')->count(),
            'total_pegawai' => $allStatsActivities->pluck('user_id')->unique()->count(),
        ];

        // Terapkan filter status validasi
        if ($status && in_array($status, ['Menunggu', 'Disetujui', 'Ditolak'])) {
            $baseQuery->where('status_validasi', $status);
        }

        // Terapkan filter pencarian (uraian, kategori, lokasi, nama unit, catatan, nama pegawai, pin)
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('uraian', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%")
                  ->orWhere('lokasi', 'like', "%{$search}%")
                  ->orWhere('nama_unit', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('pin', 'like', "%{$search}%");
                  });
            });
        }

        $activities = $baseQuery
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Ambil daftar seksi aktif
        $seksiList = Seksi::active()->orderBy('nama')->pluck('nama')->values();
        if ($seksiList->isEmpty()) {
            $seksiList = User::whereNotNull('seksi')->where('seksi', '!=', '')->distinct()->orderBy('seksi')->pluck('seksi')->values();
        }

        // Ambil daftar pegawai aktif (PJLP dan Kasi) untuk opsi dropdown filter
        $usersList = User::whereIn('role', ['PJLP', 'Kasi'])
            ->active()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'pin', 'seksi', 'posisi_pekerjaan', 'role']);

        return Inertia::render('Activity/RekapAktivitas', [
            'activities' => $activities,
            'stats' => $stats,
            'seksiList' => $seksiList,
            'usersList' => $usersList,
            'filters' => [
                'bulan' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'seksi' => $seksi,
                'status' => $status,
                'search' => $search,
                'user_id' => $targetUserId,
            ],
        ]);
    }

    /**
     * Query data aktivitas untuk export Excel & PDF dengan filter yang sama dengan tampilan rekap.
     */
    protected function getFilteredActivitiesForExport(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['Superadmin', 'Kasudin', 'Admin', 'Kasi'])) {
            abort(403, 'Akses ditolak: Hanya Administrator, Kasudin, atau Kasi yang dapat mengunduh rekap aktivitas.');
        }

        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $seksi = $request->input('seksi');
        $status = $request->input('status');
        $search = $request->input('search');
        $targetUserId = $request->input('user_id');

        $baseQuery = Activity::query()
            ->with(['user:id,nama_lengkap,pin,seksi,posisi_pekerjaan,role']);

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('tanggal', [$startDate, $endDate]);
        } elseif ($month) {
            $baseQuery->where('tanggal', 'like', "{$month}%");
        }

        // Jika Kasi: batasi otomatis hanya untuk pegawai di seksinya
        if ($user->isKasi()) {
            if ($user->seksi) {
                $baseQuery->whereHas('user', function ($q) use ($user) {
                    $q->seksi($user->seksi);
                });
            }
        } elseif ($seksi && $seksi !== 'all') {
            $baseQuery->whereHas('user', function ($q) use ($seksi) {
                $q->seksi($seksi);
            });
        }

        if ($targetUserId && $targetUserId !== 'all') {
            $baseQuery->where('user_id', $targetUserId);
        }

        if ($status && in_array($status, ['Menunggu', 'Disetujui', 'Ditolak'])) {
            $baseQuery->where('status_validasi', $status);
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('uraian', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%")
                  ->orWhere('lokasi', 'like', "%{$search}%")
                  ->orWhere('nama_unit', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('pin', 'like', "%{$search}%");
                  });
            });
        }

        return $baseQuery
            ->orderBy('tanggal', 'asc')
            ->orderBy('jam_mulai', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Export rekap aktivitas ke format Excel (.xlsx) dengan link aktif ke foto dan dokumen PDF.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $activities = $this->getFilteredActivitiesForExport($request);
        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $seksi = $request->input('seksi');
        $status = $request->input('status');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Aktivitas');

        // Judul Laporan
        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'REKAPITULASI AKTIVITAS HARIAN PEGAWAI');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Utara');
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $user = $request->user();
        $periodLabel = ($startDate && $endDate)
            ? "{$this->formatIndoDate($startDate)} s/d {$this->formatIndoDate($endDate)}"
            : $this->formatIndoPeriod($month);

        $seksiLabel = ($user && $user->isKasi()) ? ($user->seksi ?: 'Seksi') : (($seksi && $seksi !== 'all') ? $seksi : 'Semua Seksi');

        $sheet->setCellValue('A4', 'Periode: ' . $periodLabel);
        $sheet->setCellValue('A5', 'Seksi: ' . $seksiLabel . ' | Status: ' . ($status ?: 'Semua Status') . ' | Total Aktivitas: ' . $activities->count());
        $sheet->getStyle('A4:A5')->getFont()->setBold(true);

        // Header Kolom Tabel
        $headers = [
            'A7' => 'No',
            'B7' => 'Tanggal',
            'C7' => 'Jam Kerja',
            'D7' => 'Nama Pegawai',
            'E7' => 'PIN',
            'F7' => 'Seksi / Satker',
            'G7' => 'Posisi / Jabatan',
            'H7' => 'Kategori',
            'I7' => 'Lokasi / Unit',
            'J7' => 'Uraian Aktivitas & Capaian (Lampiran)',
            'K7' => 'Status Validasi',
            'L7' => 'Catatan Verifikator',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($cell)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        $rowNum = 8;
        $baseUrl = $request->getSchemeAndHttpHost() . '/aktivitas/lampiran/view/';

        foreach ($activities as $idx => $act) {
            $pjlp = $act->user;
            $lampiranStr = $act->lampiran;
            $files = !empty($lampiranStr) ? array_filter(array_map('trim', explode(',', $lampiranStr))) : [];

            $photoList = [];
            $pdfItem = null;

            foreach ($files as $file) {
                $clean = explode('?', $file)[0];
                $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
                $fileUrl = $baseUrl . $file;
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $photoList[] = [
                        'name' => $file,
                        'url' => $fileUrl,
                    ];
                } elseif ($ext === 'pdf') {
                    $pdfItem = [
                        'name' => $file,
                        'url' => $fileUrl,
                    ];
                }
            }

            $jamKerja = ($act->jam_mulai && $act->jam_selesai)
                ? substr($act->jam_mulai, 0, 5) . ' - ' . substr($act->jam_selesai, 0, 5)
                : '-';

            $lokasiDisplay = $act->lokasi ?: '-';
            if ($act->nama_unit) {
                $lokasiDisplay .= ' (' . $act->nama_unit . ')';
            }

            $sheet->setCellValue('A' . $rowNum, $idx + 1);
            $sheet->setCellValue('B' . $rowNum, $act->tanggal ? Carbon::parse($act->tanggal)->format('d/m/Y') : '-');
            $sheet->setCellValue('C' . $rowNum, $jamKerja);
            $sheet->setCellValue('D' . $rowNum, $pjlp?->nama_lengkap ?: '-');
            $sheet->setCellValueExplicit('E' . $rowNum, (string) ($pjlp?->pin ?: '-'), DataType::TYPE_STRING);
            $sheet->setCellValue('F' . $rowNum, $pjlp?->seksi ?: '-');
            $sheet->setCellValue('G' . $rowNum, $pjlp?->posisi_pekerjaan ?: ($pjlp?->role ?: '-'));
            $sheet->setCellValue('H' . $rowNum, $act->kategori ?: '-');
            $sheet->setCellValue('I' . $rowNum, $lokasiDisplay);

            // Susun tautan lampiran foto kemudian PDF
            $allLinks = [];
            foreach ($photoList as $p) {
                $allLinks[] = $p['url'];
            }
            if ($pdfItem) {
                $allLinks[] = $pdfItem['url'];
            }

            // Format penomoran per baris (2 tautan per baris):
            // (1). link image (2). link image
            // (3). link image (4). link image
            // (5). pdf
            $linkLines = [];
            $totalLinks = count($allLinks);
            for ($i = 0; $i < $totalLinks; $i += 2) {
                $chunk = [];
                $num1 = $i + 1;
                $chunk[] = "({$num1}). " . $allLinks[$i];
                if ($i + 1 < $totalLinks) {
                    $num2 = $i + 2;
                    $chunk[] = "({$num2}). " . $allLinks[$i + 1];
                }
                $linkLines[] = implode("  ", $chunk);
            }

            $uraianContent = $act->uraian ?: '-';
            if (!empty($linkLines)) {
                $uraianContent .= "\n\n" . implode("\n", $linkLines);
            }

            $sheet->setCellValue('J' . $rowNum, $uraianContent);
            $sheet->setCellValue('K' . $rowNum, $act->status_validasi ?: 'Menunggu');
            $sheet->setCellValue('L' . $rowNum, $act->catatan ?: '-');

            // Alignment per baris
            $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('K' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('J' . $rowNum)->getAlignment()->setWrapText(true);
            $sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

            $rowNum++;
        }

        if ($rowNum > 8) {
            $sheet->getStyle('A7:L' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'K', 'L'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('J')->setWidth(55);

        $fileName = 'Rekap_Aktivitas_' . ($startDate && $endDate ? "{$startDate}_{$endDate}" : $month) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export rekap aktivitas ke format PDF (.pdf) dengan link aktif ke foto dan dokumen PDF.
     */
    public function exportPdf(Request $request)
    {
        $activities = $this->getFilteredActivitiesForExport($request);
        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $seksi = $request->input('seksi');
        $status = $request->input('status');

        $baseUrl = $request->getSchemeAndHttpHost() . '/storage/lampiran_aktivitas/';

        $records = $activities->map(function ($act) use ($baseUrl) {
            $lampiranStr = $act->lampiran;
            $files = !empty($lampiranStr) ? array_filter(array_map('trim', explode(',', $lampiranStr))) : [];

            $photos = [];
            $pdf = null;

            foreach ($files as $file) {
                $clean = explode('?', $file)[0];
                $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
                $fileUrl = $baseUrl . $file;
                $viewerUrl = route('activity.lampiran.view', ['filename' => $clean]);
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $photos[] = [
                        'name' => $file,
                        'url' => $fileUrl,
                        'viewer_url' => $viewerUrl,
                    ];
                } elseif ($ext === 'pdf') {
                    $pdf = [
                        'name' => $file,
                        'url' => $fileUrl,
                        'viewer_url' => $viewerUrl,
                    ];
                }
            }

            $jamKerja = ($act->jam_mulai && $act->jam_selesai)
                ? substr($act->jam_mulai, 0, 5) . ' - ' . substr($act->jam_selesai, 0, 5)
                : '-';

            return [
                'id' => $act->id,
                'tanggal' => $act->tanggal ? Carbon::parse($act->tanggal)->format('d/m/Y') : '-',
                'jam_kerja' => $jamKerja,
                'nama_lengkap' => $act->user?->nama_lengkap ?: '-',
                'pin' => $act->user?->pin ?: '-',
                'seksi' => $act->user?->seksi ?: '-',
                'posisi' => $act->user?->posisi_pekerjaan ?: ($act->user?->role ?: '-'),
                'kategori' => $act->kategori ?: '-',
                'lokasi' => $act->lokasi ?: '-',
                'nama_unit' => $act->nama_unit ?: null,
                'uraian' => $act->uraian ?: '-',
                'status_validasi' => $act->status_validasi ?: 'Menunggu',
                'catatan' => $act->catatan ?: null,
                'photos' => $photos,
                'pdf' => $pdf,
            ];
        });

        $user = $request->user();
        $periodLabel = ($startDate && $endDate)
            ? "{$this->formatIndoDate($startDate)} s/d {$this->formatIndoDate($endDate)}"
            : $this->formatIndoPeriod($month);

        $seksiLabel = ($user && $user->isKasi()) ? ($user->seksi ?: 'Seksi') : (($seksi && $seksi !== 'all') ? $seksi : 'Seluruh Seksi');

        $pdf = Pdf::loadView('pdf.activity_recap', [
            'records' => $records,
            'periode' => $periodLabel,
            'seksiLabel' => $seksiLabel,
            'statusLabel' => $status ?: 'Seluruh Status',
            'logoBase64' => $this->getLogoBase64(),
        ])->setPaper('a4', 'landscape');

        $output = $pdf->output();

        // Injeksi atribut /NewWindow true pada setiap URI Action di PDF agar aplikasi PDF pembaca membukanya di jendela/tab baru
        $modified = preg_replace(
            '/(<<\s*\/Type\s*\/Action\s*\/S\s*\/URI\s*\/URI\s*\([^)]+\))(\s*>>)/s',
            "$1\n/NewWindow true$2",
            $output
        );

        $finalPdf = $this->fixPdfXref($modified ?: $output);

        $fileName = 'Rekap_Aktivitas_' . ($startDate && $endDate ? "{$startDate}_{$endDate}" : $month) . '.pdf';

        return response($finalPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Halaman pratinjau lampiran interaktif (foto atau dokumen PDF)
     * yang dibuka dari tautan pada berkas rekap PDF.
     */
    public function viewAttachment(Request $request, string $filename)
    {
        $cleanFilename = basename($filename);
        $cleanFilename = explode('?', $cleanFilename)[0];

        $filePath = storage_path('app/public/lampiran_aktivitas/' . $cleanFilename);

        if (!file_exists($filePath)) {
            abort(404, 'Berkas lampiran tidak ditemukan di penyimpanan server.');
        }

        $ext = strtolower(pathinfo($cleanFilename, PATHINFO_EXTENSION));
        $isPdf = $ext === 'pdf';
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'jfif']);
        $fileUrl = asset('storage/lampiran_aktivitas/' . $cleanFilename);
        $fileSize = file_exists($filePath) ? round(filesize($filePath) / 1024, 1) . ' KB' : '-';

        return view('activity.attachment_viewer', [
            'filename' => $cleanFilename,
            'fileUrl' => $fileUrl,
            'isPdf' => $isPdf,
            'isImage' => $isImage,
            'fileSize' => $fileSize,
            'extension' => strtoupper($ext),
        ]);
    }

    /**
     * Memperbaiki dan menyusun ulang tabel xref PDF setelah injeksi atribut aksi /NewWindow true.
     */
    protected function fixPdfXref(string $pdf): string
    {
        $trailerPos = strrpos($pdf, 'trailer');
        if ($trailerPos === false) return $pdf;

        $trailerSection = substr($pdf, $trailerPos);
        preg_match('/\/Size\s+(\d+)/', $trailerSection, $sizeMatch);
        if (!$sizeMatch) return $pdf;
        $size = (int) $sizeMatch[1];

        $offsets = array_fill(0, $size, 0);
        $pattern = '/(?:\r\n|\r|\n)(\d+)\s+0\s+obj/';
        preg_match_all($pattern, $pdf, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $idx => $matchInfo) {
            $objNum = (int) $matchInfo[0];
            $objOffset = $matchInfo[1];
            if ($objNum < $size) {
                $offsets[$objNum] = $objOffset;
            }
        }

        $newXref = "xref\n0 $size\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $newXref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        preg_match('/trailer\s*(<<.+?>>)\s*startxref\s*\d+\s*%%EOF/s', $trailerSection, $tMatch);
        if (!$tMatch) return $pdf;
        $trailerDict = $tMatch[1];

        $body = substr($pdf, 0, $trailerPos);
        $oldXrefPos = strrpos($body, 'xref');
        if ($oldXrefPos !== false) {
            $cleanBody = substr($body, 0, $oldXrefPos);
        } else {
            $cleanBody = $body;
        }

        $startXref = strlen($cleanBody);
        return $cleanBody . $newXref . "trailer\n" . $trailerDict . "\nstartxref\n" . $startXref . "\n%%EOF\n";
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

    /**
     * Helper render lembar aktivitas pengguna tertentu.
     */
    protected function renderActivitySheet(Request $request, User $targetUser, bool $isOwner): Response|RedirectResponse
    {
        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $startDate = $request->input('start_date', "{$month}-01");
        $endDate = $request->input('end_date', Carbon::parse($startDate)->endOfMonth()->toDateString());
        $status = $request->input('status');

        $activitiesQuery = Activity::where('user_id', $targetUser->id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $allActivities = (clone $activitiesQuery)->get();

        $stats = [
            'total' => $allActivities->count(),
            'disetujui' => $allActivities->where('status_validasi', 'Disetujui')->count(),
            'menunggu' => $allActivities->where('status_validasi', 'Menunggu')->count(),
            'ditolak' => $allActivities->where('status_validasi', 'Ditolak')->count(),
        ];

        if ($status && in_array($status, ['Menunggu', 'Disetujui', 'Ditolak'])) {
            $activitiesQuery->where('status_validasi', $status);
        }

        $activities = $activitiesQuery->paginate(10)->withQueryString();

        // Jika halaman yang diminta melebihi total halaman (misalnya data berkurang atau filter berubah), reset ke halaman 1
        if ($request->has('page') && (int)$request->input('page') > 1 && $activities->isEmpty()) {
            return redirect()->to($request->fullUrlWithQuery(['page' => 1]));
        }

        $monthlyReport = MonthlyActivityReport::where('user_id', $targetUser->id)
            ->where('periode', $month)
            ->first();

        $userSeksi = trim($targetUser->seksi ?? '');
        $cleanSeksi = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $userSeksi));

        $categories = ActivityCategory::where(function ($q) use ($userSeksi, $cleanSeksi) {
            if ($userSeksi !== '') {
                $q->where('seksi', $userSeksi)
                  ->orWhere('seksi', $cleanSeksi)
                  ->orWhere('seksi', 'Seksi ' . $cleanSeksi)
                  ->orWhere('seksi', 'Seksi ' . strtoupper($cleanSeksi))
                  ->orWhere('seksi', 'like', '%' . $cleanSeksi . '%');
            }
        })->orderBy('nama')->pluck('nama')->unique()->values();

        if ($categories->isEmpty()) {
            $categories = ActivityCategory::orderBy('nama')->pluck('nama')->unique()->values();
        }

        return Inertia::render('Activity/Index', [
            'isOwner' => $isOwner,
            'targetUser' => $targetUser,
            'activities' => $activities,
            'monthlyReport' => $monthlyReport,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => [
                'bulan' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Endpoint API JSON untuk mengambil daftar kategori pelayanan aktivitas (fallback/asinkron).
     */
    public function categoryList(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $targetUserId = $request->input('user_id');
        $targetUser = ($user && !$user->isPjlp() && $targetUserId) ? User::find($targetUserId) : $user;

        $userSeksi = trim($targetUser->seksi ?? '');
        $cleanSeksi = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $userSeksi));

        $categories = ActivityCategory::where(function ($q) use ($userSeksi, $cleanSeksi) {
            if ($userSeksi !== '') {
                $q->where('seksi', $userSeksi)
                  ->orWhere('seksi', $cleanSeksi)
                  ->orWhere('seksi', 'Seksi ' . $cleanSeksi)
                  ->orWhere('seksi', 'Seksi ' . strtoupper($cleanSeksi))
                  ->orWhere('seksi', 'like', '%' . $cleanSeksi . '%');
            }
        })->orderBy('nama')->pluck('nama')->unique()->values();

        if ($categories->isEmpty()) {
            $categories = ActivityCategory::orderBy('nama')->pluck('nama')->unique()->values();
        }

        return response()->json($categories);
    }

    /**
     * Tampilan rekap daftar pegawai untuk Admin/Kasudin/Kasi validasi aktivitas.
     */
    protected function indexPjlpListForAdmin(Request $request, User $admin): Response
    {
        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $search = $request->input('search');
        $status = $request->input('status');

        // Jika Kasi: hanya menampilkan pegawai PJLP di seksinya
        // Jika Kasudin / Superadmin: menampilkan PJLP dan juga Kasi agar aktivitas Kasi dapat divalidasi oleh Kasudin
        if ($admin->isKasi()) {
            $query = User::pjlp()->active();
            if ($admin->seksi) {
                $query->seksi($admin->seksi);
            }
        } else {
            $query = User::whereIn('role', ['PJLP', 'Kasi'])->active();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $pjlpUsers = $query->orderBy('role')->orderBy('nama_lengkap')->get()->map(function ($pjlp) use ($month) {
            $activities = Activity::where('user_id', $pjlp->id)
                ->where('tanggal', 'like', "{$month}%")
                ->orderBy('tanggal', 'desc')
                ->orderBy('jam_mulai', 'desc')
                ->get();

            $totalAktivitas = $activities->count();
            $menungguCount = $activities->where('status_validasi', 'Menunggu')->count();
            $disetujuiCount = $activities->where('status_validasi', 'Disetujui')->count();
            $ditolakCount = $activities->where('status_validasi', 'Ditolak')->count();

            // Status keseluruhan untuk kolom Status
            $statusValidasi = 'Belum Ada Laporan';
            if ($totalAktivitas > 0) {
                if ($menungguCount > 0) {
                    $statusValidasi = 'Menunggu';
                } elseif ($ditolakCount > 0 && $disetujuiCount === 0) {
                    $statusValidasi = 'Ditolak';
                } elseif ($disetujuiCount > 0 && $ditolakCount === 0) {
                    $statusValidasi = 'Disetujui';
                } else {
                    $statusValidasi = 'Sebagian Disetujui';
                }
            }

            return [
                'id' => $pjlp->id,
                'nama_lengkap' => $pjlp->nama_lengkap,
                'pin' => $pjlp->pin,
                'seksi' => $pjlp->seksi,
                'role' => $pjlp->role,
                'posisi' => $pjlp->posisi_pekerjaan ?? ($pjlp->role === 'Kasi' ? 'Kepala Seksi' : 'PJLP'),
                'total_aktivitas' => $totalAktivitas,
                'menunggu' => $menungguCount,
                'disetujui' => $disetujuiCount,
                'ditolak' => $ditolakCount,
                'status_validasi' => $statusValidasi,
                'activities' => $activities->map(function ($act) {
                    return [
                        'id' => $act->id,
                        'no' => $act->no,
                        'tanggal' => $act->tanggal ? Carbon::parse($act->tanggal)->format('Y-m-d') : null,
                        'jam_mulai' => $act->jam_mulai,
                        'jam_selesai' => $act->jam_selesai,
                        'kategori' => $act->kategori,
                        'lokasi' => $act->lokasi,
                        'nama_unit' => $act->nama_unit,
                        'uraian' => $act->uraian,
                        'lampiran' => $act->lampiran,
                        'status_validasi' => $act->status_validasi,
                        'catatan' => $act->catatan,
                        'created_at' => $act->created_at ? $act->created_at->format('Y-m-d H:i:s') : null,
                    ];
                })->values(),
            ];
        });

        if ($status === 'Menunggu') {
            $pjlpUsers = $pjlpUsers->filter(fn($u) => $u['menunggu'] > 0)->values();
        }

        return Inertia::render('Activity/PjlpList', [
            'pjlpUsers' => $pjlpUsers,
            'filters' => [
                'bulan' => $month,
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Simpan aktivitas baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'tanggal' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'kategori' => 'required|string',
            'lokasi' => 'nullable|string|max:255',
            'nama_unit' => 'nullable|string|max:255',
            'uraian' => 'required|string',
        ];

        // Lampiran: 1 file maksimal 10 MB, image minimal 1 dan maksimal 5, format image bebas
        $allowedMimes = 'jpeg,png,jpg,webp,gif,bmp,svg,avif,heic,heif,tiff,jfif,ico,pdf,xlsx,xls,csv';
        if ($request->hasFile('lampiran') && !is_array($request->file('lampiran'))) {
            $rules['lampiran'] = 'required|file|mimes:' . $allowedMimes . '|max:10240';
        } else {
            $rules['lampiran'] = 'required|array|min:1|max:6';
            $rules['lampiran.*'] = 'required|file|mimes:' . $allowedMimes . '|max:10240';
        }

        $messages = [
            'uraian.required' => 'Uraian tugas & hasil capaian wajib diisi.',
            'uraian.string' => 'Uraian tugas & hasil capaian harus berupa teks.',
            'lampiran.required' => 'Lampiran foto dokumentasi kegiatan wajib diisi minimal 1 foto.',
            'lampiran.min' => 'Lampiran foto dokumentasi kegiatan minimal 1 foto.',
            'lampiran.max' => 'Total berkas lampiran maksimal 6 berkas (maksimal 5 foto dan 1 file PDF).',
            'lampiran.*.required' => 'File lampiran wajib diunggah.',
            'lampiran.*.file' => 'File lampiran tidak valid.',
            'lampiran.*.mimes' => 'Format berkas tidak didukung. Harap unggah Foto (format bebas) atau Dokumen PDF.',
            'lampiran.*.max' => 'Ukuran setiap berkas lampiran maksimal 10 MB.',
        ];

        $validated = $request->validate($rules, $messages);

        $files = $request->file('lampiran') ?? [];
        if (!is_array($files)) {
            $files = [$files];
        }

        // Validasi batasan: Minimal 1 foto, Maksimal 5 foto, dan Maksimal 1 PDF. Setiap berkas maks 10 MB.
        $imageCount = 0;
        $pdfCount = 0;
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif', 'heic', 'heif', 'tiff', 'jfif', 'ico'];

        foreach ($files as $file) {
            if ($file->getSize() > 10 * 1024 * 1024) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lampiran' => "Ukuran berkas '{$file->getClientOriginalName()}' melebihi batas maksimal 10 MB.",
                ]);
            }

            $ext = strtolower($file->getClientOriginalExtension());
            $mime = $file->getMimeType() ?? '';

            if (in_array($ext, $imageExtensions) || str_starts_with($mime, 'image/')) {
                $imageCount++;
            } elseif ($ext === 'pdf' || $mime === 'application/pdf') {
                $pdfCount++;
            }
        }

        if ($imageCount < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lampiran' => 'Lampiran foto dokumentasi kegiatan wajib diunggah minimal 1 foto (maksimal 5 foto).',
            ]);
        }

        if ($imageCount > 5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lampiran' => 'Maksimal 5 foto lampiran yang dapat diunggah.',
            ]);
        }

        if ($pdfCount > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lampiran' => 'Maksimal 1 file PDF yang dapat diunggah.',
            ]);
        }

        $user = $request->user();
        $activity = $this->activityService->createActivity($user, $validated, $files);

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'TAMBAH_AKTIVITAS',
            'module' => 'Aktivitas',
            'description' => "Menambahkan aktivitas: {$activity->uraian} ({$activity->tanggal})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['activity_id' => $activity->id, 'kategori' => $activity->kategori],
        ]);

        // Jika Kasi yang mengajukan, notifikasi ditujukan ke Kasudin
        $targetRole = $user->isKasi() ? 'Kasudin' : 'Kasi';
        $senderTitle = $user->isKasi()
            ? "{$user->nama_lengkap} (Kepala Seksi {$user->seksi})"
            : "{$user->nama_lengkap} ({$user->seksi})";

        AppNotification::send([
            'title' => 'Pengajuan Aktivitas Baru',
            'message' => "{$senderTitle} menambahkan aktivitas: \"{$activity->uraian}\"",
            'type' => 'info',
            'module' => 'Aktivitas',
            'target_role' => $targetRole,
            'link' => '/aktivitas?user_id=' . $user->id,
        ]);

        $activityMonth = Carbon::parse($activity->tanggal)->format('Y-m');
        $successMsg = 'Aktivitas berhasil ditambahkan ke Log Aktivitas Harian.';

        if ($user->isPjlp()) {
            return redirect()->route('activity.index', ['bulan' => $activityMonth])
                ->with('success', $successMsg);
        } elseif ($user->isKasi()) {
            return redirect()->route('activity.my', ['bulan' => $activityMonth])
                ->with('success', $successMsg);
        } else {
            return redirect()->route('activity.index', ['user_id' => $user->id, 'bulan' => $activityMonth])
                ->with('success', $successMsg);
        }
    }

    /**
     * Perbarui data aktivitas.
     */
    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $user = $request->user();

        // Bukan Superadmin: hanya pemilik aktivitas yang boleh mengedit dan jika belum Disetujui
        if (!$user->isSuperadmin()) {
            if ($activity->user_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda tidak memiliki akses untuk mengubah aktivitas ini.');
            }
            if ($activity->status_validasi === 'Disetujui') {
                return back()->with('error', 'Aktivitas yang sudah disetujui tidak dapat diubah.');
            }
        }

        $hasExistingFiles = !empty($activity->lampiran);
        $deletedFiles = $request->input('deleted_files') ?? [];
        $existingCount = 0;
        if ($hasExistingFiles) {
            $currentFiles = array_filter(array_map('trim', explode(',', $activity->lampiran)));
            $remainingFiles = array_diff($currentFiles, $deletedFiles);
            $existingCount = count($remainingFiles);
        }

        $rules = [
            'tanggal' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'kategori' => 'required|string',
            'lokasi' => 'nullable|string|max:255',
            'nama_unit' => 'nullable|string|max:255',
            'uraian' => 'required|string',
            'deleted_files' => 'nullable|array',
        ];

        // Jika tidak ada berkas lama yang tersisa, wajib unggah lampiran baru (Minimal 1 foto, maks. 5 foto dan 1 PDF)
        $allowedMimes = 'jpeg,png,jpg,webp,gif,bmp,svg,avif,heic,heif,tiff,jfif,ico,pdf,xlsx,xls,csv';
        if ($existingCount === 0) {
            if ($request->hasFile('lampiran') && !is_array($request->file('lampiran'))) {
                $rules['lampiran'] = 'required|file|mimes:' . $allowedMimes . '|max:10240';
            } else {
                $rules['lampiran'] = 'required|array|min:1|max:6';
                $rules['lampiran.*'] = 'required|file|mimes:' . $allowedMimes . '|max:10240';
            }
        } else {
            if ($request->hasFile('lampiran')) {
                if (!is_array($request->file('lampiran'))) {
                    $rules['lampiran'] = 'nullable|file|mimes:' . $allowedMimes . '|max:10240';
                } else {
                    $rules['lampiran'] = 'nullable|array|max:6';
                    $rules['lampiran.*'] = 'nullable|file|mimes:' . $allowedMimes . '|max:10240';
                }
            }
        }

        $messages = [
            'uraian.required' => 'Uraian tugas & hasil capaian wajib diisi.',
            'uraian.string' => 'Uraian tugas & hasil capaian harus berupa teks.',
            'lampiran.required' => 'Lampiran foto dokumentasi kegiatan wajib diisi minimal 1 foto.',
            'lampiran.min' => 'Lampiran foto dokumentasi kegiatan minimal 1 foto.',
            'lampiran.max' => 'Total berkas lampiran maksimal 6 berkas (maksimal 5 foto dan 1 file PDF).',
            'lampiran.*.required' => 'File lampiran wajib diunggah.',
            'lampiran.*.file' => 'File lampiran tidak valid.',
            'lampiran.*.mimes' => 'Format berkas tidak didukung. Harap unggah Foto (format bebas) atau Dokumen PDF.',
            'lampiran.*.max' => 'Ukuran setiap berkas lampiran maksimal 10 MB.',
        ];

        $validated = $request->validate($rules, $messages);

        $files = $request->file('lampiran') ?? [];
        if (!is_array($files)) {
            $files = [$files];
        }

        $deletedFiles = $request->input('deleted_files') ?? [];

        // Hitung akumulasi foto dan PDF (berkas lama yang tidak dihapus + berkas baru yang diunggah)
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif', 'heic', 'heif', 'tiff', 'jfif', 'ico'];
        $imageCount = 0;
        $pdfCount = 0;

        foreach ($remainingFiles as $rf) {
            $ext = strtolower(pathinfo($rf, PATHINFO_EXTENSION));
            if (in_array($ext, $imageExtensions)) {
                $imageCount++;
            } elseif ($ext === 'pdf') {
                $pdfCount++;
            }
        }

        foreach ($files as $file) {
            if ($file->getSize() > 10 * 1024 * 1024) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lampiran' => "Ukuran berkas '{$file->getClientOriginalName()}' melebihi batas maksimal 10 MB.",
                ]);
            }

            $ext = strtolower($file->getClientOriginalExtension());
            $mime = $file->getMimeType() ?? '';

            if (in_array($ext, $imageExtensions) || str_starts_with($mime, 'image/')) {
                $imageCount++;
            } elseif ($ext === 'pdf' || $mime === 'application/pdf') {
                $pdfCount++;
            }
        }

        if ($imageCount < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lampiran' => 'Lampiran foto dokumentasi kegiatan wajib diunggah minimal 1 foto (maksimal 5 foto).',
            ]);
        }

        if ($imageCount > 5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lampiran' => 'Maksimal 5 foto lampiran yang diperbolehkan.',
            ]);
        }

        if ($pdfCount > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lampiran' => 'Maksimal 1 file PDF yang diperbolehkan.',
            ]);
        }

        $this->activityService->updateActivity($activity, $validated, $files, $deletedFiles);

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'UPDATE_AKTIVITAS',
            'module' => 'Aktivitas',
            'description' => "Memperbarui aktivitas: {$activity->uraian} ({$activity->tanggal})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['activity_id' => $activity->id],
        ]);

        // Notifikasi ke atasan bahwa aktivitas telah diperbarui dan siap divalidasi ulang
        $targetRole = $user->isKasi() ? 'Kasudin' : 'Kasi';
        $senderTitle = $user->isKasi()
            ? "{$user->nama_lengkap} (Kepala Seksi {$user->seksi})"
            : "{$user->nama_lengkap} ({$user->seksi})";

        AppNotification::send([
            'title' => 'Aktivitas Telah Diperbarui',
            'message' => "{$senderTitle} memperbarui catatan aktivitas: \"{$activity->uraian}\"",
            'type' => 'info',
            'module' => 'Aktivitas',
            'target_role' => $targetRole,
            'link' => '/aktivitas?user_id=' . $user->id,
        ]);

        $activityMonth = Carbon::parse($activity->tanggal)->format('Y-m');
        $successMsg = 'Aktivitas berhasil diperbarui.';

        if ($user->isPjlp()) {
            return redirect()->route('activity.index', ['bulan' => $activityMonth])
                ->with('success', $successMsg);
        } elseif ($user->isKasi()) {
            return redirect()->route('activity.my', ['bulan' => $activityMonth])
                ->with('success', $successMsg);
        } else {
            return redirect()->route('activity.index', ['user_id' => $user->id, 'bulan' => $activityMonth])
                ->with('success', $successMsg);
        }
    }

    /**
     * Hapus aktivitas.
     */
    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $user = $request->user();

        // Bukan Superadmin: hanya pemilik aktivitas yang boleh menghapus dan jika belum Disetujui
        if (!$user->isSuperadmin()) {
            if ($activity->user_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda tidak memiliki akses untuk menghapus aktivitas ini.');
            }
            if ($activity->status_validasi === 'Disetujui') {
                return back()->with('error', 'Aktivitas yang sudah disetujui tidak dapat dihapus.');
            }
        }

        $uraian = $activity->uraian;
        $id = $activity->id;

        $this->activityService->deleteActivity($activity);

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'HAPUS_AKTIVITAS',
            'module' => 'Aktivitas',
            'description' => "Menghapus aktivitas: {$uraian}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['deleted_activity_id' => $id],
        ]);

        return back()->with('success', 'Aktivitas berhasil dihapus.');
    }

    /**
     * Validasi cepat satuan oleh Kasi atau Admin (mendukung response JSON & Redirect).
     */
    public function quickValidate(Request $request, Activity $activity)
    {
        $user = $request->user();

        // Hanya Superadmin, Kasudin, dan Kasi yang dapat memvalidasi aktivitas
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang memvalidasi aktivitas.');
        }

        // Tidak boleh memvalidasi aktivitas dirinya sendiri
        if ($activity->user_id === $user->id) {
            abort(403, 'Akses ditolak: Anda tidak dapat memvalidasi aktivitas Anda sendiri.');
        }

        // Kasi hanya dapat memvalidasi aktivitas PJLP di seksinya sendiri (tidak boleh memvalidasi Kasi lain / Kasudin)
        if ($user->isKasi()) {
            $activityUser = $activity->user;
            if ($activityUser && ($activityUser->isKasi() || $activityUser->isKasudin() || $activityUser->isSuperadmin())) {
                abort(403, 'Akses ditolak: Kasi hanya dapat memvalidasi aktivitas pegawai PJLP.');
            }

            if ($activityUser && $user->seksi && !User::isSameSeksi($user->seksi, $activityUser->seksi)) {
                abort(403, 'Akses ditolak: Anda hanya dapat memvalidasi aktivitas PJLP di seksi Anda.');
            }
        }

        // Keputusan validasi hanya dapat dilakukan jika status masih 'Menunggu' (hanya satu pilihan dan tidak bisa diubah)
        if (!$user->isSuperadmin() && $activity->status_validasi !== 'Menunggu') {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aktivitas sudah divalidasi (' . $activity->status_validasi . ') dan keputusan tidak dapat diubah.',
                ], 422);
            }
            return back()->with('error', 'Aktivitas sudah divalidasi (' . $activity->status_validasi . ') dan keputusan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'status' => 'required|in:Disetujui,Ditolak',
            'catatan' => 'nullable|string',
        ]);

        $this->activityService->validateActivity($activity, $validated['status'], $validated['catatan'] ?? null);

        $actionName = $validated['status'] === 'Disetujui' ? 'VALIDASI_AKTIVITAS' : 'TOLAK_AKTIVITAS';

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => $actionName,
            'module' => 'Aktivitas',
            'description' => "Memvalidasi aktivitas tanggal {$activity->tanggal} menjadi '{$validated['status']}' untuk pengguna #{$activity->user_id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'activity_id' => $activity->id,
                'target_user_id' => $activity->user_id,
                'status' => $validated['status'],
                'catatan' => $validated['catatan'] ?? null,
            ],
        ]);

        AppNotification::send([
            'title' => 'Aktivitas ' . $validated['status'],
            'message' => "Aktivitas tanggal {$activity->tanggal} telah {$validated['status']} oleh {$user->nama_lengkap}." . ($validated['catatan'] ? " (Catatan: {$validated['catatan']})" : ''),
            'type' => $validated['status'] === 'Disetujui' ? 'success' : 'danger',
            'module' => 'Aktivitas',
            'target_user_id' => $activity->user_id,
            'link' => '/aktivitas/saya',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status aktivitas berhasil diubah menjadi ' . $validated['status'],
                'activity' => $activity->fresh(['user:id,nama_lengkap,seksi,posisi_pekerjaan']),
            ]);
        }

        return back()->with('success', 'Aktivitas berhasil divalidasi (' . $validated['status'] . ').');
    }

    /**
     * Validasi checkbox / massal oleh Kasi atau Admin.
     */
    public function validateCheckbox(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Hanya Superadmin, Kasudin, dan Kasi yang dapat memvalidasi aktivitas
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang memvalidasi aktivitas.');
        }

        $validated = $request->validate([
            'activity_ids' => 'required|array',
            'activity_ids.*' => 'integer|exists:aktivitas,id',
            'status' => 'required|in:Disetujui,Ditolak',
            'catatan' => 'nullable|string',
        ]);

        // Kasi tidak boleh memvalidasi aktivitas dirinya sendiri atau sesama Kasi
        if ($user->isKasi()) {
            $hasForbidden = Activity::whereIn('id', $validated['activity_ids'])
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereHas('user', function ($uq) {
                          $uq->whereIn('role', ['Kasi', 'Kasudin', 'Superadmin']);
                      });
                })->exists();

            if ($hasForbidden) {
                abort(403, 'Akses ditolak: Anda tidak dapat memvalidasi aktivitas Anda sendiri atau sesama Kasi.');
            }

            // Kasi hanya dapat memvalidasi PJLP di seksinya sendiri
            if ($user->seksi) {
                $rawSeksi = $user->seksi;
                $cleanSeksi = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $rawSeksi));
                $normSeksi = User::normalizeSeksi($rawSeksi);

                $unauthorizedCount = Activity::whereIn('id', $validated['activity_ids'])
                    ->whereHas('user', function ($q) use ($rawSeksi, $cleanSeksi, $normSeksi) {
                        $q->where('seksi', '!=', $rawSeksi)
                          ->where('seksi', '!=', $cleanSeksi)
                          ->where('seksi', '!=', 'Seksi ' . $cleanSeksi)
                          ->where('seksi', '!=', 'Seksi ' . strtoupper($cleanSeksi))
                          ->where('seksi', '!=', $normSeksi)
                          ->where('seksi', 'not like', '%' . $cleanSeksi . '%');
                    })->count();

                if ($unauthorizedCount > 0) {
                    abort(403, 'Akses ditolak: Terdapat aktivitas dari seksi lain yang tidak dapat Anda validasi.');
                }
            }

            // Hanya aktivitas yang masih berstatus 'Menunggu' yang dapat diproses validasi
            $validated['activity_ids'] = Activity::whereIn('id', $validated['activity_ids'])
                ->where('status_validasi', 'Menunggu')
                ->pluck('id')
                ->toArray();

            if (empty($validated['activity_ids'])) {
                return back()->with('error', 'Tidak ada aktivitas berstatus Menunggu yang dapat diproses.');
            }
        }

        $this->activityService->validateBulk(
            $validated['activity_ids'],
            $validated['status'],
            $validated['catatan'] ?? null
        );

        $user = $request->user();
        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'VALIDASI_AKTIVITAS',
            'module' => 'Aktivitas',
            'description' => "Validasi massal " . count($validated['activity_ids']) . " aktivitas menjadi '{$validated['status']}'",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'count' => count($validated['activity_ids']),
                'status' => $validated['status'],
            ],
        ]);

        return back()->with('success', count($validated['activity_ids']) . ' aktivitas berhasil divalidasi.');
    }

    /**
     * Simpan analisa kesimpulan & rekomendasi bulanan PJLP.
     */
    public function saveMonthlyReport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periode' => 'required|string',
            'analisa_kesimpulan' => 'nullable|string',
            'rekomendasi' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $targetUser = $request->user()->isPjlp()
            ? $request->user()
            : User::findOrFail($validated['user_id'] ?? $request->user()->id);

        $this->activityService->saveMonthlyReport(
            $targetUser,
            $validated['periode'],
            $validated['analisa_kesimpulan'] ?? null,
            $validated['rekomendasi'] ?? null
        );

        return back()->with('success', 'Catatan laporan bulanan berhasil disimpan.');
    }
}
