<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use App\Models\Category;
use Inertia\Inertia;


class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::orderBy('name')->get();
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Categories/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->whereNull('deleted_at')],
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return Redirect::route('admin.categories.index')->with('success', 'Kategorija je uspešno dodata.');
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
    public function edit(Category $category)
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->whereNull('deleted_at')->ignore($category->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return Redirect::route('admin.categories.index')->with('success', 'Kategorija je uspešno izmenjena.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Soft delete ne okida FK cascade, pa proizvodi/potkategorije moraju da se provere ručno.
        if ($category->products()->exists() || $category->children()->exists()) {
            return Redirect::route('admin.categories.index')
                ->with('error', 'Kategorija ima proizvode ili potkategorije i ne može se obrisati. Premesti ih ili obriši prvo, ili deaktiviraj kategoriju.');
        }

        try {
            $category->delete();
        } catch (QueryException $e) {
            report($e);

            return Redirect::route('admin.categories.index')
                ->with('error', 'Kategoriju trenutno nije moguće obrisati. Deaktiviraj je umesto toga.');
        }

        return Redirect::route('admin.categories.index')->with('success', 'Kategorija je uspešno obrisana.');
    }
}
