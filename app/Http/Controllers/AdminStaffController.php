<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminStaffController extends Controller
{
    public function index()
    {
        return response()->json(
            User::where('role', 'admin')->with(['userType', 'permissions'])->latest()->get()
        );
    }

    public function update(Request $request, User $user)
    {
        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['This account is not an employee.'],
            ]);
        }

        $validated = $request->validate([
            'user_type_id' => ['nullable', 'integer', 'exists:user_types,id'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        if ($request->has('user_type_id')) {
            $user->update(['user_type_id' => $validated['user_type_id'] ?? null]);
        }

        if ($request->has('permission_ids')) {
            $user->permissions()->sync($validated['permission_ids']);
        }

        return response()->json($user->load(['userType', 'permissions']));
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['This account is not an employee and cannot be removed from here.'],
            ]);
        }

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot remove your own account.'],
            ]);
        }

        $user->delete();

        return response()->json(['message' => 'Employee removed.']);
    }
}
