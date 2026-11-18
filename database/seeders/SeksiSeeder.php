<?php

namespace Database\Seeders;

use App\Models\Seksi;
use Illuminate\Database\Seeder;

class SeksiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seksis = [
            [
                'nama' => 'Astik',
                'kode' => 'ASTIK',
                'deskripsi' => 'Seksi Aplikasi Siber dan Transformasi Informasi Komunikasi',
                'is_active' => true,
            ],
            [
                'nama' => 'KIP',
                'kode' => 'KIP',
                'deskripsi' => 'Seksi Komunikasi dan Informasi Publik',
                'is_active' => true,
            ],
            [
                'nama' => 'ID',
                'kode' => 'ID',
                'deskripsi' => 'Seksi Infrastruktur Digital dan Jaringan',
                'is_active' => true,
            ],
            [
                'nama' => 'Tata Usaha',
                'kode' => 'TU',
                'deskripsi' => 'Subbagian Tata Usaha dan Kepegawaian',
                'is_active' => true,
            ],
            [
                'nama' => 'Pimpinan',
                'kode' => 'PIMPINAN',
                'deskripsi' => 'Pimpinan Suku Dinas Kominfotik',
                'is_active' => true,
            ],
        ];

        foreach ($seksis as $item) {
            Seksi::updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
