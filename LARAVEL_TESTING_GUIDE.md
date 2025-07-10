# Laravel 10 Testing Guide for MinIO Storage Utils

This guide demonstrates how to test the MinIO Storage Utils library with Laravel 10.

## Setup Summary

✅ **Completed Setup:**
1. Laravel 10 project created
2. MinIO Storage Utils library installed via Composer
3. Configuration published and filesystem disk configured
4. Test controller and routes created
5. Web interface for testing created

## Project Structure

```
laravel-minio-test/
├── app/Http/Controllers/MinioTestController.php  # Test controller
├── config/filesystems.php                       # MinIO disk configuration
├── config/minio-storage.php                     # MinIO library config
├── resources/views/minio-test.blade.php         # Test interface
├── routes/web.php                               # Test routes
├── test-minio.php                              # Standalone test script
└── .env                                        # Environment configuration
```

## Configuration Details

### Environment Variables (.env)
```env
# MinIO Configuration
MINIO_ENDPOINT=http://localhost:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_BUCKET=test-bucket
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE_ENDPOINT=true

# MinIO Storage Options
MINIO_STORAGE_SCAN_ENABLED=true
MINIO_STORAGE_NAMING_STRATEGY=hash
MINIO_STORAGE_PRESERVE_STRUCTURE=true
MINIO_STORAGE_MAX_FILE_SIZE=10485760

# Image Compression Settings
MINIO_COMPRESSION_ENABLED=true
MINIO_COMPRESSION_QUALITY=85
MINIO_COMPRESSION_FORMAT=jpg
MINIO_COMPRESSION_PROGRESSIVE=true

# Security Settings
MINIO_SECURITY_SCAN_IMAGES=true
MINIO_SECURITY_SCAN_DOCUMENTS=true
MINIO_SECURITY_MAX_FILE_SIZE=10485760
```

### Filesystem Configuration (config/filesystems.php)
```php
'disks' => [
    // ... existing disks

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

## Test Controller Features

The `MinioTestController` provides comprehensive testing endpoints:

### Available Endpoints

1. **System Status** - `GET /minio-test/status`
   - Check system compatibility
   - Verify MinIO connection
   - Check available extensions

2. **Basic Upload** - `POST /minio-test/upload`
   - Simple file upload with security scanning
   - Automatic file naming and path generation

3. **Image Upload with Compression** - `POST /minio-test/upload-image`
   - Image compression with quality settings
   - Thumbnail generation
   - Format conversion (JPG, PNG, WebP)

4. **Web Optimization** - `POST /minio-test/web-optimize`
   - Optimize images for web delivery
   - Automatic resizing and compression
   - WebP format conversion

5. **Video Upload** - `POST /minio-test/upload-video`
   - Video file upload
   - FFmpeg availability check
   - Optional video processing

6. **Batch Upload** - `POST /minio-test/batch-upload`
   - Multiple file upload (up to 5 files)
   - Individual file processing results

7. **File Operations**
   - Download: `POST /minio-test/download`
   - Delete: `DELETE /minio-test/delete`
   - Metadata: `GET /minio-test/metadata`

8. **Compression Comparison** - `POST /minio-test/compression-comparison`
   - Compare different compression levels
   - Multiple format testing (JPG, WebP)
   - Compression ratio analysis

## Testing Methods

### 1. Web Interface Testing

Access the web interface at: `http://localhost:8000/minio-test`

The interface provides:
- System status checking
- File upload forms with various options
- Real-time results display
- Error handling and feedback

### 2. API Testing with cURL

```bash
# Test system status
curl -X GET http://localhost:8000/minio-test/status

# Test basic upload
curl -X POST -F "file=@test-image.jpg" http://localhost:8000/minio-test/upload

# Test image compression
curl -X POST \
  -F "image=@test-image.jpg" \
  -F "quality=75" \
  -F "format=webp" \
  http://localhost:8000/minio-test/upload-image

# Test file download
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"file_path":"uploads/2024/07/10/abc123.jpg"}' \
  http://localhost:8000/minio-test/download
```

### 3. Standalone Testing

Run the standalone test script:
```bash
php test-minio.php
```

This script tests:
- Class loading and autoloading
- System compatibility
- MinIO configuration
- Basic file operations (if MinIO server is running)
- Image processing capabilities

## Setting Up MinIO Server

### Using Docker (Recommended)

```bash
# Start MinIO server
docker run -p 9000:9000 -p 9001:9001 \
  --name minio \
  -e "MINIO_ROOT_USER=minioadmin" \
  -e "MINIO_ROOT_PASSWORD=minioadmin" \
  minio/minio server /data --console-address ":9001"

# Access MinIO Console: http://localhost:9001
# Username: minioadmin
# Password: minioadmin
```

### Create Test Bucket

1. Open MinIO Console at http://localhost:9001
2. Login with minioadmin/minioadmin
3. Create a bucket named `test-bucket`
4. Set bucket policy to public (for testing)

## Usage Examples

### Basic Usage in Laravel Controller

```php
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;

public function uploadFile(Request $request)
{
    $request->validate(['file' => 'required|file|max:10240']);
    
    $result = MinioStorage::upload($request->file('file'));
    
    return response()->json([
        'success' => true,
        'path' => $result['path'],
        'url' => $result['url'] ?? null
    ]);
}
```

### Image Upload with Processing

```php
public function uploadImage(Request $request)
{
    $result = MinioStorage::upload($request->file('image'), null, [
        'compress' => true,
        'compression_options' => [
            'quality' => 85,
            'format' => 'jpg',
            'progressive' => true
        ],
        'thumbnail' => [
            'width' => 300,
            'height' => 300,
            'quality' => 80
        ]
    ]);
    
    return response()->json($result);
}
```

### Video Upload with Processing

```php
public function uploadVideo(Request $request)
{
    $options = [];
    
    if (MinioStorage::isVideoProcessingAvailable()) {
        $options = [
            'process_video' => true,
            'video_options' => [
                'thumbnail' => true,
                'thumbnail_time' => 5,
                'quality' => 'medium'
            ]
        ];
    }
    
    $result = MinioStorage::upload($request->file('video'), null, $options);
    
    return response()->json([
        'success' => true,
        'data' => $result,
        'ffmpeg_available' => MinioStorage::isVideoProcessingAvailable()
    ]);
}
```

## Test Results

### System Compatibility ✅
- PHP 8.1.33 ✅
- GD Extension ✅
- cURL Extension ✅
- OpenSSL Extension ✅
- Intervention Image ✅
- AWS SDK ✅

### Library Status ✅
- MinIO Storage Utils properly installed
- All classes loadable
- Configuration working
- Service provider registered
- Facade available

### Missing (Optional)
- ImageMagick extension (not required, GD works fine)
- FFmpeg (for video processing features)

## Troubleshooting

### Common Issues

1. **Routes not loading**
   - Clear route cache: `php artisan route:clear`
   - Check controller syntax: `php -l app/Http/Controllers/MinioTestController.php`

2. **MinIO connection fails**
   - Verify MinIO server is running
   - Check endpoint URL and credentials
   - Ensure bucket exists

3. **File upload errors**
   - Check file permissions
   - Verify file size limits
   - Check security scanner settings

4. **Image processing issues**
   - Ensure GD extension is installed
   - Check memory limits for large images
   - Verify Intervention Image installation

## Performance Notes

- **Image Compression**: Reduces file sizes by 40-70% typically
- **WebP Format**: 25-35% smaller than JPEG with same quality
- **Thumbnail Generation**: Fast with GD, supports multiple formats
- **Security Scanning**: Minimal performance impact for most files

## Security Considerations

- Enable security scanning for user uploads
- Use hash-based file naming to prevent conflicts
- Configure appropriate file type restrictions
- Set reasonable file size limits
- Monitor upload logs for suspicious activity

## Next Steps

1. **Production Setup**
   - Configure production MinIO server
   - Set up proper SSL certificates
   - Configure backup strategies

2. **Advanced Features**
   - Install FFmpeg for video processing
   - Configure CDN integration
   - Set up automated testing

3. **Monitoring**
   - Set up logging and monitoring
   - Configure alerts for failures
   - Monitor storage usage

## Support

For issues and questions:
- Check the main library documentation
- Review test outputs for specific errors
- Verify MinIO server connectivity
- Check Laravel logs for detailed error information 