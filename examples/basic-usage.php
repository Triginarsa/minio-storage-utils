<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\StorageServiceFactory;
use Triginarsa\MinioStorageUtils\Naming\HashNamer;
use Triginarsa\MinioStorageUtils\Naming\SlugNamer;
use Triginarsa\MinioStorageUtils\Naming\OriginalNamer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// ================================================
// CONFIGURATION
// ================================================

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

// ================================================
// BASIC UPLOADS WITH SPECIFIC PATHS
// ================================================

try {
    echo "=== 1. Basic Image Upload - /img/upload/ ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        '/img/upload/'
    );
    echo "Upload successful!\n";
    print_r($result);
    $imageUploadPath = $result['main']['path'] ?? null;

    echo "\n=== 2. Basic Document Upload - /doc/upload/ ===\n";
    $result = $storageService->upload(
        '/path/to/your/document.pdf',
        '/doc/upload/'
    );
    echo "Document upload successful!\n";
    print_r($result);
    $documentUploadPath = $result['main']['path'] ?? null;

    echo "\n=== 3. Basic Video Upload - /vid/upload/ ===\n";
    $result = $storageService->upload(
        '/path/to/your/video.mp4',
        '/vid/upload/'
    );
    echo "Video upload successful!\n";
    echo "NOTE: For video processing features, install FFmpeg: sudo apt-get install ffmpeg\n";
    print_r($result);
    $videoUploadPath = $result['main']['path'] ?? null;

    // ================================================
    // ADVANCED IMAGE PROCESSING
    // ================================================

    echo "\n=== 4. Image Upload with Compression ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        '/img/compressed/',
        [
            'compress' => true,
            'compression_options' => [
                'quality' => 75,
                'format' => 'jpg',
                'progressive' => true,
                'target_size' => 500000, // 500KB target size
            ]
        ]
    );
    echo "Image compressed successfully!\n";
    print_r($result);
    $compressedImagePath = $result['main']['path'] ?? null;

    echo "\n=== 5. Image Upload with Security Scan ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        '/img/secure/',
        [
            'scan' => true,
            'security' => [
                'strict_mode' => true,
                'scan_images' => true,
                'quarantine_suspicious' => true,
            ]
        ]
    );
    echo "Image uploaded with security scan!\n";
    print_r($result);
    $secureImagePath = $result['main']['path'] ?? null;

    echo "\n=== 6. Image Upload with Crop to Square ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        '/img/square/',
        [
            'image' => [
                'resize' => [
                    'width' => 500,
                    'height' => 500,
                    'method' => 'crop', // Crop to square
                ],
                'quality' => 85,
            ]
        ]
    );
    echo "Image cropped to square successfully!\n";
    print_r($result);
    $squareImagePath = $result['main']['path'] ?? null;

    echo "\n=== 7. Image Upload with Thumbnail Generation ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        '/img/with-thumb/',
        [
            'thumbnail' => [
                'width' => 200,
                'height' => 200,
                'method' => 'fit',
                'quality' => 75,
                'optimize' => true,
                'suffix' => '-thumb'
            ]
        ]
    );
    echo "Image uploaded with thumbnail!\n";
    print_r($result);
    $thumbnailImagePath = $result['main']['path'] ?? null;

    echo "\n=== 8. Document Upload with Security Scan ===\n";
    $result = $storageService->upload(
        '/path/to/your/document.pdf',
        '/doc/secure/',
        [
            'scan' => true,
            'security' => [
                'scan_documents' => true,
                'strict_mode' => true,
                'quarantine_suspicious' => true,
            ]
        ]
    );
    echo "Document uploaded with security scan!\n";
    print_r($result);
    $secureDocumentPath = $result['main']['path'] ?? null;

    echo "\n=== 9. Video Upload with Compression ===\n";
    echo "NOTE: This requires FFmpeg installation and configuration\n";
    echo "Install FFmpeg:\n";
    echo "  Ubuntu/Debian: sudo apt-get install ffmpeg\n";
    echo "  CentOS/RHEL: sudo yum install ffmpeg\n";
    echo "  macOS: brew install ffmpeg\n";
    echo "  Test: ffmpeg -version\n\n";
    
    $result = $storageService->upload(
        '/path/to/your/video.mp4',
        '/vid/compressed/',
        [
            'compress' => true,
            'video' => [
                'compression' => 'medium', // ultrafast, fast, medium, slow, veryslow
                'format' => 'mp4',
                'video_bitrate' => 1500, // kbps
                'audio_bitrate' => 128,  // kbps
                'max_width' => 1280,
                'max_height' => 720,
                'quality' => 'medium',
            ],
            'thumbnail' => [
                'width' => 320,
                'height' => 240,
                'time' => 5, // Generate thumbnail at 5 seconds
            ]
        ]
    );
    echo "Video compressed successfully!\n";
    print_r($result);
    $compressedVideoPath = $result['main']['path'] ?? null;

    // ================================================
    // URL GENERATION EXAMPLES
    // ================================================

    echo "\n=== URL Generation Examples ===\n";
    
    // Use the first uploaded file for URL examples
    $testPath = $imageUploadPath ?? '/img/upload/test.jpg';
    
    if ($storageService->fileExists($testPath)) {
        // Get public URL (no expiration)
        $publicUrl = $storageService->getPublicUrl($testPath);
        echo "Public URL: $publicUrl\n";
        
        // Get signed URL with 1 hour expiration
        $signedUrl = $storageService->getUrl($testPath, 3600);
        echo "Signed URL (1 hour): $signedUrl\n";
        
        // Get signed URL with 1 day expiration
        $signedUrl24h = $storageService->getUrl($testPath, 86400);
        echo "Signed URL (24 hours): $signedUrl24h\n";
        
        // Get file metadata
        $metadata = $storageService->getMetadata($testPath);
        echo "File metadata:\n";
        print_r($metadata);
    }

    // ================================================
    // FILE DELETION EXAMPLES
    // ================================================

    echo "\n=== File Deletion Examples ===\n";
    
    // Delete a specific file
    if ($compressedImagePath && $storageService->fileExists($compressedImagePath)) {
        echo "Deleting compressed image: $compressedImagePath\n";
        if ($storageService->delete($compressedImagePath)) {
            echo "✓ File deleted successfully\n";
        } else {
            echo "✗ Failed to delete file\n";
        }
    }

    // ================================================
    // ADVANCED PROCESSING EXAMPLES
    // ================================================

    echo "\n=== Advanced Image Processing with All Options ===\n";
    $result = $storageService->upload(
        '/path/to/your/image.jpg',
        '/img/full-processing/',
        [
            'scan' => true,
            'naming' => new HashNamer(), // or new SlugNamer(), new OriginalNamer()
            'compress' => true,
            'image' => [
                'resize' => [
                    'width' => 1024,
                    'height' => 768,
                    'method' => 'fit'
                ],
                'quality' => 85,
                'format' => 'jpg',
                'progressive' => true,
                'auto_orient' => true,
                'strip_metadata' => true,
                'watermark' => [
                    'path' => '/path/to/watermark.png',
                    'position' => 'bottom-right',
                    'opacity' => 70,
                    'margin' => 10
                ]
            ],
            'thumbnail' => [
                'width' => 200,
                'height' => 200,
                'method' => 'crop',
                'quality' => 75,
                'optimize' => true,
                'suffix' => '-thumb'
            ],
            'security' => [
                'scan_images' => true,
                'strict_mode' => true,
                'quarantine_suspicious' => true,
            ]
        ]
    );
    echo "Advanced processing completed!\n";
    print_r($result);

    // ================================================
    // BATCH OPERATIONS
    // ================================================

    echo "\n=== Batch Upload Example ===\n";
    $filesToUpload = [
        '/path/to/image1.jpg',
        '/path/to/image2.jpg',
        '/path/to/document.pdf',
    ];
    
    $batchResults = [];
    foreach ($filesToUpload as $filePath) {
        if (file_exists($filePath)) {
            try {
                $result = $storageService->upload($filePath, '/batch-upload/');
                $batchResults[] = [
                    'file' => basename($filePath),
                    'success' => true,
                    'result' => $result
                ];
                echo "✓ Uploaded: " . basename($filePath) . "\n";
            } catch (\Exception $e) {
                $batchResults[] = [
                    'file' => basename($filePath),
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                echo "✗ Failed: " . basename($filePath) . " - " . $e->getMessage() . "\n";
            }
        } else {
            echo "⚠ File not found: $filePath\n";
        }
    }
    
    echo "Batch upload completed. Results:\n";
    print_r($batchResults);

    // ================================================
    // COMPRESSION PRESETS EXAMPLE
    // ================================================

    echo "\n=== Compression Presets Example ===\n";
    $presets = ['low', 'medium', 'high', 'very_high', 'max'];
    
    foreach ($presets as $preset) {
        echo "Testing preset: $preset\n";
        try {
            $result = $storageService->upload(
                '/path/to/your/image.jpg',
                "/img/preset-$preset/",
                [
                    'compress' => true,
                    'compression_options' => [
                        'quality_preset' => $preset,
                        'format' => 'jpg',
                        'progressive' => true,
                    ]
                ]
            );
            echo "✓ Preset $preset applied successfully\n";
        } catch (\Exception $e) {
            echo "✗ Preset $preset failed: " . $e->getMessage() . "\n";
        }
    }

    // ================================================
    // NAMING STRATEGIES EXAMPLE
    // ================================================

    echo "\n=== Naming Strategies Example ===\n";
    $namingStrategies = [
        'hash' => new HashNamer(),
        'slug' => new SlugNamer(),
        'original' => new OriginalNamer()
    ];
    
    foreach ($namingStrategies as $strategyName => $namer) {
        echo "Testing naming strategy: $strategyName\n";
        try {
            $result = $storageService->upload(
                '/path/to/your/image.jpg',
                "/img/naming-$strategyName/",
                [
                    'naming' => $namer
                ]
            );
            echo "✓ Naming strategy $strategyName applied successfully\n";
            echo "  Generated filename: " . basename($result['main']['path']) . "\n";
        } catch (\Exception $e) {
            echo "✗ Naming strategy $strategyName failed: " . $e->getMessage() . "\n";
        }
    }

    // ================================================
    // FILE OPERATIONS EXAMPLES
    // ================================================

    echo "\n=== File Operations Examples ===\n";
    
    // List some file operations
    $testFiles = [
        $imageUploadPath,
        $documentUploadPath,
        $videoUploadPath,
        $secureImagePath,
        $thumbnailImagePath
    ];
    
    foreach ($testFiles as $filePath) {
        if (!$filePath) continue;
        
        echo "Checking file: $filePath\n";
        
        // Check existence
        if ($storageService->fileExists($filePath)) {
            echo "  ✓ File exists\n";
            
            // Get metadata
            try {
                $metadata = $storageService->getMetadata($filePath);
                echo "  📊 Size: " . ($metadata['size'] ?? 'unknown') . " bytes\n";
                echo "  🎯 Type: " . ($metadata['mime_type'] ?? 'unknown') . "\n";
                echo "  📅 Modified: " . date('Y-m-d H:i:s', $metadata['last_modified'] ?? 0) . "\n";
            } catch (\Exception $e) {
                echo "  ✗ Failed to get metadata: " . $e->getMessage() . "\n";
            }
            
            // Get URLs
            try {
                $publicUrl = $storageService->getPublicUrl($filePath);
                echo "  🔗 Public URL: $publicUrl\n";
                
                $signedUrl = $storageService->getUrl($filePath, 3600);
                echo "  🔐 Signed URL (1h): $signedUrl\n";
            } catch (\Exception $e) {
                echo "  ✗ Failed to get URLs: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  ✗ File does not exist\n";
        }
        echo "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "📋 Trace: " . $e->getTraceAsString() . "\n";
    
    // If the exception has context (custom exceptions)
    if (method_exists($e, 'getContext')) {
        echo "🔍 Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    }
}

// ================================================
// CONFIGURATION EXAMPLES
// ================================================

echo "\n=== Configuration Examples ===\n";

// Example of different configuration options
$advancedConfig = new MinioConfig(
    key: 'your-minio-access-key',
    secret: 'your-minio-secret-key',
    bucket: 'your-bucket-name',
    endpoint: 'http://localhost:9000',
    region: 'us-east-1',
    usePathStyleEndpoint: true,
    options: [
        'version' => 'latest',
        'signature_version' => 'v4',
        'use_accelerate_endpoint' => false,
        'use_dual_stack_endpoint' => false,
    ]
);

echo "Advanced configuration created successfully!\n";

// ================================================
// NOTES AND REQUIREMENTS
// ================================================

echo "\n=== Requirements and Notes ===\n";
echo "📋 System Requirements:\n";
echo "  • PHP 8.0+\n";
echo "  • GD or ImageMagick extension for image processing\n";
echo "  • FFmpeg for video processing (optional)\n";
echo "  • cURL extension for HTTP requests\n";
echo "  • OpenSSL extension for secure connections\n";
echo "\n";

echo "🔧 FFmpeg Installation:\n";
echo "  Ubuntu/Debian: sudo apt-get install ffmpeg\n";
echo "  CentOS/RHEL: sudo yum install ffmpeg\n";
echo "  macOS: brew install ffmpeg\n";
echo "  Windows: Download from https://ffmpeg.org/download.html\n";
echo "\n";

echo "⚙️  Configuration Files:\n";
echo "  • Copy config/minio-storage.php to your project\n";
echo "  • Update .env with your MinIO credentials\n";
echo "  • Configure FFmpeg paths if needed\n";
echo "\n";

echo "🚀 Usage Tips:\n";
echo "  • Always use try-catch blocks for error handling\n";
echo "  • Enable security scanning for uploaded files\n";
echo "  • Use compression for web-optimized images\n";
echo "  • Generate thumbnails for better performance\n";
echo "  • Use signed URLs for private files\n";
echo "  • Implement proper file validation\n";
echo "\n";

echo "✅ All examples completed successfully!\n"; 