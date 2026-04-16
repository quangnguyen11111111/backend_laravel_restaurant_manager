<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;

interface ImageStorageServiceInterface
{
    /**
     * @return array{key: string, url: string}
     */
    public function uploadPublicImage(UploadedFile $file, string $directory): array;

    public function deleteImage(string $key): void;

    public function moveImage(string $fromKey, string $toKey): void;

    public function url(string $key): string;

    public function exists(string $key): bool;
}
