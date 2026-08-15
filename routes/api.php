<?php

use App\Http\Controllers\AdminHostelController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register/verify', [AuthController::class, 'verifyRegistration']);
Route::post('/register/resend-otp', [AuthController::class, 'resendRegistrationOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// Public, read-only price so the marketing/landing page can show a real
// number. Deliberately separate from the admin-only /settings/price group
// below, which also allows PATCH.
Route::get('/price', [SettingController::class, 'showPrice']);

// Public, read-only hostel list so Register (unauthenticated) can offer the
// same picker as the logged-in Profile/Create Order pages. Admin-only
// create/update/delete lives under the super_admin group below.
Route::get('/hostels', function () {
    return response()->json(
        \App\Models\Hostel::where('is_active', true)->orderBy('name')->get(['id', 'name'])
    );
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{order}/verify-payment', [OrderController::class, 'verifyPayment']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::post('/profile/cylinder-image', [ProfileController::class, 'uploadCylinderImage']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::middleware('admin')->group(function () {
        // Shared by three different admin "lenses" on the same order data
        // (Dashboard, Payments, Reports) — any one of those permissions is
        // enough to read it. Advancing an order's status is the one
        // meaningfully different, mutating action, so it stays scoped to
        // manage_orders specifically.
        Route::middleware('permission:manage_orders,manage_payments,manage_reports')->group(function () {
            Route::get('/orders', [OrderController::class, 'index']);
        });
        Route::middleware('permission:manage_orders')->group(function () {
            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        });

        Route::middleware('permission:manage_settings')->group(function () {
            Route::get('/settings/price', [SettingController::class, 'showPrice']);
            Route::patch('/settings/price', [SettingController::class, 'updatePrice']);
            Route::post('/settings/broadcast-offer', [SettingController::class, 'broadcastOffer']);
        });

        Route::middleware('permission:manage_students')->group(function () {
            Route::get('/admin/students', [AdminStudentController::class, 'index']);
            Route::get('/admin/students/{user}', [AdminStudentController::class, 'show']);
            Route::delete('/admin/students/{user}', [AdminStudentController::class, 'destroy']);
        });

        // Creating a user is allowed for anyone with manage_students (plain
        // student accounts), but creating an *employee* account with
        // permissions is gated to super_admin inside the controller itself
        // since it's the same endpoint handling both cases.
        Route::post('/admin/users', [AdminUserController::class, 'store']);

        Route::middleware('super_admin')->group(function () {
            Route::get('/admin/staff', [AdminStaffController::class, 'index']);
            Route::patch('/admin/staff/{user}', [AdminStaffController::class, 'update']);
            Route::delete('/admin/staff/{user}', [AdminStaffController::class, 'destroy']);

            Route::get('/admin/permissions', [PermissionController::class, 'index']);

            Route::get('/admin/user-types', [UserTypeController::class, 'index']);
            Route::post('/admin/user-types', [UserTypeController::class, 'store']);
            Route::patch('/admin/user-types/{userType}', [UserTypeController::class, 'update']);
            Route::delete('/admin/user-types/{userType}', [UserTypeController::class, 'destroy']);

            Route::get('/admin/hostels', [AdminHostelController::class, 'index']);
            Route::post('/admin/hostels', [AdminHostelController::class, 'store']);
            Route::patch('/admin/hostels/{hostel}', [AdminHostelController::class, 'update']);
            Route::delete('/admin/hostels/{hostel}', [AdminHostelController::class, 'destroy']);
        });
    });
});
Route::post('/paystack/webhook', [App\Http\Controllers\PaystackWebhookController::class, 'handle']);
