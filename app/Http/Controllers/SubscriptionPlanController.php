<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        return response()->json(
            SubscriptionPlan::orderBy('package_type')->orderBy('price')->get()
        );
    }

    // Same endpoint handles two levels of access, same pattern as
    // AdminUserController::store: a regular manage_subscriptions holder can
    // only ever touch price (cylinder_kg/perks are fixed shape for a given
    // tier from their perspective, and already-locked subscribers snapshot
    // their own price, so this only affects subscriptions created after the
    // call). A super_admin can edit every field — but note price is the
    // only one that's snapshotted: changing tier/package_type/cylinder_kg
    // here also reshapes every subscriber still pointing at this plan row,
    // including in-flight refills and activation calendars.
    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        if ($request->user()->role === 'super_admin') {
            $validated = $request->validate([
                'package_type' => ['sometimes', Rule::in(['session', 'semester'])],
                'tier' => ['sometimes', Rule::in(['bronze', 'silver', 'gold'])],
                'cylinder_kg' => ['sometimes', 'numeric', 'min:0.01'],
                'price' => ['sometimes', 'numeric', 'min:0.01'],
                'foodstuff_pack_value' => ['nullable', 'numeric', 'min:0'],
                'has_souvenir' => ['sometimes', 'boolean'],
                'has_publicity' => ['sometimes', 'boolean'],
            ]);
        } else {
            $validated = $request->validate([
                'price' => ['required', 'numeric', 'min:0.01'],
            ]);
        }

        $subscriptionPlan->update($validated);

        return response()->json($subscriptionPlan);
    }
}
