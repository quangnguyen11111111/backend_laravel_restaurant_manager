<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * Lấy danh sách category phẳng với phân trang
     */
    public function getPaginatedFlat(int $perPage, int $page): LengthAwarePaginator
    {
        return Category::query()
            ->orderBy('order')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Lấy tất cả category (không phân trang)
     */
    public function getAll(): Collection
    {
        return Category::query()
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Lấy danh sách category cha (parent_id = null)
     */
    public function getRootCategories(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->with('children')
            ->get();
    }

    /**
     * Tìm category theo ID
     */
    public function findById(int $id): ?Category
    {
        return Category::query()
            ->with('children')
            ->find($id);
    }

    /**
     * Tạo category mới
     */
    public function create(array $attributes): Category
    {
        return Category::query()->create($attributes);
    }

    /**
     * Cập nhật category
     */
    public function update(Category $category, array $attributes): bool
    {
        return $category->update($attributes);
    }

    /**
     * Xóa category
     */
    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }
}
