<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\StorageServiceFactory;
use Triginarsa\MinioStorageUtils\Naming\HashNamer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 1. Configure Minio connection
$config = new MinioConfig(
    key: 'your-minio-access-key',
    secret: 'your-minio-secret-key',
    bucket: 'your-bucket-name',
    endpoint: 'http://localhost:9000', // or your Minio server URL
    region: 'us-east-1'
);

// 2. Create logger (optional)
$logger = new Logger('minio-storage');
$logger->pushHandler(new StreamHandler(__DIR__ . '/storage.log', Logger::INFO));

// 3. Create storage service
$storageService = StorageServiceFactory::create($config, $logger);

try {
    // Example 1: Simple file upload
    echo "=== Simple Upload ===\n";
    $result = $storageService->upload(
        '/path/to/your/file.jpg',
        'uploads/simple-file.jpg'
    );
    print_r($result);

    // Example 2: Upload with security scanning
    echo "\n=== Upload with Security Scan ===\n";
    $result = $storageService->upload(
        '/path/to/your/file.jpg',
        'uploads/secure-file.jpg',
        [
            'scan' => true
        ]
    );
    print_r($result);

    // Example 3: Upload image with processing
    echo "\n=== Image Processing ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        'uploads/processed-image.jpg',
        [
            'scan' => true,
            'naming' => new HashNamer(),
            'image' => [
                'resize' => ['width' => 800, 'height' => 600],
                'convert' => 'jpg',
                'quality' => 85,
                'watermark' => [
                    'path' => '/path/to/watermark.png',
                    'position' => 'bottom-right',
                    'opacity' => 70
                ]
            ],
            'thumbnail' => [
                'width' => 200,
                'height' => 200,
                'method' => 'fit',
                'quality' => 75,
                'suffix' => '-thumb'
            ]
        ]
    );
    print_r($result);

    // Example 4: Check file existence and get metadata
    echo "\n=== File Operations ===\n";
    $path = $result['main']['path'];
    
    if ($storageService->fileExists($path)) {
        echo "File exists!\n";
        $metadata = $storageService->getMetadata($path);
        print_r($metadata);
        
        // Get URL
        $url = $storageService->getUrl($path, 3600);
        echo "File URL: $url\n";
    }

    // Example 5: Delete file
    echo "\n=== Delete File ===\n";
    if ($storageService->delete($path)) {
        echo "File deleted successfully\n";
    } else {
        echo "Failed to delete file\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext ?? []) . "\n";
} 