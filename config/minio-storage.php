<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Minio Storage Disk
    |--------------------------------------------------------------------------
    |
    | This value determines which disk configuration from filesystems.php
    | will be used for Minio storage operations.
    |
    */
    'disk' => env('MINIO_STORAGE_DISK', 'minio'),

    /*
    |--------------------------------------------------------------------------
    | Default Upload Options
    |--------------------------------------------------------------------------
    |
    | These are the default options that will be applied to all uploads
    | unless overridden in the upload method.
    |
    */
    'default_options' => [
        'scan' => env('MINIO_SECURITY_SCAN', true),
        'naming' => env('MINIO_NAMING_STRATEGY', 'hash'), // hash, slug, original
        'preserve_structure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing Options
    |--------------------------------------------------------------------------
    |
    | Default settings for image processing operations.
    |
    */
    'image' => [
        'quality' => env('MINIO_IMAGE_QUALITY', 85),
        'max_width' => env('MINIO_IMAGE_MAX_WIDTH', 2048),
        'max_height' => env('MINIO_IMAGE_MAX_HEIGHT', 2048),
        'auto_orient' => true,
        'strip_metadata' => true,
        'optimize' => env('MINIO_IMAGE_OPTIMIZE', false),
        'smart_compression' => env('MINIO_IMAGE_SMART_COMPRESSION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Compression Options
    |--------------------------------------------------------------------------
    |
    | Dedicated image compression settings for optimal file size reduction.
    |
    */
    'compression' => [
        'quality' => env('MINIO_COMPRESSION_QUALITY', 80),
        'format' => env('MINIO_COMPRESSION_FORMAT', 'jpg'),
        'target_size' => env('MINIO_COMPRESSION_TARGET_SIZE', null), // Target size in bytes
        'max_quality' => env('MINIO_COMPRESSION_MAX_QUALITY', 95),
        'min_quality' => env('MINIO_COMPRESSION_MIN_QUALITY', 60),
        'progressive' => env('MINIO_COMPRESSION_PROGRESSIVE', true),
        'quality_preset' => env('MINIO_COMPRESSION_PRESET', 'high'), // low, medium, high, very_high, max
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Optimization Options
    |--------------------------------------------------------------------------
    |
    | Settings for web-optimized image processing with automatic resizing
    | and compression for web delivery.
    |
    */
    'web_optimization' => [
        'max_width' => env('MINIO_WEB_MAX_WIDTH', 1920),
        'max_height' => env('MINIO_WEB_MAX_HEIGHT', 1080),
        'quality' => env('MINIO_WEB_QUALITY', 85),
        'format' => env('MINIO_WEB_FORMAT', 'jpg'),
        'progressive' => env('MINIO_WEB_PROGRESSIVE', true),
        'strip_metadata' => env('MINIO_WEB_STRIP_METADATA', true),
        'auto_orient' => env('MINIO_WEB_AUTO_ORIENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Processing Options
    |--------------------------------------------------------------------------
    |
    | Default settings for video processing operations.
    |
    */
    'video' => [
        'compression' => env('MINIO_VIDEO_COMPRESSION', 'medium'), // ultrafast, fast, medium, slow, veryslow
        'format' => env('MINIO_VIDEO_FORMAT', 'mp4'), // mp4, webm
        'video_bitrate' => env('MINIO_VIDEO_BITRATE', 2000),
        'audio_bitrate' => env('MINIO_VIDEO_AUDIO_BITRATE', 128),
        'max_width' => env('MINIO_VIDEO_MAX_WIDTH', 1920),
        'max_height' => env('MINIO_VIDEO_MAX_HEIGHT', 1080),
        'quality' => env('MINIO_VIDEO_QUALITY', 'medium'), // low, medium, high, ultra
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Options
    |--------------------------------------------------------------------------
    |
    | Default settings for thumbnail generation.
    |
    */
    'thumbnail' => [
        'width' => env('MINIO_THUMBNAIL_WIDTH', 200),
        'height' => env('MINIO_THUMBNAIL_HEIGHT', 200),
        'method' => env('MINIO_THUMBNAIL_METHOD', 'fit'), // fit, crop, proportional, scale, resize
        'quality' => env('MINIO_THUMBNAIL_QUALITY', 75),
        'suffix' => env('MINIO_THUMBNAIL_SUFFIX', '-thumb'),
        'path' => env('MINIO_THUMBNAIL_PATH', 'thumbnails'),
        'optimize' => env('MINIO_THUMBNAIL_OPTIMIZE', true),
        'format' => env('MINIO_THUMBNAIL_FORMAT', 'jpg'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Thumbnail Options
    |--------------------------------------------------------------------------
    |
    | Default settings for video thumbnail generation.
    |
    */
    'video_thumbnail' => [
        'width' => env('MINIO_VIDEO_THUMBNAIL_WIDTH', 320),
        'height' => env('MINIO_VIDEO_THUMBNAIL_HEIGHT', 240),
        'time' => env('MINIO_VIDEO_THUMBNAIL_TIME', 5), // seconds or 00:00:05 format
        'suffix' => env('MINIO_VIDEO_THUMBNAIL_SUFFIX', '-thumb'),
        'path' => env('MINIO_VIDEO_THUMBNAIL_PATH', 'thumbnails'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FFmpeg Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for FFmpeg binary paths and settings.
    |
    */
    'ffmpeg' => [
        'ffmpeg.binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
        'ffprobe.binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
        'timeout' => env('FFMPEG_TIMEOUT', 3600),
        'ffmpeg.threads' => env('FFMPEG_THREADS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Scanner Options
    |--------------------------------------------------------------------------
    |
    | Configuration for the security scanner.
    |
    */
    'security' => [
        'scan_images' => env('MINIO_SCAN_IMAGES', false),
        'scan_documents' => env('MINIO_SCAN_DOCUMENTS', true),
        'scan_videos' => env('MINIO_SCAN_VIDEOS', false),
        'scan_archives' => env('MINIO_SCAN_ARCHIVES', true),
        'max_file_size' => env('MINIO_MAX_SCAN_SIZE', 10485760), // 10MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    |
    | Define which file types are allowed for upload.
    |
    */
    'allowed_types' => [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
        'videos' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp'],
        'archives' => ['zip', 'rar', '7z', 'tar', 'gz'],
        'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for URL generation.
    |
    */
    'url' => [
        'default_expiration' => env('MINIO_URL_DEFAULT_EXPIRATION', 3600), // 1 hour
        'max_expiration' => env('MINIO_URL_MAX_EXPIRATION', 604800), // 7 days
    ],
]; 