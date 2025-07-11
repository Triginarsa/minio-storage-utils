# MinIO Storage Utils - API Reference

Complete reference for all functions, options, and properties available in the MinIO Storage Utils library.

## Table of Contents

1. [Main Functions](#main-functions)
2. [Upload Function Options](#upload-function-options)
3. [Image Processing Options](#image-processing-options)
4. [Thumbnail Options](#thumbnail-options)
5. [Video Processing Options](#video-processing-options)
6. [Security Options](#security-options)
7. [Naming Strategies](#naming-strategies)
8. [Response Structures](#response-structures)
9. [Exception Types](#exception-types)

---

## Main Functions

### `upload($source, $destinationPath, $options)`

Upload a file with optional processing.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$source` | `UploadedFile\|string\|StreamInterface` | Yes | Laravel uploaded file, file path, or stream |
| `$destinationPath` | `string\|null` | No | Target path in storage (auto-generated if null) |
| `$options` | `array` | No | Processing options (see [Upload Options](#upload-function-options)) |

#### Returns
`array` - Upload result with file information and URLs

#### Example
```php
$result = MinioStorage::upload($file, 'uploads/photo.jpg', [
    'scan' => true,
    'image' => ['resize' => ['width' => 1024]]
]);
```

---

### `delete($path)`

Delete a file from storage.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$path` | `string` | Yes | File path in storage |

#### Returns
`bool` - True if deleted successfully, false otherwise

#### Example
```php
$deleted = MinioStorage::delete('uploads/photo.jpg');
```

---

### `fileExists($path)`

Check if a file exists in storage.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$path` | `string` | Yes | File path in storage |

#### Returns
`bool` - True if file exists, false otherwise

#### Example
```php
$exists = MinioStorage::fileExists('uploads/photo.jpg');
```

---

### `getMetadata($path)`

Get detailed metadata for a file.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$path` | `string` | Yes | File path in storage |

#### Returns
`array` - File metadata including size, mime type, dimensions (for images)

#### Example
```php
$metadata = MinioStorage::getMetadata('uploads/photo.jpg');
// Returns: ['size' => 1024, 'mime_type' => 'image/jpeg', 'width' => 800, 'height' => 600]
```

---

### `getUrl($path, $expiration)`

Generate a URL for file access.

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$path` | `string` | Yes | File path in storage |
| `$expiration` | `int\|null` | No | URL expiration in seconds (uses config default if null) |

#### Returns
`string` - File URL (public or presigned based on configuration)

#### Example
```php
$url = MinioStorage::getUrl('uploads/photo.jpg', 3600); // 1 hour expiration
```

---

### `getPublicUrl($path)`

Generate a public URL (no expiration).

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$path` | `string` | Yes | File path in storage |

#### Returns
`string` - Public URL without expiration

#### Example
```php
$publicUrl = MinioStorage::getPublicUrl('uploads/photo.jpg');
```

---

## Upload Function Options

### General Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `scan` | `bool` | `true` | Enable security scanning for malicious content |
| `naming` | `string\|NamerInterface` | `'hash'` | Naming strategy: `'hash'`, `'slug'`, `'original'`, or custom namer |
| `preserve_structure` | `bool` | `false` | Maintain original directory structure |

### Example
```php
$options = [
    'scan' => true,
    'naming' => 'slug',
    'preserve_structure' => false
];
```

---

## Image Processing Options

### Main Image Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `resize` | `array` | `null` | Resize configuration |
| `convert` | `string` | `null` | Convert to format: `'jpg'`, `'png'`, `'webp'`, `'gif'` |
| `quality` | `int` | `85` | Compression quality (1-100) |
| `auto_orient` | `bool` | `true` | Auto-rotate based on EXIF data |
| `strip_metadata` | `bool` | `true` | Remove EXIF and other metadata |
| `max_width` | `int` | `2048` | Maximum width constraint |
| `max_height` | `int` | `2048` | Maximum height constraint |
| `optimize` | `bool` | `false` | Enable smart optimization |
| `format` | `string` | `'jpg'` | Default output format |
| `progressive` | `bool` | `true` | Enable progressive JPEG |
| `watermark` | `array` | `null` | Watermark configuration |

### Resize Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `width` | `int` | Required | Target width in pixels |
| `height` | `int` | Required | Target height in pixels |
| `method` | `string` | `'fit'` | Resize method (see [Resize Methods](#resize-methods)) |

#### Resize Methods

| Method | Description | Use Case |
|--------|-------------|----------|
| `'fit'` | Maintains aspect ratio, fits within bounds | Profile pictures, preserving full image |
| `'crop'` | Fills entire area, crops excess | Thumbnails, consistent dimensions |
| `'fill'` | Same as crop, alternative naming | Thumbnails, consistent dimensions |
| `'stretch'` | Forces exact dimensions (may distort) | When exact size is critical |
| `'proportional'` | Scales proportionally to fit | General resizing |
| `'scale'` | Same as proportional | General resizing |

### Watermark Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `path` | `string` | Required | Path to watermark image file |
| `position` | `string` | `'bottom-right'` | Position on image |
| `opacity` | `int` | `70` | Opacity percentage (0-100) |
| `margin` | `int` | `10` | Margin from edges in pixels |
| `auto_resize` | `bool` | `true` | Automatically resize watermark |
| `resize_method` | `string` | `'proportional'` | Resize method for watermark |
| `size_ratio` | `float` | `0.15` | Size ratio for proportional method |
| `min_size` | `int` | `50` | Minimum watermark size |
| `max_size` | `int` | `400` | Maximum watermark size |
| `width` | `int` | `null` | Fixed width (when resize_method is 'fixed') |
| `height` | `int` | `null` | Fixed height (when resize_method is 'fixed') |

#### Watermark Positions

| Position | Description |
|----------|-------------|
| `'top-left'` | Top-left corner |
| `'top-right'` | Top-right corner |
| `'bottom-left'` | Bottom-left corner |
| `'bottom-right'` | Bottom-right corner |
| `'center'` | Center of image |

### Example
```php
$imageOptions = [
    'image' => [
        'resize' => [
            'width' => 1024,
            'height' => 768,
            'method' => 'fit'
        ],
        'convert' => 'jpg',
        'quality' => 85,
        'auto_orient' => true,
        'strip_metadata' => true,
        'watermark' => [
            'path' => public_path('watermark.png'),
            'position' => 'bottom-right',
            'opacity' => 70,
            'margin' => 20
        ]
    ]
];
```

---

## Thumbnail Options

### Thumbnail Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `width` | `int` | `200` | Thumbnail width in pixels |
| `height` | `int` | `200` | Thumbnail height in pixels |
| `method` | `string` | `'fit'` | Sizing method |
| `quality` | `int` | `75` | Compression quality |
| `format` | `string` | `'jpg'` | Output format |
| `suffix` | `string` | `'-thumb'` | Filename suffix |
| `path` | `string` | `'thumbnails'` | Directory for thumbnails |
| `optimize` | `bool` | `true` | Apply optimization |

#### Thumbnail Methods

| Method | Description |
|--------|-------------|
| `'fit'` | Proportionally resize to fit within bounds |
| `'crop'` | Crop to exact dimensions |
| `'proportional'` | Scale proportionally |
| `'scale'` | Scale proportionally to fit |
| `'resize'` | Force exact dimensions (may distort) |

### Example
```php
$thumbnailOptions = [
    'thumbnail' => [
        'width' => 200,
        'height' => 200,
        'method' => 'crop',
        'quality' => 80,
        'format' => 'jpg',
        'suffix' => '-thumb',
        'path' => 'thumbnails'
    ]
];
```

---

## Video Processing Options

**Note:** Video processing requires FFmpeg to be installed. Without FFmpeg, video uploads work but processing features are disabled.

### Video Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `format` | `string` | `'mp4'` | Output format: `'mp4'`, `'webm'`, `'avi'`, `'mov'` |
| `compression` | `string` | `'medium'` | Compression preset |
| `resize` | `array` | `null` | Video resizing configuration |
| `clip` | `array` | `null` | Video clipping configuration |
| `rotate` | `int` | `null` | Rotation angle: `90`, `180`, `270` |
| `watermark` | `array` | `null` | Video watermark configuration |
| `video_bitrate` | `string` | `'2000k'` | Video bitrate |
| `audio_bitrate` | `string` | `'128k'` | Audio bitrate |
| `additional_params` | `array` | `[]` | Custom FFmpeg parameters |

#### Video Compression Presets

| Preset | Description | Use Case |
|--------|-------------|----------|
| `'ultrafast'` | Fastest encoding, larger file size | Quick processing |
| `'fast'` | Fast encoding, good quality | Balanced speed/quality |
| `'medium'` | Balanced encoding | General use |
| `'slow'` | Slow encoding, better quality | High quality output |
| `'veryslow'` | Slowest encoding, best quality | Maximum quality |

### Video Resize Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `width` | `int` | Required | Target width in pixels |
| `height` | `int` | Required | Target height in pixels |
| `mode` | `string` | `'fit'` | Resize mode: `'fit'`, `'crop'` |

### Video Clip Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `start` | `string\|int` | Required | Start time ('00:00:10' or seconds) |
| `duration` | `string\|int` | Required | Duration ('00:01:30' or seconds) |

### Video Watermark Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `path` | `string` | Required | Path to watermark image |
| `position` | `string` | `'bottom-right'` | Position on video |
| `opacity` | `float` | `0.7` | Opacity (0.0 to 1.0) |

### Video Thumbnail Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `time` | `int\|string` | `5` | Frame extraction time (seconds or '00:00:05') |
| `width` | `int` | `320` | Thumbnail width |
| `height` | `int` | `240` | Thumbnail height |
| `suffix` | `string` | `'-thumb'` | Filename suffix |
| `path` | `string` | `'thumbnails'` | Directory for thumbnails |

### Example
```php
$videoOptions = [
    'video' => [
        'format' => 'mp4',
        'compression' => 'medium',
        'resize' => [
            'width' => 1280,
            'height' => 720,
            'mode' => 'fit'
        ],
        'clip' => [
            'start' => '00:00:10',
            'duration' => '00:01:30'
        ],
        'watermark' => [
            'path' => public_path('logo.png'),
            'position' => 'bottom-right',
            'opacity' => 0.7
        ]
    ],
    'video_thumbnail' => [
        'time' => 5,
        'width' => 320,
        'height' => 240
    ]
];
```

---

## Security Options

### Security Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `scan` | `bool` | `true` | Enable security scanning |
| `scan_images` | `bool` | `true` | Scan image files for threats |
| `scan_documents` | `bool` | `true` | Scan document files for threats |
| `scan_videos` | `bool` | `false` | Scan video files for threats |
| `scan_archives` | `bool` | `true` | Scan archive files for threats |
| `max_scan_size` | `int` | `10485760` | Maximum file size to scan (bytes) |
| `strict_mode` | `bool` | `false` | Enable strict security mode |
| `allow_svg` | `bool` | `true` | Allow SVG files (can contain scripts) |
| `quarantine` | `bool` | `true` | Quarantine suspicious files |

### Security Threats Detected

| Threat Type | Description |
|-------------|-------------|
| **PHP/Script Injection** | PHP tags, ASP tags, JavaScript, VBScript |
| **Polyglot Files** | Files with multiple format signatures |
| **Image Threats** | Scripts hidden after image end markers |
| **EXIF Threats** | Malicious data in image metadata |
| **SVG Threats** | JavaScript embedded in SVG files |
| **Obfuscated Code** | Base64, hex-encoded malicious payloads |
| **File System Functions** | Dangerous file manipulation functions |
| **Network Functions** | Remote file inclusion, network operations |

### Example
```php
$securityOptions = [
    'scan' => true,
    'scan_images' => true,
    'scan_documents' => true,
    'strict_mode' => false,
    'allow_svg' => false
];
```

---

## Naming Strategies

### Built-in Naming Strategies

| Strategy | Description | Example Output |
|----------|-------------|----------------|
| `'hash'` | SHA256 hash of file content | `a1b2c3d4e5f6...789.jpg` |
| `'slug'` | URL-friendly slug with timestamp | `my-vacation-photo-1704067200.jpg` |
| `'original'` | Keep original filename | `My Vacation Photo.jpg` |

### Custom Naming

You can also use custom naming strategies by implementing the `NamerInterface`:

```php
use Triginarsa\MinioStorageUtils\Naming\NamerInterface;

class CustomNamer implements NamerInterface
{
    public function generate(string $originalName, string $extension): string
    {
        return 'custom-' . time() . '.' . $extension;
    }
}

$result = MinioStorage::upload($file, null, [
    'naming' => new CustomNamer()
]);
```

---

## Response Structures

### Upload Response

```php
[
    'success' => true,
    'main' => [
        'path' => 'uploads/photo.jpg',
        'url' => 'http://localhost:9000/bucket/uploads/photo.jpg',
        'size' => 1024,
        'mime_type' => 'image/jpeg',
        'width' => 800,      // For images
        'height' => 600      // For images
    ],
    'thumbnail' => [         // If thumbnail generated
        'path' => 'thumbnails/photo-thumb.jpg',
        'url' => 'http://localhost:9000/bucket/thumbnails/photo-thumb.jpg',
        'size' => 256,
        'width' => 200,
        'height' => 200
    ],
    'video_thumbnail' => [   // If video thumbnail generated
        'path' => 'thumbnails/video-thumb.jpg',
        'url' => 'http://localhost:9000/bucket/thumbnails/video-thumb.jpg',
        'size' => 128,
        'width' => 320,
        'height' => 240
    ]
]
```

### Metadata Response

```php
[
    'path' => 'uploads/photo.jpg',
    'size' => 1024,
    'mime_type' => 'image/jpeg',
    'last_modified' => '2024-01-01T12:00:00Z',
    'width' => 800,      // For images
    'height' => 600,     // For images
    'duration' => 120    // For videos (seconds)
]
```

---

## Exception Types

### SecurityException

Thrown when malicious content is detected.

```php
try {
    $result = MinioStorage::upload($file);
} catch (SecurityException $e) {
    $context = $e->getContext();
    // Contains: filename, threat, pattern, etc.
}
```

### UploadException

Thrown when upload fails.

```php
try {
    $result = MinioStorage::upload($file);
} catch (UploadException $e) {
    $context = $e->getContext();
    // Contains: filename, error details, etc.
}
```

### FileNotFoundException

Thrown when trying to access non-existent files.

```php
try {
    $metadata = MinioStorage::getMetadata('non-existent.jpg');
} catch (FileNotFoundException $e) {
    // Handle missing file
}
```

---

## Complete Example

Here's a comprehensive example showing multiple options:

```php
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;

$result = MinioStorage::upload($request->file('image'), 'uploads/processed/', [
    // General options
    'scan' => true,
    'naming' => 'slug',
    'preserve_structure' => false,
    
    // Image processing
    'image' => [
        'resize' => [
            'width' => 1024,
            'height' => 768,
            'method' => 'fit'
        ],
        'convert' => 'jpg',
        'quality' => 85,
        'auto_orient' => true,
        'strip_metadata' => true,
        'watermark' => [
            'path' => public_path('watermark.png'),
            'position' => 'bottom-right',
            'opacity' => 70,
            'margin' => 20
        ]
    ],
    
    // Thumbnail generation
    'thumbnail' => [
        'width' => 200,
        'height' => 200,
        'method' => 'crop',
        'quality' => 75,
        'format' => 'jpg'
    ],
    
    // Video processing (if applicable)
    'video' => [
        'format' => 'mp4',
        'compression' => 'medium',
        'resize' => ['width' => 1280, 'height' => 720]
    ],
    
    // Security options
    'scan_images' => true,
    'scan_documents' => true,
    'strict_mode' => false
]);

// Access results
$mainFile = $result['main'];
$thumbnail = $result['thumbnail'] ?? null;
$videoThumbnail = $result['video_thumbnail'] ?? null;
```

This reference covers all available options and their meanings. Use it to understand what each option does and how to configure the library for your specific needs. 