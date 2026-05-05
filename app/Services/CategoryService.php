<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

class CategoryService
{
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {}

    /**
     * Lấy danh sách category dạng phẳng cho admin (có phân trang)
     * @return array<string, mixed>
     */
    public function indexForAdmin(array $validated): array
    {
        $page = (int) ($validated['page'] ?? 1);

        $paginatedCategories = $this->categoryRepository->getPaginatedFlat(
            self::INDEX_PER_PAGE,
            $page
        );

        $categories = collect($paginatedCategories->items())
            ->map(fn(Category $category): array => $this->mapCategoryFlat($category))
            ->values();

        return [
            'data' => $categories,
            'pagination' => [
                'page' => $paginatedCategories->currentPage(),
                'perPage' => $paginatedCategories->perPage(),
                'totalItems' => $paginatedCategories->total(),
                'totalPages' => $paginatedCategories->lastPage(),
                'hasNextPage' => $paginatedCategories->hasMorePages(),
                'hasPreviousPage' => $paginatedCategories->currentPage() > 1,
            ],
            'message' => 'Lấy danh sách danh mục thành công!',
        ];
    }

    /**
     * Lấy danh sách category dạng cây cho user (không phân trang)
     * @return array<string, mixed>
     */
    public function indexForUser(): array
    {
        $rootCategories = $this->categoryRepository->getRootCategories();

        $tree = collect($rootCategories)
            ->map(fn(Category $category): array => $this->buildCategoryTree($category))
            ->values();

        return [
            'data' => $tree,
            'message' => 'Lấy danh sách danh mục thành công!',
        ];
    }

    /**
     * Lấy chi tiết category
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            throw new ServiceException('Không tìm thấy danh mục', 404);
        }

        return [
            'data' => $this->mapCategoryFlat($category),
            'message' => 'Lấy thông tin danh mục thành công!',
        ];
    }

    /**
     * Tạo category mới
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function store(array $validated): array
    {
        // Validate parent_id nếu có
        if (!empty($validated['parent_id'])) {
            $parentCategory = $this->categoryRepository->findById((int) $validated['parent_id']);
            if (!$parentCategory) {
                throw new ServiceException('Danh mục cha không tồn tại', 404);
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'parent_id' => !empty($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        ];

        if (array_key_exists('status', $validated) && !empty($validated['status'])) {
            $attributes['status'] = $validated['status'];
        }

        if (array_key_exists('order', $validated) && $validated['order'] !== null) {
            $attributes['order'] = (int) $validated['order'];
        }

        $category = $this->categoryRepository->create($attributes);
        $category->refresh();

        return [
            'data' => $this->mapCategoryFlat($category),
            'message' => 'Tạo danh mục thành công!',
        ];
    }

    /**
     * Cập nhật category
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function update(int $id, array $validated): array
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            throw new ServiceException('Không tìm thấy danh mục', 404);
        }

        // Validate parent_id nếu có
        if (!empty($validated['parent_id'])) {
            if ((int) $validated['parent_id'] === $id) {
                throw new ServiceException('Không thể chọn danh mục này làm cha của chính nó', 400);
            }

            $parentCategory = $this->categoryRepository->findById((int) $validated['parent_id']);
            if (!$parentCategory) {
                throw new ServiceException('Danh mục cha không tồn tại', 404);
            }
        }

        $attributes = [];

        if (!empty($validated['name'])) {
            $attributes['name'] = $validated['name'];
        }

        if (array_key_exists('parent_id', $validated)) {
            $attributes['parent_id'] = !empty($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        }

        if (array_key_exists('status', $validated) && !empty($validated['status'])) {
            $attributes['status'] = $validated['status'];
        }

        if (array_key_exists('order', $validated) && $validated['order'] !== null) {
            $attributes['order'] = (int) $validated['order'];
        }

        $this->categoryRepository->update($category, $attributes);
        $category->refresh();

        return [
            'data' => $this->mapCategoryFlat($category),
            'message' => 'Cập nhật danh mục thành công!',
        ];
    }

    /**
     * Xóa category
     * @return array<string, mixed>
     */
    public function destroy(int $id): array
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            throw new ServiceException('Không tìm thấy danh mục', 404);
        }

        $this->categoryRepository->delete($category);

        return [
            'message' => 'Xóa danh mục thành công!',
        ];
    }

    /**
     * Map category sang dạng phẳng
     * @return array<string, mixed>
     */
    private function mapCategoryFlat(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'status' => $category->status,
            'order' => $category->order,
            'createdAt' => $category->created_at?->toIso8601String(),
            'updatedAt' => $category->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Xây dựng cây category (dạng nested)
     * @return array<string, mixed>
     */
    private function buildCategoryTree(Category $category): array
    {
        $data = [
            'id' => $category->id,
            'name' => $category->name,
            'status' => $category->status,
        ];

        // Nếu có children, thêm vào
        if ($category->children && count($category->children) > 0) {
            $data['children'] = collect($category->children)
                ->map(fn(Category $child): array => $this->buildCategoryTree($child))
                ->values()
                ->toArray();
        }

        return $data;
    }
}
