<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Table;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableService
{
    public function __construct(
        private readonly TableRepositoryInterface $tableRepository,
        private readonly GuestRepositoryInterface $guestRepository
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $tables = $this->tableRepository
            ->getAllOrderByCreatedAtDesc()
            ->map(fn(Table $table): array => $this->mapTable($table))
            ->values();

        return [
            'data' => $tables,
            'message' => 'Lấy danh sách bàn thành công!',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $number): array
    {
        $table = $this->tableRepository->findByNumber($number);

        if (!$table) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        return [
            'data' => $this->mapTable($table),
            'message' => 'Lấy thông tin bàn thành công!',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function store(array $validated): array
    {
        $number = (int) $validated['number'];

        if ($this->tableRepository->findByNumber($number) !== null) {
            throw new ServiceException('Lỗi xảy ra khi xác thực dữ liệu...', 422, [
                [
                    'field' => 'number',
                    'message' => 'Số bàn này đã tồn tại',
                ],
            ]);
        }

        $attributes = [
            'number' => $number,
            'capacity' => (int) $validated['capacity'],
            'max_capacity' => isset($validated['max_capacity']) ? (int) $validated['max_capacity'] : (int) $validated['capacity'] + 1,
            'group_id' => $validated['group_id'] ?? null,
            'group_order' => isset($validated['group_order']) ? (int) $validated['group_order'] : null,
            'token' => $this->generateToken(),
        ];

        if (array_key_exists('status', $validated) && !empty($validated['status'])) {
            $attributes['status'] = $validated['status'];
        }

        $table = $this->tableRepository->create($attributes);
        $table->refresh();

        return [
            'data' => $this->mapTable($table),
            'message' => 'Tạo bàn thành công!',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function update(int $number, array $validated): array
    {
        $table = $this->tableRepository->findByNumber($number);

        if (!$table) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        if ((bool) $validated['changeToken']) {
            return DB::transaction(function () use ($table, $validated, $number): array {
                $attributes = $this->buildUpdateAttributes($validated, true);
                $updated = $this->tableRepository->update($table, $attributes);

                if (!$updated) {
                    throw new ServiceException('Không thể cập nhật bàn.', 500);
                }

                $this->guestRepository->clearRefreshTokensByTableNumber($number);
                $table->refresh();

                return [
                    'data' => $this->mapTable($table),
                    'message' => 'Cập nhật bàn thành công!',
                ];
            });
        }

        $attributes = $this->buildUpdateAttributes($validated, false);
        $updated = $this->tableRepository->update($table, $attributes);

        if (!$updated) {
            throw new ServiceException('Không thể cập nhật bàn.', 500);
        }

        $table->refresh();

        return [
            'data' => $this->mapTable($table),
            'message' => 'Cập nhật bàn thành công!',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(int $number): array
    {
        $table = $this->tableRepository->findByNumber($number);

        if (!$table) {
            throw new ServiceException('Không tìm thấy dữ liệu', 404);
        }

        $tableData = $this->mapTable($table);
        $deleted = $this->tableRepository->delete($table);

        if (!$deleted) {
            throw new ServiceException('Không thể xóa bàn.', 500);
        }

        return [
            'data' => $tableData,
            'message' => 'Xóa bàn thành công!',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function buildUpdateAttributes(array $validated, bool $includeToken): array
    {
        $attributes = [
            'capacity' => (int) $validated['capacity'],
        ];

        if (isset($validated['max_capacity'])) {
            $attributes['max_capacity'] = (int) $validated['max_capacity'];
        }

        if (array_key_exists('group_id', $validated)) {
            $attributes['group_id'] = $validated['group_id'];
        }

        if (array_key_exists('group_order', $validated)) {
            $attributes['group_order'] = $validated['group_order'] ? (int) $validated['group_order'] : null;
        }

        if (array_key_exists('status', $validated) && !empty($validated['status'])) {
            $attributes['status'] = $validated['status'];
        }

        if ($includeToken) {
            $attributes['token'] = $this->generateToken();
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTable(Table $table): array
    {
        return [
            'number' => $table->number,
            'capacity' => $table->capacity,
            'max_capacity' => $table->max_capacity,
            'group_id' => $table->group_id,
            'group_order' => $table->group_order,
            'status' => $table->status,
            'token' => $table->token,
            'createdAt' => $table->created_at,
            'updatedAt' => $table->updated_at,
        ];
    }

    private function generateToken(): string
    {
        return str_replace('-', '', (string) Str::uuid());
    }
}
