<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AccountService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $accounts = $this->accountRepository
            ->getAllOrderByCreatedAtDesc()
            ->map(fn (Account $account): array => $this->mapAccount($account));

        return [
            'data' => $accounts,
            'message' => 'Lấy danh sách nhân viên thành công',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function store(array $validated): array
    {
        $account = $this->accountRepository->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'avatar' => $validated['avatar'] ?? null,
            'role' => Account::ROLE_EMPLOYEE,
        ]);

        return [
            'data' => $this->mapAccount($account),
            'message' => 'Tạo tài khoản thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $account = $this->accountRepository->findByIdOrFail($id);

        return [
            'data' => $this->mapAccount($account),
            'message' => 'Lấy thông tin nhân viên thành công',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function update(int $id, array $validated): array
    {
        $account = $this->accountRepository->findById($id);

        if (!$account) {
            throw new ServiceException(
                'Tài khoản bạn đang cập nhật không còn tồn tại nữa!',
                422,
                [
                    ['field' => 'email', 'message' => 'Tài khoản bạn đang cập nhật không còn tồn tại nữa!'],
                ]
            );
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'avatar' => $validated['avatar'] ?? $account->avatar,
            'role' => $validated['role'] ?? $account->role,
        ];

        if (!empty($validated['changePassword']) && !empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $this->accountRepository->update($account, $updateData);

        return [
            'data' => $this->mapAccount($account),
            'message' => 'Cập nhật thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(int $id): array
    {
        $account = $this->accountRepository->findByIdOrFail($id);
        $accountData = $this->mapAccount($account);

        $this->accountRepository->delete($account);

        return [
            'data' => $accountData,
            'message' => 'Xóa thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function me(Account $account): array
    {
        return [
            'data' => $this->mapAccount($account),
            'message' => 'Lấy thông tin thành công',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function updateMe(Account $account, array $validated): array
    {
        $this->accountRepository->update($account, [
            'name' => $validated['name'],
            'avatar' => $validated['avatar'] ?? $account->avatar,
        ]);

        return [
            'data' => $this->mapAccount($account),
            'message' => 'Cập nhật thông tin thành công',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function changePassword(Account $account, array $validated): array
    {
        if (!Hash::check($validated['oldPassword'], $account->password)) {
            throw new ServiceException(
                'Mật khẩu cũ không đúng',
                422,
                [
                    ['field' => 'oldPassword', 'message' => 'Mật khẩu cũ không đúng'],
                ]
            );
        }

        $this->accountRepository->update($account, [
            'password' => Hash::make($validated['password']),
        ]);

        return [
            'data' => $this->mapAccount($account),
            'message' => 'Đổi mật khẩu thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAccount(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'role' => $account->role,
            'avatar' => $account->avatar,
        ];
    }
}
