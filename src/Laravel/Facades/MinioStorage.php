<?php

namespace Triginarsa\MinioStorageUtils\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array upload($source, string $destinationPath = null, array $options = [])
 * @method static bool delete(string $path)
 * @method static bool fileExists(string $path, string|null $bucket = null)
 * @method static array getMetadata(string $path)
 * @method static string getUrl(string $path, int $expiration = null, bool $signed = null)
 * @method static string getPublicUrl(string $path)
 * @method static string|null getUrlPublic(string $path, string|null $bucket = null, bool $checkExists = true)
 */
class MinioStorage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'minio-storage';
    }
} 