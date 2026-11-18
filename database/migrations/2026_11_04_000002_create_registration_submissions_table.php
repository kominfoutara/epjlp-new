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
        Schema::create('registration_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('temporary_user_id')->nullable();
            $table->string('registration_code', 50)->unique();
            $table->string('nama_lengkap');
            $table->string('username');
            $table->string('email')->nullable();
            $table->string('nik', 50)->nullable();
            $table->string('no_wa', 50)->nullable();
            $table->string('seksi', 100)->nullable();
            $table->string('posisi_pekerjaan')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('status_pernikahan', 50)->nullable();
            $table->string('jenis_kelamin', 50)->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->text('alamat_domisili')->nullable();
            $table->string('password');
            $table->string('pin', 50)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('catatan_admin')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('registered_user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_submissions');
    }
};

