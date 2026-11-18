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
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('judul');
            $table->string('kategori', 64);
            $table->enum('prioritas', ['Rendah', 'Sedang', 'Tinggi', 'Darurat'])->default('Sedang');
            $table->text('deskripsi');
            $table->string('lampiran')->nullable();
            $table->enum('status', ['Menunggu', 'Diproses', 'Selesai', 'Ditolak'])->default('Menunggu');
            $table->text('tanggapan_it')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'kategori']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_reports');
    }
};
