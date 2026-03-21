<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ForumCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = ForumCategory::orderBy('order')
            ->withCount('threads')
            ->get();

        return Inertia::render('Admin/ForumCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        ForumCategory::create($validated);

        return back()->with('success', 'Kategori forum berhasil dibuat.');
    }

    public function update(Request $request, ForumCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $category->update($validated);

        return back()->with('success', 'Kategori forum berhasil diperbarui.');
    }

    public function destroy(ForumCategory $category): RedirectResponse
    {
        // Set threads in this category to uncategorized (null)
        $category->threads()->update(['forum_category_id' => null]);
        $category->delete();

        return back()->with('success', 'Kategori forum berhasil dihapus.');
    }
}
