<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    // Admin-created accounts skip the OTP flow entirely — the admin is
    // vouching for the person directly, so the account is verified and
    // usable immediately.
    public function store(Request $request)
    {
        $accountType = $request->input('account_type', 'student');
        $actor = $request->user();

        if ($accountType === 'admin') {
            // Granting access/permissions is a privileged action — only a
            // super admin can create other admin accounts, regardless of
            // what permissions the actor themselves holds.
            if ($actor->role !== 'super_admin') {
                abort(403, 'Only a super admin can add an employee account.');
            }
        } elseif (! in_array('manage_students', $actor->permission_keys->all(), true)) {
            abort(403, 'You do not have permission to add students.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'hostel' => ['nullable', 'string', 'max:255'],
            'use_default_password' => ['sometimes', 'boolean'],
            'password' => [Rule::requiredIf(! $request->boolean('use_default_password')), 'nullable', 'string', 'min:8'],
            'user_type_id' => ['nullable', 'integer', 'exists:user_types,id'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $password = $request->boolean('use_default_password') ? '123456789' : $validated['password'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $password,
            'phone' => $validated['phone'] ?? null,
            'hostel' => $validated['hostel'] ?? null,
            'role' => $accountType === 'admin' ? 'admin' : 'student',
            'user_type_id' => $accountType === 'admin' ? ($validated['user_type_id'] ?? null) : null,
            'email_verified_at' => now(),
        ]);

        if ($accountType === 'admin') {
            $user->permissions()->sync($validated['permission_ids'] ?? []);
        }

        return response()->json($user->load('permissions'), 201);
    }
}
