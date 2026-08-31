<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('variants')->orderBy('group')->orderBy('category')->orderBy('sort_order');

        if ($request->filled('group')) {
            $query->where('group', $request->string('group'));
        }

        return response()->json($query->get());
    }

    // POST, not PATCH/PUT — this endpoint may carry an image file in the
    // same request, and multipart bodies don't parse reliably on PATCH in
    // PHP (same reasoning as ProfileController::uploadCylinderImage already
    // being a POST route despite being semantically an update).
    public function store(Request $request)
    {
        $validated = $this->validateProduct($request);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        return response()->json($product->load('variants'), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request, $product, forUpdate: true);

        $oldImage = null;
        if ($request->hasFile('image')) {
            $oldImage = $product->image_path;
            $validated['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        if ($oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return response()->json($product->load('variants'));
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete(); // product_variants cascade-delete at the DB level

        return response()->json(['message' => 'Product removed.']);
    }

    private function validateProduct(Request $request, ?Product $product = null, bool $forUpdate = false): array
    {
        $requiredOrSometimes = $forUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'group' => [$requiredOrSometimes, Rule::in(Product::GROUPS)],
            'category' => [
                $requiredOrSometimes,
                function ($attribute, $value, $fail) use ($request, $product) {
                    $group = $request->input('group', $product?->group);
                    $allowed = Product::CATEGORIES[$group] ?? [];

                    if (! in_array($value, $allowed, true)) {
                        $fail('The selected category is not valid for this group.');
                    }
                },
            ],
            'name' => [$requiredOrSometimes, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => [$requiredOrSometimes, 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    // --- Variants are nested here rather than in a separate controller
    // class: unlike Refill (child of Subscriber, but with its own
    // independent cross-subscriber admin list page), a variant has no
    // independent listing — it only ever gets created/edited/deleted
    // inline while editing its one parent product.

    public function storeVariant(Request $request, Product $product)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $variant = $product->variants()->create($validated);

        return response()->json($variant, 201);
    }

    public function updateVariant(Request $request, ProductVariant $productVariant)
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $productVariant->update($validated);

        return response()->json($productVariant);
    }

    public function destroyVariant(ProductVariant $productVariant)
    {
        $productVariant->delete();

        return response()->json(['message' => 'Variant removed.']);
    }
}
