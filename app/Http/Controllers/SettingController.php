<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function showPrice()
    {
        return response()->json([
            'price_per_kg' => Setting::current()->price_per_kg,
        ]);
    }

    public function updatePrice(Request $request)
    {
        $validated = $request->validate([
            'price_per_kg' => ['required', 'numeric', 'min:0'],
        ]);

        $setting = Setting::current();
        $setting->update(['price_per_kg' => $validated['price_per_kg']]);

        return response()->json([
            'price_per_kg' => $setting->price_per_kg,
        ]);
    }
}
