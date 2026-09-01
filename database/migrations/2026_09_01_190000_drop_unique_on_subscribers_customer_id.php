<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// customer_id on a subscriber row now always mirrors its CURRENT owner's own
// permanent users.customer_id (see SubscriberController::store/transfer) — it
// changes hands on transfer instead of following the original subscriber
// around, and a student's own past subscriber rows are never deleted or
// relabeled. Both of those mean the same code can legitimately sit on more
// than one row (the student's own historical rows, or — briefly, in theory —
// a row and the account it mirrors), so a global unique constraint on this
// column no longer holds and was causing every second-time subscriber to be
// handed a fabricated code instead of their real one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropUnique(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->unique('customer_id');
        });
    }
};
