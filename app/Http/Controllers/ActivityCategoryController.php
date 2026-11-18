<?php

namespace App\Http\Controllers;

use App\Models\ActivityCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $seksi = $request->input('seksi', ($user->isKasi() ? $user->seksi : null));

        $query = ActivityCategory::query();

        if ($seksi) {
            $query->where('seksi', $seksi);
        }

        $categories = $query->orderBy('seksi')->orderBy('nama')->paginate(20)->withQueryString();
        $seksiList = User::whereNotNull('seksi')->distinct()->pluck('seksi');

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'seksiList' => $seksiList,
            'filters' => [
                'seksi' => $seksi,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang mengelola kategori aktivitas.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'seksi' => 'required|string|max:100',
        ]);

        if ($user->isKasi() && $user->seksi && $validated['seksi'] !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat menambahkan kategori untuk seksi Anda.');
        }

        ActivityCategory::create($validated);

        return back()->with('success', 'Kategori aktivitas berhasil ditambahkan.');
    }

    public function update(Request $request, ActivityCategory $category): RedirectResponse
    {
        $user = $request->user();
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang mengelola kategori aktivitas.');
        }

        if ($user->isKasi() && $user->seksi && $category->seksi !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengubah kategori untuk seksi Anda.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'seksi' => 'required|string|max:100',
        ]);

        if ($user->isKasi() && $user->seksi && $validated['seksi'] !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda tidak dapat memindahkan kategori ke seksi lain.');
        }

        $category->update($validated);

        return back()->with('success', 'Kategori aktivitas berhasil diperbarui.');
    }

    public function destroy(Request $request, ActivityCategory $category): RedirectResponse
    {
        $user = $request->user();
        if ($user->isPjlp()) {
            abort(403, 'Akses ditolak: PJLP tidak berwenang menghapus kategori aktivitas.');
        }

        if ($user->isKasi() && $user->seksi && $category->seksi !== $user->seksi) {
            abort(403, 'Akses ditolak: Anda hanya dapat menghapus kategori untuk seksi Anda.');
        }

        $category->delete();

        return back()->with('success', 'Kategori aktivitas berhasil dihapus.');
    }
}
