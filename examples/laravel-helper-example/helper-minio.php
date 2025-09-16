<?php

use Illuminate\Http\UploadedFile;
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;

// 1) Upload image simply with try/catch
if (!function_exists('minio_upload_image_simple')) {
    function minio_upload_image_simple(UploadedFile $file, string $destination = '/img/') : array
    {
        try {
            return MinioStorage::upload($file, $destination);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'main' => [
                    'url' => 'img/default.png',
                    'path' => 'img/default.png',
                ],
            ];
        }
    }
}

// 2) Upload image with image resize
if (!function_exists('minio_upload_image_resize')) {
    function minio_upload_image_resize(
        UploadedFile $file,
        string $destination = '/img/',
        int $width = 1024,
        int $height = 768,
        ?string $convert = 'jpg',
        int $quality = 85
    ) : array {
        try {
            return MinioStorage::upload($file, $destination, [
                'image' => [
                    'resize' => ['width' => $width, 'height' => $height],
                    'quality' => $quality,
                    'convert' => $convert,
                ],
            ]);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'main' => [
                    'url' => 'img/default.png',
                    'path' => 'img/default.png',
                ],
            ];
        }
    }
}

// 3) Upload image with watermark
if (!function_exists('minio_upload_image_watermark')) {
    function minio_upload_image_watermark(
        UploadedFile $file,
        string $destination = '/img/',
        ?string $watermarkPath = null,
        string $position = 'bottom-right',
        int $opacity = 70
    ) : array {
        $watermarkPath = $watermarkPath ?? public_path('watermark.png');

        try {
            return MinioStorage::upload($file, $destination, [
                // Top-level watermark is supported by the library
                'watermark' => [
                    'path' => $watermarkPath,
                    'position' => $position,
                    'opacity' => $opacity,
                ],
                'image' => [
                    'quality' => 85,
                ],
            ]);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'main' => [
                    'url' => 'img/default.png',
                    'path' => 'img/default.png',
                ],
            ];
        }
    }
}

// 4) Upload image with thumbnail
if (!function_exists('minio_upload_image_thumbnail')) {
    function minio_upload_image_thumbnail(
        UploadedFile $file,
        string $destination = '/img/',
        int $thumbWidth = 200,
        int $thumbHeight = 200,
        string $method = 'fit',
        int $quality = 75,
        string $suffix = '-thumb'
    ) : array {
        try {
            return MinioStorage::upload($file, $destination, [
                'thumbnail' => [
                    'width' => $thumbWidth,
                    'height' => $thumbHeight,
                    'method' => $method, // fit, crop, proportional
                    'quality' => $quality,
                    'suffix' => $suffix,
                ],
            ]);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'main' => [
                    'url' => 'img/default.png',
                    'path' => 'img/default.png',
                ],
            ];
        }
    }
}

// 5) getUrlPublic with try/catch and optional error callback
if (!function_exists('minio_get_public_url')) {
    /**
     * @param callable|null $onError function (Throwable $e, string $path): string
     */
    function minio_get_public_url(string $path, ?callable $onError = null, bool $checkExists = true, ?string $bucket = null) : string
    {
        try {
            return MinioStorage::getUrlPublic($path, $checkExists, $bucket);
        } catch (\Throwable $e) {
            if ($onError) {
                $fallback = $onError($e, $path);
                return is_string($fallback) && $fallback !== '' ? $fallback : 'img/default.png';
            }
            return 'img/default.png';
        }
    }
}

// 6) Delete image
if (!function_exists('minio_delete_image')) {
    function minio_delete_image(string $path) : bool
    {
        try {
            return MinioStorage::delete($path);
        } catch (\Throwable $e) {
            return false;
        }
    }
}


