<?php
namespace App\Http\Controllers\API;

use App\Models\Product;
use Illuminate\Http\Client\Request;
use App\Http\Controllers\Controller as Controller;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-products');

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|integer|min:0',
        ]);

        $product = Product::create($validatedData);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('manage-products');

        $validatedData = $request->validate([
            'name' => 'string|max:255',
            'amount' => 'integer|min:0',
        ]);

        $product->update($validatedData);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('manage-products');

        $product->delete();

        return response()->json(null, 204);
    }
}
