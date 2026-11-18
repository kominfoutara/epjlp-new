<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\DinasLuar;
use App\Models\Seksi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $currentMonth = Carbon::now()->format('Y-m');

        if ($user->isSuperadmin()) {
            $validated = $request->validate(['periode' => ['nullable', 'date_format:Y-m']]);
            $currentMonth = $validated['periode'] ?? $currentMonth;
            return $this->dashboardSuperadmin($user, $today, $currentMonth);
        }

        if ($user->isPjlp()) {
            return $this->dashboardPjlp($user, $request, $today, $currentMonth);
        }

        return $this->dashboardAdmin($user, $today, $currentMonth);
    }

    protected function dashboardPjlp(User $user, Request $request, string $today, string $currentMonth): Response
    {
        $request->validate([
            'tahun' => ['nullable', 'integer', 'between:1900,9999'],
            'bulan' => ['nullable', 'in:all,1,2,3,4,5,6,7,8,9,10,11,12'],
        ]);
        $year = (int) $request->input('tahun', Carbon::now()->year);
        // Jika parameter bulan ada (bisa 'all' atau '1'..'12'). Jika belum di-filter, default ke bulan berjalan saat ini.
        $hasMonthParam = $request->has('bulan');
        $month = $hasMonthParam ? $request->input('bulan') : (string) Carbon::now()->month;

        // Absensi hari ini
        $todayAttendance = Attendance::where('pin', $user->pin)
            ->where('tanggal', $today)
            ->first();

        // Rekap absensi sesuai filter periode
        $attendanceQuery = Attendance::where('pin', $user->pin)
            ->whereYear('tanggal', $year);

        if ($month && $month !== 'all') {
            $mFormatted = sprintf('%02d', (int) $month);
            $attendanceQuery->where('tanggal', 'like', "{$year}-{$mFormatted}%");
        }
        $monthlyAttendances = $attendanceQuery->get();

        $attendanceStats = [
            'total_hadir' => $monthlyAttendances->where('keterangan', 'WFO')->count(),
            'total_dl' => $monthlyAttendances->filter(fn($a) => str_starts_with($a->keterangan ?? '', 'DL'))->count(),
            'total_sakit' => $monthlyAttendances->where('keterangan', 'SAKIT')->count(),
            'total_cuti' => $monthlyAttendances->where('keterangan', 'CUTI')->count(),
            'total_izin' => $monthlyAttendances->filter(fn($a) => str_starts_with($a->keterangan ?? '', 'IZIN'))->count(),
            'total_alpa' => $monthlyAttendances->where('keterangan', 'ALPA')->count(),
        ];

        // Base Query Aktivitas Tahunan untuk PJLP ini
        $allYearActivities = Activity::where('user_id', $user->id)
            ->whereYear('tanggal', $year)
            ->get(['id', 'tanggal', 'status_validasi']);

        // Data 12 bulan untuk perbandingan atau distribusi diagram batang
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthFullNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $pjlpMonthlyStats = [];
        for ($m = 1; $m <= 12; $m++) {
            $mFormatted = sprintf('%02d', $m);
            $monthItems = $allYearActivities->filter(function ($act) use ($year, $mFormatted) {
                return Carbon::parse($act->tanggal)->format('Y-m') === "{$year}-{$mFormatted}";
            });

            $pjlpMonthlyStats[] = [
                'bulan' => $m,
                'label' => $monthLabels[$m - 1],
                'nama' => $monthFullNames[$m - 1],
                'total' => $monthItems->count(),
                'disetujui' => $monthItems->whereIn('status_validasi', ['Disetujui', 'Diterima'])->count(),
                'menunggu' => $monthItems->where('status_validasi', 'Menunggu')->count(),
                'ditolak' => $monthItems->where('status_validasi', 'Ditolak')->count(),
                'lainnya' => $monthItems->whereNotIn('status_validasi', ['Disetujui', 'Diterima', 'Menunggu', 'Ditolak'])->count(),
            ];
        }

        // Filter aktivitas sesuai pilihan bulan
        if ($month && $month !== 'all') {
            $mFormatted = sprintf('%02d', (int) $month);
            $filteredActivities = $allYearActivities->filter(function ($act) use ($year, $mFormatted) {
                return Carbon::parse($act->tanggal)->format('Y-m') === "{$year}-{$mFormatted}";
            });
            $monthName = $monthFullNames[(int)$month - 1] ?? 'Bulan ' . $month;
            $currentMonthLabel = "{$monthName} {$year}";
        } else {
            $filteredActivities = $allYearActivities;
            $currentMonthLabel = "Tahun {$year} (Semua Bulan)";
        }

        $totalActs = $filteredActivities->count();
        // Legacy imported records use Diterima for approved activities.
        $disetujuiActs = $filteredActivities->whereIn('status_validasi', ['Disetujui', 'Diterima'])->count();
        $menungguActs = $filteredActivities->where('status_validasi', 'Menunggu')->count();
        $ditolakActs = $filteredActivities->where('status_validasi', 'Ditolak')->count();

        $activityStats = [
            'total' => $totalActs,
            'disetujui' => $disetujuiActs,
            'menunggu' => $menungguActs,
            'ditolak' => $ditolakActs,
            'lainnya' => $totalActs - $disetujuiActs - $menungguActs - $ditolakActs,
            'persen_disetujui' => $totalActs > 0 ? round(($disetujuiActs / $totalActs) * 100, 1) : 0,
            'persen_menunggu' => $totalActs > 0 ? round(($menungguActs / $totalActs) * 100, 1) : 0,
            'persen_ditolak' => $totalActs > 0 ? round(($ditolakActs / $totalActs) * 100, 1) : 0,
        ];

        // Aktivitas terbaru
        $recentActivities = Activity::where('user_id', $user->id)
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->limit(5)
            ->get();

        // Riwayat DL terbaru
        $recentDinasLuar = DinasLuar::where('user_id', $user->id)
            ->orderBy('waktu', 'desc')
            ->limit(5)
            ->get();

        // Available years
        $allDates = Activity::where('user_id', $user->id)->pluck('tanggal');
        $dbYears = $allDates->map(function ($d) {
            try {
                return (int) Carbon::parse($d)->format('Y');
            } catch (\Throwable $e) {
                return null;
            }
        })->filter()->unique()->all();

        $currentYr = Carbon::now()->year;
        $availableYears = collect(array_merge([$currentYr - 2, $currentYr - 1, $currentYr, $currentYr + 1], $dbYears))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'role' => 'PJLP',
            'todayAttendance' => $todayAttendance,
            'attendanceStats' => $attendanceStats,
            'activityStats' => $activityStats,
            'pjlpMonthlyStats' => $pjlpMonthlyStats,
            'recentActivities' => $recentActivities,
            'recentDinasLuar' => $recentDinasLuar,
            'currentMonthLabel' => $currentMonthLabel,
            'filters' => [
                'tahun' => (int) $year,
                'bulan' => (string) $month,
            ],
            'availableYears' => $availableYears,
        ]);
    }

    protected function dashboardAdmin(User $user, string $today, string $currentMonth): Response
    {
        request()->validate([
            'tahun' => ['nullable', 'integer', 'between:1900,9999'],
            'bulan' => ['nullable', 'in:all,1,2,3,4,5,6,7,8,9,10,11,12'],
            'status' => ['nullable', 'in:all,Menunggu,Disetujui,Ditolak'],
            'user_id' => ['nullable', 'integer'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $year = (int) (request('tahun') ?: Carbon::now()->year);
        $month = request('bulan'); // 'all', '1'..'12', or null
        $status = request('status'); // 'all', 'Menunggu', 'Disetujui', 'Ditolak', or null
        $selectedUserId = request('user_id');
        $selectedCategory = request('kategori');
        $search = request('search');

        $isKasi = $user->isKasi();
        $pjlpQuery = User::pjlp()->active();

        $pjlpUsers = $pjlpQuery->get();
        if ($isKasi) {
            $seksi = trim((string) $user->seksi);
            $pjlpUsers = $pjlpUsers->filter(fn ($pjlp) => trim((string) User::normalizeSeksi($seksi)) !== ''
                && User::isSameSeksi($seksi, trim((string) $pjlp->seksi)))->values();
        }
        $totalPjlp = $pjlpUsers->count();
        $pjlpPins = $pjlpUsers->pluck('pin')->filter()->values();
        $pjlpUserIds = $pjlpUsers->pluck('id')->values();

        // 1. Absensi Hari Ini untuk Seluruh PJLP dalam Seksi
        $todayAttendances = Attendance::whereIn('pin', $pjlpPins)
            ->whereDate('tanggal', $today)
            ->get()
            ->keyBy('pin');

        $todayList = $pjlpUsers->map(function ($u) use ($todayAttendances) {
            $att = $todayAttendances->get($u->pin);
            $ket = strtoupper(trim((string) ($att?->keterangan ?? '')));
            if ($ket === '' || $ket === 'WFO') {
                $ket = $att && ($att->absen_masuk || $att->absen_pulang) ? 'WFO' : 'BELUM HADIR';
            }
            return [
                'id' => $u->id,
                'nama_lengkap' => $u->nama_lengkap,
                'posisi' => $u->posisi_pekerjaan,
                'pin' => $u->pin,
                'status' => $ket,
                'masuk' => $att ? ($att->absen_masuk ?? '--:--:--') : '--:--:--',
                'pulang' => $att ? ($att->absen_pulang ?? '--:--:--') : '--:--:--',
            ];
        });

        $todayStats = [
            'total_hadir' => $todayList->where('status', 'WFO')->count(),
            'total_dl' => $todayList->whereIn('status', ['DL AWAL', 'DL AKHIR', 'DL PENUH'])->count(),
            'total_sakit' => $todayList->where('status', 'SAKIT')->count(),
            'total_izin' => $todayList->filter(fn($item) => str_starts_with($item['status'], 'IZIN'))->count(),
            'total_cuti' => $todayList->where('status', 'CUTI')->count(),
            'total_belum_hadir' => $todayList->where('status', 'BELUM HADIR')->count(),
            'list' => $todayList->values(),
        ];

        // 2. Base Query Aktivitas Tahunan dalam Seksi
        $baseActivitiesQuery = Activity::whereIn('user_id', $pjlpUserIds)
            ->whereYear('tanggal', $year);

        if ($selectedUserId) {
            $baseActivitiesQuery->where('user_id', $selectedUserId);
        }
        if ($selectedCategory && $selectedCategory !== 'all') {
            $baseActivitiesQuery->where('kategori', $selectedCategory);
        }
        if ($search) {
            $baseActivitiesQuery->where(function ($q) use ($search) {
                $q->where('uraian', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhere('no', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('pin', 'like', "%{$search}%"));
            });
        }

        // Statistik Bulanan (1..12) untuk Chart
        $allYearActivities = (clone $baseActivitiesQuery)->get(['id', 'tanggal', 'status_validasi']);

        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthFullNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $monthlyStats = [];
        for ($m = 1; $m <= 12; $m++) {
            $mFormatted = sprintf('%02d', $m);
            $monthItems = $allYearActivities->filter(function ($act) use ($year, $mFormatted) {
                return Carbon::parse($act->tanggal)->format('Y-m') === "{$year}-{$mFormatted}";
            });

            $monthlyStats[] = [
                'bulan' => $m,
                'label' => $monthLabels[$m - 1],
                'nama' => $monthFullNames[$m - 1],
                'total' => $monthItems->count(),
                'diterima' => $monthItems->whereIn('status_validasi', ['Disetujui', 'Diterima'])->count(),
                'menunggu' => $monthItems->where('status_validasi', 'Menunggu')->count(),
                'ditolak' => $monthItems->where('status_validasi', 'Ditolak')->count(),
                'lainnya' => $monthItems->whereNotIn('status_validasi', ['Disetujui', 'Diterima', 'Menunggu', 'Ditolak'])->count(),
            ];
        }

        // Highlight insight teks
        $highestMonth = collect($monthlyStats)->sortByDesc('total')->first();
        $secondMonth = collect($monthlyStats)->sortByDesc('total')->skip(1)->first();
        $insightText = $highestMonth && $highestMonth['total'] > 0
            ? "Puncak volume pelaporan tertinggi tercatat pada bulan {$highestMonth['nama']} ({$highestMonth['total']})" . ($secondMonth && $secondMonth['total'] > 0 ? " dan {$secondMonth['nama']} ({$secondMonth['total']})." : ".")
            : "Belum ada aktivitas untuk tahun dan filter yang dipilih.";

        // KPI Keseluruhan (Tergantung filter bulan jika ada)
        $kpiPool = (clone $allYearActivities);
        if ($month && $month !== 'all') {
            $mFormatted = sprintf('%02d', (int)$month);
            $kpiPool = $kpiPool->filter(function ($act) use ($year, $mFormatted) {
                return Carbon::parse($act->tanggal)->format('Y-m') === "{$year}-{$mFormatted}";
            });
        }

        $totalKpi = $kpiPool->count();
        $diterimaKpi = $kpiPool->whereIn('status_validasi', ['Disetujui', 'Diterima'])->count();
        $menungguKpi = $kpiPool->where('status_validasi', 'Menunggu')->count();
        $ditolakKpi = $kpiPool->where('status_validasi', 'Ditolak')->count();

        $kpiStats = [
            'total' => $totalKpi,
            'diterima' => $diterimaKpi,
            'menunggu' => $menungguKpi,
            'ditolak' => $ditolakKpi,
            'persen_diterima' => $totalKpi > 0 ? round(($diterimaKpi / $totalKpi) * 100, 1) : 0,
            'persen_menunggu' => $totalKpi > 0 ? round(($menungguKpi / $totalKpi) * 100, 1) : 0,
            'persen_ditolak' => $totalKpi > 0 ? round(($ditolakKpi / $totalKpi) * 100, 1) : 0,
            'lainnya' => $totalKpi - $diterimaKpi - $menungguKpi - $ditolakKpi,
            'insight_text' => $insightText,
        ];

        // 3. Work Queue Tabel Aktivitas
        $tableQuery = (clone $baseActivitiesQuery)->with(['user:id,nama_lengkap,seksi,posisi_pekerjaan,pin']);

        if ($month && $month !== 'all') {
            $mFormatted = sprintf('%02d', (int)$month);
            $tableQuery->where('tanggal', 'like', "{$year}-{$mFormatted}%");
        }
        if ($status && $status !== 'all') {
            $tableQuery->whereIn('status_validasi', $status === 'Disetujui' ? ['Disetujui', 'Diterima'] : [$status]);
        }
        $activities = $tableQuery->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->paginate(10)
            ->withQueryString();

        // 4. Pending Dinas Luar
        $pendingDinasLuarQuery = DinasLuar::with('user:id,nama_lengkap,seksi,posisi_pekerjaan')
            ->whereIn('user_id', $pjlpUserIds)
            ->where('status_verifikasi', 'Menunggu');
        $pendingDinasLuarCount = (clone $pendingDinasLuarQuery)->count();
        $pendingDinasLuar = $pendingDinasLuarQuery->orderBy('waktu', 'desc')
            ->limit(10)
            ->get();

        $categories = Activity::whereIn('user_id', $pjlpUserIds)
            ->whereNotNull('kategori')
            ->distinct()
            ->pluck('kategori');

        return Inertia::render('Dashboard', [
            'role' => $user->role,
            'userSeksi' => $user->seksi,
            'currentMonthLabel' => $month && $month !== 'all'
                ? ($monthFullNames[(int) $month - 1] . ' ' . $year)
                : 'Tahun ' . $year . ' (Semua Bulan)',
            'filters' => [
                'tahun' => $year,
                'bulan' => $month ?? 'all',
                'status' => $status ?? 'all',
                'user_id' => $selectedUserId,
                'kategori' => $selectedCategory ?? 'all',
                'search' => $search ?? '',
            ],
            'kpiStats' => $kpiStats,
            'monthlyStats' => $monthlyStats,
            'todayStats' => $todayStats,
            'activities' => $activities,
            'pjlpList' => $pjlpUsers->map(fn($p) => [
                'id' => $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'seksi' => $p->seksi,
                'posisi' => $p->posisi_pekerjaan,
            ]),
            'categories' => $categories,
            'pendingDinasLuar' => $pendingDinasLuar,
            'pendingDinasLuarCount' => $pendingDinasLuarCount,
            'totalPjlp' => $totalPjlp,
            'systemStatus' => [
                'is_live' => true,
                'server_time' => Carbon::now()->toIso8601String(),
                'server_time_formatted' => Carbon::now()->translatedFormat('l, d F Y - H:i:s') . ' WIB',
            ],
        ]);
    }

    protected function dashboardSuperadmin(User $user, string $today, string $currentMonth): Response
    {
        $allUsers = User::orderBy('id')->get();
        $totalUsers = $allUsers->count();
        $activeUsers = $allUsers->where('status_user', 'Aktif')->count();
        $inactiveUsers = $totalUsers - $activeUsers;

        $sectionKey = fn (?string $name) => mb_strtolower(
            trim((string) User::normalizeSeksi(trim((string) $name)))
        );
        $usersBySeksi = $allUsers->groupBy(fn ($u) => $sectionKey($u->seksi));
        $countId = $usersBySeksi->get('id', collect())->count();
        $countAstik = $usersBySeksi->get('astik', collect())->count();
        $countKip = $usersBySeksi->get('kip', collect())->count();

        $userBreakdown = [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $inactiveUsers,
            'superadmin' => $allUsers->where('role', 'Superadmin')->count(),
            'kasudin' => $allUsers->where('role', 'Kasudin')->count(),
            'kasi' => $allUsers->where('role', 'Kasi')->count(),
            'pjlp' => $allUsers->where('role', 'PJLP')->count(),
            'id' => $countId,
            'astik' => $countAstik,
            'kip' => $countKip,
        ];

        // PJLP Presence Today (Fetched early for cross-referencing per Seksi)
        $pjlpUsers = $allUsers->where('role', 'PJLP');
        $pjlpPins = $pjlpUsers->pluck('pin')->filter()->values();
        $todayAttendances = Attendance::whereIn('pin', $pjlpPins)
            ->whereDate('tanggal', $today)
            ->get()
            ->keyBy('pin');

        $todayPresence = [
            'total_pjlp' => $pjlpUsers->count(),
            'wfo' => 0,
            'dl' => 0,
            'sakit_cuti' => 0,
            'belum_hadir' => 0,
        ];

        foreach ($pjlpUsers as $pjlp) {
            $att = $todayAttendances->get($pjlp->pin);
            if (!$att) {
                $todayPresence['belum_hadir']++;
            } else {
                $ket = strtoupper($att->keterangan ?? 'WFO');
                if (str_starts_with($ket, 'DL')) {
                    $todayPresence['dl']++;
                } elseif (in_array($ket, ['SAKIT', 'CUTI', 'IZIN'])) {
                    $todayPresence['sakit_cuti']++;
                } else {
                    $todayPresence['wfo']++;
                }
            }
        }

        // Seksi Overview with Attendance per Seksi
        $allSeksi = Seksi::orderBy('nama')->get();
        $seksiOverview = $allSeksi->map(function ($s) use ($usersBySeksi, $sectionKey, $todayAttendances) {
            $key = $sectionKey($s->nama);
            $members = $key !== '' ? $usersBySeksi->get($key, collect()) : collect();
            $pjlpMembers = $members->where('role', 'PJLP');
            $pjlpCount = $pjlpMembers->count();
            $kasi = $members->firstWhere('role', 'Kasi');

            $hadirCount = 0;
            foreach ($pjlpMembers as $pjlp) {
                $attendance = $pjlp->pin ? $todayAttendances->get($pjlp->pin) : null;
                if ($attendance && ($attendance->absen_masuk || $attendance->absen_pulang)) {
                    $hadirCount++;
                }
            }

            return [
                'id' => $s->id,
                'nama' => $s->nama,
                'kode' => $s->kode,
                'deskripsi' => $s->deskripsi,
                'is_active' => (bool)$s->is_active,
                'total_anggota' => $members->count(),
                'total_pjlp' => $pjlpCount,
                'hadir_hari_ini' => $hadirCount,
                'nama_kasi' => $kasi ? $kasi->nama_lengkap : 'Belum Ditugaskan',
            ];
        });

        // Activities Overview
        $periodStart = Carbon::createFromFormat('!Y-m', $currentMonth);
        $activitiesThisMonth = Activity::whereBetween('tanggal', [$periodStart->toDateString(), $periodStart->copy()->endOfMonth()->toDateString()])
            ->get(['id', 'user_id', 'kategori', 'status_validasi']);
        $allActivitiesCount = Activity::count();
        $activityStats = [
            'total_all_time' => $allActivitiesCount,
            'total_month' => $activitiesThisMonth->count(),
            'disetujui' => $activitiesThisMonth->where('status_validasi', 'Disetujui')->count(),
            'menunggu' => $activitiesThisMonth->where('status_validasi', 'Menunggu')->count(),
            'ditolak' => $activitiesThisMonth->where('status_validasi', 'Ditolak')->count(),
        ];

        $categories = $activitiesThisMonth->groupBy(fn ($a) => trim((string) $a->kategori) ?: 'Tanpa kategori')
            ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
            ->sortBy([['count', 'desc'], ['name', 'asc']])->values();
        $usersById = $allUsers->keyBy('id');
        $sectionActivityCounts = $activitiesThisMonth->groupBy(fn ($a) => $sectionKey($usersById->get($a->user_id)?->seksi))
            ->map->count();
        $topSections = $allSeksi->map(fn ($s) => [
            'name' => $s->nama,
            'count' => $sectionActivityCounts->get($sectionKey($s->nama), 0),
        ])->sortBy([['count', 'desc'], ['name', 'asc']])->take(5)->values();
        $yearCounts = Activity::whereBetween('tanggal', [
            $periodStart->copy()->startOfYear()->toDateString(),
            $periodStart->copy()->endOfYear()->toDateString(),
        ])->select('tanggal')->selectRaw('COUNT(*) as total')->groupBy('tanggal')->get()
            ->groupBy(fn ($row) => (int) $row->tanggal->format('n'))
            ->map(fn ($rows) => (int) $rows->sum('total'));
        $trend = collect(range(1, 12))->map(fn ($month) => [
            'month' => $month,
            'count' => $yearCounts->get($month, 0),
        ]);
        $scanCount = $pjlpUsers->filter(function ($pjlp) use ($todayAttendances) {
            $att = $pjlp->pin ? $todayAttendances->get($pjlp->pin) : null;
            return $att && ($att->absen_masuk || $att->absen_pulang);
        })->count();

        // Recent Registered/Updated Users with Official Government Employee Attributes
        $recentUsers = User::orderBy('updated_at', 'desc')
            ->limit(10)
            ->get([
                'id',
                'nama_lengkap',
                'username',
                'nik',
                'pin',
                'no_registrasi',
                'email',
                'role',
                'seksi',
                'posisi_pekerjaan',
                'status_user',
                'updated_at',
            ]);

        // Recent Activities Across Sudin
        $recentActivities = Activity::with(['user:id,nama_lengkap,seksi,posisi_pekerjaan,pin'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->limit(8)
            ->get();

        // System telemetry
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_os' => PHP_OS,
            'database' => config('database.default'),
            'app_env' => config('app.env'),
            'current_time' => Carbon::now()->translatedFormat('l, d F Y - H:i:s') . ' WIB',
        ];

        return Inertia::render('Dashboard', [
            'role' => 'Superadmin',
            'userSeksi' => $user->seksi ?? 'Subbagian Tata Usaha',
            'currentMonthLabel' => Carbon::now()->translatedFormat('F Y'),
            'superadminData' => [
                'period' => $currentMonth,
                'periodLabel' => $periodStart->locale('id')->translatedFormat('F Y'),
                'today' => $today,
                'scanCount' => $scanCount,
                'categories' => $categories,
                'trend' => $trend,
                'topSections' => $topSections,
                'userBreakdown' => $userBreakdown,
                'seksiOverview' => $seksiOverview,
                'todayPresence' => $todayPresence,
                'activityStats' => $activityStats,
                'recentUsers' => $recentUsers,
                'recentActivities' => $recentActivities,
                'systemInfo' => $systemInfo,
            ],
        ]);
    }
}
