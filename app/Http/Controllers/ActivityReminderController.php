<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityReminderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['Superadmin', 'Kasi', 'Kasudin', 'PJLP'], true), 403);

        $validated = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $today = CarbonImmutable::now('Asia/Jakarta');
        $month = $validated['bulan'] ?? $today->format('Y-m');
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
        $end = $start->endOfMonth();
        $selectedDate = $validated['tanggal'] ?? ($month === $today->format('Y-m')
            ? $today->toDateString() : $start->toDateString());
        abort_unless(substr($selectedDate, 0, 7) === $month, 422, 'Tanggal harus berada dalam bulan yang dipilih.');

        $query = Activity::query()->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->whereHas('user', fn ($q) => $q->where('role', 'PJLP'));

        if ($user->isPjlp()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isKasi()) {
            // Match canonical section names exactly; a missing section grants no access.
            $ids = User::pjlp()->get(['id', 'seksi'])
                ->filter(fn ($pjlp) => trim((string) User::normalizeSeksi($user->seksi)) !== ''
                    && User::isSameSeksi($user->seksi, $pjlp->seksi))
                ->pluck('id');
            $query->whereIn('user_id', $ids);
        }

        $nodes = (clone $query)->select('tanggal', 'user_id')
            ->selectRaw('COUNT(*) as activity_count')
            ->with('user:id,nama_lengkap,name,seksi')
            ->groupBy('tanggal', 'user_id')->orderBy('tanggal')->orderBy('user_id')->get()
            ->map(fn ($node) => [
                'date' => $node->tanggal->format('Y-m-d'),
                'user_id' => $node->user_id,
                'name' => $node->user->nama_lengkap ?: $node->user->name,
                'seksi' => $node->user->seksi,
                'count' => (int) $node->activity_count,
            ]);

        $activities = (clone $query)->whereDate('tanggal', $selectedDate)
            ->with('user:id,nama_lengkap,name,seksi')
            ->select(['id', 'user_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'kategori', 'uraian', 'status_validasi'])
            ->orderBy('jam_mulai')->orderBy('id')->paginate(20)->withQueryString();

        return Inertia::render('Activity/ReminderCalendar', [
            'month' => $month,
            'today' => $today->toDateString(),
            'selectedDate' => $selectedDate,
            'nodes' => $nodes,
            'activities' => $activities,
        ]);
    }
}
