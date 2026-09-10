<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Data\Categories\CategoryData;
use App\Models\Category;
use App\Models\User;

class CategoryService
{
    public function __construct(
        readonly private AuditLogContract $categoryAuditLogService
    )
    {}

    public function createCategory(User $creator, CategoryData $categoryData)
    {
        Category::create([
            "name" => $categoryData->name,
            "description" => $categoryData->description,
        ]);

        $this->categoryAuditLogService->log("category_created", user_id: $creator->id);
    }

    public function getCategoriesList()
    {
        return Category::query()
            ->orderBy("created_at")
            ->get();
    }

    public function updateCategory(User $user, Category $category, CategoryData $categoryData)
    {
        $category->fill((array) $categoryData);
        $category->save();
        $this->categoryAuditLogService->log("category_updated", user_id: $user->id, parameters: (array) $categoryData);
    }

    public function deleteCategory(User $user, Category $category): void
    {
        if ($category->events()->exists()) {
            abort(409, "Category is used by events.");
        }

        $category->delete();

        $this->categoryAuditLogService->log(
            "category_deleted",
            user_id: $user->id,
        );
    }
}
