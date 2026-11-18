<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nama_lengkap',
        'username',
        'nik',
        'no_wa',
        'email',
        'password',
        'jenis_kelamin',
        'tanggal_lahir',
        'tempat_lahir',
        'mulai_bekerja',
        'pendidikan_terakhir',
        'nama_instansi',
        'status_pernikahan',
        'seksi',
        'posisi_pekerjaan',
        'jam_masuk',
        'jam_pulang',
        'foto',
        'role',
        'status_user',
        'no_registrasi',
        'pin',
        'jabatan',
        'nrk',
        'is_temporary',
        'temporary_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'tanggal_lahir' => 'date',
            'mulai_bekerja' => 'date',
            'is_temporary' => 'boolean',
            'temporary_expires_at' => 'datetime',
        ];
    }

    // Role Checks
    public function isSuperadmin(): bool
    {
        return $this->role === 'Superadmin';
    }

    public function isKasudin(): bool
    {
        return $this->role === 'Kasudin';
    }

    public function isKasi(): bool
    {
        return $this->role === 'Kasi';
    }

    public function isPjlp(): bool
    {
        return $this->role === 'PJLP';
    }

    public function isTemporary(): bool
    {
        return (bool) ($this->is_temporary || $this->role === 'Temporary');
    }

    public function isTemporaryExpired(): bool
    {
        if (! $this->isTemporary()) {
            return false;
        }

        return $this->temporary_expires_at && now()->greaterThanOrEqualTo($this->temporary_expires_at);
    }

    // Scopes
    public function scopePjlp($query)
    {
        return $query->where('role', 'PJLP');
    }

    public function scopeActive($query)
    {
        return $query->where('status_user', 'Aktif');
    }

    /**
     * Scope query untuk mencocokkan seksi secara fleksibel (baik dengan prefix 'Seksi ' maupun format ringkas).
     */
    public function scopeSeksi($query, ?string $seksi)
    {
        if (!$seksi) {
            return $query;
        }

        $raw = trim($seksi);
        $clean = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $raw));
        $normalized = self::normalizeSeksi($raw);

        return $query->where(function ($q) use ($raw, $clean, $normalized) {
            $q->where('seksi', $raw)
              ->orWhere('seksi', $clean)
              ->orWhere('seksi', 'Seksi ' . $clean)
              ->orWhere('seksi', 'Seksi ' . strtoupper($clean))
              ->orWhere('seksi', $normalized)
              ->orWhere('seksi', 'like', '%' . $clean . '%');
        });
    }

    /**
     * Normalisasi nama seksi menjadi format standar ringkas.
     */
    public static function normalizeSeksi(?string $seksi): ?string
    {
        if (!$seksi) {
            return null;
        }

        $clean = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $seksi));

        return match (strtoupper($clean)) {
            'ASTIK' => 'Astik',
            'KIP' => 'KIP',
            'ID' => 'ID',
            'TU', 'TATA USAHA', 'SUBBAGIAN TATA USAHA' => 'Tata Usaha',
            'PIMPINAN' => 'Pimpinan',
            default => $clean,
        };
    }

    /**
     * Periksa apakah dua nama seksi merujuk ke seksi yang sama.
     */
    public static function isSameSeksi(?string $seksi1, ?string $seksi2): bool
    {
        if (!$seksi1 || !$seksi2) {
            return false;
        }

        $norm1 = self::normalizeSeksi($seksi1);
        $norm2 = self::normalizeSeksi($seksi2);

        if (strcasecmp($norm1, $norm2) === 0) {
            return true;
        }

        $clean1 = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $seksi1));
        $clean2 = trim(preg_replace('/^(seksi|subbagian)\s+/i', '', $seksi2));

        return strcasecmp($clean1, $clean2) === 0;
    }

    /**
     * Periksa apakah pengguna saat ini berada dalam seksi yang sama dengan seksi target.
     */
    public function isInSeksi(?string $targetSeksi): bool
    {
        return self::isSameSeksi($this->seksi, $targetSeksi);
    }

    // Relasi
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'pin', 'pin');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    public function dinasLuar(): HasMany
    {
        return $this->hasMany(DinasLuar::class, 'user_id');
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyActivityReport::class, 'user_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function issueReports(): HasMany
    {
        return $this->hasMany(IssueReport::class, 'user_id');
    }
}
