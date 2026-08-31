<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The investment offer is now a calculator (any capital >= a minimum, at a
// fixed monthly rate, for an admin-editable set of tenures) rather than
// fixed plan cards — see restructure_investments_table_for_calculator and
// drop_investment_plans_table. These fields live on Setting the same way
// price_per_kg does, so admins edit them from the same Settings screen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('investment_monthly_rate_percent', 5, 2)->default(2.50)->after('investment_account_number');
            $table->decimal('investment_minimum_amount', 12, 2)->default(50000)->after('investment_monthly_rate_percent');
            // JSON rather than a fixed enum/table so an admin can add or
            // remove tenure options later without a migration.
            $table->json('investment_tenures_months')->nullable()->after('investment_minimum_amount');
            $table->string('investment_whatsapp_number')->nullable()->after('investment_tenures_months');
        });

        DB::table('settings')->whereNull('investment_tenures_months')->update([
            'investment_tenures_months' => json_encode([6, 12]),
        ]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'investment_monthly_rate_percent',
                'investment_minimum_amount',
                'investment_tenures_months',
                'investment_whatsapp_number',
            ]);
        });
    }
};
