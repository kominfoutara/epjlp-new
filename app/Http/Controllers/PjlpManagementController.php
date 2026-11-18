<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PjlpManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Hanya Superadmin, Kasudin, dan Kasi yang dapat melihat direktori data pengguna
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak memiliki akses ke direktori data pengguna.');
        }

        $search = $request->input('search');
        $seksi = $request->input('seksi');
        $role = $request->input('role');
        $status = $request->input('status');

        // Tampilkan seluruh pengguna selain Superadmin
        $query = User::where('role', '!=', 'Superadmin');

        if ($seksi) {
            $query->where('seksi', $seksi);
        }

        if ($role && in_array($role, ['PJLP', 'Kasi', 'Kasudin'])) {
            $query->where('role', $role);
        }

        if ($status) {
            $mappedStatus = $status === 'Nonaktif' ? 'Suspend' : $status;
            $query->where('status_user', $mappedStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%")
                  ->orWhere('posisi_pekerjaan', 'like', "%{$search}%");
            });
        }

        $list = $query->orderBy('nama_lengkap')->paginate(20)->withQueryString();
        $seksiList = User::whereNotNull('seksi')->where('seksi', '!=', '')->distinct()->pluck('seksi');

        return Inertia::render('Pjlp/Index', [
            'list' => $list,
            'filters' => [
                'search' => $search,
                'seksi' => $seksi,
                'role' => $role,
                'status' => $status,
            ],
            'seksiList' => $seksiList,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperadmin() && !$user->isKasi()) {
            abort(403, 'Akses ditolak: Hanya Superadmin dan Kasi yang dapat menambahkan data PJLP.');
        }

        if ($user->isKasi() && $user->seksi && $request->input('seksi') !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat menambahkan PJLP untuk seksi Anda.');
        }

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'nik' => 'nullable|string|max:50',
            'pin' => 'nullable|string|max:50',
            'seksi' => 'required|string',
            'posisi_pekerjaan' => 'nullable|string',
            'jam_masuk' => 'required|string',
            'jam_pulang' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'name' => $validated['nama_lengkap'],
            'nama_lengkap' => $validated['nama_lengkap'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'nik' => $validated['nik'] ?? null,
            'pin' => $validated['pin'] ?? null,
            'no_registrasi' => $validated['pin'] ?? null,
            'seksi' => $validated['seksi'],
            'posisi_pekerjaan' => $validated['posisi_pekerjaan'] ?? null,
            'jam_masuk' => $validated['jam_masuk'],
            'jam_pulang' => $validated['jam_pulang'],
            'password' => Hash::make($validated['password']),
            'role' => 'PJLP',
            'status_user' => 'Aktif',
        ]);

        return back()->with('success', 'Data PJLP berhasil ditambahkan.');
    }

    public function update(Request $request, User $pjlp): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperadmin() && !$user->isKasi()) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data PJLP.');
        }

        if ($user->isKasi() && $user->seksi && $pjlp->seksi !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengubah data PJLP untuk seksi Anda.');
        }

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username,' . $pjlp->id,
            'email' => 'required|email|max:255|unique:users,email,' . $pjlp->id,
            'nik' => 'nullable|string|max:50',
            'pin' => 'nullable|string|max:50',
            'seksi' => 'required|string',
            'posisi_pekerjaan' => 'nullable|string',
            'jam_masuk' => 'required|string',
            'jam_pulang' => 'required|string',
            'status_user' => 'required|in:Aktif,Menunggu,Suspend,Nonaktif',
            'password' => 'nullable|string|min:6',
        ]);

        $statusUser = $validated['status_user'];
        if ($statusUser === 'Nonaktif') {
            $statusUser = 'Suspend';
        }

        $updateData = [
            'name' => $validated['nama_lengkap'],
            'nama_lengkap' => $validated['nama_lengkap'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'nik' => $validated['nik'] ?? $pjlp->nik,
            'pin' => $validated['pin'] ?? $pjlp->pin,
            'no_registrasi' => $validated['pin'] ?? $pjlp->no_registrasi,
            'seksi' => $validated['seksi'],
            'posisi_pekerjaan' => $validated['posisi_pekerjaan'] ?? $pjlp->posisi_pekerjaan,
            'jam_masuk' => $validated['jam_masuk'],
            'jam_pulang' => $validated['jam_pulang'],
            'status_user' => $statusUser,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $pjlp->update($updateData);

        return back()->with('success', 'Data PJLP berhasil diperbarui.');
    }

    public function destroy(Request $request, User $pjlp): RedirectResponse
    {
        $user = $request->user();
        if (!$user->isSuperadmin() && !$user->isKasi()) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus data PJLP.');
        }

        if ($user->isKasi() && $user->seksi && $pjlp->seksi !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat menghapus data PJLP di seksi Anda.');
        }

        $pjlp->delete();

        return back()->with('success', 'Data PJLP berhasil dihapus.');
    }
}
