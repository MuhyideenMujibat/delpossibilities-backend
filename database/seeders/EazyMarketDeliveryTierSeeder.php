<?php

namespace Database\Seeders;

use App\Models\EazyMarketDeliveryTier;
use Illuminate\Database\Seeder;

class EazyMarketDeliveryTierSeeder extends Seeder
{
    private const TIERS = [
        ['min_amount' => 0, 'max_amount' => 4999, 'fee' => 300, 'sort_order' => 1],
        ['min_amount' => 5000, 'max_amount' => 14999, 'fee' => 500, 'sort_order' => 2],
        ['min_amount' => 15000, 'max_amount' => null, 'fee' => 800, 'sort_order' => 3],
    ];

    public function run(): void
    {
        foreach (self::TIERS as $tier) {
            EazyMarketDeliveryTier::firstOrCreate(['min_amount' => $tier['min_amount']], $tier);
        }
    }
}
