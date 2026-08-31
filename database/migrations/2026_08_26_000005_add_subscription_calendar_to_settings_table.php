<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->date('session_starts_at')->nullable();
            $table->date('session_ends_at')->nullable();
            $table->date('semester_starts_at')->nullable();
            $table->date('semester_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['session_starts_at', 'session_ends_at', 'semester_starts_at', 'semester_ends_at']);
        });
    }
};
