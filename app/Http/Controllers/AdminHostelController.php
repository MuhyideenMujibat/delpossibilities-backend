<?php

namespace App\Http\Controllers;

use App\Models\Hostel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminHostelController extends Controller
{
    public function index()
    {
        return response()->json(Hostel::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:hostels,name'],
        ]);

        $hostel = Hostel::create($validated);

        return response()->json($hostel, 201);
    }

    public function update(Request $request, Hostel $hostel)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('hostels', 'name')->ignore($hostel->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hostel->update($validated);

        return response()->json($hostel);
    }

    public function destroy(Hostel $hostel)
    {
        $hostel->delete();

        return response()->json(['message' => 'Hostel removed.']);
    }
}
