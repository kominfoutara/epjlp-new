<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\IssueReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IssueReportController extends Controller
{
    /**
     * Display a listing of the issue reports.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->isSuperadmin();

        // Base query for stats
        $baseStatsQuery = IssueReport::query()->forUser($user);

        $stats = [
            'total' => (clone $baseStatsQuery)->count(),
            'menunggu' => (clone $baseStatsQuery)->where('status', 'Menunggu')->count(),
            'diproses' => (clone $baseStatsQuery)->where('status', 'Diproses')->count(),
            'selesai' => (clone $baseStatsQuery)->where('status', 'Selesai')->count(),
            'darurat' => (clone $baseStatsQuery)->where('prioritas', 'Darurat')->whereIn('status', ['Menunggu', 'Diproses'])->count(),
        ];

        // Paginated records
        $reports = IssueReport::with([
            'user:id,name,nama_lengkap,role,seksi,posisi_pekerjaan,foto,no_wa',
            'handler:id,name,nama_lengkap,role,seksi'
        ])
            ->forUser($user)
            ->filter($request->only(['search', 'status', 'kategori', 'prioritas']))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = [
            'Aplikasi & Fitur',
            'Presensi & Mesin Fingerprint',
            'Akun & Autentikasi',
            'Kamera & Foto Lapangan',
            'Jaringan & Akses Portal',
            'Lainnya',
        ];

        $priorities = ['Rendah', 'Sedang', 'Tinggi', 'Darurat'];

        return Inertia::render('IssueReports/Index', [
            'reports' => $reports,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'kategori', 'prioritas']),
            'categories' => $categories,
            'priorities' => $priorities,
            'isSuperadmin' => $isSuperadmin,
        ]);
    }

    /**
     * Store a newly created issue report in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'string', 'max:64'],
            'prioritas' => ['required', 'in:Rendah,Sedang,Tinggi,Darurat'],
            'deskripsi' => ['required', 'string', 'min:10'],
            'lampiran' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:5120'],
        ], [
            'judul.required' => 'Judul kendala wajib diisi.',
            'judul.max' => 'Judul kendala maksimal 200 karakter.',
            'kategori.required' => 'Pilih kategori kendala.',
            'prioritas.required' => 'Pilih tingkat prioritas kendala.',
            'deskripsi.required' => 'Jelaskan deskripsi kendala yang dialami secara rinci.',
            'deskripsi.min' => 'Deskripsi kendala minimal 10 karakter.',
            'lampiran.mimes' => 'Format file bukti kendala harus berupa JPG, PNG, WEBP, atau PDF.',
            'lampiran.max' => 'Ukuran berkas lampiran maksimal 5MB.',
        ]);

        $lampiranPath = null;
        if ($request->hasFile('lampiran')) {
            $lampiranPath = $request->file('lampiran')->store('issue_attachments', 'public');
        }

        $ticketNumber = IssueReport::generateTicketNumber();

        $report = IssueReport::create([
            'ticket_number' => $ticketNumber,
            'user_id' => $user->id,
            'judul' => $validated['judul'],
            'kategori' => $validated['kategori'],
            'prioritas' => $validated['prioritas'],
            'deskripsi' => $validated['deskripsi'],
            'lampiran' => $lampiranPath,
            'status' => 'Menunggu',
        ]);

        // Send notification to Superadmin (Tim IT)
        AppNotification::send([
            'role' => 'Superadmin',
            'title' => "Laporan Kendala Baru: {$report->ticket_number}",
            'message' => "{$user->nama_lengkap} ({$user->role}) melaporkan kendala: {$report->judul}",
            'type' => $report->prioritas === 'Darurat' ? 'danger' : ($report->prioritas === 'Tinggi' ? 'warning' : 'info'),
            'url' => route('issue-reports.index', ['search' => $report->ticket_number]),
        ]);

        // Audit log
        ActivityLog::record(
            action: 'LAPOR_KENDALA',
            module: 'Laporan Kendala',
            description: "Membuat tiket aduan kendala {$report->ticket_number}: {$report->judul}",
            properties: [
                'ticket_number' => $report->ticket_number,
                'kategori' => $report->kategori,
                'prioritas' => $report->prioritas,
            ]
        );

        return redirect()->route('issue-reports.index')
            ->with('success', "Laporan kendala berhasil dikirimkan ke Tim IT Superadmin. Nomor Tiket Anda: {$ticketNumber}");
    }

    /**
     * Update the specified issue report (tindak lanjut oleh Superadmin IT).
     */
    public function update(Request $request, IssueReport $issueReport): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isSuperadmin()) {
            abort(403, 'Hanya Tim IT Superadmin yang berwenang menindaklanjuti laporan kendala.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:Menunggu,Diproses,Selesai,Ditolak'],
            'tanggapan_it' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required' => 'Pilih status penanganan kendala.',
            'status.in' => 'Status kendala tidak valid.',
            'tanggapan_it.max' => 'Catatan tanggapan IT maksimal 2000 karakter.',
        ]);

        $updateData = [
            'status' => $validated['status'],
            'handled_by' => $user->id,
            'tanggapan_it' => array_key_exists('tanggapan_it', $validated) ? $validated['tanggapan_it'] : $issueReport->tanggapan_it,
        ];

        if ($validated['status'] === 'Diproses' && !$issueReport->handled_at) {
            $updateData['handled_at'] = now();
        }

        if ($validated['status'] === 'Selesai') {
            $updateData['resolved_at'] = now();
            if (!$issueReport->handled_at) {
                $updateData['handled_at'] = now();
            }
        } elseif ($validated['status'] !== 'Selesai') {
            $updateData['resolved_at'] = null;
        }

        $issueReport->update($updateData);

        // Notify reporter
        $statusLabels = [
            'Diproses' => 'sedang diproses oleh Tim IT',
            'Selesai' => 'telah diselesaikan oleh Tim IT',
            'Ditolak' => 'ditolak oleh Tim IT',
            'Menunggu' => 'dikembalikan ke status menunggu',
        ];

        $freshReport = $issueReport->fresh();
        $noteSnippet = $freshReport->tanggapan_it ? " Catatan Tim IT: {$freshReport->tanggapan_it}" : '';

        AppNotification::send([
            'user_id' => $issueReport->user_id,
            'title' => "Update Tiket {$issueReport->ticket_number}: {$issueReport->status}",
            'message' => "Laporan kendala Anda ({$issueReport->judul}) " . ($statusLabels[$validated['status']] ?? $validated['status']) . '.' . $noteSnippet,
            'type' => $validated['status'] === 'Selesai' ? 'success' : ($validated['status'] === 'Ditolak' ? 'danger' : 'info'),
            'url' => route('issue-reports.index', ['search' => $issueReport->ticket_number]),
        ]);

        // Audit log
        ActivityLog::record(
            action: 'TINDAK_LANJUT_KENDALA',
            module: 'Laporan Kendala',
            description: "Memperbarui tindak lanjut tiket {$issueReport->ticket_number} menjadi status {$issueReport->status}",
            properties: [
                'ticket_number' => $issueReport->ticket_number,
                'status' => $issueReport->status,
                'tanggapan_it' => $validated['tanggapan_it'] ?? null,
            ]
        );

        return redirect()->route('issue-reports.index')
            ->with('success', "Tindak lanjut kendala {$issueReport->ticket_number} berhasil disimpan.");
    }

    /**
     * Remove the specified issue report from storage.
     */
    public function destroy(Request $request, IssueReport $issueReport): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isSuperadmin()) {
            if ($issueReport->user_id !== $user->id) {
                abort(403, 'Anda tidak memiliki hak untuk menghapus tiket kendala ini.');
            }
            if ($issueReport->status !== 'Menunggu') {
                return redirect()->route('issue-reports.index')
                    ->with('error', 'Tiket yang sedang ditangani atau sudah selesai tidak dapat dibatalkan.');
            }
        }

        $ticketNumber = $issueReport->ticket_number;

        // Delete attachment if exists
        if ($issueReport->lampiran && Storage::disk('public')->exists($issueReport->lampiran)) {
            Storage::disk('public')->delete($issueReport->lampiran);
        }

        $issueReport->delete();

        // Audit log
        ActivityLog::record(
            action: 'HAPUS_KENDALA',
            module: 'Laporan Kendala',
            description: "Menghapus/membatalkan tiket kendala {$ticketNumber}",
            properties: [
                'ticket_number' => $ticketNumber,
            ]
        );

        return redirect()->route('issue-reports.index')
            ->with('success', "Tiket laporan kendala {$ticketNumber} berhasil dihapus.");
    }
}
