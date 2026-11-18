<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_temporary')->default(false)->after('role')->index();
            $table->timestamp('temporary_expires_at')->nullable()->after('is_temporary')->index();
        });

        // Update role enum to include 'Temporary'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('Superadmin', 'Kasudin', 'Kasi', 'PJLP', 'Temporary') NOT NULL DEFAULT 'PJLP'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('Superadmin', 'Kasudin', 'Kasi', 'PJLP') NOT NULL DEFAULT 'PJLP'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_temporary']);
            $table->dropIndex(['temporary_expires_at']);
            $table->dropColumn(['is_temporary', 'temporary_expires_at']);
        });
    }
};

