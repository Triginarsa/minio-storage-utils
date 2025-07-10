<?php

namespace Triginarsa\MinioStorageUtils\Contracts;

use Psr\Http\Message\StreamInterface;

interface StorageServiceInterface
{
    /**
     * Upload a file from a path or a stream to Minio.
     *
     * @param string|StreamInterface $source The source file path or stream.
     * @param string $destinationPath The destination path in the bucket.
     * @param array $options An array of options for processing.
     * @return array Information about the uploaded file(s).
     */
    public function upload($source, string $destinationPath, array $options = []): array;

    /**
     * Delete a file from the Minio bucket.
     *
     * @param string $path The path of the file to delete.
     * @return bool True on success, false on failure.
     */
    public function delete(string $path): bool;

    /**
     * Check if a file exists in the bucket.
     *
     * @param string $path The path of the file.
     * @return bool
     */
    public function fileExists(string $path): bool;

    /**
     * Get metadata for a file.
     *
     * @param string $path The path of the file.
     * @return array The file metadata (size, mime type, etc.).
     */
    public function getMetadata(string $path): array;

    /**
     * Get a presigned URL for a file (with expiration).
     *
     * @param string $path The path of the file.
     * @param int|null $expiration Expiration time in seconds (null for default).
     * @return string The presigned URL.
     */
    public function getUrl(string $path, ?int $expiration = null): string;

    /**
     * Get a public URL for a file (no expiration, for public buckets).
     *
     * @param string $path The path of the file.
     * @return string The public URL.
     */
    public function getPublicUrl(string $path): string;
} 