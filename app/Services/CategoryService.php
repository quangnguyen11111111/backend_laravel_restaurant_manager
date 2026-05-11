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
    public function indexForUser(bool $listTree = true): array
    {
        if ($listTree) {
            $rootCategories = $this->categoryRepository->getRootCategories();

            $tree = collect($rootCategories)
                ->map(fn(Category $category): array => $this->buildCategoryTree($category))
                ->values()
                ->toArray();
        } else {
            $tree = $this->buildCategoryTreeWithOrderFilter();
        }

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

            $parentId = (int) $validated['parent_id'];
            if ($this->isDescendant($id, $parentId)) {
                throw new ServiceException('Không thể chọn danh mục con làm cha', 400);
            }

            $parentCategory = $this->categoryRepository->findById($parentId);
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
     * Kiểm tra danh mục có phải là hậu duệ của danh mục hiện tại không
     */
    private function isDescendant(int $categoryId, int $possibleParentId): bool
    {
        $categories = $this->categoryRepository->getAll();
        $childrenMap = [];

        foreach ($categories as $category) {
            if ($category->parent_id !== null) {
                $parentId = (int) $category->parent_id;
                $childrenMap[$parentId][] = (int) $category->id;
            }
        }

        $queue = $childrenMap[$categoryId] ?? [];
        $visited = [];

        for ($index = 0; $index < count($queue); $index++) {
            $currentId = $queue[$index];

            if ($currentId === $possibleParentId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            if (!empty($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childId) {
                    $queue[] = $childId;
                }
            }
        }

        return false;
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
            'parent_id' => $category->parent_id,
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

    /**
     * Xây dựng cây category chỉ lấy các danh mục có order khác 0
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryTreeWithOrderFilter(): array
    {
        $categories = $this->categoryRepository->getAll();
        $categoryMap = [];
        $childrenMap = [];

        foreach ($categories as $category) {
            if ((int) $category->order === 0) {
                continue;
            }

            $categoryMap[$category->id] = $category;

            if ($category->parent_id !== null) {
                $parentId = (int) $category->parent_id;
                $childrenMap[$parentId][] = (int) $category->id;
            }
        }

        $roots = [];
        foreach ($categoryMap as $id => $category) {
            $parentId = $category->parent_id !== null ? (int) $category->parent_id : null;
            if ($parentId === null || !isset($categoryMap[$parentId])) {
                $roots[] = $id;
            }
        }

        $tree = [];
        foreach ($roots as $rootId) {
            $tree[] = $this->buildCategoryTreeFromMaps($rootId, $categoryMap, $childrenMap);
        }

        return $tree;
    }

    /**
     * Xây dựng node tree từ map category theo id
     * @param array<int, Category> $categoryMap
     * @param array<int, int[]> $childrenMap
     * @return array<string, mixed>
     */
    private function buildCategoryTreeFromMaps(int $categoryId, array $categoryMap, array $childrenMap): array
    {
        $category = $categoryMap[$categoryId];

        $data = [
            'id' => $category->id,
            'name' => $category->name,
            'status' => $category->status,
        ];

        if (!empty($childrenMap[$categoryId])) {
            $data['children'] = array_map(
                fn(int $childId): array => $this->buildCategoryTreeFromMaps($childId, $categoryMap, $childrenMap),
                $childrenMap[$categoryId]
            );
        }

        return $data;
    }
}
