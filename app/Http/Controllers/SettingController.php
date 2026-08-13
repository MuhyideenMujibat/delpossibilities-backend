<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    public function showPrice()
    {
        return response()->json(Setting::current());
    }

    public function updatePrice(Request $request)
    {
        $validated = $request->validate([
            'price_per_kg' => ['required', 'numeric', 'min:0'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0'],
            'offer_active' => ['sometimes', 'boolean'],
            'offer_title' => ['nullable', 'string', 'max:120'],
            'offer_message' => ['nullable', 'string', 'max:1000'],
            'offer_price_per_kg' => ['nullable', 'numeric', 'min:0'],
        ]);

        $setting = Setting::current();
        $setting->update([
            'price_per_kg' => $validated['price_per_kg'],
            'delivery_fee' => $validated['delivery_fee'] ?? 0,
            'offer_active' => $validated['offer_active'] ?? false,
            'offer_title' => $validated['offer_title'] ?? null,
            'offer_message' => $validated['offer_message'] ?? null,
            'offer_price_per_kg' => $validated['offer_price_per_kg'] ?? null,
        ]);

        return response()->json($setting);
    }

    public function broadcastOffer(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $users = User::where('role', 'student')->whereNotNull('email')->get();

        foreach ($users as $user) {
            Mail::raw($validated['message'], function ($mail) use ($user, $validated) {
                $mail->to($user->email)->subject($validated['subject']);
            });
        }

        return response()->json([
            'message' => 'Broadcast sent.',
            'recipients' => $users->count(),
        ]);
    }
}
