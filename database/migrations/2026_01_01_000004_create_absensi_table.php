<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 50)->nullable()->index();
            $table->date('tanggal')->index();
            $table->time('absen_masuk')->nullable();
            $table->time('absen_pulang')->nullable();
            $table->time('terlambat')->nullable();
            $table->time('pulang_cepat')->nullable();
            $table->time('waktu_kompensasi')->nullable();
            $table->enum('keterangan', [
                'WFO',
                'IZIN AWAL',
                'IZIN AKHIR',
                'IZIN PENUH',
                'SAKIT',
                'CUTI',
                'ALPA',
                'DL AWAL',
                'DL AKHIR',
                'DL PENUH',
                'WFH',
                'DL DITOLAK'
            ])->nullable();
            $table->timestamps();

            $table->unique(['pin', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
