# Minio Storage Utils

A PHP library for secure file handling with MinIO object storage, designed specifically for Laravel applications. Features include image processing, security scanning, flexible naming strategies, and logging.

## Features

- **🔒 Enhanced Security Scanning**: Advanced threat detection and prevention
  - **PHP/Script Injection**: Detects embedded PHP, ASP, and JavaScript code
  - **Polyglot File Detection**: Identifies files with multiple format signatures
  - **EXIF Malicious Data**: Scans image metadata for suspicious content
  - **Image End Marker Bypass**: Detects scripts hidden after image end markers
  - **SVG Script Injection**: Comprehensive SVG security scanning
  - **Obfuscated Code Detection**: Identifies encoded and obfuscated malicious code
  - **File System Function Detection**: Scans for dangerous file manipulation functions
  - **Network Function Detection**: Identifies suspicious network-related code
  - **Processed Image Scanning**: Scans both original and processed images
  - **Configurable Security Levels**: Basic, strict, and custom security modes
  - **Automatic Threat Blocking**: Prevents malicious uploads with detailed logging
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
MINIO_IMAGE_AUTO_ORIENT=true
MINIO_IMAGE_STRIP_METADATA=true
MINIO_IMAGE_OPTIMIZE=false
MINIO_IMAGE_FORMAT=jpg
MINIO_IMAGE_PROGRESSIVE=true

# Image compression settings
MINIO_COMPRESSION_QUALITY=80
MINIO_COMPRESSION_TARGET_SIZE=
MINIO_COMPRESSION_MAX_QUALITY=95
MINIO_COMPRESSION_MIN_QUALITY=60
MINIO_COMPRESSION_PRESET=high

# Web optimization settings
MINIO_WEB_MAX_WIDTH=1920
MINIO_WEB_MAX_HEIGHT=1080
MINIO_WEB_QUALITY=85

# Watermark settings
MINIO_WATERMARK_AUTO_RESIZE=true
MINIO_WATERMARK_RESIZE_METHOD=proportional
MINIO_WATERMARK_SIZE_RATIO=0.15
MINIO_WATERMARK_MIN_SIZE=50
MINIO_WATERMARK_MAX_SIZE=400
MINIO_WATERMARK_POSITION=bottom-right
MINIO_WATERMARK_OPACITY=70
MINIO_WATERMARK_MARGIN=10

# Thumbnail settings
MINIO_THUMBNAIL_WIDTH=200
MINIO_THUMBNAIL_HEIGHT=200
MINIO_THUMBNAIL_METHOD=fit
MINIO_THUMBNAIL_QUALITY=75
MINIO_THUMBNAIL_OPTIMIZE=true
MINIO_THUMBNAIL_FORMAT=jpg
MINIO_THUMBNAIL_SUFFIX=-thumb
MINIO_THUMBNAIL_PATH=thumbnails

# Video thumbnail settings
MINIO_VIDEO_THUMBNAIL_WIDTH=320
MINIO_VIDEO_THUMBNAIL_HEIGHT=240
MINIO_VIDEO_THUMBNAIL_TIME=5

# URL Configuration
MINIO_URL_DEFAULT_EXPIRATION=    # null = public URLs (no expiration)
MINIO_URL_MAX_EXPIRATION=604800

# Video Processing Settings
MINIO_VIDEO_COMPRESSION=medium
MINIO_VIDEO_FORMAT=mp4
MINIO_VIDEO_BITRATE=2000
MINIO_VIDEO_AUDIO_BITRATE=128
MINIO_VIDEO_MAX_WIDTH=1920
MINIO_VIDEO_MAX_HEIGHT=1080
MINIO_VIDEO_QUALITY=medium

# FFmpeg Configuration
FFMPEG_BINARIES=/usr/bin/ffmpeg
FFPROBE_BINARIES=/usr/bin/ffprobe
FFMPEG_TIMEOUT=3600
FFMPEG_THREADS=12
```

## URL Configuration

### Public URLs (No Expiration)

By default, the library generates presigned URLs with expiration times for security. However, you can configure it to generate public URLs that don't expire:

```env
# Set to empty/null for public URLs
MINIO_URL_DEFAULT_EXPIRATION=
```

**Important Notes:**
- When `MINIO_URL_DEFAULT_EXPIRATION` is set to `null` or empty, the library automatically uses public URLs
- Public URLs require your MinIO bucket to have public read access configured
- Public URLs are direct links to files without presigned parameters

### Configuring Public Bucket Access

To use public URLs, you need to set a public read policy on your MinIO bucket:

```bash
# Using MinIO client (mc)
mc policy set public your-alias/your-bucket-name

# Or using MinIO console
# Navigate to your bucket → Access Rules → Add Access Rule
# Set prefix: * (all files)
# Set access: Read Only
```

### URL Generation Examples

```php
// Simple URL generation - uses config default
$url = MinioStorage::getUrl('path/to/file.jpg');
// Result when MINIO_URL_DEFAULT_EXPIRATION is null: http://localhost:9000/my-bucket/path/to/file.jpg
// Result when MINIO_URL_DEFAULT_EXPIRATION has value: http://localhost:9000/my-bucket/path/to/file.jpg?X-Amz-Algorithm=...

// Upload results automatically include URLs based on config
$result = MinioStorage::upload($file);
echo $result['main']['url']; // Public or presigned URL based on config

// Explicitly request public URL (always public regardless of config)
$publicUrl = MinioStorage::getPublicUrl('path/to/file.jpg');
echo $publicUrl; // http://localhost:9000/my-bucket/path/to/file.jpg

// Explicitly request presigned URL (always presigned regardless of config)
$presignedUrl = MinioStorage::getUrl('path/to/file.jpg', 3600);
echo $presignedUrl; // http://localhost:9000/my-bucket/path/to/file.jpg?X-Amz-Algorithm=...
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
    
    // The result includes URL based on your configuration
    // Public URL if MINIO_URL_DEFAULT_EXPIRATION is null
    // Presigned URL if MINIO_URL_DEFAULT_EXPIRATION has a value
    $fileUrl = $result['main']['url'];
  
    return response()->json(['success' => true, 'data' => $result]);
}

// Get URL for existing file
public function getFileUrl($path)
{
    // Uses config default - public or presigned based on MINIO_URL_DEFAULT_EXPIRATION
    $url = MinioStorage::getUrl($path);
    
    return response()->json(['url' => $url]);
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

## Security Features

### Enhanced Security Scanning

The library now includes comprehensive security scanning that detects multiple types of threats:

#### Basic Security Usage

```php
// Secure image upload with enhanced scanning
$result = MinioStorage::upload(
    $request->file('image'),
    'uploads/secure/',
    [
        'scan' => true,  // Enable security scanning (default: true)
        'naming' => 'hash',
        'image' => [
            'strip_metadata' => true,  // Remove potentially malicious EXIF data
            'quality' => 85,
            'convert' => 'jpg'
        ]
    ]
);
```

#### Security Configuration

Add to your `.env` file:

```env
# Security settings
MINIO_SCAN_IMAGES=true
MINIO_SCAN_DOCUMENTS=true
MINIO_SCAN_VIDEOS=false
MINIO_SCAN_ARCHIVES=true
MINIO_MAX_SCAN_SIZE=10485760
MINIO_SECURITY_STRICT_MODE=false
MINIO_SECURITY_ALLOW_SVG=true
MINIO_SECURITY_QUARANTINE=true
```

#### Threat Detection Examples

The security scanner detects various types of malicious content:

```php
// This will detect and block malicious uploads
try {
    $result = MinioStorage::upload($suspiciousFile, 'test/', ['scan' => true]);
} catch (SecurityException $e) {
    $threatInfo = $e->getContext();
  
    // Log the threat
    Log::warning('Security threat detected', [
        'file' => $threatInfo['filename'],
        'threat' => $threatInfo['threat'] ?? 'unknown',
        'pattern' => $threatInfo['pattern'] ?? null
    ]);
  
    return response()->json([
        'error' => 'Security threat detected',
        'details' => $e->getMessage()
    ], 403);
}
```

#### Detected Threats Include:

1. **PHP/Script Injection**

   - PHP tags (`<?php`, `<?`, `<?=`)
   - ASP tags (`<%`)
   - JavaScript injection
   - VBScript injection
2. **Polyglot Files**

   - ZIP files disguised as images
   - PDF files with image extensions
   - Executable files with image signatures
3. **Image-Specific Threats**

   - Scripts hidden after image end markers
   - Malicious EXIF data
   - SVG with embedded JavaScript
   - HTML comments in images
4. **Obfuscated Code**

   - Base64 encoded payloads
   - Hexadecimal encoded strings
   - Character manipulation functions
   - Function obfuscation
5. **File System Functions**

   - File manipulation functions
   - Directory traversal attempts
   - Permission modification attempts
6. **Network Functions**

   - Remote file inclusion
   - Curl/socket operations
   - Network communication attempts

#### Custom Security Patterns

You can add custom security patterns:

```php
// In a service provider or during application boot
app(SecurityScanner::class)->addPattern('/your-custom-pattern/i');
```

#### Security Scanning Levels

Configure different security levels in your config:

```php
// config/minio-storage.php
'security' => [
    'scan_images' => true,
    'scan_documents' => true,
    'strict_mode' => false,        // Enable for maximum security
    'allow_svg' => false,          // SVG files can contain scripts
    'quarantine_suspicious' => false, // Move suspicious files to quarantine
],
```

#### Testing Security

Use the provided security test controller:

```php
// Test various security scenarios
Route::post('/security/test', [SecurityTestController::class, 'testImageSecurity']);
Route::post('/security/polyglot', [SecurityTestController::class, 'testPolyglotDetection']);
Route::post('/security/exif', [SecurityTestController::class, 'testExifSecurity']);
Route::get('/security/report', [SecurityTestController::class, 'getSecurityReport']);
```

## Advanced Usage

### File Management Operations

```php
// Check if file exists
if (MinioStorage::fileExists('path/to/file.jpg')) {
    // Get detailed metadata
    $metadata = MinioStorage::getMetadata('path/to/file.jpg');
  
    // Generate URL (public or presigned based on config)
    $url = MinioStorage::getUrl('path/to/file.jpg');
  
    // Or explicitly request presigned URL (expires in 1 hour)
    $presignedUrl = MinioStorage::getUrl('path/to/file.jpg', 3600);
  
    // Or explicitly request public URL
    $publicUrl = MinioStorage::getPublicUrl('path/to/file.jpg');
  
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
// Example output: "a1b2c3d4e5f6789abcdef0123456789abcdef0123456789abcdef0123456789.jpg"

$result = MinioStorage::upload($file, null, ['naming' => 'slug']);
// Example output: "my-vacation-photo-1704067200.jpg"

$result = MinioStorage::upload($file, null, ['naming' => 'original']);
// Example output: "My Vacation Photo.jpg" (keeps original name)

// Using custom namer instance
$result = MinioStorage::upload($file, null, ['naming' => new HashNamer()]);
// Example output: "b2c3d4e5f6789abcdef0123456789abcdef0123456789abcdef0123456789a1.jpg"
```

#### Naming Strategy Examples

For a file originally named `"My Vacation Photo.jpg"`:

| Strategy       | Generated Filename                   | Description                            |
| -------------- | ------------------------------------ | -------------------------------------- |
| `'hash'`     | `a1b2c3d4e5f6...123456789.jpg`     | SHA256 hash of file content (64 chars) |
| `'slug'`     | `my-vacation-photo-1704067200.jpg` | URL-friendly slug + timestamp          |
| `'original'` | `My Vacation Photo.jpg`            | Original filename preserved            |

#### Benefits of Each Strategy

- **Hash**: Content-based, prevents duplicates, cache-friendly
- **Slug**: SEO-friendly URLs, readable filenames, timestamp prevents conflicts
- **Original**: Preserves user-intended names, familiar to users

### Image Processing & Advanced Compression

#### Resize Methods

The resize option supports multiple methods for different use cases:

```php
// Fit - Maintains aspect ratio, fits within bounds (letterbox/pillarbox)
'resize' => ['width' => 800, 'height' => 600, 'method' => 'fit']

// Crop - Fills entire area, crops excess (no distortion)
'resize' => ['width' => 800, 'height' => 600, 'method' => 'crop']

// Fill - Same as crop, fills and crops from center
'resize' => ['width' => 800, 'height' => 600, 'method' => 'fill']

// Stretch - Forces exact dimensions (may distort aspect ratio)
'resize' => ['width' => 800, 'height' => 600, 'method' => 'stretch']

// Proportional - Scales down proportionally to fit (default)
'resize' => ['width' => 800, 'height' => 600, 'method' => 'proportional']

// Scale - Same as proportional
'resize' => ['width' => 800, 'height' => 600, 'method' => 'scale']

// Width or height only - always proportional
'resize' => ['width' => 800] // Height auto-calculated
'resize' => ['height' => 600] // Width auto-calculated
```

**Method Comparison:**

- **fit/contain**: Best for profile pictures, maintains full image visibility
- **crop/cover**: Best for thumbnails, fills entire area without distortion
- **fill**: Same as crop, alternative naming
- **stretch/force**: Use when exact dimensions are critical (may distort)
- **proportional/scale**: Default behavior, safe scaling that never distorts

#### Basic Image Processing

```php
$result = MinioStorage::upload($image, null, [
    'image' => [
        'resize' => [
            'width' => 800, 
            'height' => 600,
            'method' => 'fit' // fit, crop, fill, stretch, proportional, scale
        ],
        'convert' => 'jpg',
        'quality' => 85, // User quality setting - will NOT be overridden
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
        'method' => 'fit', // fit, crop, proportional, scale, resize
        'quality' => 75,
        'format' => 'jpg',
        'suffix' => '-thumb',
        'path' => 'thumbnails'
    ]
]);
```

#### Quality Settings Priority

The system respects quality settings in this order:

1. **User-defined quality** (in `'image'` options) - highest priority, never overridden
2. Quality preset (e.g., 'low', 'medium', 'high')
3. Smart compression calculations (only if no user quality set)
4. Format defaults

```php
// Explicit quality - guaranteed to be used
'image' => ['quality' => 10] // Will use quality 10

// Quality preset - also guaranteed
'image' => ['quality_preset' => 'low'] // Uses quality 60

// No quality set - uses smart compression or defaults
'image' => ['optimize' => true] // Quality determined automatically
```

#### Debugging Quality Issues

If your quality settings aren't working as expected, check the logs for quality determination:

```php
// Enable logging to see what quality is actually used
$result = MinioStorage::upload($image, null, [
    'image' => [
        'quality' => 10, // Your explicit quality setting
        'convert' => 'jpg'
    ]
]);

// Check your Laravel logs for entries like:
// "Quality from user options" - your setting was used
// "Quality from format default" - your setting was ignored (check options structure)
// "Smart compression applied" - automatic quality was calculated
```

**Common Issues:**

- Quality set in wrong location (should be in `'image'` array)
- Config defaults overriding (now fixed)
- Smart compression overriding (now fixed)
- PNG format ignoring quality (PNG uses different compression)

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
  - `resize` (array): Width/height/method for resizing
    - `width` (int): Target width in pixels
    - `height` (int): Target height in pixels
    - `method` (string): Resize method - 'fit', 'crop', 'fill', 'stretch', 'proportional', 'scale'
  - `convert` (string): Target format ('jpg', 'png', 'webp')
  - `quality` (int): Compression quality (1-100)
  - `auto_orient` (bool): Auto-rotate based on EXIF
  - `strip_metadata` (bool): Remove EXIF data
  - `max_width/max_height` (int): Maximum dimensions
  - `watermark` (array): Watermark configuration
    - `path` (string): Path to watermark image file
    - `auto_resize` (bool): Enable automatic watermark resizing (default: true)
    - `resize_method` (string): Resizing method - 'proportional', 'percentage', 'fixed'
    - `size_ratio` (float): Size ratio for proportional method (default: 0.15 = 15%)
    - `min_size` (int): Minimum watermark size in pixels (default: 50)
    - `max_size` (int): Maximum watermark size in pixels (default: 400)
    - `position` (string): Position - 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'
    - `opacity` (int): Opacity percentage (default: 70)
    - `margin` (int): Margin from edges in pixels (default: 10)
    - `width` (int): Fixed width (when resize_method is 'fixed')
    - `height` (int): Fixed height (when resize_method is 'fixed')

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
  - `method` (string): Sizing method:
    - `'fit'`: Proportionally resize to fit within bounds (default)
    - `'crop'`: Crop to exact dimensions
    - `'proportional'`: Scale proportionally (same as 'fit')
    - `'scale'`: Scale proportionally to fit within bounds
    - `'resize'`: Force exact dimensions (may distort)
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
MINIO_IMAGE_FORMAT=jpg
MINIO_IMAGE_PROGRESSIVE=true
MINIO_WEB_MAX_WIDTH=2560
MINIO_WEB_QUALITY=90

# Optimized for web performance
MINIO_COMPRESSION_QUALITY=75
MINIO_IMAGE_FORMAT=webp
MINIO_WEB_MAX_WIDTH=1920
MINIO_WEB_QUALITY=80
MINIO_IMAGE_OPTIMIZE=true

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
    'image' => [
        'quality' => env('MINIO_IMAGE_QUALITY', 85),
        'format' => env('MINIO_IMAGE_FORMAT', 'jpg'),
        'progressive' => env('MINIO_IMAGE_PROGRESSIVE', true),
        'auto_orient' => env('MINIO_IMAGE_AUTO_ORIENT', true),
        'strip_metadata' => env('MINIO_IMAGE_STRIP_METADATA', true),
    
        'compression' => [
            'quality' => env('MINIO_COMPRESSION_QUALITY', 80),
            'preset' => env('MINIO_COMPRESSION_PRESET', 'high'),
            'target_size' => env('MINIO_COMPRESSION_TARGET_SIZE', null),
        ],
    
        'web' => [
            'max_width' => env('MINIO_WEB_MAX_WIDTH', 1920),
            'max_height' => env('MINIO_WEB_MAX_HEIGHT', 1080),
            'quality' => env('MINIO_WEB_QUALITY', 85),
        ],
    ],
  
    'thumbnail' => [
        'image' => [
            'optimize' => env('MINIO_THUMBNAIL_OPTIMIZE', true),
            'format' => env('MINIO_THUMBNAIL_FORMAT', 'jpg'),
            'quality' => env('MINIO_THUMBNAIL_QUALITY', 75),
        ],
        'video' => [
            'width' => env('MINIO_VIDEO_THUMBNAIL_WIDTH', 320),
            'height' => env('MINIO_VIDEO_THUMBNAIL_HEIGHT', 240),
            'time' => env('MINIO_VIDEO_THUMBNAIL_TIME', 5),
        ],
        'common' => [
            'suffix' => env('MINIO_THUMBNAIL_SUFFIX', '-thumb'),
            'path' => env('MINIO_THUMBNAIL_PATH', 'thumbnails'),
        ],
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
