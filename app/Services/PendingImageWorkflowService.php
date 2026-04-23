<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Services\Contracts\ImageStorageServiceInterface;
use App\Services\Contracts\MediaUploadServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PendingImageWorkflowService implements MediaUploadServiceInterface
{
    public function __construct(
        private readonly ImageStorageServiceInterface $imageStorageService
    ) {}

    /**
     * @return array{key: string, url: string}
     */
    public function uploadPendingImage(
        UploadedFile $file,
        string $scope,
        int $ownerId,
        string $uploadErrorMessage
    ): array {
        try {
            return $this->uploadPendingImageInternal($file, $scope, $ownerId);
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceException($uploadErrorMessage, 500);
        }
    }

    /**
     * @return array{key: string, url: string}
     */
    public function finalizePendingImage(
        string $pendingKey,
        string $scope,
        int $ownerId,
        string $fieldName,
        string $invalidMessage,
        string $finalizeErrorMessage
    ): array {
        if (!$this->isPendingKeyOwnedBy($pendingKey, $scope, $ownerId)) {
            throw $this->validationException($fieldName, $invalidMessage);
        }

        try {
            return $this->finalizePendingImageInternal($pendingKey, $scope, $ownerId);
        } catch (InvalidArgumentException) {
            throw $this->validationException($fieldName, $invalidMessage);
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceException($finalizeErrorMessage, 500);
        }
    }

    public function deletePendingImage(
        string $pendingKey,
        string $scope,
        int $ownerId,
        string $fieldName,
        string $invalidMessage,
        string $deleteErrorMessage
    ): void {
        if (!$this->isPendingKeyOwnedBy($pendingKey, $scope, $ownerId)) {
            throw $this->validationException($fieldName, $invalidMessage);
        }

        try {
            $this->deletePendingImageInternal($pendingKey);
        } catch (InvalidArgumentException) {
            throw $this->validationException($fieldName, $invalidMessage);
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceException($deleteErrorMessage, 500);
        }
    }

    public function isPendingKeyOwnedBy(string $pendingKey, string $scope, int $ownerId): bool
    {
        $expectedPrefix = $this->pendingDirectoryForOwner($scope, $ownerId) . '/';

        return str_starts_with($pendingKey, $expectedPrefix);
    }

    public function deleteImage(string $key, string $deleteErrorMessage): void
    {
        try {
            $this->imageStorageService->deleteImage($key);
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceException($deleteErrorMessage, 500);
        }
    }

    public function safeDeleteImage(?string $key): void
    {
        if (empty($key)) {
            return;
        }

        try {
            $this->imageStorageService->deleteImage($key);
        } catch (Throwable) {
            // Best-effort cleanup for failed update/rollback.
        }
    }

    /**
     * @return array{key: string, url: string}
     */
    private function uploadPendingImageInternal(UploadedFile $file, string $scope, int $ownerId): array
    {
        return $this->imageStorageService->uploadPublicImage(
            $file,
            $this->pendingDirectoryForOwner($scope, $ownerId)
        );
    }

    /**
     * @return array{key: string, url: string}
     */
    private function finalizePendingImageInternal(string $pendingKey, string $scope, int $ownerId): array
    {
        if (!$this->imageStorageService->exists($pendingKey)) {
            throw new InvalidArgumentException('Ảnh tạm không tồn tại hoặc đã hết hạn');
        }

        $finalKey = $this->buildFinalKey($pendingKey, $scope, $ownerId);
        $this->imageStorageService->moveImage($pendingKey, $finalKey);

        return [
            'key' => $finalKey,
            'url' => $this->imageStorageService->url($finalKey),
        ];
    }

    private function deletePendingImageInternal(string $pendingKey): void
    {
        $this->imageStorageService->deleteImage($pendingKey);
    }

    private function validationException(string $fieldName, string $message): ServiceException
    {
        return new ServiceException($message, 422, [
            ['field' => $fieldName, 'message' => $message],
        ]);
    }

    private function pendingDirectoryForOwner(string $scope, int $ownerId): string
    {
        $directory = $this->scopeDirectory($scope, 'pending_directory');

        return $directory . '/' . $ownerId;
    }

    private function finalDirectoryForOwner(string $scope, int $ownerId): string
    {
        $directory = $this->scopeDirectory($scope, 'final_directory');

        return $directory . '/' . $ownerId;
    }

    private function scopeDirectory(string $scope, string $key): string
    {
        $value = config("media.scopes.{$scope}.{$key}");

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Image scope không được hỗ trợ');
        }

        return trim($value, '/');
    }

    private function buildFinalKey(string $pendingKey, string $scope, int $ownerId): string
    {
        $extension = pathinfo($pendingKey, PATHINFO_EXTENSION);
        $fileName = (string) Str::uuid();

        if ($extension !== '') {
            $fileName .= '.' . $extension;
        }

        return $this->finalDirectoryForOwner($scope, $ownerId) . '/' . $fileName;
    }
}
