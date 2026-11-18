<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ActivityDownloadController extends Controller
{
    private function attachmentLinks(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($file) => basename(trim($file)))
            ->filter(fn ($file) => $file !== '' && $file !== '.' && $file !== '..')
            ->map(fn ($file) => route('activity.lampiran.view', ['filename' => $file]))
            ->values()->all();
    }

    private function filteredData(Request $request): array
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['Kasi', 'Kasudin', 'Superadmin'], true), 403);
        $input = $request->validate([
            'mode' => ['nullable', 'in:range,month'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'start_date' => ['required_if:mode,range', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:mode,range', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'seksi' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $month = $input['bulan'] ?? CarbonImmutable::now('Asia/Jakarta')->format('Y-m');
        $mode = $input['mode'] ?? 'month';
        $monthStart = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
        $start = $mode === 'range' ? $input['start_date'] : $monthStart->toDateString();
        $end = $mode === 'range' ? $input['end_date'] : $monthStart->endOfMonth()->toDateString();
        $key = fn ($name) => mb_strtolower(trim((string) User::normalizeSeksi(trim((string) $name))));

        // Include inactive PJLP so historical records remain exportable.
        $users = User::pjlp()->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'name', 'seksi']);
        if ($actor->isKasi()) {
            $users = $users->filter(fn ($u) => $key($actor->seksi) !== '' && $key($u->seksi) === $key($actor->seksi))->values();
        }
        $sections = $users->pluck('seksi')->map(fn ($s) => User::normalizeSeksi(trim((string) $s)))
            ->filter(fn ($s) => trim((string) $s) !== '')->unique(fn ($s) => $key($s))->sort()->values();
        $section = $actor->isKasi() ? (User::normalizeSeksi(trim((string) $actor->seksi)) ?: '') : ($input['seksi'] ?? '');
        if ($actor->isKasi() && !empty($input['seksi'])) {
            abort_unless($key($input['seksi']) === $key($actor->seksi), 403);
        }
        if ($section !== '') {
            $users = $users->filter(fn ($u) => $key($u->seksi) === $key($section))->values();
        }
        $userId = $input['user_id'] ?? null;
        if ($userId !== null) {
            abort_unless($users->contains('id', (int) $userId), 403, 'PJLP di luar cakupan filter atau akses Anda.');
        }
        $filters = [
            'mode' => $mode, 'bulan' => $month, 'start_date' => $start, 'end_date' => $end,
            'seksi' => $section, 'user_id' => $userId, 'search' => $input['search'] ?? '',
            'per_page' => (int) ($input['per_page'] ?? 10),
        ];
        $query = Activity::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('tanggal', [$start, $end]);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        if ($filters['search'] !== '') {
            $search = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('no', 'like', $search)->orWhere('uraian', 'like', $search)
                ->orWhere('kategori', 'like', $search)->orWhere('lokasi', 'like', $search));
        }

        return [$query, $filters, $users, $sections];
    }

    public function index(Request $request)
    {
        [$query, $filters, $users, $sections] = $this->filteredData($request);
        $summary = [
            'total' => (clone $query)->count(),
            'pjlp' => (clone $query)->distinct()->count('user_id'),
            'categories' => (clone $query)->whereNotNull('kategori')->where('kategori', '!=', '')->distinct()->count('kategori'),
        ];
        $activities = $query->with('user:id,nama_lengkap,name,seksi')->orderBy('tanggal')->orderBy('jam_mulai')->orderBy('id')
            ->paginate($filters['per_page'], ['id', 'user_id', 'tanggal', 'no', 'kategori', 'uraian', 'lokasi', 'nama_unit', 'jam_mulai', 'jam_selesai', 'status_validasi', 'lampiran'])
            ->withQueryString();
        $activities->through(function ($activity) {
            $activity->setAttribute('attachment_links', $this->attachmentLinks($activity->lampiran));
            unset($activity->lampiran);
            return $activity;
        });

        return Inertia::render('Activity/Download', compact('activities', 'filters', 'summary', 'users', 'sections'));
    }

    public function excel(Request $request)
    {
        [$query, $filters] = $this->filteredData($request);
        abort_if((clone $query)->count() > 1048575, 422, 'Data melampaui batas baris Excel. Pilih rentang lebih kecil.');
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Aktivitas PJLP');
        $headers = ['No', 'Tanggal', 'No Aktivitas', 'Nama PJLP', 'Seksi', 'Kategori', 'Uraian Aktivitas', 'Lokasi', 'Nama Unit', 'Jam Mulai', 'Jam Selesai', 'Status Validasi', 'Lampiran 1', 'Lampiran 2', 'Lampiran 3', 'Lampiran 4', 'Lampiran 5', 'Lampiran 6'];
        $sheet->fromArray($headers, null, 'A1');
        $row = 2;
        foreach ($query->with('user:id,nama_lengkap,name,seksi')->orderBy('tanggal')->orderBy('jam_mulai')->orderBy('id')->lazy(500) as $activity) {
            $values = [$row - 1, $activity->tanggal->format('Y-m-d'), $activity->no,
                $activity->user->nama_lengkap ?: $activity->user->name, $activity->user->seksi,
                $activity->kategori, $activity->uraian, $activity->lokasi, $activity->nama_unit,
                $activity->jam_mulai, $activity->jam_selesai, $activity->status_validasi];
            foreach ($values as $index => $value) {
                // Preserve leading zeros and prevent user text becoming Excel formulas.
                $sheet->setCellValueExplicit([$index + 1, $row], (string) ($value ?? ''), DataType::TYPE_STRING);
            }
            foreach (array_slice($this->attachmentLinks($activity->lampiran), 0, 6) as $index => $url) {
                $cell = [$index + 13, $row];
                $sheet->setCellValueExplicit($cell, $url, DataType::TYPE_STRING);
                $sheet->getCell($cell)->getHyperlink()->setUrl($url);
            }
            $row++;
        }
        $sheet->freezePane('D2');
        $sheet->setAutoFilter('A1:R' . max(1, $row - 1));
        $sheet->getStyle('A1:R1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:R1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF166534');
        foreach (['A' => 7, 'B' => 14, 'C' => 20, 'D' => 28, 'E' => 24, 'F' => 26, 'G' => 70, 'H' => 30, 'I' => 30, 'J' => 14, 'K' => 14, 'L' => 20, 'M' => 62, 'N' => 62, 'O' => 62, 'P' => 62, 'Q' => 62, 'R' => 62] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle('A1:R' . max(1, $row - 1))->getAlignment()->setVertical('top')->setWrapText(true);
        $filename = 'aktivitas-pjlp-' . $filters['start_date'] . '_' . $filters['end_date'] . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
