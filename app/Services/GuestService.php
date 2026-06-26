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

    public function generateTokens(Guest $guest): array
    {
        $now = time();
        $accessExp = $now + $this->parseExpiry(config('auth.guest_access_token_expires_in', config('auth.access_token_expires_in', '1d')));
        $refreshExp = $now + $this->parseExpiry(config('auth.guest_refresh_token_expires_in', config('auth.refresh_token_expires_in', '7d')));

        $accessPayload = [
            'userId' => $guest->id,
            'role' => Guest::ROLE_GUEST,
            'tokenType' => 'AccessToken',
            'iat' => $now,
            'exp' => $accessExp,
        ];

        $refreshPayload = [
            'userId' => $guest->id,
            'role' => Guest::ROLE_GUEST,
            'tokenType' => 'RefreshToken',
            'iat' => $now,
            'exp' => $refreshExp,
        ];

        $accessToken = \Firebase\JWT\JWT::encode($accessPayload, config('auth.access_token_secret'), 'HS256');
        $refreshToken = \Firebase\JWT\JWT::encode($refreshPayload, config('auth.refresh_token_secret'), 'HS256');

        $guest->refresh_token = $refreshToken;
        $guest->refresh_token_expires_at = date('Y-m-d H:i:s', $refreshExp);
        $guest->save();

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ];
    }

    public function parseExpiry(string $expiry): int
    {
        $unit = substr($expiry, -1);
        $value = (int) substr($expiry, 0, -1);

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => (int) $expiry,
        };
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
