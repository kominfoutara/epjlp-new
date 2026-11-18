<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'role',
        'title',
        'message',
        'type',
        'url',
        'is_read',
        'read_at',
    ];

    protected $appends = ['link'];

    public function getLinkAttribute(): ?string
    {
        return $this->url;
    }

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere(function ($sub) use ($user) {
                  $sub->whereNull('user_id')
                      ->where(function ($roleQuery) use ($user) {
                          $roleQuery->where('role', $user->role)
                                    ->orWhere('role', 'all');
                      });
              });
        });
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public static function send(
        string|array $title,
        ?string $message = null,
        string $type = 'info',
        ?int $userId = null,
        ?string $role = null,
        ?string $url = null
    ): self {
        if (is_array($title)) {
            $data = $title;
            return self::create([
                'user_id' => $data['user_id'] ?? ($data['target_user_id'] ?? null),
                'role' => $data['role'] ?? ($data['target_role'] ?? null),
                'title' => $data['title'] ?? 'Pemberitahuan Sistem',
                'message' => $data['message'] ?? '',
                'type' => $data['type'] ?? 'info',
                'url' => $data['url'] ?? ($data['link'] ?? null),
                'is_read' => false,
            ]);
        }

        return self::create([
            'user_id' => $userId,
            'role' => $role,
            'title' => $title,
            'message' => $message ?? '',
            'type' => $type,
            'url' => $url,
            'is_read' => false,
        ]);
    }
}
