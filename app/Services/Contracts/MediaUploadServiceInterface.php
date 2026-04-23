<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;

interface MediaUploadServiceInterface
{
    /**
     * @return array{key: string, url: string}
     */
    public function uploadPendingImage(
        UploadedFile $file,
        string $scope,
        int $ownerId,
        string $uploadErrorMessage
    ): array;

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
    ): array;

    public function deletePendingImage(
        string $pendingKey,
        string $scope,
        int $ownerId,
        string $fieldName,
        string $invalidMessage,
        string $deleteErrorMessage
    ): void;

    public function isPendingKeyOwnedBy(string $pendingKey, string $scope, int $ownerId): bool;

    public function deleteImage(string $key, string $deleteErrorMessage): void;

    public function safeDeleteImage(?string $key): void;
}
