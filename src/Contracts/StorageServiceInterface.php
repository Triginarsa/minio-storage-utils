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
     * @param string|null $bucket Custom bucket name (overrides default bucket).
     * @return bool
     */
    public function fileExists(string $path, ?string $bucket = null): bool;

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
     * @param bool|null $signed Whether to generate a signed URL (overrides config).
     * @return string The presigned URL.
     */
    public function getUrl(string $path, ?int $expiration = null, ?bool $signed = null): string;

    /**
     * Get a public URL for a file (no expiration, for public buckets).
     *
     * @param string $path The path of the file.
     * @return string The public URL.
     */
    public function getPublicUrl(string $path): string;

    /**
     * Get a public URL for a file with existence check (optimized for public read access).
     *
     * @param string $path The path of the file.
     * @param bool $checkExists Whether to verify file existence before generating URL (optional, default: true).
     * @param string|null $bucket Custom bucket name (overrides default bucket).
     * @return string|null The public URL or null if file doesn't exist (when checkExists is true).
     * @throws FileNotFoundException When file doesn't exist and checkExists is true.
     */
    public function getUrlPublic(string $path, bool $checkExists = true, ?string $bucket = null): ?string;
} 