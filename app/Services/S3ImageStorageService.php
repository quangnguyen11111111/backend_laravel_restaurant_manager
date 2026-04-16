<?php

namespace App\Services;

use App\Services\Contracts\ImageStorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class S3ImageStorageService implements ImageStorageServiceInterface
{
    private const DISK = 's3';

    /**
     * @return array{key: string, url: string}
     */
    public function uploadPublicImage(UploadedFile $file, string $directory): array
    {
        // Use standard PutObject without ACL to support buckets with ACLs disabled
        // (Object Ownership: Bucket owner enforced).
        $path = $file->store($directory, ['disk' => self::DISK]);

        if (!$path) {
            throw new RuntimeException('Không thể tải ảnh lên S3');
        }

        return [
            'key' => $path,
            'url' => $this->url($path),
        ];
    }

    public function deleteImage(string $key): void
    {
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($key)) {
            return;
        }

        if (!$disk->delete($key)) {
            throw new RuntimeException('Không thể xóa ảnh trên S3');
        }
    }

    public function moveImage(string $fromKey, string $toKey): void
    {
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($fromKey)) {
            throw new InvalidArgumentException('Ảnh nguồn không tồn tại');
        }

        if (!$disk->copy($fromKey, $toKey)) {
            throw new RuntimeException('Không thể chuyển ảnh sang vị trí chính thức');
        }

        if (!$disk->delete($fromKey)) {
            $disk->delete($toKey);

            throw new RuntimeException('Không thể xóa ảnh tạm sau khi chuyển');
        }
    }

    public function url(string $key): string
    {
        return Storage::disk(self::DISK)->url($key);
    }

    public function exists(string $key): bool
    {
        return Storage::disk(self::DISK)->exists($key);
    }
}
