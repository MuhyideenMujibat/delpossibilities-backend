<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('student', 'admin', 'super_admin') DEFAULT 'student'");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_type_id')->nullable()->after('role')->nullOnDelete();
        });

        // The one existing admin account becomes the super admin — the only
        // role that can create other admins and manage permissions.
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_type_id');
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('student', 'admin') DEFAULT 'student'");
    }
};
