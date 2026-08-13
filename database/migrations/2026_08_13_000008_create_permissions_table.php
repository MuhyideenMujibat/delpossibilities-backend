<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Permissions are a fixed set tied 1:1 to real access-control checks
    // elsewhere in the app (routes/middleware), not a freeform list — so
    // they're seeded here rather than left for someone to invent arbitrary,
    // unenforced entries through a CRUD screen.
    private const PERMISSIONS = [
        ['key' => 'manage_orders', 'label' => 'Orders & Fulfillment'],
        ['key' => 'manage_payments', 'label' => 'Payments'],
        ['key' => 'manage_reports', 'label' => 'Reports'],
        ['key' => 'manage_settings', 'label' => 'Price Settings'],
        ['key' => 'manage_students', 'label' => 'Students'],
    ];

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        $now = now();
        foreach (self::PERMISSIONS as $permission) {
            \DB::table('permissions')->insert([
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
