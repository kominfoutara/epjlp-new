<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\DinasLuar;
use App\Models\User;
use App\Services\DinasLuar\DinasLuarService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DinasLuarController extends Controller
{
    public function __construct(
        protected DinasLuarService $dinasLuarService
    ) {}

    /**
     * Tampilkan data riwayat & permohonan dinas luar.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $month = $request->input('bulan', Carbon::now()->format('Y-m'));
        $status = $request->input('status');
        $seksi = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));

        $query = DinasLuar::with(['user:id,nama_lengkap,seksi,pin', 'verifier:id,nama_lengkap']);

        if ($user->isPjlp()) {
            $query->where('user_id', $user->id);
        } else {
            if ($seksi) {
                $query->whereHas('user', function ($q) use ($seksi) {
                    $q->where('seksi', $seksi);
                });
            }
        }

        if ($month) {
            $query->where('waktu', 'like', "{$month}%");
        }

        if ($status) {
            $query->where('status_verifikasi', $status);
        }

        $list = $query->orderBy('waktu', 'desc')->paginate(20)->withQueryString();
        $seksiList = User::whereNotNull('seksi')->distinct()->pluck('seksi');

        return Inertia::render('DinasLuar/Index', [
            'isPjlp' => $user->isPjlp(),
            'canVerify' => $user->isKasi() || $user->isKasudin(),
            'isSuperadmin' => $user->isSuperadmin(),
            'list' => $list,
            'filters' => [
                'bulan' => $month,
                'status' => $status,
                'seksi' => $seksi,
            ],
            'seksiList' => $seksiList,
        ]);
    }

    /**
     * Ajukan permohonan dinas luar baru (termasuk selfie & geotagging).
     */
    public function store(Request $request): RedirectResponse
    {
        $fotoRules = $request->hasFile('foto')
            ? ['required', 'file', 'mimes:jpeg,png,jpg,webp', 'max:5120']
            : ['required', 'string', 'min:30'];

        $validated = $request->validate([
            'tipe' => 'required|in:DL AWAL,DL AKHIR,DL PENUH',
            'keterangan' => 'required|string|min:3',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'alamat' => 'nullable|string',
            'foto' => $fotoRules,
        ], [
            'foto.required' => 'Wajib mengambil atau mengunggah swafoto kehadiran dinas luar.',
            'foto.min' => 'Berkas atau data swafoto tidak valid.',
            'tipe.required' => 'Pilih tipe dinas luar.',
            'keterangan.required' => 'Keterangan atau maksud surat tugas wajib diisi.',
            'keterangan.min' => 'Keterangan minimal 3 karakter.',
        ]);

        $photo = $request->file('foto') ?? $request->input('foto');
        $user = $request->user();

        $dinasLuar = $this->dinasLuarService->submitDinasLuar(
            $user,
            $validated,
            $photo
        );

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'PENGAJUAN_DINAS_LUAR',
            'module' => 'Dinas Luar',
            'description' => "Mengajukan Dinas Luar ({$dinasLuar->tipe}): {$dinasLuar->keterangan}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['dinas_luar_id' => $dinasLuar->id, 'tipe' => $dinasLuar->tipe],
        ]);

        AppNotification::send([
            'title' => 'Pengajuan Dinas Luar Baru',
            'message' => "{$user->nama_lengkap} mengajukan {$dinasLuar->tipe}." . ($dinasLuar->keterangan ? " Ket: {$dinasLuar->keterangan}" : ''),
            'type' => 'info',
            'module' => 'Dinas Luar',
            'target_role' => 'Kasi',
            'link' => '/dinas-luar',
        ]);

        return back()->with('success', 'Pengajuan Dinas Luar berhasil dikirim.');
    }

    /**
     * Verifikasi satuan dinas luar oleh Kasi/Admin.
     */
    public function verify(Request $request, DinasLuar $dinasLuar): RedirectResponse
    {
        $user = $request->user();

        // Superadmin hanya dapat memantau dan tidak berwenang memverifikasi
        if ($user->isSuperadmin()) {
            abort(403, 'Akses ditolak: Superadmin hanya dapat memantau dan tidak berwenang memverifikasi dinas luar.');
        }

        // Hanya Kasudin dan Kasi yang dapat memverifikasi dinas luar
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang memverifikasi dinas luar.');
        }

        // Kasi hanya dapat memverifikasi pengajuan dinas luar di seksinya sendiri
        if ($user->isKasi() && $dinasLuar->user && $user->seksi && $dinasLuar->user->seksi !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat memverifikasi pengajuan dinas luar di seksi Anda.');
        }

        $validated = $request->validate([
            'status' => 'required|in:Disetujui,Ditolak',
        ]);

        $this->dinasLuarService->verify($dinasLuar, $validated['status'], $user);

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'VERIFIKASI_DINAS_LUAR',
            'module' => 'Dinas Luar',
            'description' => "Memverifikasi pengajuan dinas luar #{$dinasLuar->id} ({$dinasLuar->tipe}) menjadi '{$validated['status']}'",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'dinas_luar_id' => $dinasLuar->id,
                'target_user_id' => $dinasLuar->user_id,
                'status' => $validated['status'],
            ],
        ]);

        AppNotification::send([
            'title' => 'Dinas Luar ' . $validated['status'],
            'message' => "Pengajuan Dinas Luar ({$dinasLuar->tipe}) Anda telah di-{$validated['status']} oleh {$user->nama_lengkap}.",
            'type' => $validated['status'] === 'Disetujui' ? 'success' : 'danger',
            'module' => 'Dinas Luar',
            'target_user_id' => $dinasLuar->user_id,
            'link' => '/dinas-luar',
        ]);

        return back()->with('success', "Pengajuan Dinas Luar telah di-{$validated['status']}.");
    }

    /**
     * Verifikasi massal pengajuan dinas luar.
     */
    public function verifyBulk(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Superadmin hanya dapat memantau dan tidak berwenang memverifikasi massal
        if ($user->isSuperadmin()) {
            abort(403, 'Akses ditolak: Superadmin hanya dapat memantau dan tidak berwenang memverifikasi massal dinas luar.');
        }

        // Hanya Kasudin dan Kasi yang dapat memverifikasi dinas luar
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang memverifikasi dinas luar.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:dinas_luar,id',
            'status' => 'required|in:Disetujui,Ditolak',
        ]);

        // Kasi hanya dapat memverifikasi seksi sendiri
        if ($user->isKasi() && $user->seksi) {
            $unauthorizedCount = DinasLuar::whereIn('id', $validated['ids'])
                ->whereHas('user', function ($q) use ($user) {
                    $q->where('seksi', '!=', $user->seksi);
                })->count();

            if ($unauthorizedCount > 0) {
                abort(403, 'Akses ditolak: Terdapat pengajuan dinas luar dari seksi lain yang tidak dapat Anda verifikasi.');
            }
        }

        $count = $this->dinasLuarService->verifyBulk(
            $validated['ids'],
            $validated['status'],
            $user
        );

        ActivityLog::record([
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?? $user->name,
            'user_role' => $user->role,
            'user_seksi' => $user->seksi,
            'action' => 'VERIFIKASI_DINAS_LUAR',
            'module' => 'Dinas Luar',
            'description' => "Verifikasi massal {$count} pengajuan Dinas Luar menjadi '{$validated['status']}'",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'count' => $count,
                'status' => $validated['status'],
            ],
        ]);

        return back()->with('success', "{$count} pengajuan Dinas Luar berhasil diverifikasi.");
    }
}
