<?php

namespace Triginarsa\MinioStorageUtils\Laravel;

use Illuminate\Support\ServiceProvider;
use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\StorageService;
use Triginarsa\MinioStorageUtils\Processors\ImageProcessor;
use Triginarsa\MinioStorageUtils\Processors\SecurityScanner;
use Triginarsa\MinioStorageUtils\Processors\DocumentProcessor;
use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;

class MinioStorageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/minio-storage.php',
            'minio-storage'
        );

        $this->app->singleton(StorageService::class, function ($app) {
            $diskName = config('minio-storage.disk', 'minio');
            $diskConfig = config("filesystems.disks.{$diskName}");
            
            if (!$diskConfig) {
                throw new \InvalidArgumentException("Disk '{$diskName}' not found in filesystem configuration");
            }

            $config = new MinioConfig(
                key: $diskConfig['key'],
                secret: $diskConfig['secret'],
                bucket: $diskConfig['bucket'],
                endpoint: $diskConfig['endpoint'],
                region: $diskConfig['region'] ?? 'us-east-1',
                version: $diskConfig['version'] ?? 'latest',
                usePathStyleEndpoint: $diskConfig['use_path_style_endpoint'] ?? true
            );

            $logger = $app->make('log');
            $ffmpegConfig = config('minio-storage.ffmpeg', []);
            
            return new StorageService(
                $config,
                $logger,
                new ImageProcessor($logger),
                new SecurityScanner($logger),
                new DocumentProcessor($logger),
                new VideoProcessor($logger, $ffmpegConfig)
            );
        });

        $this->app->alias(StorageService::class, 'minio-storage');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/minio-storage.php' => config_path('minio-storage.php'),
        ], 'config');
    }
} 