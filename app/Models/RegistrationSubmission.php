<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RegistrationSubmission extends Model
{
    use HasFactory;

    protected $table = 'registration_submissions';

    protected $fillable = [
        'temporary_user_id',
        'registration_code',
        'nama_lengkap',
        'username',
        'email',
        'nik',
        'no_wa',
        'seksi',
        'posisi_pekerjaan',
        'jabatan',
        'status_pernikahan',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'pendidikan_terakhir',
        'alamat_domisili',
        'password',
        'pin',
        'status',
        'catatan_admin',
        'approved_by',
        'approved_at',
        'registered_user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Generate unique registration code: REG-YYYYMMDD-XXXXX
     */
    public static function generateCode(): string
    {
        do {
            $code = 'REG-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        } while (static::where('registration_code', $code)->exists());

        return $code;
    }

    // Relasi
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}

