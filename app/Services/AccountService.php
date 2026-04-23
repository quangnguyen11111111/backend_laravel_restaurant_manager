<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Services\Contracts\MediaUploadServiceInterface;
use App\Support\ImageScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountService
{
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly MediaUploadServiceInterface $mediaUploadService
    ) {}

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function index(array $validated): array
    {
        $page = (int) ($validated['page'] ?? 1);

        $paginatedAccounts = $this->accountRepository->getPaginatedOrderByCreatedAtDesc(
            self::INDEX_PER_PAGE,
            $page
        );

        $accounts = collect($paginatedAccounts->items())
            ->map(fn(Account $account): array => $this->mapAccount($account))
            ->values();

        return [
            'data' => $accounts,
            'pagination' => [
                'page' => $paginatedAccounts->currentPage(),
                'perPage' => $paginatedAccounts->perPage(),
                'totalItems' => $paginatedAccounts->total(),
                'totalPages' => $paginatedAccounts->lastPage(),
                'hasNextPage' => $paginatedAccounts->hasMorePages(),
                'hasPreviousPage' => $paginatedAccounts->currentPage() > 1,
            ],
            'message' => 'Lấy danh sách nhân viên thành công',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function store(array $validated): array
    {
        return DB::transaction(function () use ($validated) {

            $hasNewAvatar = !empty($validated['avatarS3Key']);

            // 1. Tạo account trước
            $account = $this->accountRepository->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'avatar' => null,
                'avatar_s3_key' => null,
                'role' => Account::ROLE_EMPLOYEE,
            ]);

            if ($hasNewAvatar) {
                $userIdOfUploader = $validated['userIdOfUploader'];
                $finalizedAvatar = $this->handleAvatar(
                    (string) $validated['avatarS3Key'],
                    $userIdOfUploader
                );


                $updated = $this->accountRepository->update($account, [
                    'avatar' => $finalizedAvatar['url'],
                    'avatar_s3_key' => $finalizedAvatar['key'],
                ]);

                if (!$updated) {
                    $this->safeDeleteImage((string) $finalizedAvatar['key']);
                    throw new ServiceException('Không thể cập nhật avatar.', 500);
                }
            }

            return [
                'data' => $this->mapAccount($account),
                'message' => 'Tạo tài khoản thành công',
            ];
        });
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
        $hasNewAvatar = !empty($validated['avatarS3Key']);
        $oldName = $account->name;
        $newAvatarUrl = $account->avatar;
        $newAvatarS3Key = $account->avatar_s3_key;
        $oldAvatarUrl = $account->avatar;
        $oldAvatarS3Key = $account->avatar_s3_key;

        // Xử lý avatar nếu có
        if ($hasNewAvatar) {
            // mã của người gửi ảnh
            $userIdOfUploader = $validated['userIdOfUploader'];
            $finalizedAvatar = $this->handleAvatar(
                (string) $validated['avatarS3Key'],
                $userIdOfUploader
            );

            $newAvatarUrl = $finalizedAvatar['url'];
            $newAvatarS3Key = $finalizedAvatar['key'];
        }
        //Chuẩn bị data update
        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'avatar' => $newAvatarUrl,
            'avatar_s3_key' => $newAvatarS3Key,
            'role' => $validated['role'] ?? $account->role,
        ];

        if (!empty($validated['changePassword']) && !empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }
        //Update DB
        $updated = $this->accountRepository->update($account, $updateData);
        if (!$updated) {
            if ($hasNewAvatar) {
                $this->safeDeleteImage((string) $newAvatarS3Key);
            }

            throw new ServiceException('Không thể cập nhật tài khoản.', 500);
        }
        //  Xóa ảnh cũ nếu có avatar mới
        if ($hasNewAvatar && $oldAvatarS3Key && $oldAvatarS3Key !== $newAvatarS3Key) {
            try {
                $this->mediaUploadService->deleteImage(
                    $oldAvatarS3Key,
                    'Không thể xóa ảnh đại diện cũ trên S3.'
                );
            } catch (ServiceException) {

                // rollback DB
                $this->accountRepository->update($account, [
                    'name' => $oldName,
                    'avatar' => $oldAvatarUrl,
                    'avatar_s3_key' => $oldAvatarS3Key,
                ]);

                // xóa ảnh mới
                $this->safeDeleteImage((string) $newAvatarS3Key);

                throw new ServiceException('Không thể xóa ảnh cũ, thao tác đã được hoàn tác.', 500);
            }
        }

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

        $avatarS3Key = $account->avatar_s3_key;

        if (!empty($avatarS3Key)) {
            $this->mediaUploadService->deleteImage(
                (string) $avatarS3Key,
                'Không thể xóa ảnh đại diện trên S3.'
            );
        }

        $deleted = $this->accountRepository->delete($account);

        if (!$deleted) {
            throw new ServiceException('Không thể xóa tài khoản.', 500);
        }


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

        //Xử lý avatar nếu có
        if ($hasNewAvatar) {
            $finalizedAvatar = $this->handleAvatar(
                (string) $validated['avatarS3Key'],
                $account->id
            );

            $newAvatarUrl = $finalizedAvatar['url'];
            $newAvatarS3Key = $finalizedAvatar['key'];
        }

        //Update DB
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
        // Xóa ảnh cũ (nếu có avatar mới)
        if ($hasNewAvatar && $oldAvatarS3Key && $oldAvatarS3Key !== $newAvatarS3Key) {
            try {
                $this->mediaUploadService->deleteImage(
                    $oldAvatarS3Key,
                    'Không thể xóa ảnh đại diện cũ trên S3.'
                );
            } catch (ServiceException) {

                $this->accountRepository->update($account, [
                    'name' => $oldName,
                    'avatar' => $oldAvatarUrl,
                    'avatar_s3_key' => $oldAvatarS3Key,
                ]);
                // xóa ảnh mới
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
        $uploadedAvatar = $this->mediaUploadService->uploadPendingImage(
            $image,
            ImageScope::ACCOUNT_AVATAR,
            $account->id,
            'Đã xảy ra lỗi khi tải ảnh lên.'
        );

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
        if (!$this->mediaUploadService->isPendingKeyOwnedBy(
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

        $this->mediaUploadService->deletePendingImage(
            $avatarS3Key,
            ImageScope::ACCOUNT_AVATAR,
            $account->id,
            'avatarS3Key',
            'Khóa ảnh tạm không hợp lệ.',
            'Không thể xóa ảnh tạm trên S3.'
        );

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
        $this->mediaUploadService->safeDeleteImage($avatarS3Key);
    }

    private function handleAvatar(string $avatarS3Key, int $accountId): array
    {
        return $this->mediaUploadService->finalizePendingImage(
            $avatarS3Key,
            ImageScope::ACCOUNT_AVATAR,
            $accountId,
            'avatarS3Key',
            'Ảnh tải lên không hợp lệ hoặc đã hết phiên.',
            'Không thể xác nhận ảnh tải lên.'
        );
    }
}
