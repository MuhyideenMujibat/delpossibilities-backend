<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Shown to a prospective investor so they know where to send
            // their bank transfer — no payment gateway involved, see
            // 2026_08_28_120153_create_investments_table.php.
            $table->string('investment_bank_name')->nullable();
            $table->string('investment_account_name')->nullable();
            $table->string('investment_account_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['investment_bank_name', 'investment_account_name', 'investment_account_number']);
        });
    }
};
