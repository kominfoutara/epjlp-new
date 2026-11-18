<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DinasLuar extends Model
{
    use HasFactory;

    protected $table = 'dinas_luar';

    protected $fillable = [
        'user_id',
        'tipe',
        'keterangan',
        'status_verifikasi',
        'verified_by',
        'verified_at',
        'latitude',
        'longitude',
        'alamat',
        'foto',
        'waktu',
    ];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'verified_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status_verifikasi', 'Menunggu');
    }

    public function scopeDisetujui($query)
    {
        return $query->where('status_verifikasi', 'Disetujui');
    }
}
