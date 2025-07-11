<?php

namespace Triginarsa\MinioStorageUtils;

use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\Contracts\StorageServiceInterface;
use Triginarsa\MinioStorageUtils\Exceptions\FileNotFoundException;
use Triginarsa\MinioStorageUtils\Exceptions\UploadException;
use Triginarsa\MinioStorageUtils\Naming\NamerInterface;
use Triginarsa\MinioStorageUtils\Processors\ImageProcessor;
use Triginarsa\MinioStorageUtils\Processors\SecurityScanner;
use Triginarsa\MinioStorageUtils\Processors\DocumentProcessor;
use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\StreamInterface;
use Illuminate\Http\UploadedFile;

class StorageService implements StorageServiceInterface
{
    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private ImageProcessor $imageProcessor;
    private SecurityScanner $securityScanner;
    private DocumentProcessor $documentProcessor;
    private VideoProcessor $videoProcessor;
    private S3Client $s3Client;
    private string $bucket;

    public function __construct(
        MinioConfig $config,
        LoggerInterface $logger,
        ?ImageProcessor $imageProcessor = null,
        ?SecurityScanner $securityScanner = null,
        ?DocumentProcessor $documentProcessor = null,
        ?VideoProcessor $videoProcessor = null
    ) {
        $this->logger = $logger;
        $this->bucket = $config->getBucket();
        
        // Initialize S3 client
        $this->s3Client = new S3Client($config->getClientConfig());
        
        // Initialize filesystem
        $adapter = new AwsS3V3Adapter($this->s3Client, $this->bucket);
        $this->filesystem = new Filesystem($adapter);
        
        // Initialize processors
        $this->imageProcessor = $imageProcessor ?? new ImageProcessor($logger);
        $this->securityScanner = $securityScanner ?? new SecurityScanner($logger);
        $this->documentProcessor = $documentProcessor ?? new DocumentProcessor($logger);
        $this->videoProcessor = $videoProcessor ?? new VideoProcessor($logger);
    }

    public function upload($source, string $destinationPath = null, array $options = []): array
    {
        // Auto-generate destination path if not provided
        if ($destinationPath === null) {
            $destinationPath = $this->generateDestinationPath($source);
        }

        // Merge with default options
        $options = array_merge($this->getDefaultOptions(), $options);

        $this->logger->info('Upload started', [
            'destination' => $destinationPath,
            'options' => $options
        ]);

        try {
            // Read file content and get info
            $fileInfo = $this->getFileInfo($source);
            $content = $fileInfo['content'];
            $originalName = $fileInfo['name'];
            $mimeType = $fileInfo['mime_type'];
            $extension = $fileInfo['extension'];

            // Validate file type
            $this->validateFileType($extension, $mimeType);

            // Security scanning based on file type
            $this->performSecurityScan($content, $originalName, $mimeType, $options);

            // Generate filename if namer is provided
            $filename = $this->generateFilename($originalName, $content, $extension, $options['naming'] ?? null);
            $finalPath = $this->buildFinalPath($destinationPath, $filename, $options);

            $results = [];
            
            // Process based on file type
            if ($this->imageProcessor->isImage($mimeType)) {
                $results = $this->processImage($content, $finalPath, $mimeType, $options);
            } elseif ($this->videoProcessor->isVideo($mimeType)) {
                $results = $this->processVideo($source, $finalPath, $mimeType, $options);
            } else {
                // Upload original file for other types
                $results['main'] = $this->uploadFile($finalPath, $content, $mimeType);
            }

            $this->logger->info('Upload completed successfully', [
                'destination' => $destinationPath,
                'results' => $results
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Upload failed', [
                'destination' => $destinationPath,
                'error' => $e->getMessage()
            ]);
            
            throw new UploadException(
                "Failed to upload file: {$e->getMessage()}",
                ['destination' => $destinationPath],
                $e
            );
        }
    }

    public function delete(string $path): bool
    {
        $this->logger->info('Delete started', ['path' => $path]);

        try {
            $this->filesystem->delete($path);
            $this->logger->info('Delete completed successfully', ['path' => $path]);
            return true;
        } catch (FilesystemException $e) {
            $this->logger->error('Delete failed', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function fileExists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (FilesystemException $e) {
            $this->logger->error('File existence check failed', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getMetadata(string $path): array
    {
        $this->logger->info('Getting metadata', ['path' => $path]);

        try {
            if (!$this->fileExists($path)) {
                throw new FileNotFoundException($path);
            }

            $metadata = [
                'path' => $path,
                'size' => $this->filesystem->fileSize($path),
                'mime_type' => $this->filesystem->mimeType($path),
                'last_modified' => $this->filesystem->lastModified($path),
                'visibility' => $this->filesystem->visibility($path),
            ];

            // Add type-specific metadata
            if ($this->imageProcessor->isImage($metadata['mime_type'])) {
                $content = $this->filesystem->read($path);
                $imageInfo = $this->imageProcessor->getImageInfo($content);
                $metadata = array_merge($metadata, $imageInfo);
            } elseif ($this->videoProcessor->isVideo($metadata['mime_type'])) {
                // For video metadata, we'd need to download the file temporarily
                // This is optional and can be expensive for large files
                if ($this->videoProcessor->isFFmpegAvailable() && $metadata['size'] < 50 * 1024 * 1024) { // Only for files < 50MB
                    try {
                        $tempPath = $this->downloadToTemp($path);
                        $videoInfo = $this->videoProcessor->getVideoInfo($tempPath);
                        $metadata = array_merge($metadata, $videoInfo);
                        unlink($tempPath);
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to get video metadata', [
                            'path' => $path,
                            'error' => $e->getMessage()
                        ]);
                        // Continue without video metadata
                    }
                } elseif (!$this->videoProcessor->isFFmpegAvailable()) {
                    $metadata['video_processing_available'] = false;
                    $metadata['note'] = 'Video metadata unavailable: FFmpeg not installed';
                }
            }

            $this->logger->info('Metadata retrieved successfully', [
                'path' => $path,
                'metadata' => $metadata
            ]);

            return $metadata;

        } catch (FilesystemException $e) {
            $this->logger->error('Failed to get metadata', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            throw new FileNotFoundException($path, ['error' => $e->getMessage()]);
        }
    }

    public function getUrl(string $path, ?int $expiration = null): string
    {
        $this->logger->info('Generating URL', ['path' => $path, 'expiration' => $expiration]);

        try {
            // If expiration is null, check config for default behavior
            if ($expiration === null) {
                $defaultExpiration = config('minio-storage.url.default_expiration', 3600);
                $expiration = $defaultExpiration;
            }

            if ($expiration === null) {
                return $this->getPublicUrl($path);
            }

            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $path
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiration} seconds");
            $url = (string) $request->getUri();

            $this->logger->info('URL generated successfully', [
                'path' => $path,
                'url' => $url,
                'expiration' => $expiration
            ]);

            return $url;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            throw new UploadException(
                "Failed to generate URL: {$e->getMessage()}",
                ['path' => $path],
                $e
            );
        }
    }

    /**
     * Get a public URL for files in public buckets (no expiration)
     */
    public function getPublicUrl(string $path): string
    {
        $this->logger->info('Generating public URL', ['path' => $path]);

        try {
            $endpoint = rtrim(config("filesystems.disks.minio.endpoint"), '/');
            $bucket = config("filesystems.disks.minio.bucket");
            $publicUrl = "{$endpoint}/{$bucket}/" . ltrim($path, '/');

            $this->logger->info('Public URL generated', ['path' => $path, 'url' => $publicUrl]);
            return $publicUrl;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate public URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            throw new UploadException(
                "Failed to generate public URL: {$e->getMessage()}",
                ['path' => $path],
                $e
            );
        }
    }

    private function getFileInfo($source): array
    {
        if ($source instanceof UploadedFile) {
            return [
                'content' => file_get_contents($source->getPathname()),
                'name' => $source->getClientOriginalName(),
                'mime_type' => $source->getMimeType(),
                'extension' => $source->getClientOriginalExtension(),
                'size' => $source->getSize(),
            ];
        }

        if ($source instanceof StreamInterface) {
            $content = $source->getContents();
            return [
                'content' => $content,
                'name' => 'uploaded-file',
                'mime_type' => $this->getMimeType($content),
                'extension' => $this->getExtensionFromMimeType($this->getMimeType($content)),
                'size' => strlen($content),
            ];
        }
        
        if (is_string($source) && file_exists($source)) {
            $content = file_get_contents($source);
            return [
                'content' => $content,
                'name' => basename($source),
                'mime_type' => $this->getMimeType($content),
                'extension' => pathinfo($source, PATHINFO_EXTENSION),
                'size' => filesize($source),
            ];
        }
        
        throw new UploadException("Invalid source provided");
    }

    private function generateDestinationPath($source): string
    {
        $timestamp = date('Y/m/d');
        
        if ($source instanceof UploadedFile) {
            $filename = $source->getClientOriginalName();
        } elseif (is_string($source) && file_exists($source)) {
            $filename = basename($source);
        } else {
            $filename = 'uploaded-file-' . time();
        }

        return "uploads/{$timestamp}/{$filename}";
    }

    private function getDefaultOptions(): array
    {
        return function_exists('config') ? config('minio-storage.default_options', [
            'scan' => true,
            'naming' => 'hash',
            'preserve_structure' => true,
        ]) : [
            'scan' => true,
            'naming' => 'hash',
            'preserve_structure' => true,
        ];
    }

    private function validateFileType(string $extension, string $mimeType): void
    {
        $allowedTypes = function_exists('config') ? config('minio-storage.allowed_types', []) : [];
        $allAllowed = empty($allowedTypes) ? [] : array_merge(...array_values($allowedTypes));
        
        if (!empty($allAllowed) && !in_array(strtolower($extension), $allAllowed)) {
            throw new UploadException(
                "File type '{$extension}' is not allowed",
                ['extension' => $extension, 'mime_type' => $mimeType]
            );
        }
    }

    private function performSecurityScan(string $content, string $filename, string $mimeType, array $options): void
    {
        if (!($options['scan'] ?? false)) {
            return;
        }

        // Scan images if enabled
        if ($this->imageProcessor->isImage($mimeType) && (function_exists('config') ? config('minio-storage.security.scan_images', true) : true)) {
            $this->securityScanner->scan($content, $filename);
        }

        // Scan documents if enabled
        if ($this->documentProcessor->isDocument($mimeType) && (function_exists('config') ? config('minio-storage.security.scan_documents', true) : true)) {
            $this->documentProcessor->scan($content, $filename, $mimeType);
        }

        // Scan other files
        if (!$this->imageProcessor->isImage($mimeType) && !$this->documentProcessor->isDocument($mimeType)) {
            $this->securityScanner->scan($content, $filename);
        }
    }

    private function performSecurityScanOnProcessedImage(string $processedContent, string $finalPath, array $options): void
    {
        $scanProcessedImages = function_exists('config') ? config('minio-storage.security.scan_images', true) : true;
        
        if (!$scanProcessedImages) {
            return;
        }

        $this->logger->info('Security scan on processed image started', ['path' => $finalPath]);

        try {
            // Scan processed image content
            $this->securityScanner->scan($processedContent, basename($finalPath));
            
            $this->logger->info('Security scan on processed image completed', ['path' => $finalPath]);
        } catch (\Exception $e) {
            $this->logger->error('Security scan on processed image failed', [
                'path' => $finalPath,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw security exceptions
            throw $e;
        }
    }

    private function processImage(string $content, string $finalPath, string $mimeType, array $options): array
    {
        $results = [];
        $processedContent = $content;
        
        // Check for compression-specific options
        $shouldCompress = $options['compress'] ?? false;
        $shouldOptimize = $options['optimize'] ?? false;
        $shouldOptimizeForWeb = $options['optimize_for_web'] ?? false;
        
        // Apply different processing based on options
        if ($shouldOptimizeForWeb) {
            // Web optimization (resize + compress for web)
            $webOptions = array_merge(function_exists('config') ? config('minio-storage.web_optimization', []) : [], $options['web_options'] ?? []);
            $processedContent = $this->imageProcessor->optimizeForWeb($content, $webOptions);
            $results['main'] = $this->uploadFile($finalPath, $processedContent, $this->getOptimizedMimeType($mimeType, $webOptions));
            
        } elseif ($shouldCompress) {
            // Dedicated compression
            $compressionOptions = array_merge(function_exists('config') ? config('minio-storage.compression', []) : [], $options['compression_options'] ?? []);
            $processedContent = $this->imageProcessor->compressImage($content, $compressionOptions);
            $results['main'] = $this->uploadFile($finalPath, $processedContent, $this->getOptimizedMimeType($mimeType, $compressionOptions));
            
        } elseif ($shouldOptimize || isset($options['image'])) {
            // General image processing with optimization
            $defaultImageOptions = function_exists('config') ? config('minio-storage.image', []) : [];
            
            // Merge user options AFTER defaults to ensure user settings take precedence
            $imageOptions = array_merge($defaultImageOptions, $options['image'] ?? []);
            
            // Enable optimization if requested (but don't override user quality settings)
            if ($shouldOptimize) {
                $imageOptions['optimize'] = true;
                $imageOptions['smart_compression'] = $options['smart_compression'] ?? true;
            }
            
            $processedContent = $this->imageProcessor->process($content, $imageOptions);
            
            // Security scan processed image if enabled
            if ($options['scan'] ?? false) {
                $this->performSecurityScanOnProcessedImage($processedContent, $finalPath, $options);
            }
            
            $results['main'] = $this->uploadFile($finalPath, $processedContent, $this->getOptimizedMimeType($mimeType, $imageOptions));
            
        } else {
            // Upload original image
            $results['main'] = $this->uploadFile($finalPath, $content, $mimeType);
        }
        
        // Create thumbnail if requested
        if (isset($options['thumbnail'])) {
            $thumbnailOptions = array_merge(function_exists('config') ? config('minio-storage.thumbnail', []) : [], $options['thumbnail']);
            
            // Apply compression to thumbnail as well
            if ($shouldCompress || $shouldOptimize) {
                $thumbnailOptions['quality'] = $thumbnailOptions['quality'] ?? 75;
                $thumbnailOptions['optimize'] = true;
            }
            
            $thumbnailContent = $this->imageProcessor->createThumbnail($processedContent, $thumbnailOptions);
            $thumbnailPath = $this->buildThumbnailPath($finalPath, $thumbnailOptions);
            $thumbnailMimeType = $this->getOptimizedMimeType($mimeType, $thumbnailOptions);
            $results['thumbnail'] = $this->uploadFile($thumbnailPath, $thumbnailContent, $thumbnailMimeType);
        }

        return $results;
    }

    private function processVideo($source, string $finalPath, string $mimeType, array $options): array
    {
        $results = [];
        
        // Check if video processing is requested but FFmpeg is not available
        $requiresProcessing = isset($options['video']) || isset($options['video_thumbnail']);
        
        if ($requiresProcessing && !$this->videoProcessor->isFFmpegAvailable()) {
            // Log warning but continue with upload
            $this->logger->warning('Video processing requested but FFmpeg is not available', [
                'path' => $finalPath,
                'requested_options' => array_keys(array_filter(['video' => $options['video'] ?? null, 'video_thumbnail' => $options['video_thumbnail'] ?? null]))
            ]);
        }
        
        // Create temporary file for video processing
        $tempInputPath = $this->createTempFile($source);
        $tempOutputPath = $this->createTempOutputPath($finalPath);
        
        try {
            // Apply video processing if options are provided and FFmpeg is available
            if (isset($options['video']) && $this->videoProcessor->isFFmpegAvailable()) {
                $this->videoProcessor->process($tempInputPath, $tempOutputPath, $options['video']);
                
                // Upload processed video
                $processedContent = file_get_contents($tempOutputPath);
                $results['main'] = $this->uploadFile($finalPath, $processedContent, 'video/mp4');
                
                // Create video thumbnail if requested
                if (isset($options['video_thumbnail'])) {
                    $thumbnailPath = $this->createTempThumbnailPath($finalPath);
                    $this->videoProcessor->createThumbnail($tempOutputPath, $thumbnailPath, $options['video_thumbnail']);
                    
                    $thumbnailContent = file_get_contents($thumbnailPath);
                    $thumbnailUploadPath = $this->buildVideoThumbnailPath($finalPath, $options['video_thumbnail']);
                    $results['thumbnail'] = $this->uploadFile($thumbnailUploadPath, $thumbnailContent, 'image/jpeg');
                    
                    unlink($thumbnailPath);
                }
            } else {
                // Upload original video
                $originalContent = file_get_contents($tempInputPath);
                $results['main'] = $this->uploadFile($finalPath, $originalContent, $mimeType);
                
                // Create thumbnail even for original video if requested and FFmpeg is available
                if (isset($options['video_thumbnail']) && $this->videoProcessor->isFFmpegAvailable()) {
                    $thumbnailPath = $this->createTempThumbnailPath($finalPath);
                    $this->videoProcessor->createThumbnail($tempInputPath, $thumbnailPath, $options['video_thumbnail']);
                    
                    $thumbnailContent = file_get_contents($thumbnailPath);
                    $thumbnailUploadPath = $this->buildVideoThumbnailPath($finalPath, $options['video_thumbnail']);
                    $results['thumbnail'] = $this->uploadFile($thumbnailUploadPath, $thumbnailContent, 'image/jpeg');
                    
                    unlink($thumbnailPath);
                } elseif (isset($options['video_thumbnail']) && !$this->videoProcessor->isFFmpegAvailable()) {
                    // Add warning about thumbnail creation failure
                    $results['warnings'] = $results['warnings'] ?? [];
                    $results['warnings'][] = 'Video thumbnail creation skipped: FFmpeg not available';
                }
            }
            
        } finally {
            // Clean up temporary files
            if (file_exists($tempInputPath)) {
                unlink($tempInputPath);
            }
            if (file_exists($tempOutputPath)) {
                unlink($tempOutputPath);
            }
        }

        return $results;
    }

    private function createTempFile($source): string
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('minio_video_input_') . '.tmp';
        
        if ($source instanceof UploadedFile) {
            copy($source->getPathname(), $tempPath);
        } elseif (is_string($source) && file_exists($source)) {
            copy($source, $tempPath);
        } else {
            throw new UploadException("Cannot create temporary file for video processing");
        }
        
        return $tempPath;
    }

    private function createTempOutputPath(string $finalPath): string
    {
        $extension = pathinfo($finalPath, PATHINFO_EXTENSION);
        return sys_get_temp_dir() . '/' . uniqid('minio_video_output_') . '.' . $extension;
    }

    private function createTempThumbnailPath(string $finalPath): string
    {
        return sys_get_temp_dir() . '/' . uniqid('minio_video_thumb_') . '.jpg';
    }

    private function buildVideoThumbnailPath(string $originalPath, array $thumbnailOptions): string
    {
        $directory = dirname($originalPath);
        $filename = basename($originalPath);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        $suffix = $thumbnailOptions['suffix'] ?? '-thumb';
        $thumbnailFilename = $name . $suffix . '.jpg';
        
        $thumbnailDirectory = $thumbnailOptions['path'] ?? $directory . '/thumbnails';
        
        return $thumbnailDirectory . '/' . $thumbnailFilename;
    }

    private function getMimeType(string $content): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($content);
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];
        
        return $mimeToExt[$mimeType] ?? 'bin';
    }

    private function generateFilename(string $originalName, string $content, string $extension, $namer): string
    {
        if ($namer instanceof NamerInterface) {
            return $namer->generateName($originalName, $content, $extension);
        }
        
        if (is_string($namer)) {
            switch ($namer) {
                case 'hash':
                    return (new \Triginarsa\MinioStorageUtils\Naming\HashNamer())->generateName($originalName, $content, $extension);
                case 'slug':
                    return (new \Triginarsa\MinioStorageUtils\Naming\SlugNamer())->generateName($originalName, $content, $extension);
                case 'original':
                    return $originalName;
                default:
                    return $originalName;
            }
        }
        
        return $originalName;
    }

    private function buildFinalPath(string $destinationPath, string $filename, array $options): string
    {
        $directory = rtrim($destinationPath, '/');
        
        if ($options['preserve_structure'] ?? true) {
            return $directory === '' ? $filename : $directory . '/' . $filename;
        }
        
        return $filename;
    }

    private function buildThumbnailPath(string $originalPath, array $thumbnailOptions): string
    {
        $directory = dirname($originalPath);
        $filename = basename($originalPath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        $suffix = $thumbnailOptions['suffix'] ?? '-thumb';
        $thumbnailFilename = $name . $suffix . '.' . $extension;
        
        $thumbnailDirectory = $thumbnailOptions['path'] ?? $directory . '/thumbnails';
        
        return $thumbnailDirectory . '/' . $thumbnailFilename;
    }

    private function uploadFile(string $path, string $content, string $mimeType): array
    {
        $this->filesystem->write($path, $content, [
            'ContentType' => $mimeType,
        ]);

        return [
            'path' => $path,
            'size' => strlen($content),
            'mime_type' => $mimeType,
            'url' => $this->getUrl($path),
        ];
    }

    private function downloadToTemp(string $path): string
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('minio_temp_') . '.tmp';
        $content = $this->filesystem->read($path);
        file_put_contents($tempPath, $content);
        return $tempPath;
    }

    private function getOptimizedMimeType(string $originalMimeType, array $options): string
    {
        // Check if format conversion is specified
        if (isset($options['format'])) {
            $formatMimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'avif' => 'image/avif',
                'gif' => 'image/gif',
            ];
            
            return $formatMimeMap[$options['format']] ?? $originalMimeType;
        }
        
        if (isset($options['convert'])) {
            $formatMimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'avif' => 'image/avif',
                'gif' => 'image/gif',
            ];
            
            return $formatMimeMap[$options['convert']] ?? $originalMimeType;
        }
        
        return $originalMimeType;
    }
} 