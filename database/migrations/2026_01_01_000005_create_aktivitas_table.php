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
        Schema::create('aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('no', 50)->nullable();
            $table->date('tanggal')->index();
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('kategori');
            $table->string('lokasi');
            $table->string('nama_unit')->nullable();
            $table->text('uraian');
            $table->string('lampiran')->nullable();
            $table->enum('status_validasi', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aktivitas');
    }
};
