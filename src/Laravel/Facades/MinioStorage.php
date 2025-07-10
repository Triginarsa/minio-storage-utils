<?php

namespace Triginarsa\MinioStorageUtils\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array upload($source, string $destinationPath = null, array $options = [])
 * @method static bool delete(string $path)
 * @method static bool fileExists(string $path)
 * @method static array getMetadata(string $path)
 * @method static string getUrl(string $path, int $expiration = 3600)
 */
class MinioStorage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'minio-storage';
    }
} 