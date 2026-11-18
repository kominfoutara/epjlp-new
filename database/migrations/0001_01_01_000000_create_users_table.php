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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('nama_lengkap')->nullable();
            $table->string('username')->unique();
            $table->string('nik', 45)->nullable();
            $table->string('no_wa', 45)->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('jenis_kelamin', 45)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('mulai_bekerja')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('nama_instansi')->nullable();
            $table->string('status_pernikahan', 45)->nullable();
            $table->string('seksi', 100)->nullable();
            $table->string('posisi_pekerjaan')->nullable();
            $table->time('jam_masuk')->default('07:30:00');
            $table->time('jam_pulang')->default('16:00:00');
            $table->string('foto')->nullable();
            $table->enum('role', ['Superadmin', 'Kasudin', 'Kasi', 'PJLP', 'Temporary'])->default('PJLP');
            $table->enum('status_user', ['Aktif', 'Menunggu', 'Suspend'])->default('Aktif');
            $table->string('no_registrasi', 50)->nullable();
            $table->string('pin', 50)->nullable()->index();
            $table->string('jabatan')->nullable();
            $table->string('nrk', 50)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
