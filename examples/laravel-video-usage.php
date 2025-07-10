<?php

// In your Laravel controller
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VideoUploadController extends Controller
{
    /**
     * Check if video processing is available
     */
    public function checkVideoProcessingAvailability()
    {
        $videoProcessor = new VideoProcessor(Log::getLogger());
        $isAvailable = $videoProcessor->isFFmpegAvailable();
        
        return response()->json([
            'video_processing_available' => $isAvailable,
            'message' => $isAvailable 
                ? 'Full video processing features are available'
                : 'Video uploads work normally, but processing features require FFmpeg installation'
        ]);
    }

    /**
     * Simple video upload without processing (works with or without FFmpeg)
     */
    public function uploadVideoSimple(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400', // 100MB max
        ]);

        try {
            // Simple upload without processing - works even without FFmpeg
            $result = MinioStorage::upload(
                $request->file('video'),
                null, // Auto-generate path
                [
                    'scan' => false, // Usually disabled for videos
                    'naming' => 'hash',
                    // No video processing options - just upload
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Video uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Video upload with processing (requires FFmpeg)
     */
    public function uploadVideoWithProcessing(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400', // 100MB max
        ]);

        try {
            // Check if processing is available
            $videoProcessor = new VideoProcessor(Log::getLogger());
            if (!$videoProcessor->isFFmpegAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video processing is not available. FFmpeg is required for video processing features.',
                    'suggestion' => 'You can still upload videos without processing using the simple upload endpoint.',
                    'install_instructions' => [
                        'Install FFmpeg system binary: sudo apt-get install ffmpeg',
                        'Install PHP package: composer require php-ffmpeg/php-ffmpeg'
                    ]
                ], 422);
            }

            // Video upload with conversion to MP4
            $result = MinioStorage::upload(
                $request->file('video'),
                null, // Auto-generate path
                [
                    'scan' => false,
                    'naming' => 'hash',
                    'video' => [
                        'format' => 'mp4',
                        'compression' => 'medium',
                        'resize' => [
                            'width' => 1280,
                            'height' => 720,
                            'mode' => 'fit'
                        ]
                    ],
                    'video_thumbnail' => [
                        'width' => 320,
                        'height' => 240,
                        'time' => 5, // 5 seconds into video
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Video processed and uploaded successfully'
            ]);

        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'FFmpeg') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video processing failed: ' . $e->getMessage(),
                    'suggestion' => 'Use the simple upload endpoint to upload without processing.',
                    'ffmpeg_error' => true
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Smart video upload - automatically chooses processing or simple upload
     */
    public function uploadVideoSmart(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400',
            'enable_processing' => 'boolean'
        ]);

        try {
            $videoProcessor = new VideoProcessor(Log::getLogger());
            $processingAvailable = $videoProcessor->isFFmpegAvailable();
            $enableProcessing = $request->input('enable_processing', true);

            $options = [
                'scan' => false,
                'naming' => 'hash',
            ];

            // Add processing options only if available and requested
            if ($processingAvailable && $enableProcessing) {
                $options['video'] = [
                    'format' => 'mp4',
                    'compression' => 'medium',
                    'resize' => [
                        'width' => 1280,
                        'height' => 720,
                        'mode' => 'fit'
                    ]
                ];
                $options['video_thumbnail'] = [
                    'width' => 320,
                    'height' => 240,
                    'time' => 5
                ];
            }

            $result = MinioStorage::upload(
                $request->file('video'),
                null,
                $options
            );

            $message = $processingAvailable && $enableProcessing
                ? 'Video processed and uploaded successfully'
                : 'Video uploaded successfully' . ($processingAvailable ? '' : ' (processing not available)');

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => $message,
                'processing_applied' => $processingAvailable && $enableProcessing,
                'processing_available' => $processingAvailable,
                'warnings' => $result['warnings'] ?? []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Advanced video processing with full options (requires FFmpeg)
     */
    public function compressVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:204800', // 200MB max
            'compression_level' => 'string|in:ultrafast,fast,medium,slow,veryslow'
        ]);

        try {
            $videoProcessor = new VideoProcessor(Log::getLogger());
            if (!$videoProcessor->isFFmpegAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video compression requires FFmpeg installation.',
                    'alternative' => 'Use the simple upload endpoint for basic video uploads.'
                ], 422);
            }

            $compressionLevel = $request->input('compression_level', 'medium');

            // High compression video upload
            $result = MinioStorage::upload(
                $request->file('video'),
                'compressed-videos/' . time() . '.mp4',
                [
                    'video' => [
                        'format' => 'mp4',
                        'compression' => $compressionLevel,
                        'video_bitrate' => '1000k', // Lower bitrate for smaller files
                        'audio_bitrate' => '96k',
                        'resize' => [
                            'width' => 1280,
                            'height' => 720,
                            'mode' => 'fit'
                        ],
                        'clip' => [
                            'start' => '00:00:00',
                            'duration' => '00:05:00' // Limit to 5 minutes
                        ]
                    ],
                    'video_thumbnail' => [
                        'width' => 320,
                        'height' => 240,
                        'time' => '00:00:10'
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Video compressed and uploaded successfully',
                'compression_level' => $compressionLevel
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Video upload with watermark (requires FFmpeg)
     */
    public function uploadWithWatermark(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400',
            'watermark_position' => 'string|in:top-left,top-right,bottom-left,bottom-right,center'
        ]);

        try {
            $videoProcessor = new VideoProcessor(Log::getLogger());
            if (!$videoProcessor->isFFmpegAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video watermarking requires FFmpeg installation.',
                    'alternative' => 'Use the simple upload endpoint for basic video uploads.'
                ], 422);
            }

            $watermarkPath = public_path('watermark.png');
            if (!file_exists($watermarkPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Watermark image not found at: ' . $watermarkPath
                ], 400);
            }

            $position = $request->input('watermark_position', 'bottom-right');

            // Video upload with watermark
            $result = MinioStorage::upload(
                $request->file('video'),
                'watermarked-videos/' . time() . '.mp4',
                [
                    'video' => [
                        'format' => 'mp4',
                        'compression' => 'medium',
                        'watermark' => [
                            'path' => $watermarkPath,
                            'position' => $position
                        ],
                        'resize' => [
                            'width' => 1280,
                            'height' => 720,
                            'mode' => 'fit'
                        ]
                    ],
                    'video_thumbnail' => [
                        'width' => 480,
                        'height' => 360,
                        'time' => 3
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Video with watermark uploaded successfully',
                'watermark_position' => $position
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch video upload with different processing options
     */
    public function batchUploadVideos(Request $request)
    {
        $request->validate([
            'videos' => 'required|array|min:1|max:5',
            'videos.*' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400'
        ]);

        try {
            $videoProcessor = new VideoProcessor(Log::getLogger());
            $processingAvailable = $videoProcessor->isFFmpegAvailable();
            
            $results = [];
            $processedCount = 0;
            $uploadedCount = 0;

            foreach ($request->file('videos') as $index => $video) {
                try {
                    $options = [
                        'scan' => false,
                        'naming' => 'hash',
                    ];

                    // Add processing options only if available
                    if ($processingAvailable) {
                        $options['video'] = [
                            'format' => 'mp4',
                            'compression' => 'fast', // Faster for batch processing
                            'resize' => [
                                'width' => 1280,
                                'height' => 720,
                                'mode' => 'fit'
                            ]
                        ];
                        $options['video_thumbnail'] = [
                            'width' => 320,
                            'height' => 240,
                            'time' => 5
                        ];
                    }

                    $result = MinioStorage::upload(
                        $video,
                        "batch-videos/" . time() . "-{$index}.mp4",
                        $options
                    );

                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result,
                        'processed' => $processingAvailable
                    ];

                    if ($processingAvailable) {
                        $processedCount++;
                    }
                    $uploadedCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total' => count($request->file('videos')),
                    'uploaded' => $uploadedCount,
                    'processed' => $processedCount,
                    'processing_available' => $processingAvailable
                ],
                'message' => "Batch upload completed: {$uploadedCount} videos uploaded" . 
                           ($processingAvailable ? ", {$processedCount} processed" : " (processing not available)")
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get video metadata (enhanced when FFmpeg is available)
     */
    public function getVideoInfo($path)
    {
        try {
            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found'
                ], 404);
            }

            $metadata = MinioStorage::getMetadata($path);
            $url = MinioStorage::getUrl($path, 3600);

            // Check if enhanced metadata is available
            $videoProcessor = new VideoProcessor(Log::getLogger());
            $enhancedMetadata = $videoProcessor->isFFmpegAvailable();

            return response()->json([
                'success' => true,
                'data' => array_merge($metadata, ['url' => $url]),
                'enhanced_metadata_available' => $enhancedMetadata,
                'note' => $enhancedMetadata ? 'Full video metadata available' : 'Basic metadata only (FFmpeg not available)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert video format (requires FFmpeg)
     */
    public function convertVideoFormat(Request $request, $path)
    {
        $request->validate([
            'target_format' => 'required|string|in:mp4,webm'
        ]);

        try {
            $videoProcessor = new VideoProcessor(Log::getLogger());
            if (!$videoProcessor->isFFmpegAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video format conversion requires FFmpeg installation.'
                ], 422);
            }

            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source video not found'
                ], 404);
            }

            $targetFormat = $request->input('target_format');
            $outputPath = pathinfo($path, PATHINFO_DIRNAME) . '/' . 
                         pathinfo($path, PATHINFO_FILENAME) . '_converted.' . $targetFormat;

            // Download source video temporarily
            $sourceContent = MinioStorage::getUrl($path);
            
            // Process conversion
            $result = MinioStorage::upload(
                $sourceContent,
                $outputPath,
                [
                    'video' => [
                        'format' => $targetFormat,
                        'compression' => 'medium'
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => "Video converted to {$targetFormat} successfully",
                'source_path' => $path,
                'converted_path' => $outputPath
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// Example routes (add to your routes/web.php or routes/api.php)
/*
Route::prefix('video')->group(function () {
    // Check availability
    Route::get('processing-status', [VideoUploadController::class, 'checkVideoProcessingAvailability']);
    
    // Upload endpoints
    Route::post('upload/simple', [VideoUploadController::class, 'uploadVideoSimple']);
    Route::post('upload/processed', [VideoUploadController::class, 'uploadVideoWithProcessing']);
    Route::post('upload/smart', [VideoUploadController::class, 'uploadVideoSmart']);
    Route::post('upload/batch', [VideoUploadController::class, 'batchUploadVideos']);
    
    // Processing endpoints (require FFmpeg)
    Route::post('compress', [VideoUploadController::class, 'compressVideo']);
    Route::post('watermark', [VideoUploadController::class, 'uploadWithWatermark']);
    Route::post('convert/{path}', [VideoUploadController::class, 'convertVideoFormat'])->where('path', '.*');
    
    // Info endpoint
    Route::get('info/{path}', [VideoUploadController::class, 'getVideoInfo'])->where('path', '.*');
});
*/ 