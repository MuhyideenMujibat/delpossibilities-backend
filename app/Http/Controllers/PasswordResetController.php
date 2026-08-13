<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    const OTP_TTL_MINUTES = 10;

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Deliberately vague either way, so this endpoint can't be used to
        // probe which emails have accounts.
        if ($user) {
            $otp = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($otp), 'created_at' => now()]
            );

            $user->notify(new PasswordResetOtpNotification($otp));
        }

        return response()->json([
            'message' => 'If an account exists for that email, a 6-digit code has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        $expired = ! $record || Carbon::parse($record->created_at)->addMinutes(self::OTP_TTL_MINUTES)->isPast();

        if ($expired || ! Hash::check($validated['otp'], $record->token)) {
            throw ValidationException::withMessages([
                'otp' => ['That code is invalid or has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['We could not find an account for that email.'],
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Your password has been reset successfully.',
        ]);
    }
}
