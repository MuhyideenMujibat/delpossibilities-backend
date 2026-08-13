<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdminStudentController extends Controller
{
    public function index()
    {
        return response()->json(
            User::where('role', 'student')->withCount('orders')->latest()->get()
        );
    }

    public function show(User $user)
    {
        if ($user->role !== 'student') {
            throw ValidationException::withMessages([
                'user' => ['This account is not a student.'],
            ]);
        }

        return response()->json(
            $user->load(['orders' => fn ($query) => $query->latest()])
        );
    }

    public function destroy(User $user)
    {
        if ($user->role !== 'student') {
            throw ValidationException::withMessages([
                'user' => ['This account is not a student and cannot be removed from here.'],
            ]);
        }

        // Their orders cascade-delete with them (orders.user_id has an
        // onDelete('cascade') foreign key).
        $user->delete();

        return response()->json(['message' => 'Student removed.']);
    }
}
