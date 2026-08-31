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
        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();
            // Nullable — the widget is also open to logged-out landing
            // visitors, who submit anonymously.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['suggestion', 'review'])->default('suggestion');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_submissions');
    }
};
