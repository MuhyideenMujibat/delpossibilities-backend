<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registration details are staged here until the OTP is verified.
        // Nothing lands in `users` until then, so an unverified signup
        // leaves no account behind.
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('hostel');
            $table->string('phone');
            $table->string('otp_code');
            $table->timestamp('otp_expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
