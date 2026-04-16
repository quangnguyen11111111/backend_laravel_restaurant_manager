<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;

interface PendingImageWorkflowServiceInterface
{
    /**
     * @return array{key: string, url: string}
     */
    public function uploadPendingImage(UploadedFile $file, string $scope, int $ownerId): array;

    /**
     * @return array{key: string, url: string}
     */
    public function finalizePendingImage(string $pendingKey, string $scope, int $ownerId): array;

    public function deletePendingImage(string $pendingKey, string $scope, int $ownerId): void;

    public function isPendingKeyOwnedBy(string $pendingKey, string $scope, int $ownerId): bool;
}
