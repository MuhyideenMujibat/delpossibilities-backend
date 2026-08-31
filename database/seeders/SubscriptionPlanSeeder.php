<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    private const PLANS = [
        ['package_type' => 'session', 'tier' => 'bronze', 'cylinder_kg' => 22, 'price' => 27700, 'foodstuff_pack_value' => 3500, 'has_souvenir' => false, 'has_publicity' => false],
        ['package_type' => 'session', 'tier' => 'silver', 'cylinder_kg' => 35, 'price' => 43950, 'foodstuff_pack_value' => 5000, 'has_souvenir' => false, 'has_publicity' => false],
        ['package_type' => 'session', 'tier' => 'gold', 'cylinder_kg' => 45, 'price' => 56450, 'foodstuff_pack_value' => 7000, 'has_souvenir' => true, 'has_publicity' => false],
        ['package_type' => 'semester', 'tier' => 'bronze', 'cylinder_kg' => 10, 'price' => 12450, 'foodstuff_pack_value' => null, 'has_souvenir' => false, 'has_publicity' => false],
        ['package_type' => 'semester', 'tier' => 'silver', 'cylinder_kg' => 18, 'price' => 22700, 'foodstuff_pack_value' => null, 'has_souvenir' => false, 'has_publicity' => true],
        ['package_type' => 'semester', 'tier' => 'gold', 'cylinder_kg' => 25, 'price' => 31450, 'foodstuff_pack_value' => 3500, 'has_souvenir' => false, 'has_publicity' => false],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['package_type' => $plan['package_type'], 'tier' => $plan['tier']],
                $plan
            );
        }
    }
}
