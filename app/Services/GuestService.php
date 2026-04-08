<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Guest;
use App\Models\Table;
use App\Repositories\Contracts\GuestRepositoryInterface;

class GuestService
{
    public function __construct(
        private readonly GuestRepositoryInterface $guestRepository
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function createGuest(array $validated): array
    {
        $table = $this->guestRepository->findTableByNumber((int) $validated['tableNumber']);

        if (!$table) {
            throw new ServiceException('Bàn không tồn tại', 400);
        }

        if ($table->status === Table::STATUS_HIDDEN) {
            throw new ServiceException("Bàn {$table->number} đã bị ẩn, vui lòng chọn bàn khác", 400);
        }

        $guest = $this->guestRepository->create([
            'name' => $validated['name'],
            'table_number' => $validated['tableNumber'],
        ]);

        return [
            'message' => 'Tạo tài khoản khách thành công',
            'data' => $this->mapGuest($guest),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function getGuests(array $validated): array
    {
        $guests = $this->guestRepository
            ->getByFilters($validated['fromDate'] ?? null, $validated['toDate'] ?? null)
            ->map(fn (Guest $guest): array => $this->mapGuest($guest));

        return [
            'message' => 'Lấy danh sách khách thành công',
            'data' => $guests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGuest(Guest $guest): array
    {
        return [
            'id' => $guest->id,
            'name' => $guest->name,
            'role' => Guest::ROLE_GUEST,
            'tableNumber' => $guest->table_number,
            'createdAt' => $guest->created_at,
            'updatedAt' => $guest->updated_at,
        ];
    }
}
