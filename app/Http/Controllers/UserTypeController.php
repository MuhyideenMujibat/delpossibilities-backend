<?php

namespace App\Http\Controllers;

use App\Models\UserType;
use Illuminate\Http\Request;

class UserTypeController extends Controller
{
    public function index()
    {
        return response()->json(
            UserType::with('permissions')->withCount('users')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:user_types,name'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $userType = UserType::create(['name' => $validated['name']]);
        $userType->permissions()->sync($validated['permission_ids'] ?? []);

        return response()->json($userType->load('permissions'), 201);
    }

    public function update(Request $request, UserType $userType)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:user_types,name,'.$userType->id],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $userType->update(['name' => $validated['name']]);

        if ($request->has('permission_ids')) {
            $userType->permissions()->sync($validated['permission_ids']);
        }

        return response()->json($userType->load('permissions'));
    }

    public function destroy(UserType $userType)
    {
        // Users who had this type just lose the label (user_type_id is
        // nullOnDelete) — their actual permission grants were already
        // copied onto them individually and are untouched.
        $userType->delete();

        return response()->json(['message' => 'User type removed.']);
    }
}
