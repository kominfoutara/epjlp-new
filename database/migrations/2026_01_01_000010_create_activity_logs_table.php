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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name');
            $table->string('user_role');
            $table->string('user_seksi')->nullable();
            $table->string('action'); // e.g., 'LOGIN', 'TAMBAH_AKTIVITAS', 'VALIDASI_AKTIVITAS', etc.
            $table->string('module'); // e.g., 'Aktivitas', 'Dinas Luar', 'Absensi', 'Manajemen User', etc.
            $table->text('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('action');
            $table->index('module');
            $table->index('user_role');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
