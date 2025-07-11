<?php

/**
 * Laravel Video Management Example
 * 
 * This example demonstrates comprehensive video handling including:
 * - Upload with compression and format conversion
 * - Generate presigned URLs
 * - Video processing (resize, clip, rotate, watermark)
 * - Thumbnail generation
 * - Video deletion
 * - Batch video operations
 * 
 * Requirements:
 * - FFmpeg installed on the server
 * - php-ffmpeg/php-ffmpeg package
 */

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    /**
     * Simple video upload
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp',
                'max:204800' // 200MB max
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('video');
            
            // Simple upload without processing
            $result = MinioStorage::upload(
                $file,
                '/videos/uploads/',
                [
                    'scan' => true,
                    'security' => [
                        'strict_mode' => false,
                        'scan_videos' => true,
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'data' => [
                    'path' => $result['main']['path'],
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $result['main']['size'],
                    'size_human' => $this->formatBytes($result['main']['size']),
                    'mime_type' => $result['main']['mime_type'],
                    'upload_time' => now()->toISOString()
                ],
                'note' => 'Install FFmpeg for video processing features'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload video with compression
     */
    public function uploadVideoWithCompression(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp',
                'max:204800'
            ],
            'compression_level' => 'nullable|in:ultrafast,fast,medium,slow,veryslow',
            'quality' => 'nullable|in:low,medium,high,ultra'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('video');
            $compressionLevel = $request->input('compression_level', 'medium');
            $quality = $request->input('quality', 'medium');
            
            $result = MinioStorage::upload(
                $file,
                '/videos/compressed/',
                [
                    'compress' => true,
                    'video' => [
                        'compression' => $compressionLevel,
                        'quality' => $quality,
                        'format' => 'mp4',
                        'video_bitrate' => $this->getBitrate($quality),
                        'audio_bitrate' => 128,
                    ],
                    'thumbnail' => [
                        'width' => 320,
                        'height' => 240,
                        'time' => 5, // Generate thumbnail at 5 seconds
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Video compressed successfully',
                'data' => [
                    'main_video' => [
                        'path' => $result['main']['path'],
                        'size' => $result['main']['size'],
                        'size_human' => $this->formatBytes($result['main']['size']),
                    ],
                    'thumbnail' => isset($result['thumbnail']) ? [
                        'path' => $result['thumbnail']['path'],
                        'size' => $result['thumbnail']['size'],
                    ] : null,
                    'compression_info' => [
                        'original_size' => $file->getSize(),
                        'compressed_size' => $result['main']['size'],
                        'compression_ratio' => round((1 - ($result['main']['size'] / $file->getSize())) * 100, 2) . '%',
                        'compression_level' => $compressionLevel,
                        'quality' => $quality
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Compression failed: ' . $e->getMessage(),
                'note' => 'Ensure FFmpeg is installed and configured'
            ], 500);
        }
    }

    /**
     * Convert video to different format
     */
    public function convertVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp',
                'max:204800'
            ],
            'target_format' => 'required|in:mp4,webm',
            'quality' => 'nullable|in:low,medium,high'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('video');
            $targetFormat = $request->input('target_format');
            $quality = $request->input('quality', 'medium');
            
            $result = MinioStorage::upload(
                $file,
                '/videos/converted/',
                [
                    'video' => [
                        'format' => $targetFormat,
                        'quality' => $quality,
                        'compression' => 'medium',
                        'video_bitrate' => $this->getBitrate($quality),
                        'audio_bitrate' => 128,
                    ],
                    'thumbnail' => [
                        'width' => 480,
                        'height' => 360,
                        'time' => '00:00:03',
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Video converted to {$targetFormat} successfully",
                'data' => [
                    'converted_video' => [
                        'path' => $result['main']['path'],
                        'format' => $targetFormat,
                        'size' => $result['main']['size'],
                        'size_human' => $this->formatBytes($result['main']['size']),
                    ],
                    'thumbnail' => isset($result['thumbnail']) ? [
                        'path' => $result['thumbnail']['path'],
                        'size' => $result['thumbnail']['size'],
                    ] : null,
                    'conversion_info' => [
                        'original_format' => $file->getClientOriginalExtension(),
                        'target_format' => $targetFormat,
                        'quality' => $quality,
                        'original_size' => $file->getSize(),
                        'converted_size' => $result['main']['size']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Advanced video processing (clip, resize, rotate)
     */
    public function processVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp',
                'max:204800'
            ],
            'start_time' => 'nullable|string', // Format: 00:01:30
            'duration' => 'nullable|string',   // Format: 00:02:00
            'width' => 'nullable|integer|min:240|max:3840',
            'height' => 'nullable|integer|min:240|max:2160',
            'rotate' => 'nullable|in:90,180,270'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('video');
            $options = ['video' => ['format' => 'mp4', 'compression' => 'medium']];
            
            // Add clipping if specified
            if ($request->has('start_time') || $request->has('duration')) {
                $options['video']['clip'] = [];
                if ($request->has('start_time')) {
                    $options['video']['clip']['start'] = $request->input('start_time');
                }
                if ($request->has('duration')) {
                    $options['video']['clip']['duration'] = $request->input('duration');
                }
            }
            
            // Add resizing if specified
            if ($request->has('width') || $request->has('height')) {
                $options['video']['resize'] = [
                    'width' => $request->input('width'),
                    'height' => $request->input('height'),
                    'mode' => 'fit'
                ];
            }
            
            // Add rotation if specified
            if ($request->has('rotate')) {
                $options['video']['rotate'] = (int) $request->input('rotate');
            }
            
            // Generate thumbnail
            $options['thumbnail'] = [
                'width' => 320,
                'height' => 240,
                'time' => $request->input('start_time', '00:00:05')
            ];
            
            $result = MinioStorage::upload($file, '/videos/processed/', $options);

            return response()->json([
                'success' => true,
                'message' => 'Video processed successfully',
                'data' => [
                    'processed_video' => [
                        'path' => $result['main']['path'],
                        'size' => $result['main']['size'],
                        'size_human' => $this->formatBytes($result['main']['size']),
                    ],
                    'thumbnail' => isset($result['thumbnail']) ? [
                        'path' => $result['thumbnail']['path'],
                        'size' => $result['thumbnail']['size'],
                    ] : null,
                    'processing_applied' => [
                        'clipping' => $request->has('start_time') || $request->has('duration'),
                        'resizing' => $request->has('width') || $request->has('height'),
                        'rotation' => $request->has('rotate'),
                        'clip_info' => [
                            'start_time' => $request->input('start_time'),
                            'duration' => $request->input('duration')
                        ],
                        'resize_info' => [
                            'width' => $request->input('width'),
                            'height' => $request->input('height')
                        ],
                        'rotation_angle' => $request->input('rotate')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get video URL with expiration
     */
    public function getVideoUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'expiration' => 'nullable|integer|min:60|max:604800', // 1 minute to 7 days
            'include_metadata' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->input('path');
            $expiration = $request->input('expiration', 3600); // Default 1 hour
            $includeMetadata = $request->input('include_metadata', false);

            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found'
                ], 404);
            }

            $url = MinioStorage::getUrl($path, $expiration);
            $response = [
                'success' => true,
                'data' => [
                    'video_url' => $url,
                    'expires_in' => $expiration,
                    'expires_at' => now()->addSeconds($expiration)->toISOString(),
                ]
            ];

            if ($includeMetadata) {
                $metadata = MinioStorage::getMetadata($path);
                $response['data']['video_info'] = [
                    'path' => $path,
                    'size' => $metadata['size'],
                    'size_human' => $this->formatBytes($metadata['size']),
                    'mime_type' => $metadata['mime_type'],
                    'last_modified' => date('c', $metadata['last_modified'])
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete video
     */
    public function deleteVideo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->input('path');

            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found'
                ], 404);
            }

            // Get metadata before deletion
            $metadata = MinioStorage::getMetadata($path);
            
            // Delete the video
            $deleted = MinioStorage::delete($path);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Video deleted successfully',
                    'data' => [
                        'deleted_path' => $path,
                        'deleted_at' => now()->toISOString(),
                        'file_info' => [
                            'size' => $metadata['size'],
                            'size_human' => $this->formatBytes($metadata['size']),
                            'mime_type' => $metadata['mime_type']
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete video'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch video upload with different processing options
     */
    public function batchUploadVideos(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'videos' => 'required|array|max:3', // Max 3 videos at once
            'videos.*' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp',
                'max:204800'
            ],
            'apply_compression' => 'nullable|boolean',
            'compression_level' => 'nullable|in:ultrafast,fast,medium,slow,veryslow'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $results = [];
        $errors = [];
        $applyCompression = $request->input('apply_compression', false);
        $compressionLevel = $request->input('compression_level', 'medium');

        foreach ($request->file('videos') as $index => $file) {
            try {
                $options = ['scan' => true];
                
                if ($applyCompression) {
                    $options['compress'] = true;
                    $options['video'] = [
                        'compression' => $compressionLevel,
                        'format' => 'mp4',
                        'quality' => 'medium'
                    ];
                    $options['thumbnail'] = [
                        'width' => 320,
                        'height' => 240,
                        'time' => 3
                    ];
                }

                $result = MinioStorage::upload($file, '/videos/batch/', $options);

                $results[] = [
                    'index' => $index,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $result['main']['path'],
                    'size' => $result['main']['size'],
                    'size_human' => $this->formatBytes($result['main']['size']),
                    'mime_type' => $result['main']['mime_type'],
                    'compression_applied' => $applyCompression,
                    'thumbnail_path' => $result['thumbnail']['path'] ?? null,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'original_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'status' => 'failed'
                ];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'message' => sprintf(
                'Batch upload completed: %d successful, %d failed',
                count($results),
                count($errors)
            ),
            'data' => [
                'successful_uploads' => $results,
                'failed_uploads' => $errors,
                'processing_options' => [
                    'compression_applied' => $applyCompression,
                    'compression_level' => $compressionLevel
                ],
                'summary' => [
                    'total_videos' => count($request->file('videos')),
                    'successful' => count($results),
                    'failed' => count($errors)
                ]
            ]
        ]);
    }

    /**
     * Get video information and metadata
     */
    public function getVideoInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->input('path');

            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found'
                ], 404);
            }

            $metadata = MinioStorage::getMetadata($path);

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $path,
                    'size' => $metadata['size'],
                    'size_human' => $this->formatBytes($metadata['size']),
                    'mime_type' => $metadata['mime_type'],
                    'file_type' => $this->getVideoTypeFromMime($metadata['mime_type']),
                    'last_modified' => date('c', $metadata['last_modified']),
                    'visibility' => $metadata['visibility'],
                    // Video-specific metadata (if available)
                    'duration' => $metadata['duration'] ?? null,
                    'video_info' => $metadata['video'] ?? null,
                    'audio_info' => $metadata['audio'] ?? null,
                    'ffmpeg_available' => $metadata['video_processing_available'] ?? true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get video info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get bitrate based on quality
     */
    private function getBitrate(string $quality): int
    {
        $bitrates = [
            'low' => 800,
            'medium' => 1500,
            'high' => 3000,
            'ultra' => 5000
        ];

        return $bitrates[$quality] ?? 1500;
    }

    /**
     * Helper method to format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Helper method to get video type from MIME type
     */
    private function getVideoTypeFromMime(string $mimeType): string
    {
        $types = [
            'video/mp4' => 'MP4 Video',
            'video/avi' => 'AVI Video',
            'video/quicktime' => 'QuickTime Video',
            'video/x-msvideo' => 'AVI Video',
            'video/x-ms-wmv' => 'Windows Media Video',
            'video/x-flv' => 'Flash Video',
            'video/webm' => 'WebM Video',
            'video/3gpp' => '3GP Video',
            'video/x-matroska' => 'Matroska Video',
        ];

        return $types[$mimeType] ?? 'Unknown Video Type';
    }
}

/**
 * ROUTES EXAMPLE (add to routes/web.php or routes/api.php):
 * 
 * Route::prefix('videos')->group(function () {
 *     Route::post('upload', [VideoController::class, 'uploadVideo']);
 *     Route::post('upload-compress', [VideoController::class, 'uploadVideoWithCompression']);
 *     Route::post('convert', [VideoController::class, 'convertVideo']);
 *     Route::post('process', [VideoController::class, 'processVideo']);
 *     Route::post('url', [VideoController::class, 'getVideoUrl']);
 *     Route::delete('delete', [VideoController::class, 'deleteVideo']);
 *     Route::post('batch-upload', [VideoController::class, 'batchUploadVideos']);
 *     Route::get('info', [VideoController::class, 'getVideoInfo']);
 * });
 */

/**
 * FRONTEND USAGE EXAMPLES:
 * 
 * 1. Simple upload:
 * POST /videos/upload
 * Content-Type: multipart/form-data
 * Body: video (file)
 * 
 * 2. Upload with compression:
 * POST /videos/upload-compress
 * Content-Type: multipart/form-data
 * Body: video (file), compression_level (ultrafast|fast|medium|slow|veryslow), quality (low|medium|high|ultra)
 * 
 * 3. Convert video format:
 * POST /videos/convert
 * Content-Type: multipart/form-data
 * Body: video (file), target_format (mp4|webm), quality (low|medium|high)
 * 
 * 4. Advanced processing:
 * POST /videos/process
 * Content-Type: multipart/form-data
 * Body: video (file), start_time (00:01:30), duration (00:02:00), width (1280), height (720), rotate (90|180|270)
 * 
 * 5. Get video URL:
 * POST /videos/url
 * Content-Type: application/json
 * Body: {"path": "/videos/uploads/filename.mp4", "expiration": 3600, "include_metadata": true}
 * 
 * 6. Delete video:
 * DELETE /videos/delete
 * Content-Type: application/json
 * Body: {"path": "/videos/uploads/filename.mp4"}
 * 
 * 7. Get video info:
 * GET /videos/info?path=/videos/uploads/filename.mp4
 * 
 * 8. Batch upload:
 * POST /videos/batch-upload
 * Content-Type: multipart/form-data
 * Body: videos[] (multiple files), apply_compression (true|false), compression_level (medium)
 */

/**
 * JAVASCRIPT FRONTEND EXAMPLE:
 * 
 * // Upload video with compression
 * const uploadVideoWithCompression = async (file, compressionLevel = 'medium', quality = 'medium') => {
 *     const formData = new FormData();
 *     formData.append('video', file);
 *     formData.append('compression_level', compressionLevel);
 *     formData.append('quality', quality);
 *     
 *     const response = await fetch('/videos/upload-compress', {
 *         method: 'POST',
 *         body: formData,
 *         headers: {
 *             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
 *         }
 *     });
 *     
 *     return response.json();
 * };
 * 
 * // Convert video format
 * const convertVideo = async (file, targetFormat = 'mp4', quality = 'medium') => {
 *     const formData = new FormData();
 *     formData.append('video', file);
 *     formData.append('target_format', targetFormat);
 *     formData.append('quality', quality);
 *     
 *     const response = await fetch('/videos/convert', {
 *         method: 'POST',
 *         body: formData,
 *         headers: {
 *             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
 *         }
 *     });
 *     
 *     return response.json();
 * };
 * 
 * // Get video download URL
 * const getVideoUrl = async (path, expiration = 3600) => {
 *     const response = await fetch('/videos/url', {
 *         method: 'POST',
 *         headers: {
 *             'Content-Type': 'application/json',
 *             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
 *         },
 *         body: JSON.stringify({ path, expiration, include_metadata: true })
 *     });
 *     
 *     return response.json();
 * };
 * 
 * // Delete video
 * const deleteVideo = async (path) => {
 *     const response = await fetch('/videos/delete', {
 *         method: 'DELETE',
 *         headers: {
 *             'Content-Type': 'application/json',
 *             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
 *         },
 *         body: JSON.stringify({ path })
 *     });
 *     
 *     return response.json();
 * };
 */

/**
 * INSTALLATION REQUIREMENTS:
 * 
 * 1. Install FFmpeg on your server:
 *    Ubuntu/Debian: sudo apt-get install ffmpeg
 *    CentOS/RHEL: sudo yum install ffmpeg
 *    macOS: brew install ffmpeg
 * 
 * 2. Install php-ffmpeg package:
 *    composer require php-ffmpeg/php-ffmpeg
 * 
 * 3. Verify installation:
 *    ffmpeg -version
 *    ffprobe -version
 * 
 * 4. Configure FFmpeg paths in config/minio-storage.php:
 *    'ffmpeg' => [
 *        'ffmpeg.binaries' => '/usr/bin/ffmpeg',
 *        'ffprobe.binaries' => '/usr/bin/ffprobe',
 *        'timeout' => 3600,
 *        'ffmpeg.threads' => 12,
 *    ]
 */
