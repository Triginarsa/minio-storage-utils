# MinIO Storage Utils for Laravel

A simple PHP library for secure file handling with MinIO object storage, designed for Laravel applications.

## What it does

- 🔒 **Secure file uploads** with virus/malware scanning
- 🖼️ **Image processing** (resize, compress, watermark, thumbnails)
- 🎬 **Video processing** (optional, requires FFmpeg)
- 📄 **Document security** scanning
- 🔗 **Flexible URLs** (public or private with expiration)
- 📝 **Smart file naming** (hash, slug, or original names)

## Installation

### Step 1: Install via Composer

```bash
composer require triginarsa/minio-storage-utils
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="Triginarsa\MinioStorageUtils\Laravel\MinioStorageServiceProvider" --tag="config"
```

### Step 3: Configure MinIO Disk

Add to `config/filesystems.php`:

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

### Step 4: Environment Setup

Add to your `.env` file:

```env
# MinIO Connection Settings
MINIO_ACCESS_KEY=your-access-key
MINIO_SECRET_KEY=your-secret-key
MINIO_BUCKET=your-bucket-name
MINIO_ENDPOINT=http://localhost:9000
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE_ENDPOINT=true

# Storage Disk Configuration
MINIO_STORAGE_DISK=minio
MINIO_STORAGE_DISK_BACKUP=minio

# Security & File Handling
MINIO_SECURITY_SCAN=true
MINIO_NAMING_STRATEGY=hash
MINIO_MAX_FILE_SIZE=10240

# Image Processing Settings
MINIO_IMAGE_QUALITY=85
MINIO_IMAGE_MAX_WIDTH=2048
MINIO_IMAGE_MAX_HEIGHT=2048
MINIO_IMAGE_CONVERT_FORMAT=jpg

# Video Processing (Optional)
MINIO_VIDEO_PROCESSING=false
MINIO_VIDEO_MAX_SIZE=102400

# URL Configuration
MINIO_URL_DEFAULT_EXPIRATION=
MINIO_URL_FORCE_HTTPS=false
```

**Important Notes:**

- Both `MINIO_STORAGE_DISK` and `MINIO_STORAGE_DISK_BACKUP` should be set to `minio` (matching your disk name in `config/filesystems.php`)
- Leave `MINIO_URL_DEFAULT_EXPIRATION` empty for public URLs, or set seconds (e.g., `3600`) for private URLs
- Set `MINIO_VIDEO_PROCESSING=true` only if you have FFmpeg installed
- For others env can check on .env.example

## Quick Start

### Basic File Upload

```php
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;

public function upload(Request $request)
{
    $request->validate(['file' => 'required|file|max:10240']);
  
    $result = MinioStorage::upload($request->file('file'));
  
    return response()->json([
        'success' => true,
        'url' => $result['main']['url'],
        'path' => $result['main']['path']
    ]);
}
```

### Image Upload with Processing

```php
public function uploadImage(Request $request)
{
    $request->validate(['image' => 'required|image|max:10240']);
  
    $result = MinioStorage::upload($request->file('image'), null, [
        'image' => [
            'resize' => ['width' => 1024, 'height' => 768],
            'quality' => 85,
            'convert' => 'jpg'
        ],
        'thumbnail' => [
            'width' => 200,
            'height' => 200
        ]
    ]);
  
    return response()->json([
        'success' => true,
        'image' => $result['main']['url'],
        'thumbnail' => $result['thumbnail']['url']
    ]);
}
```

### Get File URLs

```php
// Get URL for existing file
$url = MinioStorage::getUrl('path/to/file.jpg');

// Get public URL (no expiration)
$publicUrl = MinioStorage::getPublicUrl('path/to/file.jpg');

// Get private URL with expiration
$privateUrl = MinioStorage::getUrl('path/to/file.jpg', 3600); // 1 hour
```

### Delete Files

```php
// Delete a file
$deleted = MinioStorage::delete('path/to/file.jpg');

// Check if file exists
$exists = MinioStorage::fileExists('path/to/file.jpg');
```

## Examples

Check out the `examples/` folder for complete working examples:

### Basic Examples

- **[basic-usage.php](examples/basic-usage.php)** - Non-Laravel usage example
- **[laravel-basic-usage.php](examples/laravel-basic-usage.php)** - Simple Laravel file upload

### Image Processing Examples

- **[laravel-image-processing-example.php](examples/laravel-image-processing-example.php)** - Image resize, compress, and thumbnails
- **[laravel-watermark-example.php](examples/laravel-watermark-example.php)** - Add watermarks to images

### Advanced Examples

- **[laravel-doc-example.php](examples/laravel-doc-example.php)** - Document upload with security scanning
- **[laravel-security-example.php](examples/laravel-security-example.php)** - Security scanning and threat detection
- **[laravel-video-usage.php](examples/laravel-video-usage.php)** - Video processing with FFmpeg

### API References

If you want to see the detailed API reference, check out this [API REFERENCE](API-REFERENCE.md) document.

## Configuration Options

### Image Processing

```php
'image' => [
    'resize' => [
        'width' => 1024,
        'height' => 768,
        'method' => 'fit' // fit, crop, stretch
    ],
    'quality' => 85,
    'convert' => 'jpg',
    'watermark' => [
        'path' => public_path('watermark.png'),
        'position' => 'bottom-right',
        'opacity' => 70
    ]
]
```

### Thumbnail Generation

```php
'thumbnail' => [
    'width' => 200,
    'height' => 200,
    'method' => 'fit', // fit, crop, proportional
    'quality' => 75
]
```

### Security Scanning

```php
'scan' => true, // Enable security scanning
'naming' => 'hash' // hash, slug, original
```

### Video Processing (Optional)

```php
'video' => [
    'format' => 'mp4',
    'compression' => 'medium',
    'resize' => ['width' => 1280, 'height' => 720]
]
```

## File Naming Strategies

- **hash**: `a1b2c3d4e5f6...123456789.jpg` (secure, prevents duplicates)
- **slug**: `my-vacation-photo-1704067200.jpg` (SEO-friendly)
- **original**: `My Vacation Photo.jpg` (keeps original name)

## URL Types

### Public URLs (No Expiration)

Set `MINIO_URL_DEFAULT_EXPIRATION=` (empty) in `.env`

- Direct access: `http://localhost:9000/bucket/file.jpg`
- Requires public bucket policy

### Private URLs (With Expiration)

Set `MINIO_URL_DEFAULT_EXPIRATION=3600` in `.env`

- Secure access: `http://localhost:9000/bucket/file.jpg?X-Amz-Algorithm=...`
- Expires after specified time

## Error Handling

```php
use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Triginarsa\MinioStorageUtils\Exceptions\UploadException;

try {
    $result = MinioStorage::upload($file);
} catch (SecurityException $e) {
    // Malicious file detected
    return response()->json(['error' => 'Security threat detected'], 403);
} catch (UploadException $e) {
    // Upload failed
    return response()->json(['error' => 'Upload failed'], 500);
}
```

## Requirements

- PHP 8.1+
- Laravel 10.0+
- MinIO server or S3-compatible storage
- GD extension (for image processing)
- FFmpeg (optional, for video processing)

## FFmpeg Installation (Optional)

For video processing features:

```bash
# Ubuntu/Debian
sudo apt-get install ffmpeg

# macOS
brew install ffmpeg

# Or skip video processing - uploads still work without it
```

## Testing

```bash
composer test
```

## Security Features

The library automatically scans uploads for:

- Malicious code (PHP, JavaScript, etc.)
- Virus signatures
- Suspicious file types
- Embedded scripts in images
- Macro-enabled documents

## Contributing

Contributions welcome! Please check the [issues page](https://github.com/triginarsa/minio-storage-utils/issues).

## License

MIT License
