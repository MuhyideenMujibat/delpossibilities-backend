<?php

use App\Http\Controllers\AdminDeliveryZoneController;
use App\Http\Controllers\AdminEazyMarketDeliveryTierController;
use App\Http\Controllers\AdminHostelController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminProductOrderController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedbackSubmissionController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductOrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefillController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\UserTypeController;
use App\Models\DeliveryZone;
use App\Models\EazyMarketDeliveryTier;
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

// Public plan list so the landing/plan-picker page can show real prices
// before login, same reasoning as /price above.
Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);

// Public so the floating feedback widget works for logged-out landing
// visitors too — the controller attributes it to a user when a valid
// Sanctum token is present, without requiring one.
Route::post('/feedback', [FeedbackSubmissionController::class, 'store']);

// Public, read-only product catalog so the landing page shop sections can
// list gas_services and eazy_market items before login. Only active
// products/variants are returned. Admin CRUD lives under super_admin below.
Route::get('/products', [ProductController::class, 'index']);

// Public so checkout can offer the zone picker for off-campus gas orders
// before login-gated pages load. Admin CRUD lives under super_admin below.
Route::get('/delivery-zones', function () {
    return response()->json(
        DeliveryZone::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'fee'])
    );
});

// Public so Eazy Market checkout can compute/display the delivery fee tier
// before login. Admin CRUD lives under super_admin below.
Route::get('/eazy-market-delivery-tiers', function () {
    return response()->json(
        EazyMarketDeliveryTier::where('is_active', true)->orderBy('sort_order')->orderBy('min_amount')
            ->get(['id', 'min_amount', 'max_amount', 'fee'])
    );
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{order}/verify-payment', [OrderController::class, 'verifyPayment']);

    Route::post('/product-orders', [ProductOrderController::class, 'store']);
    Route::get('/my-product-order', [ProductOrderController::class, 'mine']);
    Route::get('/my-product-orders', [ProductOrderController::class, 'myOrders']);
    Route::post('/product-orders/{productOrder}/pay', [ProductOrderController::class, 'pay']);
    Route::post('/product-orders/{productOrder}/verify-payment', [ProductOrderController::class, 'verifyPayment']);

    Route::post('/subscribers', [SubscriberController::class, 'store']);
    Route::get('/my-subscription', [SubscriberController::class, 'mine']);
    Route::post('/subscribers/{subscriber}/pay', [SubscriberController::class, 'pay']);
    Route::post('/subscribers/{subscriber}/verify-payment', [SubscriberController::class, 'verifyPayment']);
    Route::post('/subscribers/{subscriber}/transfer', [SubscriberController::class, 'transfer']);
    Route::post('/refills', [RefillController::class, 'store']);

    Route::post('/investments', [InvestmentController::class, 'store']);
    Route::get('/my-investments', [InvestmentController::class, 'mine']);
    Route::post('/investments/{investment}/sign', [InvestmentController::class, 'sign']);

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

            Route::get('/admin/product-orders', [AdminProductOrderController::class, 'index']);
            Route::patch('/admin/product-orders/{productOrder}', [AdminProductOrderController::class, 'update']);
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

        Route::middleware('permission:manage_subscriptions')->group(function () {
            Route::get('/admin/subscribers', [SubscriberController::class, 'index']);
            Route::get('/admin/subscription-reports', [SubscriberController::class, 'reports']);
            Route::get('/admin/refills', [RefillController::class, 'index']);
            Route::patch('/admin/refills/{refill}', [RefillController::class, 'update']);
            Route::patch('/admin/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update']);
        });

        Route::middleware('super_admin')->group(function () {
            Route::get('/admin/feedback', [FeedbackSubmissionController::class, 'index']);

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

            Route::get('/admin/products', [AdminProductController::class, 'index']);
            Route::post('/admin/products', [AdminProductController::class, 'store']);
            // POST not PATCH — this endpoint may carry an image file, see
            // AdminProductController::update for why.
            Route::post('/admin/products/{product}', [AdminProductController::class, 'update']);
            Route::delete('/admin/products/{product}', [AdminProductController::class, 'destroy']);

            Route::post('/admin/products/{product}/variants', [AdminProductController::class, 'storeVariant']);
            Route::patch('/admin/product-variants/{productVariant}', [AdminProductController::class, 'updateVariant']);
            Route::delete('/admin/product-variants/{productVariant}', [AdminProductController::class, 'destroyVariant']);

            Route::get('/admin/delivery-zones', [AdminDeliveryZoneController::class, 'index']);
            Route::post('/admin/delivery-zones', [AdminDeliveryZoneController::class, 'store']);
            Route::patch('/admin/delivery-zones/{deliveryZone}', [AdminDeliveryZoneController::class, 'update']);
            Route::delete('/admin/delivery-zones/{deliveryZone}', [AdminDeliveryZoneController::class, 'destroy']);

            Route::get('/admin/eazy-market-delivery-tiers', [AdminEazyMarketDeliveryTierController::class, 'index']);
            Route::post('/admin/eazy-market-delivery-tiers', [AdminEazyMarketDeliveryTierController::class, 'store']);
            Route::patch('/admin/eazy-market-delivery-tiers/{eazyMarketDeliveryTier}', [AdminEazyMarketDeliveryTierController::class, 'update']);
            Route::delete('/admin/eazy-market-delivery-tiers/{eazyMarketDeliveryTier}', [AdminEazyMarketDeliveryTierController::class, 'destroy']);

            Route::get('/admin/investments', [InvestmentController::class, 'index']);
            Route::patch('/admin/investments/{investment}/confirm-payment', [InvestmentController::class, 'confirmPayment']);
            Route::patch('/admin/investments/{investment}/cancel', [InvestmentController::class, 'cancel']);
        });
    });
});
Route::post('/paystack/webhook', [App\Http\Controllers\PaystackWebhookController::class, 'handle']);
