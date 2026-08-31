<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private const PRODUCTS = [
        // gas_services
        ['group' => 'gas_services', 'category' => 'cylinder_sales', 'name' => '6kg Gas Cylinder', 'price' => 25000, 'sort_order' => 1],
        ['group' => 'gas_services', 'category' => 'cylinder_sales', 'name' => '12.5kg Gas Cylinder', 'price' => 38000, 'sort_order' => 2],
        ['group' => 'gas_services', 'category' => 'accessories_burners', 'name' => 'Single-Burner Gas Cooker', 'price' => 12000, 'sort_order' => 1],
        ['group' => 'gas_services', 'category' => 'accessories_burners', 'name' => 'Gas Regulator & Hose Set', 'price' => 6500, 'sort_order' => 2],
        ['group' => 'gas_services', 'category' => 'repair_maintenance', 'name' => 'Valve Leak Repair', 'price' => 3000, 'sort_order' => 1],
        ['group' => 'gas_services', 'category' => 'repainting', 'name' => 'Cylinder Repainting (per cylinder)', 'price' => 2500, 'sort_order' => 1],
        ['group' => 'gas_services', 'category' => 'cylinder_cleaning', 'name' => 'Free Cylinder Cleaning', 'price' => 0, 'sort_order' => 1],

        // eazy_market
        ['group' => 'eazy_market', 'category' => 'groceries', 'name' => 'Rice (5kg bag)', 'price' => 9500, 'sort_order' => 1],
        ['group' => 'eazy_market', 'category' => 'groceries', 'name' => 'Vegetable Oil (5L)', 'price' => 13500, 'sort_order' => 2],
        ['group' => 'eazy_market', 'category' => 'fresh_produce', 'name' => 'Tomato Basket (small)', 'price' => 4000, 'sort_order' => 1],
        ['group' => 'eazy_market', 'category' => 'frozen_foods', 'name' => 'Frozen Chicken (per kg)', 'price' => 3200, 'sort_order' => 1],
        ['group' => 'eazy_market', 'category' => 'market_errands', 'name' => 'Market Errand Run (per trip)', 'price' => 1000, 'sort_order' => 1],
        ['group' => 'eazy_market', 'category' => 'peanuts', 'name' => 'Peanuts', 'price' => 500, 'sort_order' => 1], // base price; variants override
    ];

    private const PEANUT_VARIANTS = [
        ['label' => 'Pack', 'price' => 500, 'sort_order' => 1],
        ['label' => 'Jar', 'price' => 1200, 'sort_order' => 2],
        ['label' => 'Bottle', 'price' => 2000, 'sort_order' => 3],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $data) {
            $product = Product::firstOrCreate(
                ['group' => $data['group'], 'category' => $data['category'], 'name' => $data['name']],
                $data
            );

            if ($data['category'] === 'peanuts') {
                foreach (self::PEANUT_VARIANTS as $variant) {
                    $product->variants()->firstOrCreate(['label' => $variant['label']], $variant);
                }
            }
        }
    }
}
