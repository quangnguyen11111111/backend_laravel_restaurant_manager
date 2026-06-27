<?php

namespace App\Patterns\Strategy\ImageStorage;

use Illuminate\Http\UploadedFile;

interface ImageStorageStrategy
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
