<?php

namespace App\Services;

use App\Services\Contracts\ImageStorageServiceInterface;
use App\Services\Contracts\PendingImageWorkflowServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PendingImageWorkflowService implements PendingImageWorkflowServiceInterface
{
    public function __construct(
        private readonly ImageStorageServiceInterface $imageStorageService
    ) {
    }

    /**
     * @return array{key: string, url: string}
     */
    public function uploadPendingImage(UploadedFile $file, string $scope, int $ownerId): array
    {
        return $this->imageStorageService->uploadPublicImage(
            $file,
            $this->pendingDirectoryForOwner($scope, $ownerId)
        );
    }

    /**
     * @return array{key: string, url: string}
     */
    public function finalizePendingImage(string $pendingKey, string $scope, int $ownerId): array
    {
        if (!$this->isPendingKeyOwnedBy($pendingKey, $scope, $ownerId)) {
            throw new InvalidArgumentException('Khóa ảnh tạm không hợp lệ');
        }

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

    public function deletePendingImage(string $pendingKey, string $scope, int $ownerId): void
    {
        if (!$this->isPendingKeyOwnedBy($pendingKey, $scope, $ownerId)) {
            throw new InvalidArgumentException('Khóa ảnh tạm không hợp lệ');
        }

        $this->imageStorageService->deleteImage($pendingKey);
    }

    public function isPendingKeyOwnedBy(string $pendingKey, string $scope, int $ownerId): bool
    {
        $expectedPrefix = $this->pendingDirectoryForOwner($scope, $ownerId) . '/';

        return str_starts_with($pendingKey, $expectedPrefix);
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
