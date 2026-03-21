<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            // Major Countries (Popular for gift cards)
            ['name' => 'United States', 'code' => 'US', 'flag_emoji' => '🇺🇸', 'active' => true],
            ['name' => 'United Kingdom', 'code' => 'GB', 'flag_emoji' => '🇬🇧', 'active' => true],
            ['name' => 'Canada', 'code' => 'CA', 'flag_emoji' => '🇨🇦', 'active' => true],
            ['name' => 'Australia', 'code' => 'AU', 'flag_emoji' => '🇦🇺', 'active' => true],
            ['name' => 'Germany', 'code' => 'DE', 'flag_emoji' => '🇩🇪', 'active' => true],
            ['name' => 'France', 'code' => 'FR', 'flag_emoji' => '🇫🇷', 'active' => true],
            ['name' => 'Italy', 'code' => 'IT', 'flag_emoji' => '🇮🇹', 'active' => true],
            ['name' => 'Spain', 'code' => 'ES', 'flag_emoji' => '🇪🇸', 'active' => true],
            ['name' => 'Netherlands', 'code' => 'NL', 'flag_emoji' => '🇳🇱', 'active' => true],
            ['name' => 'Sweden', 'code' => 'SE', 'flag_emoji' => '🇸🇪', 'active' => true],
            ['name' => 'Norway', 'code' => 'NO', 'flag_emoji' => '🇳🇴', 'active' => true],
            ['name' => 'Denmark', 'code' => 'DK', 'flag_emoji' => '🇩🇰', 'active' => true],
            ['name' => 'Switzerland', 'code' => 'CH', 'flag_emoji' => '🇨🇭', 'active' => true],
            ['name' => 'Austria', 'code' => 'AT', 'flag_emoji' => '🇦🇹', 'active' => true],
            ['name' => 'Belgium', 'code' => 'BE', 'flag_emoji' => '🇧🇪', 'active' => true],
            ['name' => 'Japan', 'code' => 'JP', 'flag_emoji' => '🇯🇵', 'active' => true],
            ['name' => 'South Korea', 'code' => 'KR', 'flag_emoji' => '🇰🇷', 'active' => true],
            ['name' => 'Singapore', 'code' => 'SG', 'flag_emoji' => '🇸🇬', 'active' => true],
            ['name' => 'Hong Kong', 'code' => 'HK', 'flag_emoji' => '🇭🇰', 'active' => true],
            ['name' => 'New Zealand', 'code' => 'NZ', 'flag_emoji' => '🇳🇿', 'active' => true],
            
            // Additional Popular Countries
            ['name' => 'Brazil', 'code' => 'BR', 'flag_emoji' => '🇧🇷', 'active' => true],
            ['name' => 'Mexico', 'code' => 'MX', 'flag_emoji' => '🇲🇽', 'active' => true],
            ['name' => 'Argentina', 'code' => 'AR', 'flag_emoji' => '🇦🇷', 'active' => true],
            ['name' => 'Chile', 'code' => 'CL', 'flag_emoji' => '🇨🇱', 'active' => true],
            ['name' => 'Colombia', 'code' => 'CO', 'flag_emoji' => '🇨🇴', 'active' => true],
            ['name' => 'Peru', 'code' => 'PE', 'flag_emoji' => '🇵🇪', 'active' => true],
            ['name' => 'Venezuela', 'code' => 'VE', 'flag_emoji' => '🇻🇪', 'active' => true],
            ['name' => 'Uruguay', 'code' => 'UY', 'flag_emoji' => '🇺🇾', 'active' => true],
            
            // European Countries
            ['name' => 'Poland', 'code' => 'PL', 'flag_emoji' => '🇵🇱', 'active' => true],
            ['name' => 'Czech Republic', 'code' => 'CZ', 'flag_emoji' => '🇨🇿', 'active' => true],
            ['name' => 'Hungary', 'code' => 'HU', 'flag_emoji' => '🇭🇺', 'active' => true],
            ['name' => 'Slovakia', 'code' => 'SK', 'flag_emoji' => '🇸🇰', 'active' => true],
            ['name' => 'Slovenia', 'code' => 'SI', 'flag_emoji' => '🇸🇮', 'active' => true],
            ['name' => 'Croatia', 'code' => 'HR', 'flag_emoji' => '🇭🇷', 'active' => true],
            ['name' => 'Romania', 'code' => 'RO', 'flag_emoji' => '🇷🇴', 'active' => true],
            ['name' => 'Bulgaria', 'code' => 'BG', 'flag_emoji' => '🇧🇬', 'active' => true],
            ['name' => 'Greece', 'code' => 'GR', 'flag_emoji' => '🇬🇷', 'active' => true],
            ['name' => 'Portugal', 'code' => 'PT', 'flag_emoji' => '🇵🇹', 'active' => true],
            ['name' => 'Ireland', 'code' => 'IE', 'flag_emoji' => '🇮🇪', 'active' => true],
            ['name' => 'Finland', 'code' => 'FI', 'flag_emoji' => '🇫🇮', 'active' => true],
            ['name' => 'Estonia', 'code' => 'EE', 'flag_emoji' => '🇪🇪', 'active' => true],
            ['name' => 'Latvia', 'code' => 'LV', 'flag_emoji' => '🇱🇻', 'active' => true],
            ['name' => 'Lithuania', 'code' => 'LT', 'flag_emoji' => '🇱🇹', 'active' => true],
            
            // Asian Countries
            ['name' => 'China', 'code' => 'CN', 'flag_emoji' => '🇨🇳', 'active' => true],
            ['name' => 'India', 'code' => 'IN', 'flag_emoji' => '🇮🇳', 'active' => true],
            ['name' => 'Indonesia', 'code' => 'ID', 'flag_emoji' => '🇮🇩', 'active' => true],
            ['name' => 'Thailand', 'code' => 'TH', 'flag_emoji' => '🇹🇭', 'active' => true],
            ['name' => 'Malaysia', 'code' => 'MY', 'flag_emoji' => '🇲🇾', 'active' => true],
            ['name' => 'Philippines', 'code' => 'PH', 'flag_emoji' => '🇵🇭', 'active' => true],
            ['name' => 'Vietnam', 'code' => 'VN', 'flag_emoji' => '🇻🇳', 'active' => true],
            ['name' => 'Taiwan', 'code' => 'TW', 'flag_emoji' => '🇹🇼', 'active' => true],
            ['name' => 'Israel', 'code' => 'IL', 'flag_emoji' => '🇮🇱', 'active' => true],
            ['name' => 'Turkey', 'code' => 'TR', 'flag_emoji' => '🇹🇷', 'active' => true],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'flag_emoji' => '🇸🇦', 'active' => true],
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'flag_emoji' => '🇦🇪', 'active' => true],
            
            // African Countries
            ['name' => 'South Africa', 'code' => 'ZA', 'flag_emoji' => '🇿🇦', 'active' => true],
            ['name' => 'Nigeria', 'code' => 'NG', 'flag_emoji' => '🇳🇬', 'active' => true],
            ['name' => 'Kenya', 'code' => 'KE', 'flag_emoji' => '🇰🇪', 'active' => true],
            ['name' => 'Ghana', 'code' => 'GH', 'flag_emoji' => '🇬🇭', 'active' => true],
            ['name' => 'Egypt', 'code' => 'EG', 'flag_emoji' => '🇪🇬', 'active' => true],
            ['name' => 'Morocco', 'code' => 'MA', 'flag_emoji' => '🇲🇦', 'active' => true],
            
            // Other Notable Countries
            ['name' => 'Russia', 'code' => 'RU', 'flag_emoji' => '🇷🇺', 'active' => true],
            ['name' => 'Ukraine', 'code' => 'UA', 'flag_emoji' => '🇺🇦', 'active' => true],
            ['name' => 'Belarus', 'code' => 'BY', 'flag_emoji' => '🇧🇾', 'active' => true],
            ['name' => 'Kazakhstan', 'code' => 'KZ', 'flag_emoji' => '🇰🇿', 'active' => true],
            ['name' => 'Iceland', 'code' => 'IS', 'flag_emoji' => '🇮🇸', 'active' => true],
            ['name' => 'Luxembourg', 'code' => 'LU', 'flag_emoji' => '🇱🇺', 'active' => true],
            ['name' => 'Malta', 'code' => 'MT', 'flag_emoji' => '🇲🇹', 'active' => true],
            ['name' => 'Cyprus', 'code' => 'CY', 'flag_emoji' => '🇨🇾', 'active' => true],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->updateOrInsert(
                ['code' => $country['code']], // Check for existing by country code
                [
                    'name' => $country['name'],
                    'code' => $country['code'],
                    'flag_emoji' => $country['flag_emoji'],
                    'active' => $country['active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}