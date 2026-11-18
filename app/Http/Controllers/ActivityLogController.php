<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of system activity logs with filtering.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Only Superadmin, Kasudin, and Kasi can view system activity logs
        if (!in_array($user->role, ['Superadmin', 'Kasudin', 'Kasi'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses untuk melihat log aktivitas sistem.');
        }

        $filters = [
            'search' => $request->query('search', ''),
            'module' => $request->query('module', 'all'),
            'role' => $request->query('role', 'all'),
            'action' => $request->query('action', 'all'),
            'period' => $request->query('period', 'all'),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];

        $query = ActivityLog::with('user')
            ->search($filters['search'])
            ->module($filters['module'])
            ->role($filters['role'])
            ->actionType($filters['action'])
            ->period($filters['period'])
            ->dateRange($filters['date_from'], $filters['date_to'])
            ->latest();

        $logs = $query->paginate(20)->withQueryString();

        // Statistics
        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::whereDate('created_at', Carbon::today())->count();
        $sevenDaysLogs = ActivityLog::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        // Module summary counts
        $moduleStats = ActivityLog::selectRaw('module, count(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->pluck('count', 'module')
            ->toArray();

        // Available Filter Options
        $availableModules = [
            'Aktivitas',
            'Dinas Luar',
            'Absensi',
            'Manajemen User',
            'Master Seksi',
            'Autentikasi',
        ];

        $availableActions = [
            'LOGIN',
            'LOGOUT',
            'TAMBAH_AKTIVITAS',
            'UPDATE_AKTIVITAS',
            'HAPUS_AKTIVITAS',
            'VALIDASI_AKTIVITAS',
            'TOLAK_AKTIVITAS',
            'PENGAJUAN_DINAS_LUAR',
            'VERIFIKASI_DINAS_LUAR',
            'TAMBAH_USER',
            'UPDATE_USER',
            'RESET_PASSWORD',
            'TOGGLE_STATUS_USER',
            'HAPUS_USER',
            'TAMBAH_SEKSI',
            'UPDATE_SEKSI',
            'TOGGLE_STATUS_SEKSI',
            'HAPUS_SEKSI',
            'SYNC_ABSENSI',
        ];

        $availableRoles = [
            'Superadmin',
            'Kasudin',
            'Kasi',
            'PJLP',
        ];

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'stats' => [
                'total' => $totalLogs,
                'today' => $todayLogs,
                'seven_days' => $sevenDaysLogs,
                'modules' => $moduleStats,
            ],
            'availableModules' => $availableModules,
            'availableActions' => $availableActions,
            'availableRoles' => $availableRoles,
        ]);
    }
}
