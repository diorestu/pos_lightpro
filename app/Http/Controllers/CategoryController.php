<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::withCount('products')->orderBy('name')->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:25',
            'description' => 'nullable|string',
        ]);

        $slug = Str::slug($validated['name']);
        // Ensure uniqueness
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        Category::create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?: Category::randomPaletteColor(),
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', "Kategori {$validated['name']} berhasil dibuat!");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:25',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['color']) && empty($category->color)) {
            $validated['color'] = Category::randomPaletteColor();
        }

        $category->update($validated);

        return back()->with('success', "Kategori {$category->name} berhasil diperbarui!");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', "Kategori tidak dapat dihapus karena masih memiliki {$category->products()->count()} produk terkait.");
        }

        $name = $category->name;
        $category->delete();

        return back()->with('success', "Kategori {$name} berhasil dihapus!");
    }
}
