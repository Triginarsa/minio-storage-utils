<?php

namespace Triginarsa\MinioStorageUtils;

use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\Processors\ImageProcessor;
use Triginarsa\MinioStorageUtils\Processors\SecurityScanner;
use Triginarsa\MinioStorageUtils\Processors\DocumentProcessor;
use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class StorageServiceFactory
{
    public static function create(
        MinioConfig $config,
        ?LoggerInterface $logger = null,
        array $ffmpegConfig = []
    ): StorageService {
        // Create default logger if none provided
        if ($logger === null) {
            $logger = new Logger('minio-storage');
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        return new StorageService(
            $config,
            $logger,
            new ImageProcessor($logger),
            new SecurityScanner($logger),
            new DocumentProcessor($logger),
            new VideoProcessor($logger, $ffmpegConfig)
        );
    }
} 