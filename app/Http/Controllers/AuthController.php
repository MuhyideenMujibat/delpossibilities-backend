<?php

namespace App\Http\Controllers;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\RegistrationOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Registration is staged, not saved: the details go into
    // pending_registrations and only become a real `users` row once the
    // emailed OTP is verified below. An abandoned signup never leaves an
    // account behind.
    public function register(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'location_type' => ['required', Rule::in(['hostel', 'off_campus'])],
            // 'hostel' doubles as the free-text delivery address for
            // off-campus students — only on-campus values are checked
            // against the admin-managed hostel list.
            'hostel' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ];

        if ($request->input('location_type') === 'hostel') {
            $rules['hostel'][] = Rule::exists('hostels', 'name')->where('is_active', true);
        }

        $validated = $request->validate($rules);

        $otp = (string) random_int(100000, 999999);

        $pending = PendingRegistration::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'password' => $validated['password'],
                'hostel' => $validated['hostel'],
                'location_type' => $validated['location_type'],
                'phone' => $validated['phone'],
                'otp_code' => $otp,
                'otp_expires_at' => now()->addMinutes(10),
            ]
        );

        Notification::route('mail', $pending->email)->notify(new RegistrationOtpNotification($otp));

        return response()->json([
            'message' => 'We sent a 6-digit code to your email. Enter it to finish creating your account.',
            'email' => $pending->email,
        ]);
    }

    public function verifyRegistration(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string'],
        ]);

        $pending = PendingRegistration::where('email', $validated['email'])->first();

        if (! $pending || $pending->otp_expires_at->isPast() || ! Hash::check($validated['otp'], $pending->otp_code)) {
            throw ValidationException::withMessages([
                'otp' => ['That code is invalid or has expired. Please request a new one.'],
            ]);
        }

        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();

            throw ValidationException::withMessages([
                'email' => ['An account with this email already exists. Please log in.'],
            ]);
        }

        $user = User::create([
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => $pending->password,
            'hostel' => $pending->hostel,
            'location_type' => $pending->location_type,
            'phone' => $pending->phone,
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $pending->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function resendRegistrationOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $pending = PendingRegistration::where('email', $validated['email'])->first();

        if (! $pending) {
            throw ValidationException::withMessages([
                'email' => ['No pending registration found for this email. Please register again.'],
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $pending->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        Notification::route('mail', $pending->email)->notify(new RegistrationOtpNotification($otp));

        return response()->json([
            'message' => 'A new code has been sent to your email.',
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
