<?php

namespace App\Http\Controllers;

use App\Models\ProductOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProductOrderController extends Controller
{
    // Note on "pending" here: unlike a Refill (where pending means
    // "requested, awaiting admin approval"), a ProductOrder's `pending`
    // means "not yet paid" — it isn't admin-actionable until it becomes
    // `approved` (paid). So the default, actionable queue here is
    // `approved`/`picked_up`, not `pending`.
    public function index(Request $request)
    {
        // hasOne relations need their FK column (product_order_id) in the
        // constrained select too, or Eloquent can't match the row back.
        $query = ProductOrder::with(['user', 'items', 'order:id,status,product_order_id', 'refill:id,status,product_order_id']);

        $status = $request->input('status', 'approved');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->latest()->get());
    }

    // Only a genuinely standalone cart (never bundled into a gas order or
    // subscriber refill) can have its fulfillment status set directly here
    // — a linked one is driven by whichever it's attached to (see
    // OrderController::updateStatus / RefillController::update), so both
    // never end up with two disconnected sources of truth for one delivery.
    public function update(Request $request, ProductOrder $productOrder)
    {
        if ($productOrder->isLinked()) {
            throw ValidationException::withMessages([
                'status' => ['This order is tracked together with a gas order or subscription refill — update it from there instead.'],
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'picked_up', 'delivered', 'cancelled'])],
        ]);

        if (in_array($productOrder->status, ['delivered', 'cancelled'], true) || $productOrder->status === $validated['status']) {
            return response()->json($productOrder);
        }

        $productOrder->update(['status' => $validated['status']]);

        return response()->json($productOrder);
    }
}
