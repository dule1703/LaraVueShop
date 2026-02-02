<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with('category')->orderBy('name')->get();
        return Inertia::render('Admin/Products/Index', [
            'products' => $products
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'required|url',
            'is_active' => 'boolean'
        ]);

        Product::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image' => $validated['image'],
            'is_active' => $request->boolean('is_active', true),           
        ]);
        return Redirect::route('admin.products.index')->with('success', 'Product is successfully created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
        {
            $product->load('category');
            $categories = Category::where('is_active', true)->orderBy('name')->get();

            return Inertia::render('Admin/Products/Edit', [
                'product' => $product,
                'categories' => $categories
            ]);
        }

    /**
     * Update the specified resource in storage.
     */
public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'required|url',
            'is_active' => 'boolean'
        ]);

        $product->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'image' => $validated['image'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return Redirect::route('admin.products.index')->with('success', 'Product is successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return Redirect::route('admin.products.index')->with('success', 'Product is successfully deleted!');
    }

    public function publicIndex()
    {
        $categories = Category::with(['products' => function ($query) {
            $query->where('is_active', true); 
        }])->get();

        return Inertia::render('Shop', ['categories' => $categories]);
    }

    public function publicShow(Product $product)
    {
        if (!$product->is_active) {
            abort(404);
        }

        return Inertia::render('Product', ['product' => $product]);
    }
}
