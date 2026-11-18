<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityCategory extends Model
{
    use HasFactory;

    protected $table = 'kategori_aktivitas';

    protected $fillable = [
        'nama',
        'seksi',
    ];

    public function scopeSeksi($query, string $seksi)
    {
        return $query->where('seksi', $seksi);
    }
}
