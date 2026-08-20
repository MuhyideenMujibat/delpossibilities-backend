<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'location_type' => ['sometimes', Rule::in(['hostel', 'off_campus'])],
            'hostel' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
        ];

        $locationType = $request->input('location_type', $request->user()->location_type);
        if ($request->filled('hostel') && $locationType === 'hostel') {
            $rules['hostel'][] = Rule::exists('hostels', 'name')->where('is_active', true);
        }

        $validated = $request->validate($rules);

        $user = $request->user();
        $user->update($validated);

        return response()->json($user);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // The 'hashed' cast on User::password hashes this automatically.
        $request->user()->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function uploadCylinderImage(Request $request)
    {
        $validated = $request->validate([
            'cylinder_image' => ['required', 'image', 'max:5120'],
        ]);

        $user = $request->user();
        $oldImage = $user->cylinder_image;

        $path = $request->file('cylinder_image')->store('cylinders/profile', 'public');

        $user->update(['cylinder_image' => $path]);

        if ($oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return response()->json($user);
    }
}
