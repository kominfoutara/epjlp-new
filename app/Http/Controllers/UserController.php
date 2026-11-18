<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Tampilkan daftar seluruh pengguna sistem (All Roles).
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        $search = $request->input('search');
        $role = $request->input('role');
        $seksi = $request->input('seksi');
        $status = $request->input('status');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('nrk', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        if ($seksi) {
            $cleanSeksi = trim(preg_replace('/^Seksi\s+/i', '', $seksi));
            $normalized = $this->normalizeSeksi($seksi);

            $query->where(function ($q) use ($seksi, $cleanSeksi, $normalized) {
                $q->where('seksi', $seksi)
                  ->orWhere('seksi', "Seksi {$cleanSeksi}")
                  ->orWhere('seksi', $normalized)
                  ->orWhere('seksi', strtoupper($cleanSeksi));
            });
        }

        if ($status) {
            $query->where('status_user', $status);
        }

        $users = $query->orderBy('nama_lengkap')->paginate(15)->withQueryString();
        $seksiList = Seksi::active()->orderBy('nama')->get(['id', 'nama', 'kode']);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'seksiList' => $seksiList,
            'filters' => [
                'search' => $search,
                'role' => $role,
                'seksi' => $seksi,
                'status' => $status,
            ],
            'roles' => ['Superadmin', 'Kasudin', 'Kasi', 'PJLP'],
        ]);
    }

    /**
     * Simpan pengguna baru dengan role apa pun.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $seksi = $this->normalizeSeksi($validated['seksi'] ?? null) ?? 'Astik';

        $newUser = User::create([
            'name' => $validated['nama_lengkap'],
            'nama_lengkap' => $validated['nama_lengkap'],
            'username' => trim($validated['username']),
            'email' => trim($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'seksi' => $seksi,
            'nik' => $validated['nik'] ?? null,
            'no_wa' => $validated['no_wa'] ?? null,
            'jabatan' => $validated['jabatan'] ?? null,
            'nrk' => $validated['nrk'] ?? null,
            'pin' => $validated['pin'] ?? null,
            'no_registrasi' => $validated['pin'] ?? null,
            'posisi_pekerjaan' => $validated['posisi_pekerjaan'] ?? null,
            'jam_masuk' => $validated['jam_masuk'] ?? '07:30:00',
            'jam_pulang' => $validated['jam_pulang'] ?? '16:00:00',
            'status_user' => $validated['status_user'],
            'jenis_kelamin' => $validated['jenis_kelamin'] ?? null,
            'status_pernikahan' => $validated['status_pernikahan'] ?? null,
            'pendidikan_terakhir' => $validated['pendidikan_terakhir'] ?? null,
            'tempat_lahir' => $validated['tempat_lahir'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'mulai_bekerja' => $validated['mulai_bekerja'] ?? null,
            'nama_instansi' => $validated['nama_instansi'] ?? 'Sudin Kominfotik Jakarta Barat',
        ]);

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'TAMBAH_USER',
            'module' => 'Manajemen User',
            'description' => "Menambahkan akun pengguna baru: {$newUser->nama_lengkap} (Role: {$newUser->role}, Seksi: {$newUser->seksi})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'target_user_id' => $newUser->id,
                'target_username' => $newUser->username,
                'role' => $newUser->role,
            ],
        ]);

        AppNotification::send([
            'title' => 'Akun Baru Didaftarkan',
            'message' => "Pengguna baru {$newUser->nama_lengkap} ({$newUser->role}) telah ditambahkan oleh {$actor->nama_lengkap}.",
            'type' => 'info',
            'module' => 'Manajemen User',
            'target_role' => 'Superadmin',
            'link' => '/users',
        ]);

        return back()->with('success', "Pengguna baru '{$validated['nama_lengkap']}' ({$validated['role']}) berhasil ditambahkan.");
    }

    /**
     * Perbarui data pengguna.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $seksi = array_key_exists('seksi', $validated)
            ? ($this->normalizeSeksi($validated['seksi']) ?? 'Astik')
            : $user->seksi;

        $updateData = [
            'name' => $validated['nama_lengkap'],
            'nama_lengkap' => $validated['nama_lengkap'],
            'username' => trim($validated['username']),
            'email' => trim($validated['email']),
            'role' => $validated['role'],
            'seksi' => $seksi,
            'nik' => array_key_exists('nik', $validated) ? $validated['nik'] : $user->nik,
            'no_wa' => array_key_exists('no_wa', $validated) ? $validated['no_wa'] : $user->no_wa,
            'jabatan' => array_key_exists('jabatan', $validated) ? $validated['jabatan'] : $user->jabatan,
            'nrk' => array_key_exists('nrk', $validated) ? $validated['nrk'] : $user->nrk,
            'pin' => array_key_exists('pin', $validated) ? $validated['pin'] : $user->pin,
            'no_registrasi' => array_key_exists('pin', $validated) ? $validated['pin'] : $user->no_registrasi,
            'posisi_pekerjaan' => array_key_exists('posisi_pekerjaan', $validated) ? $validated['posisi_pekerjaan'] : $user->posisi_pekerjaan,
            'jam_masuk' => array_key_exists('jam_masuk', $validated) ? $validated['jam_masuk'] : $user->jam_masuk,
            'jam_pulang' => array_key_exists('jam_pulang', $validated) ? $validated['jam_pulang'] : $user->jam_pulang,
            'status_user' => $validated['status_user'],
            'jenis_kelamin' => array_key_exists('jenis_kelamin', $validated) ? $validated['jenis_kelamin'] : $user->jenis_kelamin,
            'status_pernikahan' => array_key_exists('status_pernikahan', $validated) ? $validated['status_pernikahan'] : $user->status_pernikahan,
            'pendidikan_terakhir' => array_key_exists('pendidikan_terakhir', $validated) ? $validated['pendidikan_terakhir'] : $user->pendidikan_terakhir,
            'tempat_lahir' => array_key_exists('tempat_lahir', $validated) ? $validated['tempat_lahir'] : $user->tempat_lahir,
            'tanggal_lahir' => array_key_exists('tanggal_lahir', $validated) ? $validated['tanggal_lahir'] : $user->tanggal_lahir,
            'mulai_bekerja' => array_key_exists('mulai_bekerja', $validated) ? $validated['mulai_bekerja'] : $user->mulai_bekerja,
            'nama_instansi' => array_key_exists('nama_instansi', $validated) ? $validated['nama_instansi'] : $user->nama_instansi,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'UPDATE_USER',
            'module' => 'Manajemen User',
            'description' => "Memperbarui data profil akun: {$user->nama_lengkap} ({$user->username})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'target_user_id' => $user->id,
                'target_username' => $user->username,
            ],
        ]);

        return back()->with('success', "Data pengguna '{$user->nama_lengkap}' berhasil diperbarui.");
    }

    /**
     * Reset kata sandi pengguna secara langsung oleh Superadmin.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 6 karakter.',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'RESET_PASSWORD',
            'module' => 'Manajemen User',
            'description' => "Mereset kata sandi akun pengguna: {$user->nama_lengkap} ({$user->username})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['target_user_id' => $user->id],
        ]);

        AppNotification::send([
            'title' => 'Password Direset',
            'message' => "Password akun Anda telah direset oleh Superadmin ({$actor->nama_lengkap}). Silakan gunakan kata sandi baru.",
            'type' => 'warning',
            'module' => 'Manajemen User',
            'target_user_id' => $user->id,
            'link' => '/profile',
        ]);

        return back()->with('success', "Password untuk akun '{$user->username}' berhasil direset.");
    }

    /**
     * Toggle status aktif / suspend akun.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $newStatus = ($user->status_user === 'Aktif') ? 'Suspend' : 'Aktif';
        $user->update(['status_user' => $newStatus]);

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'TOGGLE_STATUS_USER',
            'module' => 'Manajemen User',
            'description' => "Mengubah status akun {$user->nama_lengkap} menjadi '{$newStatus}'",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => [
                'target_user_id' => $user->id,
                'status_user' => $newStatus,
            ],
        ]);

        return back()->with('success', "Status akun '{$user->nama_lengkap}' diubah menjadi {$newStatus}.");
    }

    /**
     * Hapus akun pengguna.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $nama = $user->nama_lengkap;
        $username = $user->username;
        $userId = $user->id;
        $user->delete();

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'HAPUS_USER',
            'module' => 'Manajemen User',
            'description' => "Menghapus akun pengguna: {$nama} ({$username})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['deleted_user_id' => $userId, 'deleted_username' => $username],
        ]);

        return back()->with('success', "Akun '{$nama}' berhasil dihapus.");
    }

    /**
     * Normalisasi nama seksi menjadi format ringkas (contoh: 'Seksi ASTIK' -> 'Astik', 'Seksi KIP' -> 'KIP', 'Seksi ID' -> 'ID').
     */
    protected function normalizeSeksi(?string $seksi): ?string
    {
        if (!$seksi) {
            return null;
        }

        $clean = trim(preg_replace('/^Seksi\s+/i', '', $seksi));

        return match (strtoupper($clean)) {
            'ASTIK' => 'Astik',
            'KIP' => 'KIP',
            'ID' => 'ID',
            'TU', 'TATA USAHA', 'SUBBAGIAN TATA USAHA' => 'Tata Usaha',
            'PIMPINAN' => 'Pimpinan',
            default => $clean,
        };
    }
}
