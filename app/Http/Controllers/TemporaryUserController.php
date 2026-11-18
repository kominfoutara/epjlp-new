<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\RegistrationSubmission;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TemporaryUserController extends Controller
{
    /**
     * Tampilkan halaman pengelolaan user sementara dan pengajuan pendaftaran (Superadmin).
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperadmin($request);

        // Otomatis bersihkan user sementara yang sudah kedaluwarsa
        User::where('is_temporary', true)
            ->whereNotNull('temporary_expires_at')
            ->where('temporary_expires_at', '<=', now())
            ->delete();

        // 1. Daftar Akun Sementara yang masih aktif
        $activeTemporaryUsers = User::where('is_temporary', true)
            ->latest()
            ->paginate(10, ['*'], 'temp_page')
            ->withQueryString();

        // 2. Daftar Pengajuan Pendaftaran
        $submissionsQuery = RegistrationSubmission::with(['approver:id,nama_lengkap,username', 'registeredUser:id,nama_lengkap,username,pin'])
            ->latest();

        if ($status = $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $submissionsQuery->where('status', $status);
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $submissionsQuery->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('registration_code', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%")
                    ->orWhere('seksi', 'like', "%{$search}%");
            });
        }

        $submissions = $submissionsQuery->paginate(10, ['*'], 'sub_page')->withQueryString();

        // Statistik
        $stats = [
            'active_temporary_users' => User::where('is_temporary', true)->count(),
            'pending_submissions' => RegistrationSubmission::where('status', 'pending')->count(),
            'approved_submissions' => RegistrationSubmission::where('status', 'approved')->count(),
            'rejected_submissions' => RegistrationSubmission::where('status', 'rejected')->count(),
            'total_submissions' => RegistrationSubmission::count(),
        ];

        // Daftar Seksi untuk pilihan validasi
        $seksiList = Seksi::where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);

        return Inertia::render('TemporaryUsers/Index', [
            'activeTemporaryUsers' => $activeTemporaryUsers,
            'submissions' => $submissions,
            'stats' => $stats,
            'seksiList' => $seksiList,
            'filters' => [
                'status' => $request->query('status', 'all'),
                'search' => $request->query('search', ''),
                'tab' => $request->query('tab', 'active'),
            ],
            'createdCredentials' => session('createdCredentials'),
        ]);
    }

    /**
     * Buat akun user sementara baru dengan batas waktu tertentu (5, 10, 15 menit, atau kustom).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        $request->validate([
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_.-]+$/', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:4', 'max:50'],
            'duration_type' => ['required', 'string', 'in:5,10,15,custom'],
            'custom_minutes' => ['required_if:duration_type,custom', 'nullable', 'integer', 'min:1', 'max:1440'],
        ], [
            'username.unique' => 'Username ini sudah digunakan, silakan masukkan username lain.',
            'username.regex' => 'Username hanya boleh huruf, angka, garis bawah, strip, dan titik.',
            'duration_type.required' => 'Pilihan durasi masa aktif wajib ditentukan.',
            'custom_minutes.required_if' => 'Menit kustom wajib diisi jika memilih opsi manual.',
            'custom_minutes.min' => 'Durasi minimal adalah 1 menit.',
            'custom_minutes.max' => 'Durasi maksimal adalah 1440 menit (24 jam).',
        ]);

        $durationType = $request->input('duration_type');
        $minutes = match ($durationType) {
            '5' => 5,
            '10' => 10,
            '15' => 15,
            'custom' => (int) $request->input('custom_minutes'),
            default => 10,
        };

        $expiresAt = now()->addMinutes($minutes);

        // Generate username jika tidak diisi
        $rawUsername = $request->input('username');
        if (empty($rawUsername)) {
            do {
                $rawUsername = 'tamu_' . strtolower(Str::random(5));
            } while (User::where('username', $rawUsername)->exists());
        }

        // Generate password jika tidak diisi
        $rawPassword = $request->input('password');
        if (empty($rawPassword)) {
            $rawPassword = 'Tamu' . random_int(1000, 9999);
        }

        $user = User::create([
            'name' => 'Akun Sementara (' . $rawUsername . ')',
            'nama_lengkap' => 'Akun Sementara Registrasi',
            'username' => $rawUsername,
            'password' => Hash::make($rawPassword),
            'role' => 'Temporary',
            'status_user' => 'Aktif',
            'is_temporary' => true,
            'temporary_expires_at' => $expiresAt,
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
        ]);

        ActivityLog::record([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->nama_lengkap,
            'user_role' => $request->user()->role,
            'action' => 'TEMPORARY_USER_CREATED',
            'module' => 'User Sementara',
            'description' => "Membuat akun sementara {$user->username} (masa aktif {$minutes} menit, kedaluwarsa {$expiresAt->format('d/m/Y H:i:s')}).",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $durationLabel = $durationType === 'custom' ? "{$minutes} Menit (Manual)" : "{$minutes} Menit";

        return back()->with([
            'success' => "Akun user sementara '{$rawUsername}' berhasil dibuat dengan masa aktif {$durationLabel}.",
            'createdCredentials' => [
                'id' => $user->id,
                'username' => $rawUsername,
                'password' => $rawPassword,
                'duration_label' => $durationLabel,
                'expires_at' => $expiresAt->toIso8601String(),
                'expires_at_formatted' => $expiresAt->format('d/m/Y H:i:s'),
                'login_url' => route('login'),
            ],
        ]);
    }

    /**
     * Hapus / cabut akses akun sementara secara manual oleh Superadmin.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        if (! $user->isTemporary()) {
            return back()->with('error', 'Hanya akun bertipe sementara yang dapat dihapus melalui menu ini.');
        }

        $username = $user->username;
        $user->delete();

        ActivityLog::record([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->nama_lengkap,
            'user_role' => $request->user()->role,
            'action' => 'TEMPORARY_USER_REVOKED',
            'module' => 'User Sementara',
            'description' => "Mencabut/menghapus akun sementara {$username}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Akun sementara '{$username}' telah dicabut dan dihapus dari sistem.");
    }

    /**
     * Setujui formulir pendaftaran calon PJLP dan terbitkan akun resmi.
     */
    public function approveSubmission(Request $request, RegistrationSubmission $submission): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        if ($submission->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah pernah diproses sebelumnya.');
        }

        $request->validate([
            'pin' => ['nullable', 'string', 'max:50'],
            'seksi' => ['required', 'string', 'max:100'],
            'posisi_pekerjaan' => ['required', 'string', 'max:255'],
            'catatan_admin' => ['nullable', 'string', 'max:500'],
        ], [
            'seksi.required' => 'Seksi penempatan wajib ditentukan.',
            'posisi_pekerjaan.required' => 'Posisi/jabatan pekerjaan wajib diisi.',
        ]);

        // Cek jika username sudah diambil orang lain saat jeda pengajuan
        if (User::where('username', $submission->username)->exists()) {
            return back()->with('error', "Username '{$submission->username}' sudah digunakan di sistem. Mohon periksa kelola pengguna.");
        }

        // Terbitkan akun PJLP permanen
        $newUser = User::create([
            'name' => $submission->nama_lengkap,
            'nama_lengkap' => $submission->nama_lengkap,
            'username' => $submission->username,
            'email' => $submission->email,
            'password' => $submission->password, // sudah ter-hash saat pengajuan
            'nik' => $submission->nik,
            'no_wa' => $submission->no_wa,
            'seksi' => $request->input('seksi', $submission->seksi),
            'posisi_pekerjaan' => $request->input('posisi_pekerjaan', $submission->posisi_pekerjaan),
            'jabatan' => $submission->jabatan ?? $submission->posisi_pekerjaan,
            'status_pernikahan' => $submission->status_pernikahan,
            'jenis_kelamin' => $submission->jenis_kelamin,
            'tempat_lahir' => $submission->tempat_lahir,
            'tanggal_lahir' => $submission->tanggal_lahir,
            'pendidikan_terakhir' => $submission->pendidikan_terakhir,
            'nama_instansi' => 'Sudin Kominfotik Jakarta Timur',
            'mulai_bekerja' => now()->toDateString(),
            'role' => 'PJLP',
            'status_user' => 'Aktif',
            'pin' => $request->input('pin', $submission->pin),
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
            'is_temporary' => false,
            'temporary_expires_at' => null,
        ]);

        $submission->update([
            'status' => 'approved',
            'pin' => $newUser->pin,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'registered_user_id' => $newUser->id,
            'catatan_admin' => $request->input('catatan_admin'),
        ]);

        ActivityLog::record([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->nama_lengkap,
            'user_role' => $request->user()->role,
            'action' => 'SUBMISSION_APPROVED',
            'module' => 'Pendaftaran PJLP',
            'description' => "Menyetujui pendaftaran {$submission->nama_lengkap} [{$submission->registration_code}] dan menerbitkan akun PJLP resmi ID {$newUser->id}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Pengajuan [{$submission->registration_code}] atas nama '{$submission->nama_lengkap}' telah disetujui. Akun PJLP resmi aktif.");
    }

    /**
     * Tolak pengajuan pendaftaran calon PJLP dengan catatan.
     */
    public function rejectSubmission(Request $request, RegistrationSubmission $submission): RedirectResponse
    {
        $this->ensureSuperadmin($request);

        if ($submission->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah pernah diproses sebelumnya.');
        }

        $request->validate([
            'catatan_admin' => ['required', 'string', 'max:500'],
        ], [
            'catatan_admin.required' => 'Alasan / catatan penolakan wajib diisi agar pelamar mengetahui kendalanya.',
        ]);

        $submission->update([
            'status' => 'rejected',
            'catatan_admin' => $request->input('catatan_admin'),
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        ActivityLog::record([
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->nama_lengkap,
            'user_role' => $request->user()->role,
            'action' => 'SUBMISSION_REJECTED',
            'module' => 'Pendaftaran PJLP',
            'description' => "Menolak pengajuan pendaftaran {$submission->nama_lengkap} [{$submission->registration_code}]. Catatan: {$request->input('catatan_admin')}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Pengajuan [{$submission->registration_code}] telah ditolak dengan catatan.");
    }

    /**
     * Pastikan pengguna saat ini adalah Superadmin.
     */
    protected function ensureSuperadmin(Request $request): void
    {
        if (! $request->user() || ! $request->user()->isSuperadmin()) {
            abort(403, 'Akses terbatas untuk Superadmin.');
        }
    }
}
