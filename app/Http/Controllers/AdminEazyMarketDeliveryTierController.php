<?php

namespace App\Http\Controllers;

use App\Models\EazyMarketDeliveryTier;
use Illuminate\Http\Request;

class AdminEazyMarketDeliveryTierController extends Controller
{
    public function index()
    {
        return response()->json(EazyMarketDeliveryTier::orderBy('sort_order')->orderBy('min_amount')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'fee' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json(EazyMarketDeliveryTier::create($validated), 201);
    }

    // Note: the frontend should always submit min_amount and max_amount
    // together on every save (never a bare {fee: x} patch) — the
    // gt:min_amount rule below needs min_amount present in the same
    // request to compare against.
    public function update(Request $request, EazyMarketDeliveryTier $eazyMarketDeliveryTier)
    {
        $validated = $request->validate([
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'fee' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $eazyMarketDeliveryTier->update($validated);

        return response()->json($eazyMarketDeliveryTier);
    }

    public function destroy(EazyMarketDeliveryTier $eazyMarketDeliveryTier)
    {
        $eazyMarketDeliveryTier->delete();

        return response()->json(['message' => 'Delivery tier removed.']);
    }
}
