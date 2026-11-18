<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyActivityReport extends Model
{
    use HasFactory;

    protected $table = 'laporan_bulanan_aktivitas';

    protected $fillable = [
        'user_id',
        'periode',
        'analisa_kesimpulan',
        'rekomendasi',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
