<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The investment page's "message us" link and bank-transfer box read from
// these Setting columns (see InvestmentPreview.jsx). They shipped nullable
// with no seeded value, so the box stayed hidden until an admin filled it
// in by hand. Seed the real contact + account details now; an admin can
// still edit any of them from the Settings screen afterwards.
return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')->where('id', 1)->first();
        if (! $setting) {
            return;
        }

        $updates = [];

        if (empty($setting->investment_whatsapp_number)) {
            $updates['investment_whatsapp_number'] = '2348103217371';
        }
        if (empty($setting->investment_bank_name)) {
            $updates['investment_bank_name'] = 'Moniepoint';
        }
        if (empty($setting->investment_account_number)) {
            $updates['investment_account_number'] = '6110724571';
        }
        if (empty($setting->investment_account_name)) {
            $updates['investment_account_name'] = "D'EL-Possibilities Nig Limited";
        }

        if ($updates) {
            DB::table('settings')->where('id', 1)->update($updates);
        }
    }

    public function down(): void
    {
        // No-op: these are content values, not schema — clearing them on
        // rollback would just re-hide the investment bank box.
    }
};
