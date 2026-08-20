<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'hostel' already holds free text, so off-campus students reuse
            // it for their delivery address — this flag just says how to
            // read it (a picked hostel name vs. a typed-in address).
            $table->enum('location_type', ['hostel', 'off_campus'])->default('hostel')->after('hostel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('location_type');
        });
    }
};
