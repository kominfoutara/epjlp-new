<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'user_seksi',
        'action',
        'module',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('user_name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('action', 'like', "%{$term}%")
              ->orWhere('user_seksi', 'like', "%{$term}%")
              ->orWhere('ip_address', 'like', "%{$term}%");
        });
    }

    public function scopeModule($query, ?string $module)
    {
        if (!empty($module) && $module !== 'all') {
            return $query->where('module', $module);
        }
        return $query;
    }

    public function scopeRole($query, ?string $role)
    {
        if (!empty($role) && $role !== 'all') {
            return $query->where('user_role', $role);
        }
        return $query;
    }

    public function scopeActionType($query, ?string $action)
    {
        if (!empty($action) && $action !== 'all') {
            return $query->where('action', $action);
        }
        return $query;
    }

    public function scopePeriod($query, ?string $period)
    {
        if (empty($period) || $period === 'all') {
            return $query;
        }

        return match ($period) {
            'today' => $query->whereDate('created_at', Carbon::today()),
            'yesterday' => $query->whereDate('created_at', Carbon::yesterday()),
            '7_days' => $query->where('created_at', '>=', Carbon::now()->subDays(7)),
            '30_days' => $query->where('created_at', '>=', Carbon::now()->subDays(30)),
            'this_month' => $query->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year),
            default => $query,
        };
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }
        return $query;
    }

    public static function record(
        string|array $action,
        ?string $module = null,
        ?string $description = null,
        ?array $properties = null,
        ?User $user = null
    ): self {
        if (is_array($action)) {
            $data = $action;
            $actor = ($data['user'] ?? null) ?: (($data['user_id'] ?? null) ? User::find($data['user_id']) : Auth::user());
            return self::create([
                'user_id' => $data['user_id'] ?? $actor?->id,
                'user_name' => $data['user_name'] ?? ($actor?->nama_lengkap ?: ($actor?->name ?: 'Sistem')),
                'user_role' => $data['user_role'] ?? ($actor?->role ?: 'System'),
                'user_seksi' => $data['user_seksi'] ?? $actor?->seksi,
                'action' => $data['action'] ?? 'UNKNOWN',
                'module' => $data['module'] ?? 'Umum',
                'description' => $data['description'] ?? '',
                'properties' => $data['properties'] ?? null,
                'ip_address' => $data['ip_address'] ?? request()?->ip(),
                'user_agent' => $data['user_agent'] ?? request()?->userAgent(),
            ]);
        }

        $actor = $user ?: Auth::user();

        return self::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->nama_lengkap ?: ($actor?->name ?: 'Sistem'),
            'user_role' => $actor?->role ?: 'System',
            'user_seksi' => $actor?->seksi,
            'action' => $action,
            'module' => $module ?? 'Umum',
            'description' => $description ?? '',
            'properties' => $properties,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
