<?php

namespace Triginarsa\MinioStorageUtils\Processors;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Psr\Log\LoggerInterface;

class ImageProcessor
{
    private ImageManager $imageManager;
    private LoggerInterface $logger;
    
    // Quality presets for different use cases
    private const QUALITY_PRESETS = [
        'low' => 60,
        'medium' => 75,
        'high' => 85,
        'very_high' => 95,
        'max' => 100
    ];
    
    // Compression settings for different formats
    private const FORMAT_SETTINGS = [
        'jpg' => ['quality' => 85, 'progressive' => true],
        'jpeg' => ['quality' => 85, 'progressive' => true],
        'png' => ['quality' => 90],
        'webp' => ['quality' => 80],
        'avif' => ['quality' => 75],
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->logger = $logger;
    }

    public function process(string $content, array $options): string|array
    {
        $this->logger->info('Image processing started', ['options' => $options]);

        $image = $this->imageManager->read($content);
        $originalSize = strlen($content);

        // Auto-orient image
        if ($options['auto_orient'] ?? true) {
            $image->orient();
        }

        // Apply optimization settings
        if ($options['optimize'] ?? false) {
            $options = $this->applyOptimizationSettings($options, $image->width(), $image->height());
        }

        // Resize image with max dimensions
        $maxWidth = $options['max_width'] ?? null;
        $maxHeight = $options['max_height'] ?? null;
        
        if ($maxWidth && $maxHeight) {
            // Both max dimensions specified - scale down to fit within bounds
            $image->scaleDown($maxWidth, $maxHeight);
            
            $this->logger->info('Image scaled down to fit max dimensions', [
                'max_width' => $maxWidth,
                'max_height' => $maxHeight,
                'final_dimensions' => $image->width() . 'x' . $image->height()
            ]);
        } elseif ($maxWidth && !$maxHeight) {
            // Only max width specified - scale down proportionally to max width
            $image->scaleDown($maxWidth);
            
            $this->logger->info('Image scaled down to max width', [
                'max_width' => $maxWidth,
                'final_dimensions' => $image->width() . 'x' . $image->height()
            ]);
        } elseif (!$maxWidth && $maxHeight) {
            // Only max height specified - scale down proportionally to max height
            $image->scaleDown(height: $maxHeight);
            
            $this->logger->info('Image scaled down to max height', [
                'max_height' => $maxHeight,
                'final_dimensions' => $image->width() . 'x' . $image->height()
            ]);
        }

        // Manual resize if specified
        if (isset($options['resize'])) {
            $width = $options['resize']['width'] ?? null;
            $height = $options['resize']['height'] ?? null;
            $method = $options['resize']['method'] ?? 'proportional';
            
            if ($width && $height) {
                // Both dimensions specified - apply resize method
                switch ($method) {
                    case 'fit':
                    case 'contain':
                        // Fit within bounds maintaining aspect ratio (letterbox/pillarbox)
                        $image->contain($width, $height);
                        $this->logger->info('Image fitted to dimensions', [
                            'width' => $width,
                            'height' => $height,
                            'method' => 'fit',
                            'final_dimensions' => $image->width() . 'x' . $image->height()
                        ]);
                        break;
                    
                    case 'crop':
                    case 'cover':
                        // Crop to exact dimensions (fills entire area)
                        $image->cover($width, $height);
                        $this->logger->info('Image cropped to dimensions', [
                            'width' => $width,
                            'height' => $height,
                            'method' => 'crop',
                            'final_dimensions' => $image->width() . 'x' . $image->height()
                        ]);
                        break;
                    
                    case 'fill':
                        // Fill and crop from center
                        $image->cover($width, $height);
                        $this->logger->info('Image filled to dimensions', [
                            'width' => $width,
                            'height' => $height,
                            'method' => 'fill',
                            'final_dimensions' => $image->width() . 'x' . $image->height()
                        ]);
                        break;
                    
                    case 'stretch':
                    case 'force':
                        // Force resize (may distort aspect ratio)
                        $image->resize($width, $height);
                        $this->logger->info('Image stretched to dimensions', [
                            'width' => $width,
                            'height' => $height,
                            'method' => 'stretch',
                            'final_dimensions' => $image->width() . 'x' . $image->height()
                        ]);
                        break;
                    
                    case 'scale':
                    case 'proportional':
                    default:
                        // Scale proportionally to fit within bounds (default behavior)
                        $image->scaleDown($width, $height);
                        $this->logger->info('Image scaled proportionally', [
                            'max_width' => $width,
                            'max_height' => $height,
                            'method' => 'proportional',
                            'final_dimensions' => $image->width() . 'x' . $image->height()
                        ]);
                        break;
                }
            } elseif ($width && !$height) {
                // Only width specified - scale down proportionally to max width
                $image->scaleDown($width);
                
                $this->logger->info('Image scaled down to max width', [
                    'max_width' => $width,
                    'final_dimensions' => $image->width() . 'x' . $image->height()
                ]);
            } elseif (!$width && $height) {
                // Only height specified - scale down proportionally to max height
                $image->scaleDown(height: $height);
                
                $this->logger->info('Image scaled down to max height', [
                    'max_height' => $height,
                    'final_dimensions' => $image->width() . 'x' . $image->height()
                ]);
            }
        }

        // Apply smart compression based on image content
        if ($options['smart_compression'] ?? false) {
            $options = $this->applySmartCompression($options, $image);
        }

        // Add watermark
        $watermarkMetadata = null;
        if (isset($options['watermark'])) {
            $watermarkResult = $this->applyWatermark($image, $options['watermark']);
            $image = $watermarkResult['image'];
            $watermarkMetadata = $watermarkResult['metadata'] ?? null;
        }

        // Strip metadata if enabled
        if ($options['strip_metadata'] ?? true) {
            $this->logger->info('Image metadata stripped');
        }

        // Convert format and compress with advanced settings
        $format = $options['convert'] ?? $options['format'] ?? 'jpg';
        $quality = $this->determineQuality($options, $format);
        
        $encoded = $this->encodeWithAdvancedCompression($image, $format, $quality, $options);
        
        $finalSize = strlen($encoded->toString());
        $compressionRatio = round((1 - ($finalSize / $originalSize)) * 100, 2);
        
        $processingMetadata = [
            'original_size' => $originalSize,
            'final_size' => $finalSize,
            'compression_ratio' => $compressionRatio . '%',
            'format' => $format,
            'quality' => $quality
        ];

        // Add watermark metadata if available
        if ($watermarkMetadata) {
            $processingMetadata['watermark'] = $watermarkMetadata;
        }

        $this->logger->info('Image processing completed', $processingMetadata);

        // Return array with content and metadata when watermark was applied
        if ($watermarkMetadata) {
            return [
                'content' => $encoded->toString(),
                'metadata' => $processingMetadata
            ];
        }

        return $encoded->toString();
    }

    public function createThumbnail(string $content, array $options): string|array
    {
        $this->logger->info('Thumbnail creation started', ['options' => $options]);

        $image = $this->imageManager->read($content);
        
        $width = $options['width'] ?? 150;
        $height = $options['height'] ?? 150;
        $method = $options['method'] ?? 'fit';

        switch ($method) {
            case 'fit':
                // Fit within bounds maintaining aspect ratio
                $image->contain($width, $height);
                break;
            case 'crop':
                // Crop to exact dimensions
                $image->cover($width, $height);
                break;
            case 'proportional':
            case 'scale':
                // Scale proportionally to fit within bounds
                $image->scaleDown($width, $height);
                break;
            case 'resize':
                // Force resize (may distort)
                $image->resize($width, $height);
                break;
            default:
                // Default to proportional scaling
                $image->scaleDown($width, $height);
        }

        // Apply watermark to thumbnail if specified
        $watermarkMetadata = null;
        if (isset($options['watermark'])) {
            $watermarkResult = $this->applyWatermark($image, $options['watermark']);
            $image = $watermarkResult['image'];
            $watermarkMetadata = $watermarkResult['metadata'] ?? null;
        }

        $quality = $options['quality'] ?? 75;
        $format = $options['format'] ?? 'jpg';
        
        $encoded = $this->encodeWithAdvancedCompression($image, $format, $quality, $options);
        
        $processingMetadata = [
            'width' => $width,
            'height' => $height,
            'method' => $method,
            'format' => $format,
            'quality' => $quality
        ];

        // Add watermark metadata if available
        if ($watermarkMetadata) {
            $processingMetadata['watermark'] = $watermarkMetadata;
        }

        $this->logger->info('Thumbnail created', $processingMetadata);

        // Return array with content and metadata when watermark was applied
        if ($watermarkMetadata) {
            return [
                'content' => $encoded->toString(),
                'metadata' => $processingMetadata
            ];
        }

        return $encoded->toString();
    }

    public function compressImage(string $content, array $options = []): string
    {
        $this->logger->info('Image compression started', ['options' => $options]);

        $image = $this->imageManager->read($content);
        $originalSize = strlen($content);

        // Determine target size if specified
        $targetSize = $options['target_size'] ?? null; // in bytes
        $maxQuality = $options['max_quality'] ?? 95;
        $minQuality = $options['min_quality'] ?? 60;
        $format = $options['format'] ?? 'jpg';

        if ($targetSize) {
            return $this->compressToTargetSize($image, $targetSize, $format, $maxQuality, $minQuality);
        }

        // Use quality-based compression
        $quality = $this->determineQuality($options, $format);
        $encoded = $this->encodeWithAdvancedCompression($image, $format, $quality, $options);

        $finalSize = strlen($encoded->toString());
        $compressionRatio = round((1 - ($finalSize / $originalSize)) * 100, 2);

        $this->logger->info('Image compression completed', [
            'original_size' => $originalSize,
            'final_size' => $finalSize,
            'compression_ratio' => $compressionRatio . '%',
            'quality' => $quality
        ]);

        return $encoded->toString();
    }

    public function optimizeForWeb(string $content, array $options = []): string
    {
        $this->logger->info('Web optimization started');

        $image = $this->imageManager->read($content);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Apply web-specific optimizations
        $webOptions = array_merge([
            'max_width' => 1920,
            'max_height' => 1080,
            'quality' => 85,
            'format' => 'jpg',
            'progressive' => true,
            'strip_metadata' => true,
            'auto_orient' => true,
        ], $options);

        // Resize for web if too large
        if ($originalWidth > $webOptions['max_width'] || $originalHeight > $webOptions['max_height']) {
            $image->scaleDown($webOptions['max_width'], $webOptions['max_height']);
        }

        $encoded = $this->encodeWithAdvancedCompression($image, $webOptions['format'], $webOptions['quality'], $webOptions);

        $this->logger->info('Web optimization completed', [
            'original_dimensions' => $originalWidth . 'x' . $originalHeight,
            'final_dimensions' => $image->width() . 'x' . $image->height(),
            'format' => $webOptions['format'],
            'quality' => $webOptions['quality']
        ]);

        return $encoded->toString();
    }

    public function getImageInfo(string $content): array
    {
        $image = $this->imageManager->read($content);
        
        return [
            'width' => $image->width(),
            'height' => $image->height(),
            'aspect_ratio' => round($image->width() / $image->height(), 2),
            'file_size' => strlen($content),
            'megapixels' => round(($image->width() * $image->height()) / 1000000, 2),
        ];
    }

    public function isImage(string $mimeType): bool
    {
        return strpos($mimeType, 'image/') === 0;
    }

    public function applyWatermark($image, array $watermarkOptions)
    {
        $watermarkPath = $watermarkOptions['path'] ?? null;
        
        if (!$watermarkPath) {
            $this->logger->warning('Watermark path not provided');
            return [
                'image' => $image,
                'metadata' => null
            ];
        }

        // Handle Laravel public path resolution
        $resolvedPath = $this->resolveWatermarkPath($watermarkPath);
        
        if (!file_exists($resolvedPath)) {
            $this->logger->warning('Watermark path not found', [
                'original_path' => $watermarkPath,
                'resolved_path' => $resolvedPath
            ]);
            return [
                'image' => $image,
                'metadata' => null
            ];
        }

        $watermark = $this->imageManager->read($resolvedPath);
        
        // Get image dimensions
        $imageWidth = $image->width();
        $imageHeight = $image->height();
        
        // Get watermark configuration (merge with defaults)
        $config = array_merge([
            'auto_resize' => true,
            'resize_method' => 'proportional',
            'size_ratio' => 0.15,
            'min_size' => 50,
            'max_size' => 400,
            'position' => 'bottom-right',
            'opacity' => 70,
            'margin' => 10,
        ], $watermarkOptions);

        // Apply watermark resizing if enabled
        if ($config['auto_resize']) {
            $watermark = $this->resizeWatermark($watermark, $imageWidth, $imageHeight, $config);
        }

        // Apply watermark to image
        $image->place(
            $watermark,
            $config['position'],
            $config['margin'],
            $config['margin'],
            $config['opacity']
        );

        $watermarkMetadata = [
            'watermark_applied' => true,
            'watermark_path' => $resolvedPath,
            'watermark_filename' => basename($resolvedPath),
            'original_path' => $watermarkPath,
            'position' => $config['position'],
            'opacity' => $config['opacity'],
            'image_size' => $imageWidth . 'x' . $imageHeight,
            'watermark_size' => $watermark->width() . 'x' . $watermark->height(),
            'auto_resize' => $config['auto_resize'],
            'resize_method' => $config['resize_method'],
            'size_ratio' => $config['size_ratio']
        ];

        $this->logger->info('Watermark applied', $watermarkMetadata);

        return [
            'image' => $image,
            'metadata' => $watermarkMetadata
        ];
    }

    private function resizeWatermark($watermark, int $imageWidth, int $imageHeight, array $config)
    {
        $originalWatermarkWidth = $watermark->width();
        $originalWatermarkHeight = $watermark->height();

        switch ($config['resize_method']) {
            case 'proportional':
                $targetSize = min($imageWidth, $imageHeight) * $config['size_ratio'];
                $watermarkSize = max($originalWatermarkWidth, $originalWatermarkHeight);
                $scale = $targetSize / $watermarkSize;
                
                $newWidth = (int)($originalWatermarkWidth * $scale);
                $newHeight = (int)($originalWatermarkHeight * $scale);
                break;
                
            case 'percentage':
                $newWidth = (int)($imageWidth * $config['size_ratio']);
                $newHeight = (int)($imageHeight * $config['size_ratio']);
                break;
                
            case 'fixed':
                $newWidth = $config['width'] ?? $config['max_size'];
                $newHeight = $config['height'] ?? $config['max_size'];
                break;
                
            default:
                $targetSize = min($imageWidth, $imageHeight) * $config['size_ratio'];
                $watermarkSize = max($originalWatermarkWidth, $originalWatermarkHeight);
                $scale = $targetSize / $watermarkSize;
                
                $newWidth = (int)($originalWatermarkWidth * $scale);
                $newHeight = (int)($originalWatermarkHeight * $scale);
        }
        
        $newWidth = max($config['min_size'], min($config['max_size'], $newWidth));
        $newHeight = max($config['min_size'], min($config['max_size'], $newHeight));
        
        $watermarkAspectRatio = $originalWatermarkWidth / $originalWatermarkHeight;
        if ($newWidth / $newHeight > $watermarkAspectRatio) {
            $newWidth = (int)($newHeight * $watermarkAspectRatio);
        } else {
            $newHeight = (int)($newWidth / $watermarkAspectRatio);
        }
        
        $watermark->resize($newWidth, $newHeight);
        
        $this->logger->info('Watermark resized', [
            'original_size' => $originalWatermarkWidth . 'x' . $originalWatermarkHeight,
            'new_size' => $newWidth . 'x' . $newHeight,
            'image_size' => $imageWidth . 'x' . $imageHeight,
            'resize_method' => $config['resize_method'],
            'size_ratio' => $config['size_ratio']
        ]);
        
        return $watermark;
    }

    private function applyOptimizationSettings(array $options, int $width, int $height): array
    {
        $totalPixels = $width * $height;
        
        // Only set quality if user hasn't explicitly provided one
        if (!isset($options['quality'])) {
            // Adjust quality based on image size
            if ($totalPixels > 8000000) { // > 8MP
                $options['quality'] = 75;
            } elseif ($totalPixels > 4000000) { // > 4MP
                $options['quality'] = 80;
            } else {
                $options['quality'] = 85;
            }
        }

        // Auto-select format based on content
        if (!isset($options['format']) && !isset($options['convert'])) {
            $options['format'] = $totalPixels > 2000000 ? 'jpg' : 'png';
        }

        return $options;
    }

    private function applySmartCompression(array $options, $image): array
    {
        // Only apply smart compression if user hasn't explicitly set quality
        if (isset($options['quality'])) {
            $this->logger->info('Skipping smart compression - user quality setting detected', [
                'user_quality' => $options['quality']
            ]);
            return $options;
        }

        // Analyze image characteristics
        $width = $image->width();
        $height = $image->height();
        $aspectRatio = $width / $height;
        
        // Adjust compression based on image characteristics
        if ($aspectRatio > 2 || $aspectRatio < 0.5) {
            // Panoramic or very tall images - use higher quality
            $options['quality'] = 85;
        } elseif ($width > 3000 || $height > 3000) {
            // Very high resolution - can use lower quality
            $options['quality'] = 80;
        } else {
            // Default quality for smart compression
            $options['quality'] = 85;
        }

        $this->logger->info('Smart compression applied', [
            'calculated_quality' => $options['quality'],
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => $aspectRatio
        ]);

        return $options;
    }

    private function determineQuality(array $options, string $format): int
    {
        // Check for quality preset
        if (isset($options['quality_preset'])) {
            $preset = $options['quality_preset'];
            if (isset(self::QUALITY_PRESETS[$preset])) {
                $quality = self::QUALITY_PRESETS[$preset];
                $this->logger->info('Quality determined from preset', [
                    'preset' => $preset,
                    'quality' => $quality
                ]);
                return $quality;
            }
        }

        // Use explicit quality if provided
        if (isset($options['quality'])) {
            $quality = max(1, min(100, $options['quality']));
            $this->logger->info('Quality from user options', [
                'original_quality' => $options['quality'],
                'clamped_quality' => $quality
            ]);
            return $quality;
        }

        // Use format default
        $quality = self::FORMAT_SETTINGS[$format]['quality'] ?? 85;
        $this->logger->info('Quality from format default', [
            'format' => $format,
            'quality' => $quality
        ]);
        return $quality;
    }

    private function encodeWithAdvancedCompression($image, string $format, int $quality, array $options)
    {
        $formatSettings = self::FORMAT_SETTINGS[$format] ?? [];
        
        // Apply progressive JPEG if enabled
        if ($format === 'jpg' && ($options['progressive'] ?? $formatSettings['progressive'] ?? false)) {
            // Note: Progressive JPEG would need specific implementation
            // For now, we'll use standard encoding
        }

        // Use the new Intervention Image v3 API
        switch ($format) {
            case 'jpg':
            case 'jpeg':
                return $image->encodeByMediaType('image/jpeg', $quality);
            case 'png':
                return $image->encodeByMediaType('image/png');
            case 'webp':
                return $image->encodeByMediaType('image/webp', $quality);
            case 'avif':
                return $image->encodeByMediaType('image/avif', $quality);
            default:
                return $image->encodeByMediaType('image/jpeg', $quality);
        }
    }

    private function compressToTargetSize($image, int $targetSize, string $format, int $maxQuality, int $minQuality): string
    {
        $currentQuality = $maxQuality;
        
        while ($currentQuality >= $minQuality) {
            $encoded = $this->encodeWithAdvancedCompression($image, $format, $currentQuality, []);
            $currentSize = strlen($encoded->toString());
            
            if ($currentSize <= $targetSize) {
                $this->logger->info('Target size achieved', [
                    'target_size' => $targetSize,
                    'actual_size' => $currentSize,
                    'quality' => $currentQuality
                ]);
                return $encoded->toString();
            }
            
            $currentQuality -= 5;
        }

        // If we can't reach target size, return at minimum quality
        $encoded = $this->encodeWithAdvancedCompression($image, $format, $minQuality, []);
        $this->logger->warning('Could not reach target size', [
            'target_size' => $targetSize,
            'actual_size' => strlen($encoded->toString()),
            'quality' => $minQuality
        ]);
        
        return $encoded->toString();
    }

    /**
     * Resolve watermark path to handle Laravel public assets and relative paths
     */
    private function resolveWatermarkPath(string $path): string
    {
        // If it's already an absolute path, return as-is
        if (str_starts_with($path, '/') && file_exists($path)) {
            return $path;
        }

        // Handle Laravel public path (public/...)
        if (function_exists('public_path') && str_starts_with($path, 'public/')) {
            $publicPath = public_path(substr($path, 7)); // Remove 'public/' prefix
            if (file_exists($publicPath)) {
                return $publicPath;
            }
        }

        // Handle Laravel public path without 'public/' prefix
        if (function_exists('public_path')) {
            $publicPath = public_path($path);
            if (file_exists($publicPath)) {
                return $publicPath;
            }
        }

        // Handle Laravel storage path
        if (function_exists('storage_path') && str_starts_with($path, 'storage/')) {
            $storagePath = storage_path(substr($path, 8)); // Remove 'storage/' prefix
            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        // Handle relative paths from current working directory
        if (!str_starts_with($path, '/')) {
            $relativePath = getcwd() . '/' . $path;
            if (file_exists($relativePath)) {
                return $relativePath;
            }
        }

        // Return original path if no resolution worked
        return $path;
    }
} 