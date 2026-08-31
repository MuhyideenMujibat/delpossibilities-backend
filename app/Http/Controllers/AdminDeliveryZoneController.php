<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDeliveryZoneController extends Controller
{
    public function index()
    {
        return response()->json(DeliveryZone::orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:delivery_zones,name'],
            'fee' => ['required', 'numeric', 'min:300'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json(DeliveryZone::create($validated), 201);
    }

    public function update(Request $request, DeliveryZone $deliveryZone)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('delivery_zones', 'name')->ignore($deliveryZone->id)],
            'fee' => ['sometimes', 'numeric', 'min:300'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $deliveryZone->update($validated);

        return response()->json($deliveryZone);
    }

    public function destroy(DeliveryZone $deliveryZone)
    {
        $deliveryZone->delete();

        return response()->json(['message' => 'Delivery zone removed.']);
    }
}
