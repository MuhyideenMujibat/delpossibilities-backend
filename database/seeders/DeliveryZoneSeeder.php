<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    private const ZONES = [
        ['name' => 'Tanke', 'fee' => 300, 'sort_order' => 1],
        ['name' => 'Sango', 'fee' => 400, 'sort_order' => 2],
        ['name' => 'Fate', 'fee' => 500, 'sort_order' => 3],
        ['name' => 'Basin', 'fee' => 350, 'sort_order' => 4],
    ];

    public function run(): void
    {
        foreach (self::ZONES as $zone) {
            DeliveryZone::firstOrCreate(['name' => $zone['name']], $zone);
        }
    }
}
