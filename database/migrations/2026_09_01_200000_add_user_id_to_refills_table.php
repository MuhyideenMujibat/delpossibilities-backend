<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A refill previously had no owner of its own — it was only ever read
// through subscriber.user_id, which is mutable (see
// SubscriberController::transfer). That meant transferring a subscription
// silently rewrote who every past refill on it "belonged" to: the recipient
// would inherit the sender's whole delivery history, and the sender would
// lose visibility of their own. Snapshotting the requester on the refill
// itself (set at creation in RefillController::store, and left alone by
// transfer) keeps each refill permanently attributed to whoever actually
// requested it, independent of later ownership changes on the subscriber row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('subscriber_id')->constrained()->onDelete('cascade');
        });

        // Best-effort backfill for existing rows: the subscriber's current
        // owner is the closest available approximation of "who requested
        // this" for refills that predate this column.
        DB::statement('
            UPDATE refills
            INNER JOIN subscribers ON subscribers.id = refills.subscriber_id
            SET refills.user_id = subscribers.user_id
            WHERE refills.user_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('refills', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
