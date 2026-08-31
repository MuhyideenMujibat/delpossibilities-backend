<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Public, read-only catalog so the landing page can list gas_services
    // and eazy_market items before login (same reasoning as
    // SubscriptionPlanController::index). Only active products, and only
    // their active variants, are returned.
    public function index(Request $request)
    {
        $query = Product::with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('group')) {
            $query->where('group', $request->string('group'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return response()->json($query->get());
    }
}
