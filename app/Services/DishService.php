<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Account;
use App\Models\Dish;
use App\Repositories\Contracts\DishRepositoryInterface;
use App\Services\Contracts\MediaUploadServiceInterface;
use App\Support\ImageScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use Throwable;

class DishService
{
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly DishRepositoryInterface $dishRepository,
        private readonly MediaUploadServiceInterface $mediaUploadService
    ) {}

    /**
     * Lấy danh sách dishes cho admin (tất cả)
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function indexForAdmin(array $validated): array
    {
        $page = (int) ($validated['page'] ?? 1);

        $paginatedDishes = $this->dishRepository->getPaginatedForAdmin(
            self::INDEX_PER_PAGE,
            $page
        );

        $dishes = collect($paginatedDishes->items())
            ->map(fn(Dish $dish): array => $this->mapDishForAdmin($dish))
            ->values();

        return [
            'data' => $dishes,
            'pagination' => [
                'page' => $paginatedDishes->currentPage(),
                'perPage' => $paginatedDishes->perPage(),
                'totalItems' => $paginatedDishes->total(),
                'totalPages' => $paginatedDishes->lastPage(),
                'hasNextPage' => $paginatedDishes->hasMorePages(),
                'hasPreviousPage' => $paginatedDishes->currentPage() > 1,
            ],
            'message' => 'Lấy danh sách món ăn thành công!',
        ];
    }

    /**
     * Lấy danh sách dishes cho user (theo category hoặc tất cả)
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function indexForUser(array $validated): array
    {
        $page = (int) ($validated['page'] ?? 1);
        $categoryId = $validated['category_id'] ?? null;

        if ($categoryId === null || $categoryId == 0) {
            $paginatedDishes = $this->dishRepository->getPaginatedForUser(
                self::INDEX_PER_PAGE,
                $page
            );
        } else {
            $categoryId = (int) $categoryId;

            if ($categoryId < 0) {
                throw new ServiceException('category_id không hợp lệ', 400);
            }

            $category = Category::with('children')
                ->find($categoryId);

            if (!$category) {
                throw new ServiceException('Danh mục không tồn tại', 404);
            }

            // Lấy tất cả category_id
            $childrenIds = $category->getAllChildrenIds();

            $categoryIds = array_merge(
                [$category->id],
                $childrenIds
            );

            // Lấy dishes theo category_id (bao gồm cả category con)
            $paginatedDishes = $this->dishRepository->getPaginatedByCategoryId(
                $categoryIds,
                self::INDEX_PER_PAGE,
                $page
            );
        }

        $dishes = collect($paginatedDishes->items())
            ->map(fn(Dish $dish): array => $this->mapDishForUser($dish))
            ->values();

        return [
            'data' => $dishes,
            'pagination' => [
                'page' => $paginatedDishes->currentPage(),
                'perPage' => $paginatedDishes->perPage(),
                'totalItems' => $paginatedDishes->total(),
                'totalPages' => $paginatedDishes->lastPage(),
                'hasNextPage' => $paginatedDishes->hasMorePages(),
                'hasPreviousPage' => $paginatedDishes->currentPage() > 1,
            ],
            'message' => 'Lấy danh sách món ăn thành công!',
        ];
    }

    /**
     * Lấy danh sách dishes cũ (deprecated - giữ để backward compatible)
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function index(array $validated): array
    {
        $page = (int) ($validated['page'] ?? 1);

        $paginatedDishes = $this->dishRepository->getPaginatedOrderByCreatedAtDesc(
            self::INDEX_PER_PAGE,
            $page
        );

        $dishes = collect($paginatedDishes->items())
            ->map(fn(Dish $dish): array => $this->mapDish($dish))
            ->values();

        return [
            'data' => $dishes,
            'pagination' => [
                'page' => $paginatedDishes->currentPage(),
                'perPage' => $paginatedDishes->perPage(),
                'totalItems' => $paginatedDishes->total(),
                'totalPages' => $paginatedDishes->lastPage(),
                'hasNextPage' => $paginatedDishes->hasMorePages(),
                'hasPreviousPage' => $paginatedDishes->currentPage() > 1,
            ],
            'message' => 'Lấy danh sách món ăn thành công!',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $dish = $this->dishRepository->findById($id);

        if (!$dish) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        return [
            'data' => $this->mapDish($dish),
            'message' => 'Lấy thông tin món ăn thành công!',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function store(array $validated, Account $actor): array
    {
        $hasNewImage = !empty($validated['imageS3Key']);
        $newImageS3Key = null;
        try {
            return DB::transaction(function () use ($validated, $actor, $hasNewImage, &$newImageS3Key): array {
                $newImageUrl = $validated['image'];
                $finalizedImageS3Key = null;

                if ($hasNewImage) {
                    $finalizedImage = $this->handleProductImage(
                        (string) $validated['imageS3Key'],
                        $actor->id
                    );

                    $newImageUrl = $finalizedImage['url'];
                    $finalizedImageS3Key = $finalizedImage['key'];
                    $newImageS3Key = $finalizedImageS3Key;
                }

                $attributes = [
                    'name' => $validated['name'],
                    'price' => (int) $validated['price'],
                    'description' => $validated['description'],
                    'image' => $newImageUrl,
                    'image_s3_key' => $finalizedImageS3Key,
                ];

                if (array_key_exists('status', $validated) && !empty($validated['status'])) {
                    $attributes['status'] = $validated['status'];
                }

                if (!empty($validated['category_id'])) {
                    $attributes['category_id'] = (int) $validated['category_id'];
                }

                $dish = $this->dishRepository->create($attributes);
                $dish->refresh();

                return [
                    'data' => $this->mapDish($dish),
                    'message' => 'Tạo món ăn thành công!',
                ];
            });
        } catch (ServiceException $exception) {
            if ($hasNewImage && $newImageS3Key !== null) {
                $this->mediaUploadService->safeDeleteImage($newImageS3Key);
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($hasNewImage && $newImageS3Key !== null) {
                $this->mediaUploadService->safeDeleteImage($newImageS3Key);
            }

            report($exception);

            throw new ServiceException('Không thể tạo món ăn.', 500);
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function update(int $id, array $validated, Account $actor): array
    {
        $dish = $this->dishRepository->findById($id);

        if (!$dish) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        $hasNewImage = !empty($validated['imageS3Key']);
        $oldName = $dish->name;
        $oldPrice = $dish->price;
        $oldDescription = $dish->description;
        $oldImage = $dish->image;
        $oldImageS3Key = $dish->image_s3_key;
        $oldStatus = $dish->status;
        $oldCategoryId = $dish->category_id;

        $newImageUrl = $validated['image'];
        $newImageS3Key = $dish->image_s3_key;

        if ($hasNewImage) {
            $finalizedImage = $this->handleProductImage(
                (string) $validated['imageS3Key'],
                $actor->id
            );

            $newImageUrl = $finalizedImage['url'];
            $newImageS3Key = $finalizedImage['key'];
        } elseif ($newImageUrl !== $oldImage) {
            // Client cập nhật ảnh thủ công bằng URL mới thì không giữ S3 key cũ.
            $newImageS3Key = null;
        }

        $attributes = [
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'description' => $validated['description'],
            'image' => $newImageUrl,
            'image_s3_key' => $newImageS3Key,
        ];

        if (array_key_exists('status', $validated) && !empty($validated['status'])) {
            $attributes['status'] = $validated['status'];
        }

        if (array_key_exists('category_id', $validated)) {
            $attributes['category_id'] = !empty($validated['category_id']) ? (int) $validated['category_id'] : null;
        }

        $updated = $this->dishRepository->update($dish, $attributes);

        if (!$updated) {
            if ($hasNewImage) {
                $this->mediaUploadService->safeDeleteImage((string) $newImageS3Key);
            }

            throw new ServiceException('Không thể cập nhật món ăn.', 500);
        }

        if ($hasNewImage && $oldImageS3Key && $oldImageS3Key !== $newImageS3Key) {
            try {
                $this->mediaUploadService->deleteImage(
                    $oldImageS3Key,
                    'Không thể xóa ảnh món ăn cũ trên S3.'
                );
            } catch (ServiceException) {
                $this->dishRepository->update($dish, [
                    'name' => $oldName,
                    'price' => $oldPrice,
                    'description' => $oldDescription,
                    'image' => $oldImage,
                    'image_s3_key' => $oldImageS3Key,
                    'status' => $oldStatus,
                    'category_id' => $oldCategoryId,
                ]);

                $this->mediaUploadService->safeDeleteImage((string) $newImageS3Key);

                throw new ServiceException('Không thể xóa ảnh cũ, thao tác đã được hoàn tác.', 500);
            }
        }

        $dish->refresh();

        return [
            'data' => $this->mapDish($dish),
            'message' => 'Cập nhật món ăn thành công!',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(int $id): array
    {
        $dish = $this->dishRepository->findById($id);

        if (!$dish) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        if (!empty($dish->image_s3_key)) {
            $this->mediaUploadService->deleteImage(
                (string) $dish->image_s3_key,
                'Không thể xóa ảnh món ăn trên S3.'
            );
        }

        $dishData = $this->mapDish($dish);
        $deleted = $this->dishRepository->delete($dish);

        if (!$deleted) {
            throw new ServiceException('Không thể xóa món ăn.', 500);
        }

        return [
            'data' => $dishData,
            'message' => 'Xóa món ăn thành công!',
        ];
    }

    /**
     * Map dish cho admin (đầy đủ thông tin)
     * @return array<string, mixed>
     */
    private function mapDishForAdmin(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'price' => $dish->price,
            'description' => $dish->description,
            'image' => $dish->image,
            'status' => $dish->status,
            'category_id' => $dish->category_id,
            'category' => $dish->category ? [
                'id' => $dish->category->id,
                'name' => $dish->category->name,
            ] : null,
            'createdAt' => $dish->created_at?->toIso8601String(),
            'updatedAt' => $dish->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Map dish cho user (thông tin hiển thị)
     * @return array<string, mixed>
     */
    private function mapDishForUser(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'price' => $dish->price,
            'description' => $dish->description,
            'image' => $dish->image,
            'status' => $dish->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDish(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'price' => $dish->price,
            'description' => $dish->description,
            'image' => $dish->image,
            'imageS3Key' => $dish->image_s3_key,
            'status' => $dish->status,
            'category_id' => $dish->category_id,
            'createdAt' => $dish->created_at,
            'updatedAt' => $dish->updated_at,
        ];
    }

    public function uploadImage(Account $account, UploadedFile $image): array
    {
        $uploadedImage = $this->mediaUploadService->uploadPendingImage(
            $image,
            ImageScope::PRODUCT_IMAGE,
            $account->id,
            'Đã xảy ra lỗi khi tải ảnh lên.'
        );

        return [
            'data' => [
                'image' => $uploadedImage['url'],
                'imageS3Key' => $uploadedImage['key'],
            ],
            'message' => 'Tải ảnh món ăn thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteUploadedImage(Account $account, string $imageS3Key): array
    {
        $this->mediaUploadService->deletePendingImage(
            $imageS3Key,
            ImageScope::PRODUCT_IMAGE,
            $account->id,
            'imageS3Key',
            'Khóa ảnh tạm không hợp lệ.',
            'Không thể xóa ảnh tạm trên S3.'
        );

        return [
            'message' => 'Xóa ảnh tạm thành công',
        ];
    }

    public function uploadAvatar(Account $account, UploadedFile $image): array
    {
        return $this->uploadImage($account, $image);
    }

    /**
     * @return array{key: string, url: string}
     */
    private function handleProductImage(string $imageS3Key, int $ownerId): array
    {
        return $this->mediaUploadService->finalizePendingImage(
            $imageS3Key,
            ImageScope::PRODUCT_IMAGE,
            $ownerId,
            'imageS3Key',
            'Ảnh món ăn tải lên không hợp lệ hoặc đã hết phiên.',
            'Không thể xác nhận ảnh món ăn tải lên.'
        );
    }
}
