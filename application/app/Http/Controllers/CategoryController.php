<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;

class CategoryController extends Controller
{
    public function index(CategoryService $categoryService)
    {
        return [
            "data" => $categoryService->getCategoriesList(),
        ];
    }

    public function create(CategoryRequest $request, CategoryService $categoryService)
    {
        $categoryService->createCategory(auth()->user(), $request->toDTO());
        return response()->json(["success" => true], 201);
    }

    public function update(CategoryRequest $request, Category $category, CategoryService $categoryService)
    {
        $categoryService->updateCategory(auth()->user(), $category, $request->toDTO());
        return ["success" => true];
    }

    public function delete(Category $category, CategoryService $categoryService)
    {
        $categoryService->deleteCategory(auth()->user(), $category);
        return ["success" => true];
    }
}
