<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'manage_subscriptions',
            'label' => 'Subscriptions',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'manage_subscriptions')->delete();
    }
};
