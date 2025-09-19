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
    private ?VideoProcessor $videoProcessor;
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
        $this->videoProcessor = $videoProcessor;
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
            
            // Ensure unique filename to prevent overwriting
            $finalPath = $this->ensureUniqueFilename($finalPath);

            $results = [];
            
            // Process based on file type
            if ($this->imageProcessor->isImage($mimeType)) {
                $results = $this->processImage($content, $finalPath, $mimeType, $options, $originalName);
            } elseif ($this->videoProcessor && $this->videoProcessor->isVideo($mimeType)) {
                $results = $this->processVideo($source, $finalPath, $mimeType, $options, $originalName);
            } else {
                // Upload original file for other types
                $results['main'] = $this->uploadFile($finalPath, $content, $mimeType, $this->extractUrlOptions($options), $originalName);
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
        $path = ltrim($path, '/');

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

    public function fileExists(string $path, ?string $bucket = null): bool
    {
        $path = ltrim($path, '/');
        
        // Retry mechanism for eventual consistency issues
        $maxRetries = 3;
        $retryDelay = 100000; // 100ms in microseconds
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // If custom bucket is provided, use S3 client directly
                if ($bucket !== null) {
                    $exists = $this->s3Client->doesObjectExist($bucket, $path);
                } else {
                    // Use default filesystem for default bucket
                    $exists = $this->filesystem->fileExists($path);
                }
                
                if ($exists || $attempt === $maxRetries) {
                    return $exists;
                }
                
                // If file doesn't exist on first attempts, wait and retry
                if ($attempt < $maxRetries) {
                    $this->logger->debug('File existence check retry', [
                        'path' => $path,
                        'bucket' => $bucket ?? 'default',
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries
                    ]);
                    usleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                }
                
            } catch (\Exception $e) {
                $this->logger->error('File existence check failed', [
                    'path' => $path,
                    'bucket' => $bucket ?? 'default',
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);
                
                // If this is the last attempt, return false
                if ($attempt === $maxRetries) {
                    return false;
                }
                
                // Wait before retry
                usleep($retryDelay);
                $retryDelay *= 2;
            }
        }
        
        return false;
    }

    public function getMetadata(string $path): array
    {
        $this->logger->info('Getting metadata', ['path' => $path]);
        $path = ltrim($path, '/');

        try {
            if (!$this->fileExists($path)) {
                throw new FileNotFoundException($path);
            }

            $metadata = [
                'path' => '/' . $path,
                'file_name' => basename($path),
                'size' => $this->filesystem->fileSize($path),
                'mime_type' => $this->filesystem->mimeType($path),
                'last_modified' => $this->filesystem->lastModified($path),
            ];

            // Add type-specific metadata
            if ($this->imageProcessor->isImage($metadata['mime_type'])) {
                $content = $this->filesystem->read($path);
                $imageInfo = $this->imageProcessor->getImageInfo($content);
                $metadata = array_merge($metadata, $imageInfo);
            } elseif ($this->videoProcessor && $this->videoProcessor->isVideo($metadata['mime_type'])) {
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

    public function getUrl(string $path, ?int $expiration = null, ?bool $signed = null): string
    {
        $this->logger->info('Generating URL', ['path' => $path, 'expiration' => $expiration, 'signed' => $signed]);

        try {
            // Determine if URL should be signed based on config or parameter
            $shouldSign = $signed ?? (function_exists('config') ? config('minio-storage.url.signed_by_default', false) : false);
            
            // If not signed, use optimized public URL method
            if (!$shouldSign) {
                return $this->getUrlPublic($path, null, true);
            }

            // If expiration is null, check config for default behavior
            if ($expiration === null) {
                $defaultExpiration = function_exists('config') ? config('minio-storage.url.default_expiration', 3600) : 3600;
                $expiration = $defaultExpiration;
            }

            if ($expiration === null) {
                return $this->getUrlPublic($path, null, true);
            }

            // Clean path for S3 operations
            $cleanPath = ltrim($path, '/');

            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $cleanPath
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiration} seconds");
            $url = (string) $request->getUri();

            $this->logger->info('Signed URL generated successfully', [
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
            $endpoint = rtrim(function_exists('config') ? config("filesystems.disks.minio.endpoint", $this->s3Client->getEndpoint()) : $this->s3Client->getEndpoint(), '/');
            $bucket = rtrim(function_exists('config') ? config("filesystems.disks.minio.bucket", $this->bucket) : $this->bucket, '/');
            $cleanPath = ltrim($path, '/');
            $publicUrl = "{$endpoint}/{$bucket}/" . $cleanPath;

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

    /**
     * Get a public URL for a file with existence check (optimized for public read access).
     * This is the most efficient way to get public URLs with optional file existence verification.
     */
    public function getUrlPublic(string $path, ?string $bucket = null, bool $checkExists = true): ?string
    {
        $this->logger->info('Generating optimized public URL', [
            'path' => $path, 
            'check_exists' => $checkExists, 
            'custom_bucket' => $bucket
        ]);

        try {
            // Clean path once for all operations
            $cleanPath = ltrim($path, '/');

            // Check file existence if requested (most efficient way)
            if ($checkExists && !$this->fileExists($cleanPath, $bucket)) {
                $this->logger->warning('File not found for public URL generation', [
                    'path' => $path,
                    'bucket' => $bucket ?? 'default'
                ]);
                throw new FileNotFoundException($path);
            }

            // Cache config values to avoid repeated function calls
            static $endpoint = null;
            static $defaultBucket = null;
            
            if ($endpoint === null) {
                $endpoint = rtrim(function_exists('config') ? config("filesystems.disks.minio.endpoint", $this->s3Client->getEndpoint()) : $this->s3Client->getEndpoint(), '/');
            }
            
            if ($defaultBucket === null) {
                $defaultBucket = rtrim(function_exists('config') ? config("filesystems.disks.minio.bucket", $this->bucket) : $this->bucket, '/');
            }

            // Use provided bucket or fall back to default bucket
            $targetBucket = $bucket ? rtrim($bucket, '/') : $defaultBucket;

            // Generate URL efficiently
            $publicUrl = "{$endpoint}/{$targetBucket}/" . $cleanPath;

            $this->logger->info('Optimized public URL generated successfully', [
                'path' => $path,
                'url' => $publicUrl,
                'file_exists_checked' => $checkExists,
                'bucket_used' => $targetBucket,
                'custom_bucket' => $bucket !== null
            ]);

            return $publicUrl;

        } catch (FileNotFoundException $e) {
            // Re-throw FileNotFoundException as-is
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate optimized public URL', [
                'path' => $path,
                'bucket' => $bucket,
                'error' => $e->getMessage()
            ]);
            
            throw new UploadException(
                "Failed to generate optimized public URL: {$e->getMessage()}",
                ['path' => $path, 'bucket' => $bucket],
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

        $this->logger->info('Security scan started', [
            'filename' => $filename,
            'mime_type' => $mimeType,
            'content_size' => strlen($content)
        ]);

        try {
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

            $this->logger->info('Security scan completed successfully', [
                'filename' => $filename,
                'mime_type' => $mimeType
            ]);
        } catch (\Triginarsa\MinioStorageUtils\Exceptions\SecurityException $e) {
            $this->logger->error('Security threat detected - upload blocked', [
                'filename' => $filename,
                'mime_type' => $mimeType,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);
            
            // Re-throw to block the upload
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Security scan failed', [
                'filename' => $filename,
                'mime_type' => $mimeType,
                'error' => $e->getMessage()
            ]);
            
            throw new \Triginarsa\MinioStorageUtils\Exceptions\SecurityException(
                "Security scan failed for file: {$filename}",
                ['filename' => $filename, 'original_error' => $e->getMessage()]
            );
        }
    }

    private function performSecurityScanOnProcessedImage(string $processedContent, string $finalPath, array $options): void
    {
        $scanProcessedImages = function_exists('config') ? config('minio-storage.security.scan_images', true) : true;
        
        if (!$scanProcessedImages) {
            return;
        }

        $this->logger->info('Security scan on processed image started', [
            'path' => $finalPath,
            'content_size' => strlen($processedContent)
        ]);

        try {
            // Scan processed image content
            $this->securityScanner->scan($processedContent, basename($finalPath));
            
            $this->logger->info('Security scan on processed image completed', ['path' => $finalPath]);
        } catch (\Triginarsa\MinioStorageUtils\Exceptions\SecurityException $e) {
            $this->logger->error('Security threat detected in processed image - upload blocked', [
                'path' => $finalPath,
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);
            
            // Re-throw security exceptions to block upload
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Security scan on processed image failed', [
                'path' => $finalPath,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw as security exception to block upload
            throw new \Triginarsa\MinioStorageUtils\Exceptions\SecurityException(
                "Security scan failed for processed image: " . basename($finalPath),
                ['path' => $finalPath, 'original_error' => $e->getMessage()]
            );
        }
    }

    private function processImage(string $content, string $finalPath, string $mimeType, array $options, ?string $originalName): array
    {
        $results = [];
        $processedContent = $content;
        
        // Check for compression-specific options
        $shouldCompress = $options['compress'] ?? false;
        $shouldOptimize = $options['optimize'] ?? false;
        $shouldOptimizeForWeb = $options['optimize_for_web'] ?? false;
        
        // ✅ NEW: Auto-enable image processing if watermark is specified
        $hasWatermark = isset($options['watermark']);
        $shouldProcess = $shouldOptimize || isset($options['image']) || $hasWatermark;
        
        // Apply different processing based on options
        if ($shouldOptimizeForWeb) {
            // Web optimization (resize + compress for web)
            $webOptions = array_merge(function_exists('config') ? config('minio-storage.web_optimization', []) : [], $options['web_options'] ?? []);
            
            // Add watermark options if provided at top level
            if (isset($options['watermark'])) {
                $webOptions['watermark'] = $options['watermark'];
            }
            
            $webResult = $this->imageProcessor->optimizeForWeb($content, $webOptions);
            
            // Handle both old string return and new array return with metadata
            if (is_array($webResult)) {
                $processedContent = $webResult['content'];
                $processingMetadata = $webResult['metadata'] ?? [];
            } else {
                $processedContent = $webResult;
                $processingMetadata = [];
            }
            
            $finalPath = $this->updatePathExtensionIfNeeded($finalPath, $webOptions);
            
            $results['main'] = $this->uploadFileWithMetadata($finalPath, $processedContent, $this->getOptimizedMimeType($mimeType, $webOptions), $this->extractUrlOptions($options), $originalName, $processingMetadata);
            
        } elseif ($shouldCompress) {
            // Dedicated compression
            $compressionOptions = array_merge(function_exists('config') ? config('minio-storage.compression', []) : [], $options['compression_options'] ?? []);
            
            // Add watermark options if provided at top level
            if (isset($options['watermark'])) {
                $compressionOptions['watermark'] = $options['watermark'];
            }
            
            $compressionResult = $this->imageProcessor->compressImage($content, $compressionOptions);
            
            // Handle both old string return and new array return with metadata
            if (is_array($compressionResult)) {
                $processedContent = $compressionResult['content'];
                $processingMetadata = $compressionResult['metadata'] ?? [];
            } else {
                $processedContent = $compressionResult;
                $processingMetadata = [];
            }
            
            $finalPath = $this->updatePathExtensionIfNeeded($finalPath, $compressionOptions);
            
            $results['main'] = $this->uploadFileWithMetadata($finalPath, $processedContent, $this->getOptimizedMimeType($mimeType, $compressionOptions), $this->extractUrlOptions($options), $originalName, $processingMetadata);
            
        } elseif ($shouldProcess) {
            // General image processing (includes watermark-only processing)
            $defaultImageOptions = function_exists('config') ? config('minio-storage.image', []) : [];
            
            // ✅ NEW: Set minimal defaults for watermark-only processing
            if ($hasWatermark && !isset($options['image'])) {
                $defaultImageOptions = array_merge([
                    'quality' => 85, // Default quality for watermark-only processing
                ], $defaultImageOptions);
            }
            
            // Merge user options AFTER defaults to ensure user settings take precedence
            $imageOptions = array_merge($defaultImageOptions, $options['image'] ?? []);
            
            // Enable optimization if requested (but don't override user quality settings)
            if ($shouldOptimize) {
                $imageOptions['optimize'] = true;
                $imageOptions['smart_compression'] = $options['smart_compression'] ?? true;
            }
            
            // Add watermark options if provided at top level
            if (isset($options['watermark'])) {
                $imageOptions['watermark'] = $options['watermark'];
            }
            
            $processResult = $this->imageProcessor->process($content, $imageOptions);
            
            // Handle both old string return and new array return with metadata
            if (is_array($processResult)) {
                $processedContent = $processResult['content'];
                $processingMetadata = $processResult['metadata'] ?? [];
            } else {
                $processedContent = $processResult;
                $processingMetadata = [];
            }

            $finalPath = $this->updatePathExtensionIfNeeded($finalPath, $imageOptions);
            
            // Security scan processed image if enabled
            if ($options['scan'] ?? false) {
                $this->performSecurityScanOnProcessedImage($processedContent, $finalPath, $options);
            }
            
            $results['main'] = $this->uploadFileWithMetadata($finalPath, $processedContent, $this->getOptimizedMimeType($mimeType, $imageOptions), $this->extractUrlOptions($options), $originalName, $processingMetadata);
            
        } else {
            // Upload original image
            $results['main'] = $this->uploadFile($finalPath, $content, $mimeType, $this->extractUrlOptions($options), $originalName);
        }
        
        // Create thumbnail if requested
        if (isset($options['thumbnail'])) {
            $thumbnailOptions = array_merge(function_exists('config') ? config('minio-storage.thumbnail', []) : [], $options['thumbnail']);
            
            // Apply compression to thumbnail as well
            if ($shouldCompress || $shouldOptimize) {
                $thumbnailOptions['quality'] = $thumbnailOptions['quality'] ?? 75;
                $thumbnailOptions['optimize'] = true;
            }
            
            // ✅ REMOVED: No longer apply watermarks to thumbnails since main image already has watermark
            // Thumbnails will be generated from the already-watermarked main image content
            // if (isset($options['watermark'])) {
            //     $thumbnailOptions['watermark'] = $this->prepareThumbnailWatermarkOptions($options['watermark'], $thumbnailOptions);
            // }
            
            // ✅ SIMPLIFIED: createThumbnail now always returns string (no watermark metadata)
            $thumbnailContent = $this->imageProcessor->createThumbnail($processedContent, $thumbnailOptions);
            
            $thumbnailPath = $this->buildThumbnailPath($finalPath, $thumbnailOptions);
            
            $thumbnailPath = $this->updatePathExtensionIfNeeded($thumbnailPath, $thumbnailOptions);
            
            $thumbnailMimeType = $this->getOptimizedMimeType($mimeType, $thumbnailOptions);
            $results['thumbnail'] = $this->uploadFile($thumbnailPath, $thumbnailContent, $thumbnailMimeType, $this->extractUrlOptions($options), $originalName);
        }

        return $results;
    }

    private function processVideo($source, string $finalPath, string $mimeType, array $options, ?string $originalName): array
    {
        $results = [];
        
        // If videoProcessor is null, just upload the original file
        if (!$this->videoProcessor) {
            $content = is_string($source) ? file_get_contents($source) : $source;
            $results['main'] = $this->uploadFile($finalPath, $content, $mimeType, $this->extractUrlOptions($options), $originalName);
            return $results;
        }
        
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
                $results['main'] = $this->uploadFile($finalPath, $processedContent, 'video/mp4', $this->extractUrlOptions($options), $originalName);
                
                // Create video thumbnail if requested
                if (isset($options['video_thumbnail'])) {
                    $thumbnailPath = $this->createTempThumbnailPath($finalPath);
                    $this->videoProcessor->createThumbnail($tempOutputPath, $thumbnailPath, $options['video_thumbnail']);
                    
                    $thumbnailContent = file_get_contents($thumbnailPath);
                    $thumbnailUploadPath = $this->buildVideoThumbnailPath($finalPath, $options['video_thumbnail']);
                    $results['thumbnail'] = $this->uploadFile($thumbnailUploadPath, $thumbnailContent, 'image/jpeg', $this->extractUrlOptions($options), $originalName);
                    
                    unlink($thumbnailPath);
                }
            } else {
                // Upload original video
                $originalContent = file_get_contents($tempInputPath);
                $results['main'] = $this->uploadFile($finalPath, $originalContent, $mimeType, $this->extractUrlOptions($options), $originalName);
                
                // Create thumbnail even for original video if requested and FFmpeg is available
                if (isset($options['video_thumbnail']) && $this->videoProcessor->isFFmpegAvailable()) {
                    $thumbnailPath = $this->createTempThumbnailPath($finalPath);
                    $this->videoProcessor->createThumbnail($tempInputPath, $thumbnailPath, $options['video_thumbnail']);
                    
                    $thumbnailContent = file_get_contents($thumbnailPath);
                    $thumbnailUploadPath = $this->buildVideoThumbnailPath($finalPath, $options['video_thumbnail']);
                    $results['thumbnail'] = $this->uploadFile($thumbnailUploadPath, $thumbnailContent, 'image/jpeg', $this->extractUrlOptions($options), $originalName);
                    
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

    private function uploadFile(string $path, string $content, string $mimeType, array $urlOptions = [], ?string $originalName = null): array
    {
        return $this->uploadFileWithMetadata($path, $content, $mimeType, $urlOptions, $originalName, []);
    }

    private function uploadFileWithMetadata(string $path, string $content, string $mimeType, array $urlOptions = [], ?string $originalName = null, array $metadata = []): array
    {
        $this->filesystem->write($path, $content, ['mimetype' => $mimeType]);

        $url = $this->getUrl($path, $urlOptions['expiration'] ?? null, $urlOptions['signed'] ?? null);

        // Ensure path has single leading slash
        $normalizedPath = '/' . ltrim($path, '/');
        
        $result = [
            'path' => $normalizedPath,
            'url' => $url,
            'size' => strlen($content),
            'mime_type' => $mimeType,
            'original_name' => $originalName,
            'file_name' => basename($path),
        ];

        // Add processing metadata if available
        if (!empty($metadata)) {
            $result['processing'] = $metadata;
        }

        return $result;
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

    private function updatePathExtensionIfNeeded(string $path, array $options): string
    {
        // Check if format conversion is specified
        $newFormat = $options['convert'] ?? $options['format'] ?? null;
        
        if ($newFormat) {
            $pathInfo = pathinfo($path);
            $currentExtension = $pathInfo['extension'] ?? '';
            
            // Normalize format names
            $normalizedFormat = strtolower($newFormat);
            if ($normalizedFormat === 'jpeg') {
                $normalizedFormat = 'jpg';
            }
            
            // Only update if the format is actually different
            if ($normalizedFormat !== strtolower($currentExtension)) {
                $newPath = ($pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '') . 
                          $pathInfo['filename'] . '.' . $normalizedFormat;
                
                $this->logger->info('Updated file extension for format conversion', [
                    'original_path' => $path,
                    'new_path' => $newPath,
                    'format' => $newFormat
                ]);
                
                return $newPath;
            }
        }
        
        return $path;
    }

    // ✅ REMOVED: prepareThumbnailWatermarkOptions method no longer needed
    // Thumbnails are now created from already-watermarked main image content
    // This eliminates the need for separate watermark processing on thumbnails

    private function ensureUniqueFilename(string $path): string
    {
        if (!$this->fileExists($path)) {
            return $path;
        }

        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : '';
        $filename = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        $counter = 1;
        do {
            $newFilename = $filename . '_' . $counter . $extension;
            $newPath = $directory ? $directory . '/' . $newFilename : $newFilename;
            $counter++;
        } while ($this->fileExists($newPath) && $counter < 1000); // Safety limit
        
        $this->logger->info('Generated unique filename to prevent overwrite', [
            'original_path' => $path,
            'unique_path' => $newPath,
            'attempt_number' => $counter - 1
        ]);
        
        return $newPath;
    }

    private function extractUrlOptions(array $options): array
    {
        $urlOptions = [];
        
        // Extract URL-specific options
        if (isset($options['url'])) {
            $urlOptions = array_merge($urlOptions, $options['url']);
        }
        
        // Extract direct options for backward compatibility
        if (isset($options['signed'])) {
            $urlOptions['signed'] = $options['signed'];
        }
        
        if (isset($options['url_expiration'])) {
            $urlOptions['expiration'] = $options['url_expiration'];
        }
        
        return $urlOptions;
    }
} 