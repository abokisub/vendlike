<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Support\Facades\DB;

trait GiftCardHelpers
{
    // verifyapptoken and verifytoken are inherited from Controller base class
    // Do NOT override them here — the base Controller handles Sanctum tokens properly

    /**
     * Generate purchase reference
     */
    public function purchase_ref($prefix = 'GC_')
    {
        return $prefix . time() . rand(1000, 9999);
    }

    /**
     * Get core system settings
     */
    public function core()
    {
        return DB::table('settings')->first();
    }
}