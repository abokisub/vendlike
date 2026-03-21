<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GiftCardTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $giftCardTypes = [
            [
                'name' => 'iTunes',
                'rate' => 85.00,
                'min_amount' => 10.00,
                'max_amount' => 500.00,
                'status' => 'active',
                'description' => 'Apple iTunes gift cards - US region preferred',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Google Play',
                'rate' => 82.00,
                'min_amount' => 10.00,
                'max_amount' => 500.00,
                'status' => 'active',
                'description' => 'Google Play Store gift cards - US region',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Amazon',
                'rate' => 80.00,
                'min_amount' => 25.00,
                'max_amount' => 500.00,
                'status' => 'active',
                'description' => 'Amazon gift cards - US region only',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Steam',
                'rate' => 78.00,
                'min_amount' => 20.00,
                'max_amount' => 200.00,
                'status' => 'active',
                'description' => 'Steam gaming platform gift cards',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Walmart',
                'rate' => 75.00,
                'min_amount' => 25.00,
                'max_amount' => 500.00,
                'status' => 'active',
                'description' => 'Walmart gift cards - physical cards preferred',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'eBay',
                'rate' => 77.00,
                'min_amount' => 25.00,
                'max_amount' => 500.00,
                'status' => 'active',
                'description' => 'eBay gift cards - US region',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('gift_card_types')->insert($giftCardTypes);
    }
}