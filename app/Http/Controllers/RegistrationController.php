<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\RegistrationSubmission;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    /**
     * Tampilkan formulir pendaftaran untuk user sementara.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isTemporary()) {
            return redirect()->route('dashboard');
        }

        // Cek jika akun sudah kedaluwarsa
        if ($user->isTemporaryExpired()) {
            $user->delete();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun tidak di temukan');
        }

        $remainingSeconds = 0;
        if ($user->temporary_expires_at) {
            $remainingSeconds = max(0, now()->diffInSeconds($user->temporary_expires_at, false));
        }

        $seksiList = Seksi::where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);

        return Inertia::render('Auth/Pendaftaran', [
            'tempUser' => [
                'id' => $user->id,
                'username' => $user->username,
                'expires_at' => $user->temporary_expires_at?->toIso8601String(),
            ],
            'remainingSeconds' => $remainingSeconds,
            'seksiList' => $seksiList,
        ]);
    }

    /**
     * Proses pengiriman formulir pendaftaran PJLP dan hapus akun sementara.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->isTemporary()) {
            return redirect()->route('dashboard');
        }

        if ($user->isTemporaryExpired()) {
            $user->delete();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun tidak di temukan');
        }

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9_.-]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'string', Password::min(6), 'confirmed'],
            'email' => ['nullable', 'email', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50'],
            'no_wa' => ['nullable', 'string', 'max:50'],
            'seksi' => ['required', 'string', 'max:100'],
            'posisi_pekerjaan' => ['required', 'string', 'max:255'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'string', 'in:Laki-laki,Perempuan'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'status_pernikahan' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:255'],
            'alamat_domisili' => ['nullable', 'string', 'max:1000'],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username yang diinginkan wajib diisi.',
            'username.unique' => 'Username sudah digunakan oleh pegawai lain. Silakan pilih username lain.',
            'username.regex' => 'Username hanya boleh huruf, angka, garis bawah, strip, dan titik.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'seksi.required' => 'Pilihan seksi penempatan wajib dipilih.',
            'posisi_pekerjaan.required' => 'Posisi / jabatan pekerjaan wajib diisi.',
        ]);

        $registrationCode = RegistrationSubmission::generateCode();

        $submission = RegistrationSubmission::create([
            'temporary_user_id' => $user->id,
            'registration_code' => $registrationCode,
            'nama_lengkap' => $validated['nama_lengkap'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'nik' => $validated['nik'] ?? null,
            'no_wa' => $validated['no_wa'] ?? null,
            'seksi' => $validated['seksi'],
            'posisi_pekerjaan' => $validated['posisi_pekerjaan'],
            'jabatan' => $validated['jabatan'] ?? 'PJLP',
            'status_pernikahan' => $validated['status_pernikahan'] ?? null,
            'jenis_kelamin' => $validated['jenis_kelamin'] ?? null,
            'tempat_lahir' => $validated['tempat_lahir'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'pendidikan_terakhir' => $validated['pendidikan_terakhir'] ?? null,
            'alamat_domisili' => $validated['alamat_domisili'] ?? null,
            'password' => Hash::make($validated['password']),
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Kirim Notifikasi Sistem ke seluruh Superadmin
        $superadmins = User::where('role', 'Superadmin')->get();
        foreach ($superadmins as $admin) {
            AppNotification::send([
                'user_id' => $admin->id,
                'title' => 'Pengajuan Pendaftaran PJLP',
                'message' => "Pengajuan baru dari {$submission->nama_lengkap} ({$submission->seksi}) siap divalidasi.",
                'type' => 'info',
                'link' => route('temporary-users.index', ['tab' => 'submissions', 'code' => $submission->registration_code]),
            ]);
        }

        // Catat Audit Trail
        ActivityLog::record([
            'user_id' => null,
            'user_name' => $submission->nama_lengkap,
            'user_role' => 'Registrant',
            'user_seksi' => $submission->seksi,
            'action' => 'SUBMISSION_CREATED',
            'module' => 'Pendaftaran PJLP',
            'description' => "Pengajuan formulir pendaftaran PJLP nomor {$submission->registration_code} berhasil dikirim.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Sesuai requirement: AKUN SEMENTARA HILANG / TERHAPUS
        $user->delete();

        // Logout akun sementara
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pendaftaran.sukses', [
            'code' => $submission->registration_code,
        ])->with('submission_data', [
            'registration_code' => $submission->registration_code,
            'nama_lengkap' => $submission->nama_lengkap,
            'username' => $submission->username,
            'seksi' => $submission->seksi,
            'posisi_pekerjaan' => $submission->posisi_pekerjaan,
            'jabatan' => $submission->jabatan,
        ]);
    }

    /**
     * Tampilkan halaman landing sukses setelah form pendaftaran terkirim.
     */
    public function sukses(Request $request): Response
    {
        $code = $request->query('code');
        $submission = null;

        if ($code) {
            $submission = RegistrationSubmission::where('registration_code', $code)
                ->first(['registration_code', 'nama_lengkap', 'username', 'seksi', 'posisi_pekerjaan', 'jabatan', 'status', 'created_at']);
        }

        return Inertia::render('Auth/PendaftaranSukses', [
            'submission' => $submission,
            'flashData' => session('submission_data'),
            'statusUrl' => $code ? route('pendaftaran.status', ['code' => $code]) : route('pendaftaran.status'),
        ]);
    }

    /**
     * Tampilkan halaman pelacakan status pendaftaran calon pegawai berdasarkan kode registrasi.
     */
    public function status(Request $request): Response
    {
        $code = trim((string) $request->query('code', ''));
        $submission = null;
        $searched = false;

        if (! empty($code)) {
            $searched = true;
            $submission = RegistrationSubmission::where('registration_code', $code)
                ->first([
                    'registration_code',
                    'nama_lengkap',
                    'username',
                    'seksi',
                    'posisi_pekerjaan',
                    'jabatan',
                    'status',
                    'catatan_admin',
                    'approved_at',
                    'created_at',
                ]);
        }

        return Inertia::render('Auth/PendaftaranStatus', [
            'code' => $code,
            'submission' => $submission,
            'searched' => $searched,
        ]);
    }
}
