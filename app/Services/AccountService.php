<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Services\Contracts\ImageStorageServiceInterface;
use App\Services\Contracts\PendingImageWorkflowServiceInterface;
use App\Support\ImageScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Throwable;

class AccountService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly PendingImageWorkflowServiceInterface $pendingImageWorkflowService,
        private readonly ImageStorageServiceInterface $imageStorageService
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
        $hasNewAvatar = !empty($validated['avatarS3Key']);
        $oldName = $account->name;
        $newAvatarUrl = $account->avatar;
        $newAvatarS3Key = $account->avatar_s3_key;
        $oldAvatarUrl = $account->avatar;
        $oldAvatarS3Key = $account->avatar_s3_key;

        if ($hasNewAvatar) {
            $avatarS3Key = (string) $validated['avatarS3Key'];

            if (!$this->pendingImageWorkflowService->isPendingKeyOwnedBy(
                $avatarS3Key,
                ImageScope::ACCOUNT_AVATAR,
                $account->id
            )) {
                throw new ServiceException('Ảnh tải lên không hợp lệ hoặc đã hết phiên.', 422, [
                    ['field' => 'avatarS3Key', 'message' => 'Ảnh tải lên không hợp lệ hoặc đã hết phiên.'],
                ]);
            }

            try {
                $finalizedAvatar = $this->pendingImageWorkflowService->finalizePendingImage(
                    $avatarS3Key,
                    ImageScope::ACCOUNT_AVATAR,
                    $account->id
                );
            } catch (InvalidArgumentException) {
                throw new ServiceException('Ảnh tải lên không hợp lệ hoặc đã hết phiên.', 422, [
                    ['field' => 'avatarS3Key', 'message' => 'Ảnh tải lên không hợp lệ hoặc đã hết phiên.'],
                ]);
            } catch (Throwable $exception) {
                report($exception);

                throw new ServiceException('Không thể xác nhận ảnh tải lên.', 500);
            }

            $newAvatarUrl = $finalizedAvatar['url'];
            $newAvatarS3Key = $finalizedAvatar['key'];
        }

        $updated = $this->accountRepository->update($account, [
            'name' => $validated['name'],
            'avatar' => $newAvatarUrl,
            'avatar_s3_key' => $newAvatarS3Key,
        ]);

        if (!$updated) {
            if ($hasNewAvatar) {
                $this->safeDeleteImage((string) $newAvatarS3Key);
            }

            throw new ServiceException('Không thể cập nhật thông tin.', 500);
        }

        if ($hasNewAvatar && $oldAvatarS3Key && $oldAvatarS3Key !== $newAvatarS3Key) {
            try {
                $this->imageStorageService->deleteImage($oldAvatarS3Key);
            } catch (Throwable $exception) {
                report($exception);

                $this->accountRepository->update($account, [
                    'name' => $oldName,
                    'avatar' => $oldAvatarUrl,
                    'avatar_s3_key' => $oldAvatarS3Key,
                ]);

                $this->safeDeleteImage((string) $newAvatarS3Key);

                throw new ServiceException('Không thể xóa ảnh cũ, thao tác đã được hoàn tác.', 500);
            }
        }

        return [
            'data' => $this->mapAccount($account),
            'message' => 'Cập nhật thông tin thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadAvatar(Account $account, UploadedFile $image): array
    {
        try {
            $uploadedAvatar = $this->pendingImageWorkflowService->uploadPendingImage(
                $image,
                ImageScope::ACCOUNT_AVATAR,
                $account->id
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceException('Đã xảy ra lỗi khi tải ảnh lên.', 500);
        }

        return [
            'data' => [
                'avatar' => $uploadedAvatar['url'],
                'avatarS3Key' => $uploadedAvatar['key'],
            ],
            'message' => 'Tải ảnh đại diện thành công',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteUploadedAvatar(Account $account, string $avatarS3Key): array
    {
        if (!$this->pendingImageWorkflowService->isPendingKeyOwnedBy(
            $avatarS3Key,
            ImageScope::ACCOUNT_AVATAR,
            $account->id
        )) {
            throw new ServiceException('Khóa ảnh tạm không hợp lệ.', 422, [
                ['field' => 'avatarS3Key', 'message' => 'Khóa ảnh tạm không hợp lệ.'],
            ]);
        }

        if ($account->avatar_s3_key === $avatarS3Key) {
            throw new ServiceException('Không thể xóa ảnh đang được sử dụng.', 422, [
                ['field' => 'avatarS3Key', 'message' => 'Không thể xóa ảnh đang được sử dụng.'],
            ]);
        }

        try {
            $this->pendingImageWorkflowService->deletePendingImage(
                $avatarS3Key,
                ImageScope::ACCOUNT_AVATAR,
                $account->id
            );
        } catch (InvalidArgumentException) {
            throw new ServiceException('Khóa ảnh tạm không hợp lệ.', 422, [
                ['field' => 'avatarS3Key', 'message' => 'Khóa ảnh tạm không hợp lệ.'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceException('Không thể xóa ảnh tạm trên S3.', 500);
        }

        return [
            'message' => 'Xóa ảnh tạm thành công',
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

    private function safeDeleteImage(string $avatarS3Key): void
    {
        try {
            $this->imageStorageService->deleteImage($avatarS3Key);
        } catch (Throwable) {
            // Best-effort cleanup for failed update/rollback.
        }
    }
}
