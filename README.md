# Minio Storage Utils

A PHP library for secure file handling with MinIO object storage, designed specifically for Laravel applications. Features include image processing, security scanning, flexible naming strategies, and logging.

## Features

- **🔒 Secure File Upload**: Advanced security scanning for malicious content, macros, and suspicious patterns
- **🖼️ Advanced Image Compression**: Multiple compression algorithms with intelligent optimization
  - Quality-based compression (1-100 scale)
  - Target size compression (compress to specific file size)
  - Quality presets (low, medium, high, very_high, max)
  - Smart compression (auto-adjust based on image characteristics)
  - Web optimization (resize + compress for web delivery)
  - Progressive JPEG support
  - Format conversion (JPG, PNG, WebP, AVIF)
  - Compression ratio reporting
- **🖼️ Image Processing**: Resize, compress, watermark, format conversion, and auto-orientation
- **🎬 Video Processing**: Optional FFmpeg-based video processing with graceful fallback
  - Video format conversion (MP4, WebM, AVI, MOV)
  - Video compression and optimization
  - Thumbnail generation from video frames
  - Video clipping and resizing
  - Watermark application
  - Metadata extraction
  - Works without FFmpeg (upload-only mode)
- **📄 Document Security**: Specialized scanning for PDF, Word, Excel files detecting VBA macros and embedded threats
- **🖼️ Thumbnail Generation**: Automatic thumbnail creation with multiple sizing methods
- **📝 Flexible Naming**: Hash-based, slug-based, or custom naming strategies
- **📁 File Management**: Upload, delete, existence checks, and detailed metadata retrieval
- **📊 Comprehensive Logging**: Detailed logging for monitoring and debugging with compression analytics
- **⚡ Laravel Integration**: Native Laravel support with Service Provider and Facade
- **🔧 Simple Configuration**: Uses Laravel's filesystem disk configuration

## Installation

```bash
composer require triginarsa/minio-storage-utils
```

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- MinIO server or S3-compatible storage
- GD extension for image processing

### Optional Requirements

- **FFmpeg** (for video processing features)
  - System installation: `sudo apt-get install ffmpeg` (Ubuntu/Debian)
  - PHP package: `composer require php-ffmpeg/php-ffmpeg`
  - **Note**: Video uploads work without FFmpeg, but processing features will be disabled

## Laravel Setup

### 1. Publish Configuration

```bash
php artisan vendor:publish --provider="Triginarsa\MinioStorageUtils\Laravel\MinioStorageServiceProvider" --tag="config"
```

### 2. Configure Filesystem Disk

Add to your `config/filesystems.php`:

```php
'disks' => [
    'minio' => [
        'driver' => 's3',
        'key' => env('MINIO_ACCESS_KEY'),
        'secret' => env('MINIO_SECRET_KEY'),
        'region' => env('MINIO_REGION', 'us-east-1'),
        'bucket' => env('MINIO_BUCKET'),
        'endpoint' => env('MINIO_ENDPOINT', 'http://localhost:9000'),
        'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE_ENDPOINT', true),
        'throw' => false,
    ],
],
```

### 3. Environment Configuration

Add to your `.env` file:

```env
MINIO_ACCESS_KEY=your-minio-access-key
MINIO_SECRET_KEY=your-minio-secret-key
MINIO_BUCKET=your-bucket-name
MINIO_ENDPOINT=http://localhost:9000
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE_ENDPOINT=true

# Optional: Customize behavior
MINIO_SECURITY_SCAN=true
MINIO_NAMING_STRATEGY=hash

# Image processing settings
MINIO_IMAGE_QUALITY=85
MINIO_IMAGE_MAX_WIDTH=2048
MINIO_IMAGE_MAX_HEIGHT=2048
MINIO_IMAGE_OPTIMIZE=false
MINIO_IMAGE_SMART_COMPRESSION=false

# Compression settings
MINIO_COMPRESSION_QUALITY=80
MINIO_COMPRESSION_FORMAT=jpg
MINIO_COMPRESSION_PROGRESSIVE=true
MINIO_COMPRESSION_PRESET=high
MINIO_COMPRESSION_TARGET_SIZE=
MINIO_COMPRESSION_MAX_QUALITY=95
MINIO_COMPRESSION_MIN_QUALITY=60

# Web optimization settings
MINIO_WEB_MAX_WIDTH=1920
MINIO_WEB_MAX_HEIGHT=1080
MINIO_WEB_QUALITY=85
MINIO_WEB_FORMAT=jpg
MINIO_WEB_PROGRESSIVE=true

# Thumbnail settings
MINIO_THUMBNAIL_OPTIMIZE=true
MINIO_THUMBNAIL_FORMAT=jpg
```

## Quick Start

### Basic File Upload

```php
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;

// In your controller
public function upload(Request $request)
{
    $request->validate(['file' => 'required|file|max:10240']);

    // Simple upload - path auto-generated
    $result = MinioStorage::upload($request->file('file'));
  
    return response()->json(['success' => true, 'data' => $result]);
}
```

### Image Upload with Processing

```php
public function uploadImage(Request $request)
{
    $request->validate(['image' => 'required|image|max:10240']);

    $result = MinioStorage::upload(
        $request->file('image'),
        null, // Auto-generate path
        [
            'scan' => true,
            'naming' => 'hash',
            'image' => [
                'resize' => ['width' => 1024],
                'convert' => 'jpg',
                'quality' => 85,
                'watermark' => [
                    'path' => public_path('watermark.png'),
                    'position' => 'bottom-right',
                    'opacity' => 70
                ]
            ],
            'thumbnail' => [
                'width' => 200,
                'height' => 200,
                'method' => 'fit'
            ]
        ]
    );
  
    return response()->json(['success' => true, 'data' => $result]);
}
```

### Document Upload with Security Scanning

```php
public function uploadDocument(Request $request)
{
    $request->validate([
        'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240'
    ]);

    $result = MinioStorage::upload(
        $request->file('document'),
        'documents/' . time() . '-' . $request->file('document')->getClientOriginalName(),
        [
            'scan' => true, // Scans for macros, embedded files, suspicious content
            'naming' => 'original'
        ]
    );
  
    return response()->json(['success' => true, 'data' => $result]);
}
```

## Advanced Usage

### File Management Operations

```php
// Check if file exists
if (MinioStorage::fileExists('path/to/file.jpg')) {
    // Get detailed metadata
    $metadata = MinioStorage::getMetadata('path/to/file.jpg');
  
    // Generate presigned URL (expires in 1 hour)
    $url = MinioStorage::getUrl('path/to/file.jpg', 3600);
  
    // Delete file
    $deleted = MinioStorage::delete('path/to/file.jpg');
}
```

### Custom Naming Strategies

```php
use Triginarsa\MinioStorageUtils\Naming\HashNamer;
use Triginarsa\MinioStorageUtils\Naming\SlugNamer;

// Using built-in namers
$result = MinioStorage::upload($file, null, ['naming' => 'hash']);
$result = MinioStorage::upload($file, null, ['naming' => 'slug']);
$result = MinioStorage::upload($file, null, ['naming' => 'original']);

// Using custom namer instance
$result = MinioStorage::upload($file, null, ['naming' => new HashNamer()]);
```

### Image Processing & Advanced Compression

#### Basic Image Processing

```php
$result = MinioStorage::upload($image, null, [
    'image' => [
        'resize' => ['width' => 800, 'height' => 600],
        'convert' => 'jpg',
        'quality' => 85,
        'auto_orient' => true,
        'strip_metadata' => true,
        'max_width' => 2048,
        'max_height' => 2048,
        'watermark' => [
            'path' => '/path/to/watermark.png',
            'position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
            'opacity' => 70
        ]
    ],
    'thumbnail' => [
        'width' => 200,
        'height' => 200,
        'method' => 'fit', // fit, crop, resize
        'quality' => 75,
        'format' => 'jpg',
        'suffix' => '-thumb',
        'path' => 'thumbnails'
    ]
]);
```

#### Advanced Image Compression

```php
// Quality-based compression
$result = MinioStorage::upload($image, null, [
    'compress' => true,
    'compression_options' => [
        'quality' => 75,
        'format' => 'jpg',
        'progressive' => true,
    ]
]);

// Target size compression (compress to specific file size)
$result = MinioStorage::upload($image, null, [
    'compress' => true,
    'compression_options' => [
        'target_size' => 500000, // 500KB
        'format' => 'jpg',
        'max_quality' => 90,
        'min_quality' => 60,
    ]
]);

// Quality preset compression
$result = MinioStorage::upload($image, null, [
    'compress' => true,
    'compression_options' => [
        'quality_preset' => 'medium', // low, medium, high, very_high, max
        'format' => 'jpg',
    ]
]);
```

#### Web Optimization

```php
// Optimized for web delivery
$result = MinioStorage::upload($image, null, [
    'optimize_for_web' => true,
    'web_options' => [
        'max_width' => 1920,
        'max_height' => 1080,
        'quality' => 85,
        'format' => 'jpg',
        'progressive' => true,
    ]
]);
```

#### Smart Compression

```php
// Auto-optimization based on image characteristics
$result = MinioStorage::upload($image, null, [
    'optimize' => true,
    'smart_compression' => true,
    'image' => [
        'max_width' => 2048,
        'max_height' => 2048,
        'auto_orient' => true,
        'strip_metadata' => true,
    ]
]);
```

#### Multiple Format Generation

```php
// Generate multiple formats for optimal delivery
$formats = ['jpg', 'webp', 'avif'];
$results = [];

foreach ($formats as $format) {
    $result = MinioStorage::upload($image, "multi-format/{$format}/", [
        'compress' => true,
        'compression_options' => [
            'quality' => $format === 'avif' ? 75 : 80,
            'format' => $format,
            'progressive' => $format === 'jpg',
        ]
    ]);
    $results[$format] = $result;
}
```

#### Batch Processing with Different Compression Levels

```php
// Process multiple images with different compression settings
$compressionLevels = [
    'thumbnail' => ['quality_preset' => 'medium', 'max_width' => 300],
    'preview' => ['quality_preset' => 'high', 'max_width' => 800],
    'full' => ['quality_preset' => 'very_high', 'max_width' => 1920],
];

$results = [];
foreach ($compressionLevels as $level => $options) {
    $result = MinioStorage::upload($image, "gallery/{$level}/", [
        'compress' => true,
        'compression_options' => $options,
    ]);
    $results[$level] = $result;
}
```

### Video Processing (Optional FFmpeg)

Video processing features are **optional** and require FFmpeg to be installed. Without FFmpeg, video uploads work normally but processing features will be disabled with informative error messages.

#### Video Upload Without Processing

```php
// Video uploads work even without FFmpeg
public function uploadVideo(Request $request)
{
    $request->validate(['video' => 'required|file|mimes:mp4,avi,mov,wmv|max:51200']);

    $result = MinioStorage::upload(
        $request->file('video'),
        'videos/' . time() . '-' . $request->file('video')->getClientOriginalName()
    );
  
    return response()->json(['success' => true, 'data' => $result]);
}
```

#### Video Processing with FFmpeg

```php
// Full video processing (requires FFmpeg)
public function processVideo(Request $request)
{
    $request->validate(['video' => 'required|file|mimes:mp4,avi,mov,wmv|max:51200']);

    try {
        $result = MinioStorage::upload(
            $request->file('video'),
            'videos/processed/' . time() . '.mp4',
            [
                'video' => [
                    'format' => 'mp4',
                    'compression' => 'medium',
                    'resize' => ['width' => 1280, 'height' => 720],
                    'clip' => ['start' => 0, 'duration' => 30], // First 30 seconds
                    'watermark' => [
                        'path' => public_path('logo.png'),
                        'position' => 'bottom-right'
                    ]
                ],
                'video_thumbnail' => [
                    'time' => 5, // Extract frame at 5 seconds
                    'width' => 320,
                    'height' => 240
                ]
            ]
        );
        
        return response()->json(['success' => true, 'data' => $result]);
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'FFmpeg') !== false) {
            // Handle FFmpeg not available
            return response()->json([
                'success' => false,
                'message' => 'Video processing not available. Upload without processing?',
                'error' => $e->getMessage()
            ], 422);
        }
        throw $e;
    }
}
```

#### Checking FFmpeg Availability

```php
use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;

// Check if video processing is available
$videoProcessor = new VideoProcessor($logger);
$canProcessVideo = $videoProcessor->isFFmpegAvailable();

if ($canProcessVideo) {
    // Full video processing features available
    $result = MinioStorage::upload($video, null, [
        'video' => ['format' => 'mp4', 'compression' => 'medium']
    ]);
} else {
    // Upload without processing
    $result = MinioStorage::upload($video);
    // Add warning to user about limited functionality
}
```

#### Video Processing Options

```php
$videoOptions = [
    'video' => [
        'format' => 'mp4',           // Output format (mp4, webm)
        'compression' => 'medium',    // ultrafast, fast, medium, slow, veryslow
        'resize' => [
            'width' => 1280,
            'height' => 720,
            'mode' => 'fit'          // fit, crop
        ],
        'clip' => [
            'start' => '00:00:10',   // Start time
            'duration' => '00:01:30' // Duration
        ],
        'rotate' => 90,              // Rotation angle (90, 180, 270)
        'watermark' => [
            'path' => '/path/to/logo.png',
            'position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
            'opacity' => 0.7
        ],
        'video_bitrate' => '2000k',  // Video bitrate
        'audio_bitrate' => '128k',   // Audio bitrate
        'additional_params' => ['-preset', 'slow'] // Custom FFmpeg parameters
    ],
    'video_thumbnail' => [
        'time' => 5,                 // Time in seconds or '00:00:05'
        'width' => 320,
        'height' => 240,
        'suffix' => '-thumb',
        'path' => 'thumbnails'
    ]
];
```

### Security Scanning Configuration

```php
// Configure in config/minio-storage.php
'security' => [
    'scan_images' => false,      // Usually false for images
    'scan_documents' => true,    // Recommended for documents
    'scan_archives' => true,     // Scan ZIP, RAR files
    'max_file_size' => 10485760, // 10MB max scan size
],

// The scanner detects:
// - VBA macros in Office documents
// - JavaScript in PDFs
// - Embedded files
// - Suspicious shell commands
// - PHP code injection
// - External links and references
```

### File Type Restrictions

```php
// Configure allowed file types in config/minio-storage.php
'allowed_types' => [
    'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
    'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
    'archives' => ['zip', 'rar', '7z', 'tar', 'gz'],
    'videos' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'],
    'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg'],
],
```

## API Reference

### StorageService Methods

#### `upload($source, $destinationPath = null, $options = [])`

Upload a file with optional processing.

**Parameters:**

- `$source`: Laravel UploadedFile, file path, or StreamInterface
- `$destinationPath`: Optional destination path (auto-generated if null)
- `$options`: Array of processing options

**Returns:** Array with upload results

#### `delete($path)`

Delete a file from storage.

**Parameters:**

- `$path`: File path in storage

**Returns:** Boolean success status

#### `fileExists($path)`

Check if a file exists in storage.

**Parameters:**

- `$path`: File path in storage

**Returns:** Boolean existence status

#### `getMetadata($path)`

Get detailed file metadata.

**Parameters:**

- `$path`: File path in storage

**Returns:** Array with metadata (size, mime_type, dimensions for images, etc.)

#### `getUrl($path, $expiration = 3600)`

Generate a presigned URL for file access.

**Parameters:**

- `$path`: File path in storage
- `$expiration`: URL expiration time in seconds

**Returns:** Presigned URL string

### Upload Options

#### General Options

- `scan` (bool): Enable security scanning
- `naming` (string|NamerInterface): Naming strategy ('hash', 'slug', 'original', or custom)
- `preserve_structure` (bool): Maintain directory structure

#### Image Options

- `image` (array): Image processing configuration
  - `resize` (array): Width/height for resizing
  - `convert` (string): Target format ('jpg', 'png', 'webp')
  - `quality` (int): Compression quality (1-100)
  - `auto_orient` (bool): Auto-rotate based on EXIF
  - `strip_metadata` (bool): Remove EXIF data
  - `max_width/max_height` (int): Maximum dimensions
  - `watermark` (array): Watermark configuration

#### Compression Options

- `compress` (bool): Enable dedicated compression
- `compression_options` (array): Compression configuration
  - `quality` (int): Compression quality (1-100)
  - `quality_preset` (string): Quality preset ('low', 'medium', 'high', 'very_high', 'max')
  - `format` (string): Target format ('jpg', 'png', 'webp', 'avif')
  - `target_size` (int): Target file size in bytes
  - `max_quality` (int): Maximum quality for target size compression
  - `min_quality` (int): Minimum quality for target size compression
  - `progressive` (bool): Enable progressive JPEG

#### Optimization Options

- `optimize` (bool): Enable smart optimization
- `smart_compression` (bool): Auto-adjust compression based on image characteristics
- `optimize_for_web` (bool): Enable web optimization
- `web_options` (array): Web optimization configuration
  - `max_width/max_height` (int): Maximum dimensions for web
  - `quality` (int): Web compression quality
  - `format` (string): Web format preference
  - `progressive` (bool): Enable progressive encoding

#### Thumbnail Options

- `thumbnail` (array): Thumbnail generation configuration
  - `width/height` (int): Thumbnail dimensions
  - `method` (string): Sizing method ('fit', 'crop', 'resize')
  - `quality` (int): Compression quality
  - `format` (string): Output format
  - `suffix` (string): Filename suffix
  - `path` (string): Thumbnail directory
  - `optimize` (bool): Apply optimization to thumbnails

#### Video Options (Optional - Requires FFmpeg)

- `video` (array): Video processing configuration
  - `format` (string): Output format ('mp4', 'webm')
  - `compression` (string): Compression preset ('ultrafast', 'fast', 'medium', 'slow', 'veryslow')
  - `resize` (array): Video resizing configuration
    - `width/height` (int): Target dimensions
    - `mode` (string): Resize mode ('fit', 'crop')
  - `clip` (array): Video clipping configuration
    - `start` (string|int): Start time ('00:00:10' or seconds)
    - `duration` (string|int): Duration ('00:01:30' or seconds)
  - `rotate` (int): Rotation angle (90, 180, 270)
  - `watermark` (array): Video watermark configuration
    - `path` (string): Watermark image path
    - `position` (string): Position ('top-left', 'top-right', 'bottom-left', 'bottom-right', 'center')
    - `opacity` (float): Opacity (0.0 to 1.0)
  - `video_bitrate` (string): Video bitrate ('2000k')
  - `audio_bitrate` (string): Audio bitrate ('128k')
  - `additional_params` (array): Custom FFmpeg parameters

- `video_thumbnail` (array): Video thumbnail generation configuration
  - `time` (int|string): Frame extraction time (seconds or '00:00:05')
  - `width/height` (int): Thumbnail dimensions
  - `suffix` (string): Filename suffix
  - `path` (string): Thumbnail directory

## Compression Performance & Benefits

### Compression Efficiency

The advanced compression features provide significant benefits:

- **File Size Reduction**: Up to 80% smaller files while maintaining visual quality
- **Bandwidth Savings**: Reduced storage costs and faster transfer speeds
- **Web Performance**: Optimized images for faster page loading
- **Format Flexibility**: Multiple formats for different use cases
- **Smart Optimization**: Automatic quality adjustment based on image characteristics

### Compression Comparison

```php
// Example compression results for a 2MB image:
$originalSize = 2048000; // 2MB

// Standard compression (quality: 85)
$standardResult = MinioStorage::upload($image, null, [
    'compress' => true,
    'compression_options' => ['quality' => 85]
]);
// Result: ~600KB (70% reduction)

// Target size compression (500KB)
$targetResult = MinioStorage::upload($image, null, [
    'compress' => true,
    'compression_options' => ['target_size' => 500000]
]);
// Result: ~500KB (75% reduction)

// Web optimization
$webResult = MinioStorage::upload($image, null, [
    'optimize_for_web' => true
]);
// Result: ~400KB (80% reduction) with web-optimized dimensions
```

### Format Recommendations

- **JPEG**: Best for photos with many colors
- **WebP**: Modern format with superior compression (20-35% smaller than JPEG)
- **AVIF**: Next-gen format with excellent compression (up to 50% smaller than JPEG)
- **PNG**: Best for graphics with transparency or few colors

## Error Handling

The library provides specific exceptions for different scenarios:

```php
use Triginarsa\MinioStorageUtils\Exceptions\UploadException;
use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Triginarsa\MinioStorageUtils\Exceptions\FileNotFoundException;

try {
    $result = MinioStorage::upload($file);
} catch (SecurityException $e) {
    // Handle security threats (malicious content detected)
    Log::warning('Security threat detected', $e->getContext());
} catch (UploadException $e) {
    // Handle upload failures
    Log::error('Upload failed', $e->getContext());
} catch (FileNotFoundException $e) {
    // Handle missing files
    Log::error('File not found', $e->getContext());
}
```

## Logging

The library provides comprehensive logging for monitoring and debugging:

```php
// Example log entries
[2024-01-01 12:00:00] minio-storage.INFO: Upload started {"destination":"uploads/2024/01/01/image.jpg","options":{"scan":true}}
[2024-01-01 12:00:01] minio-storage.INFO: Security scan completed successfully {"filename":"image.jpg"}
[2024-01-01 12:00:02] minio-storage.INFO: Image processing started {"options":{"resize":{"width":1024}}}
[2024-01-01 12:00:03] minio-storage.INFO: Thumbnail created {"width":200,"height":200,"method":"fit"}
[2024-01-01 12:00:04] minio-storage.INFO: Upload completed successfully {"destination":"uploads/2024/01/01/image.jpg"}
```

## Configuration Examples

### Environment-based Compression Settings

```env
# High-quality compression for professional photography
MINIO_COMPRESSION_QUALITY=90
MINIO_COMPRESSION_FORMAT=jpg
MINIO_COMPRESSION_PROGRESSIVE=true
MINIO_WEB_MAX_WIDTH=2560
MINIO_WEB_QUALITY=90

# Optimized for web performance
MINIO_COMPRESSION_QUALITY=75
MINIO_COMPRESSION_FORMAT=webp
MINIO_WEB_MAX_WIDTH=1920
MINIO_WEB_QUALITY=80
MINIO_IMAGE_OPTIMIZE=true
MINIO_IMAGE_SMART_COMPRESSION=true

# Maximum compression for storage efficiency
MINIO_COMPRESSION_PRESET=medium
MINIO_COMPRESSION_TARGET_SIZE=300000
MINIO_WEB_MAX_WIDTH=1280
MINIO_THUMBNAIL_OPTIMIZE=true
```

### Configuration File Customization

```php
// config/minio-storage.php
return [
    'compression' => [
        'quality' => env('MINIO_COMPRESSION_QUALITY', 80),
        'format' => env('MINIO_COMPRESSION_FORMAT', 'jpg'),
        'progressive' => env('MINIO_COMPRESSION_PROGRESSIVE', true),
        'quality_preset' => env('MINIO_COMPRESSION_PRESET', 'high'),
    ],
  
    'web_optimization' => [
        'max_width' => env('MINIO_WEB_MAX_WIDTH', 1920),
        'max_height' => env('MINIO_WEB_MAX_HEIGHT', 1080),
        'quality' => env('MINIO_WEB_QUALITY', 85),
        'format' => env('MINIO_WEB_FORMAT', 'jpg'),
    ],
  
    'thumbnail' => [
        'optimize' => env('MINIO_THUMBNAIL_OPTIMIZE', true),
        'format' => env('MINIO_THUMBNAIL_FORMAT', 'jpg'),
        'quality' => env('MINIO_THUMBNAIL_QUALITY', 75),
    ],
];
```

## Non-Laravel Usage

For non-Laravel applications:

```php
use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\StorageServiceFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Configure
$config = new MinioConfig(
    key: 'your-access-key',
    secret: 'your-secret-key',
    bucket: 'your-bucket',
    endpoint: 'http://localhost:9000'
);

// Create logger
$logger = new Logger('minio-storage');
$logger->pushHandler(new StreamHandler('storage.log', Logger::INFO));

// Create service
$storage = StorageServiceFactory::create($config, $logger);

// Use service
$result = $storage->upload('/path/to/file.jpg', 'uploads/file.jpg');
```

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

## Security Considerations

- Always enable security scanning for user-uploaded files
- Configure appropriate file type restrictions
- Use hash-based naming to prevent directory traversal
- Regularly update the security patterns
- Monitor logs for security threats
- Consider implementing virus scanning for additional security

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Support

For issues and questions, please use the [GitHub Issues](https://github.com/triginarsa/minio-storage-utils/issues) page.
