<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Investments are no longer tied to a fixed plan row — a student enters any
// capital amount (>= Setting::investment_minimum_amount) and picks a tenure
// from Setting::investment_tenures_months. The rate, monthly return, and
// total payout are snapshotted at submission time (same reasoning as
// investor_name/email/phone below) so a later admin rate change never
// rewrites the terms an existing investor already agreed to.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropForeign(['investment_plan_id']);
            $table->dropColumn(['investment_plan_id', 'amount']);

            $table->decimal('capital_amount', 12, 2)->after('user_id');
            $table->unsignedInteger('tenure_months')->after('capital_amount');
            $table->decimal('rate_percent', 5, 2)->after('tenure_months');
            $table->decimal('monthly_return', 12, 2)->after('rate_percent');
            $table->decimal('total_payout', 12, 2)->after('monthly_return');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['capital_amount', 'tenure_months', 'rate_percent', 'monthly_return', 'total_payout']);
            $table->foreignId('investment_plan_id')->after('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2)->after('investment_plan_id');
        });
    }
};
