<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSeksiRequest;
use App\Http\Requests\UpdateSeksiRequest;
use App\Models\ActivityLog;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SeksiController extends Controller
{
    /**
     * Tampilkan daftar master seksi.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        $search = $request->input('search');
        $status = $request->input('status');

        $query = Seksi::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $seksiList = $query->orderBy('nama')->paginate(15)->withQueryString();

        // Legacy users store either a short name or a prefixed section name.
        // Aggregate first so counting does not load individual employee records.
        $sectionKey = fn (?string $name) => mb_strtolower(
            trim((string) User::normalizeSeksi(trim((string) $name)))
        );
        $counts = [];
        $groups = User::query()->whereNotNull('seksi')
            ->select('seksi', 'role')->selectRaw('COUNT(*) as total')
            ->groupBy('seksi', 'role')->get();

        foreach ($groups as $group) {
            $key = $sectionKey($group->seksi);
            if ($key === '') {
                continue;
            }
            $counts[$key] ??= ['total' => 0, 'pjlp' => 0];
            $counts[$key]['total'] += (int) $group->total;
            if ($group->role === 'PJLP') {
                $counts[$key]['pjlp'] += (int) $group->total;
            }
        }

        $seksiList->through(function ($seksi) use ($counts, $sectionKey) {
            $count = $counts[$sectionKey($seksi->nama)] ?? ['total' => 0, 'pjlp' => 0];
            $seksi->setAttribute('users_count', $count['total']);
            $seksi->setAttribute('pjlp_count', $count['pjlp']);

            return $seksi;
        });

        return Inertia::render('Seksi/Index', [
            'seksiList' => $seksiList,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Simpan seksi baru.
     */
    public function store(StoreSeksiRequest $request): RedirectResponse
    {
        $seksi = Seksi::create([
            'nama' => $request->nama,
            'kode' => strtoupper(trim($request->kode)),
            'deskripsi' => $request->deskripsi,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'TAMBAH_SEKSI',
            'module' => 'Master Seksi',
            'description' => "Menambahkan master seksi baru: {$seksi->nama} ({$seksi->kode})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['seksi_id' => $seksi->id, 'kode' => $seksi->kode],
        ]);

        return back()->with('success', 'Seksi baru berhasil ditambahkan.');
    }

    /**
     * Perbarui data seksi.
     */
    public function update(UpdateSeksiRequest $request, Seksi $seksi): RedirectResponse
    {
        $seksi->update([
            'nama' => $request->nama,
            'kode' => strtoupper(trim($request->kode)),
            'deskripsi' => $request->deskripsi,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'UPDATE_SEKSI',
            'module' => 'Master Seksi',
            'description' => "Memperbarui master seksi: {$seksi->nama} ({$seksi->kode})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['seksi_id' => $seksi->id],
        ]);

        return back()->with('success', 'Data seksi berhasil diperbarui.');
    }

    /**
     * Toggle status aktif/nonaktif seksi.
     */
    public function toggleStatus(Request $request, Seksi $seksi): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        $seksi->update([
            'is_active' => !$seksi->is_active,
        ]);

        $statusText = $seksi->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'TOGGLE_STATUS_SEKSI',
            'module' => 'Master Seksi',
            'description' => "Mengubah status seksi '{$seksi->nama}' menjadi {$statusText}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['seksi_id' => $seksi->id, 'is_active' => $seksi->is_active],
        ]);

        return back()->with('success', "Seksi '{$seksi->nama}' berhasil {$statusText}.");
    }

    /**
     * Hapus aman (soft delete) seksi.
     */
    public function destroy(Request $request, Seksi $seksi): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin(), 403, 'Akses terbatas untuk Superadmin.');

        $nama = $seksi->nama;
        $id = $seksi->id;
        $seksi->delete();

        $actor = $request->user();
        ActivityLog::record([
            'user_id' => $actor->id,
            'user_name' => $actor->nama_lengkap ?? $actor->name,
            'user_role' => $actor->role,
            'user_seksi' => $actor->seksi,
            'action' => 'HAPUS_SEKSI',
            'module' => 'Master Seksi',
            'description' => "Menghapus seksi: {$nama}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'properties' => ['deleted_seksi_id' => $id],
        ]);

        return back()->with('success', "Seksi '{$nama}' berhasil dihapus (soft delete).");
    }
}
