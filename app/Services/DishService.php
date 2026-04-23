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
use Throwable;

class DishService
{
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly DishRepositoryInterface $dishRepository,
        private readonly MediaUploadServiceInterface $mediaUploadService
    ) {}

    /**
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
