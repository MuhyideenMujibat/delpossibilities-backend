<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// MySQL's legacy timestamp rule silently gives the first NOT NULL TIMESTAMP
// column in a table (with no explicit DEFAULT/ON UPDATE of its own) both
// DEFAULT CURRENT_TIMESTAMP and ON UPDATE CURRENT_TIMESTAMP unless told
// otherwise — that column here was `requested_at`. Every refill status
// update (approve, pick up, deliver, cancel) was silently overwriting the
// student's original request time with "now". Only became obvious once
// refills started going through more than one update (see the
// status-pipeline migration alongside this one) — confirmed live: PATCHing
// a refill's status changed `requested_at` even though the controller never
// touches that column.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE refills MODIFY requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE refills MODIFY requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }
};
