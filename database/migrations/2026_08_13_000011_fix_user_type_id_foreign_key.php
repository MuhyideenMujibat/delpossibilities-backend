<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The original migration called ->nullOnDelete() directly on the
    // ForeignIdColumnDefinition instead of after ->constrained() — that
    // method doesn't exist there, and Laravel's Fluent::__call() magic
    // silently swallowed it instead of erroring, so no constraint was ever
    // actually created. This adds the real one.
    public function up(): void
    {
        // Without the constraint, nothing stopped user_type_id from
        // pointing at an already-deleted user_type — clear those out
        // before MySQL validates the new FK against existing data.
        \DB::statement('UPDATE users SET user_type_id = NULL WHERE user_type_id IS NOT NULL AND user_type_id NOT IN (SELECT id FROM user_types)');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('user_type_id')->references('id')->on('user_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_type_id']);
        });
    }
};
