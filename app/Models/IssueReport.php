<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueReport extends Model
{
    use HasFactory;

    protected $table = 'issue_reports';

    protected $fillable = [
        'ticket_number',
        'user_id',
        'judul',
        'kategori',
        'prioritas',
        'deskripsi',
        'lampiran',
        'status',
        'tanggapan_it',
        'handled_by',
        'handled_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('ticket_number', 'like', "%{$term}%")
                  ->orWhere('judul', 'like', "%{$term}%")
                  ->orWhere('deskripsi', 'like', "%{$term}%")
                  ->orWhereHas('user', function ($uq) use ($term) {
                      $uq->where('nama_lengkap', 'like', "%{$term}%")
                         ->orWhere('name', 'like', "%{$term}%");
                  });
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['kategori']) && $filters['kategori'] !== 'all') {
            $query->where('kategori', $filters['kategori']);
        }

        if (!empty($filters['prioritas']) && $filters['prioritas'] !== 'all') {
            $query->where('prioritas', $filters['prioritas']);
        }

        return $query;
    }

    public static function generateTicketNumber(): string
    {
        $prefix = 'KDL-' . Carbon::now()->format('Ymd') . '-';
        $latest = self::where('ticket_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($latest && preg_match('/-(\d{4})$/', $latest->ticket_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
