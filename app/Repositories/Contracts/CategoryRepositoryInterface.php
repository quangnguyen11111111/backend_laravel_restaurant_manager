<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    /**
     * Lấy danh sách category phẳng với phân trang
     */
    public function getPaginatedFlat(int $perPage, int $page): LengthAwarePaginator;

    /**
     * Lấy tất cả category (không phân trang)
     */
    public function getAll(): Collection;

    /**
     * Lấy danh sách category cha (parent_id = null)
     */
    public function getRootCategories(): Collection;

    /**
     * Tìm category theo ID
     */
    public function findById(int $id): ?Category;

    /**
     * Tạo category mới
     */
    public function create(array $attributes): Category;

    /**
     * Cập nhật category
     */
    public function update(Category $category, array $attributes): bool;

    /**
     * Xóa category
     */
    public function delete(Category $category): bool;
}
