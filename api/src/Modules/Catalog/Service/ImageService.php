<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Service;

use App\Core\Database\Connection;
use Ramsey\Uuid\Uuid;

final class ImageService
{
    private string $uploadDir;
    private string $publicUrl;

    public function __construct(
        private readonly Connection $db,
        string $basePath,
    ) {
        $this->uploadDir = $basePath . '/storage/uploads';
        $this->publicUrl = '/uploads';
    }

    public function uploadProductImage(int $productId, array $file, bool $isPrimary = false): array
    {
        $url = $this->storeFile($file, 'products');

        if ($isPrimary) {
            $this->db->execute(
                "UPDATE product_images SET is_primary = 0 WHERE product_id = ?",
                [$productId]
            );
        }

        $uuid = Uuid::uuid4()->toString();
        $sortOrder = $this->getNextSortOrder('product_images', 'product_id', $productId);

        $this->db->execute(
            "INSERT INTO product_images (uuid, product_id, url, alt_text, sort_order, is_primary)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$uuid, $productId, $url, $file['name'], $sortOrder, $isPrimary ? 1 : 0]
        );

        $id = (int) $this->db->lastInsertId();

        return $this->db->fetchOne("SELECT * FROM product_images WHERE id = ?", [$id]);
    }

    public function getProductImages(int $productId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC",
            [$productId]
        );
    }

    public function deleteProductImage(int $imageId, int $productId): bool
    {
        $image = $this->db->fetchOne(
            "SELECT * FROM product_images WHERE id = ? AND product_id = ?",
            [$imageId, $productId]
        );

        if (!$image) {
            return false;
        }

        $this->deleteFile($image['url']);
        $this->db->execute("DELETE FROM product_images WHERE id = ?", [$imageId]);

        return true;
    }

    public function setPrimaryImage(int $imageId, int $productId): bool
    {
        $image = $this->db->fetchOne(
            "SELECT * FROM product_images WHERE id = ? AND product_id = ?",
            [$imageId, $productId]
        );

        if (!$image) {
            return false;
        }

        $this->db->execute("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$productId]);
        $this->db->execute("UPDATE product_images SET is_primary = 1 WHERE id = ?", [$imageId]);

        return true;
    }

    public function uploadCategoryImage(array $file): string
    {
        return $this->storeFile($file, 'categories');
    }

    private function storeFile(array $file, string $subfolder): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed with error code: ' . $file['error']);
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('File size exceeds 5MB limit.');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowed, true)) {
            throw new \RuntimeException('Invalid file type. Allowed: JPEG, PNG, WebP, GIF.');
        }

        $ext = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $dir = $this->uploadDir . '/' . $subfolder;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = Uuid::uuid4()->toString() . '.' . $ext;
        $path = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return $this->publicUrl . '/' . $subfolder . '/' . $filename;
    }

    private function deleteFile(string $url): void
    {
        if (str_starts_with($url, $this->publicUrl)) {
            $relativePath = substr($url, strlen($this->publicUrl));
            $fullPath = $this->uploadDir . $relativePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    private function getNextSortOrder(string $table, string $column, int $value): int
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM {$table} WHERE {$column} = ?",
            [$value]
        );

        return (int) ($row['next_order'] ?? 0);
    }
}
