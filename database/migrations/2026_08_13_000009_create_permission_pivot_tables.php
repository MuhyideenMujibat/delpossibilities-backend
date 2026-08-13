<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default permission bundle for a user type (used to prefill
        // checkboxes when assigning that type to a new employee).
        Schema::create('permission_user_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_type_id', 'permission_id']);
        });

        // The actual, authoritative grants for a specific person — copied
        // from their user type at creation time, then editable per-person.
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_user_type');
    }
};
