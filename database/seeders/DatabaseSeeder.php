<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(SettingSeeder::class);
        $this->call(SubscriptionPlanSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(DeliveryZoneSeeder::class);
        $this->call(EazyMarketDeliveryTierSeeder::class);
    }
}
