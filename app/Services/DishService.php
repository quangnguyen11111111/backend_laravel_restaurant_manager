<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Dish;
use App\Repositories\Contracts\DishRepositoryInterface;

class DishService
{
    public function __construct(
        private readonly DishRepositoryInterface $dishRepository
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $dishes = $this->dishRepository
            ->getAllOrderByCreatedAtDesc()
            ->map(fn(Dish $dish): array => $this->mapDish($dish))
            ->values();

        return [
            'data' => $dishes,
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
    public function store(array $validated): array
    {
        $attributes = [
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'description' => $validated['description'],
            'image' => $validated['image'],
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
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function update(int $id, array $validated): array
    {
        $dish = $this->dishRepository->findById($id);

        if (!$dish) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        $attributes = [
            'name' => $validated['name'],
            'price' => (int) $validated['price'],
            'description' => $validated['description'],
            'image' => $validated['image'],
        ];

        if (array_key_exists('status', $validated) && !empty($validated['status'])) {
            $attributes['status'] = $validated['status'];
        }

        $updated = $this->dishRepository->update($dish, $attributes);

        if (!$updated) {
            throw new ServiceException('Không thể cập nhật món ăn.', 500);
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
            'status' => $dish->status,
            'createdAt' => $dish->created_at,
            'updatedAt' => $dish->updated_at,
        ];
    }
}
