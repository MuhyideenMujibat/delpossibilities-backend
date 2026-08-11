<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kg' => ['required', 'numeric', 'min:0.01'],
            'hostel_address' => ['required', 'string', 'max:255'],
            'cylinder_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $user = $request->user();

        if (! $user->cylinder_image) {
            throw ValidationException::withMessages([
                'cylinder_image' => ['Please upload your cylinder image to your profile before placing an order.'],
            ]);
        }

        $pricePerKg = Setting::current()->price_per_kg;

        $imagePath = $request->hasFile('cylinder_image')
            ? $request->file('cylinder_image')->store('cylinders', 'public')
            : $user->cylinder_image;

        $order = $user->orders()->create([
            'cylinder_image' => $imagePath,
            'kg' => $validated['kg'],
            'price_per_kg' => $pricePerKg,
            'total_amount' => round($validated['kg'] * $pricePerKg, 2),
            'hostel_address' => $validated['hostel_address'],
            'status' => 'pending',
        ]);

        return response()->json($order, 201);
    }

    public function myOrders(Request $request)
    {
        return response()->json(
            $request->user()->orders()->latest()->get()
        );
    }

    public function index()
    {
        return response()->json(
            Order::with('user')->latest()->get()
        );
    }

    public function pay(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            abort(403, 'This order does not belong to you.');
        }

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This order has already been paid for or handled.'],
            ]);
        }

        $reference = 'order_'.$order->id.'_'.Str::random(10);

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => (int) ($order->total_amount * 100),
                    'reference' => $reference,
                    'callback_url' => config('services.paystack.callback_url'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Paystack connection error: '.$e->getMessage());

            return response()->json([
                'message' => 'Unable to reach the payment provider. Please try again shortly.',
            ], 500);
        }

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack initialize transaction failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);

            return response()->json([
                'message' => 'Unable to initialize payment. Please try again shortly.',
            ], 500);
        }

        $order->update(['paystack_reference' => $reference]);

        return response()->json([
            'authorization_url' => $response->json('data.authorization_url'),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['picked_up', 'delivered'])],
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json($order);
    }
}
