<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'aktivitas';

    protected $fillable = [
        'user_id',
        'no',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'kategori',
        'lokasi',
        'nama_unit',
        'uraian',
        'lampiran',
        'status_validasi',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status_validasi', 'Menunggu');
    }

    public function scopeDisetujui($query)
    {
        return $query->where('status_validasi', 'Disetujui');
    }
}
