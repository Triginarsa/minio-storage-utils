<?php

// Laravel Controller Example for MinIO Storage Services
// 
// DEFAULT URL BEHAVIOR:
// - All uploaded files will have PUBLIC URLs by default (no signature required)
// - To get signed URLs with expiration, use: getUrl($path, $expiration, true)
// - To get public URLs explicitly, use: getUrl($path, null, false)
// - For config-based defaults, use: getUrl($path) // Uses MINIO_URL_SIGNED_BY_DEFAULT setting
//
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Http\Request;

class MinioStorageController extends Controller
{
    // ==============================================
    // BASIC UPLOADS WITH SPECIFIC PATHS
    // ==============================================
    
    /**
     * 1. Basic image upload with path "/img/upload/"
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240', // 10MB max
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/img/upload/'
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. Basic document upload with path "/doc/upload/"
     */
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf|max:20480', // 20MB max
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/doc/upload/'
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. Basic video upload with path "/vid/upload/"
     * Note: Requires FFmpeg installation for video processing
     */
    public function uploadVideo(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400', // 100MB max
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/vid/upload/'
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'data' => $result,
                'note' => 'For video processing features, ensure FFmpeg is installed on your server'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==============================================
    // ADVANCED IMAGE PROCESSING
    // ==============================================

    /**
     * 4. Upload image with compression
     */
    public function uploadImageWithCompression(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/img/compressed/',
                [
                    'compress' => true,
                    'compression_options' => [
                        'quality' => 75,
                        'format' => 'jpg',
                        'progressive' => true,
                        'target_size' => 500000, // 500KB target
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with compression',
                'data' => $result,
                'compression_info' => [
                    'original_size' => $request->file('file')->getSize(),
                    'compressed_size' => $result['main']['size'] ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 5. Upload image with security scan
     */
    public function uploadImageWithScan(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/img/secure/',
                [
                    'scan' => true,
                    'security' => [
                        'strict_mode' => true,
                        'scan_images' => true,
                        'quarantine_suspicious' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with security scan',
                'data' => $result,
                'security' => 'File passed security scan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 6. Upload image with crop to square
     */
    public function uploadImageWithCropSquare(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/img/square/',
                [
                    'image' => [
                        'resize' => [
                            'width' => 500,
                            'height' => 500,
                            'method' => 'crop', // Crop to square
                        ],
                        'quality' => 85,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded and cropped to square',
                'data' => $result,
                'crop_info' => 'Image cropped to 500x500 square'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 7. Upload image with thumbnail generation
     */
    public function uploadImageWithThumbnail(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/img/with-thumb/',
                [
                    'thumbnail' => [
                        'width' => 200,
                        'height' => 200,
                        'method' => 'fit',
                        'quality' => 75,
                        'optimize' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with thumbnail',
                'data' => $result,
                'thumbnail' => 'Thumbnail generated at 200x200'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 8. Upload document with security scan
     */
    public function uploadDocumentWithScan(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf|max:20480',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/doc/secure/',
                [
                    'scan' => true,
                    'security' => [
                        'scan_documents' => true,
                        'strict_mode' => true,
                        'quarantine_suspicious' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Document uploaded with security scan',
                'data' => $result,
                'security' => 'Document passed security scan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 9. Upload video with compression
     * NOTE: Requires FFmpeg installation and configuration
     * Install: sudo apt-get install ffmpeg (Ubuntu/Debian)
     *          brew install ffmpeg (macOS)
     */
    public function uploadVideoWithCompression(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:mp4,avi,mov,wmv,flv,webm,mkv,3gp|max:102400',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/vid/compressed/',
                [
                    'compress' => true,
                    'video' => [
                        'compression' => 'medium', // ultrafast, fast, medium, slow, veryslow
                        'format' => 'mp4',
                        'video_bitrate' => 1500, // kbps
                        'audio_bitrate' => 128,  // kbps
                        'max_width' => 1280,
                        'max_height' => 720,
                        'quality' => 'medium',
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
                'message' => 'Video uploaded with compression',
                'data' => $result,
                'compression_info' => [
                    'original_size' => $request->file('file')->getSize(),
                    'compressed_size' => $result['main']['size'] ?? null,
                ],
                'note' => 'FFmpeg must be installed for video processing'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'note' => 'If video processing failed, ensure FFmpeg is installed and configured'
            ], 500);
        }
    }

    // ==============================================
    // URL GENERATION
    // ==============================================

    /**
     * Get file URL (public or signed URL)
     */
    public function getFileUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'expiration' => 'nullable|integer|min:1|max:604800', // Max 7 days
        ]);

        try {
            $path = $request->input('path');
            $expiration = $request->input('expiration');

            // Check if file exists
            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Get URL (with or without expiration)
            $url = $expiration 
                ? MinioStorage::getUrl($path, $expiration, true)
                : MinioStorage::getUrl($path, null, false);
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'path' => $path,
                'expiration' => $expiration ? $expiration . ' seconds' : 'No expiration (public)',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file metadata and URL
     */
    public function getFileInfo(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');

            // Get file metadata
            $metadata = MinioStorage::getMetadata($path);
            
            // Get public URL
            $url = MinioStorage::getPublicUrl($path);
            
            return response()->json([
                'success' => true,
                'metadata' => $metadata,
                'url' => $url,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==============================================
    // FILE DELETION
    // ==============================================

    /**
     * Delete file
     */
    public function deleteFile(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');

            // Check if file exists
            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            // Delete file
            $deleted = MinioStorage::delete($path);
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully',
                    'path' => $path,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete file'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==============================================
    // BATCH OPERATIONS
    // ==============================================

    /**
     * Batch upload multiple files
     */
    public function batchUpload(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|max:10240',
            'path' => 'required|string',
        ]);

        try {
            $files = $request->file('files');
            $basePath = $request->input('path');
            $results = [];

            foreach ($files as $file) {
                $result = MinioStorage::upload($file, $basePath);
                $results[] = [
                    'filename' => $file->getClientOriginalName(),
                    'result' => $result
                ];
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Batch upload completed',
                'results' => $results,
                'count' => count($results)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch delete multiple files
     */
    public function batchDelete(Request $request)
    {
        $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'required|string',
        ]);

        try {
            $paths = $request->input('paths');
            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($paths as $path) {
                try {
                    // Check if file exists
                    if (!MinioStorage::fileExists($path)) {
                        $results[] = [
                            'path' => $path,
                            'success' => false,
                            'message' => 'File not found'
                        ];
                        $failedCount++;
                        continue;
                    }

                    // Delete file
                    $deleted = MinioStorage::delete($path);
                    
                    if ($deleted) {
                        $results[] = [
                            'path' => $path,
                            'success' => true,
                            'message' => 'File deleted successfully'
                        ];
                        $successCount++;
                    } else {
                        $results[] = [
                            'path' => $path,
                            'success' => false,
                            'message' => 'Failed to delete file'
                        ];
                        $failedCount++;
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'path' => $path,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                    $failedCount++;
                }
            }
            
            return response()->json([
                'success' => $failedCount === 0,
                'message' => "Batch delete completed. Success: $successCount, Failed: $failedCount",
                'summary' => [
                    'total' => count($paths),
                    'success' => $successCount,
                    'failed' => $failedCount,
                ],
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch get URLs for multiple files
     */
    public function batchGetUrls(Request $request)
    {
        $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'required|string',
            'expiration' => 'nullable|integer|min:1|max:604800', // Max 7 days
            'url_type' => 'nullable|in:public,signed', // public or signed URLs
        ]);

        try {
            $paths = $request->input('paths');
            $expiration = $request->input('expiration');
            $urlType = $request->input('url_type', 'public');
            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($paths as $path) {
                try {
                    // Check if file exists
                    if (!MinioStorage::fileExists($path)) {
                        $results[] = [
                            'path' => $path,
                            'success' => false,
                            'message' => 'File not found',
                            'url' => null
                        ];
                        $failedCount++;
                        continue;
                    }

                    // Get URL based on type
                    if ($urlType === 'signed' && $expiration) {
                        $url = MinioStorage::getUrl($path, $expiration, true);
                    } else {
                        $url = MinioStorage::getUrl($path, null, false);
                    }
                    
                    $results[] = [
                        'path' => $path,
                        'success' => true,
                        'url' => $url,
                        'url_type' => $urlType,
                        'expiration' => $expiration ? $expiration . ' seconds' : 'No expiration',
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'path' => $path,
                        'success' => false,
                        'message' => $e->getMessage(),
                        'url' => null
                    ];
                    $failedCount++;
                }
            }
            
            return response()->json([
                'success' => $failedCount === 0,
                'message' => "Batch URL generation completed. Success: $successCount, Failed: $failedCount",
                'summary' => [
                    'total' => count($paths),
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'url_type' => $urlType,
                    'expiration' => $expiration ? $expiration . ' seconds' : 'No expiration',
                ],
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch get file information (metadata + URLs)
     */
    public function batchGetFileInfo(Request $request)
    {
        $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'required|string',
            'include_metadata' => 'nullable|boolean',
            'include_urls' => 'nullable|boolean',
            'expiration' => 'nullable|integer|min:1|max:604800', // Max 7 days
        ]);

        try {
            $paths = $request->input('paths');
            $includeMetadata = $request->input('include_metadata', true);
            $includeUrls = $request->input('include_urls', true);
            $expiration = $request->input('expiration');
            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($paths as $path) {
                try {
                    // Check if file exists
                    if (!MinioStorage::fileExists($path)) {
                        $results[] = [
                            'path' => $path,
                            'success' => false,
                            'message' => 'File not found',
                            'metadata' => null,
                            'urls' => null
                        ];
                        $failedCount++;
                        continue;
                    }

                    $fileInfo = [
                        'path' => $path,
                        'success' => true,
                        'message' => 'File information retrieved successfully'
                    ];

                    // Get metadata if requested
                    if ($includeMetadata) {
                        try {
                            $metadata = MinioStorage::getMetadata($path);
                            $fileInfo['metadata'] = $metadata;
                        } catch (\Exception $e) {
                            $fileInfo['metadata'] = null;
                            $fileInfo['metadata_error'] = $e->getMessage();
                        }
                    }

                    // Get URLs if requested
                    if ($includeUrls) {
                        try {
                            $urls = [
                                'public' => MinioStorage::getUrl($path, null, false)
                            ];
                            
                            if ($expiration) {
                                $urls['signed'] = MinioStorage::getUrl($path, $expiration, true);
                                $urls['signed_expiration'] = $expiration . ' seconds';
                            }
                            
                            $fileInfo['urls'] = $urls;
                        } catch (\Exception $e) {
                            $fileInfo['urls'] = null;
                            $fileInfo['urls_error'] = $e->getMessage();
                        }
                    }
                    
                    $results[] = $fileInfo;
                    $successCount++;

                } catch (\Exception $e) {
                    $results[] = [
                        'path' => $path,
                        'success' => false,
                        'message' => $e->getMessage(),
                        'metadata' => null,
                        'urls' => null
                    ];
                    $failedCount++;
                }
            }
            
            return response()->json([
                'success' => $failedCount === 0,
                'message' => "Batch file info retrieval completed. Success: $successCount, Failed: $failedCount",
                'summary' => [
                    'total' => count($paths),
                    'success' => $successCount,
                    'failed' => $failedCount,
                    'include_metadata' => $includeMetadata,
                    'include_urls' => $includeUrls,
                    'expiration' => $expiration ? $expiration . ' seconds' : 'No expiration',
                ],
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch file operations (check existence, get size, get type)
     */
    public function batchFileOperations(Request $request)
    {
        $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'required|string',
            'operations' => 'nullable|array',
            'operations.*' => 'nullable|in:exists,metadata,urls,delete_check',
        ]);

        try {
            $paths = $request->input('paths');
            $operations = $request->input('operations', ['exists', 'metadata', 'urls']);
            $results = [];
            $summary = [
                'total' => count($paths),
                'exists' => 0,
                'not_exists' => 0,
                'has_metadata' => 0,
                'has_urls' => 0,
                'deletable' => 0
            ];

            foreach ($paths as $path) {
                $fileResult = [
                    'path' => $path,
                    'exists' => false,
                    'metadata' => null,
                    'urls' => null,
                    'deletable' => false,
                    'errors' => []
                ];

                try {
                    // Check existence
                    if (in_array('exists', $operations)) {
                        $fileResult['exists'] = MinioStorage::fileExists($path);
                        if ($fileResult['exists']) {
                            $summary['exists']++;
                        } else {
                            $summary['not_exists']++;
                        }
                    }

                    // Only proceed with other operations if file exists
                    if ($fileResult['exists']) {
                        // Get metadata
                        if (in_array('metadata', $operations)) {
                            try {
                                $fileResult['metadata'] = MinioStorage::getMetadata($path);
                                $summary['has_metadata']++;
                            } catch (\Exception $e) {
                                $fileResult['errors'][] = 'Metadata: ' . $e->getMessage();
                            }
                        }

                        // Get URLs
                        if (in_array('urls', $operations)) {
                            try {
                                $fileResult['urls'] = [
                                    'public' => MinioStorage::getUrl($path, null, false),
                                                'signed_1h' => MinioStorage::getUrl($path, 3600, true),
            'signed_24h' => MinioStorage::getUrl($path, 86400, true)
                                ];
                                $summary['has_urls']++;
                            } catch (\Exception $e) {
                                $fileResult['errors'][] = 'URLs: ' . $e->getMessage();
                            }
                        }

                        // Check if deletable
                        if (in_array('delete_check', $operations)) {
                            $fileResult['deletable'] = true; // File exists, so it's deletable
                            $summary['deletable']++;
                        }
                    }

                } catch (\Exception $e) {
                    $fileResult['errors'][] = 'General: ' . $e->getMessage();
                }

                $results[] = $fileResult;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Batch file operations completed',
                'operations' => $operations,
                'summary' => $summary,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==============================================
    // ADVANCED EXAMPLES
    // ==============================================

    /**
     * Upload with all processing options
     */
    public function uploadWithAllOptions(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                '/img/full-processing/',
                [
                    'scan' => true,
                    'naming' => 'hash', // hash, slug, original
                    'compress' => true,
                    'image' => [
                        'resize' => [
                            'width' => 1024,
                            'height' => 768,
                            'method' => 'fit'
                        ],
                        'quality' => 85,
                        'format' => 'jpg',
                        'progressive' => true,
                        'auto_orient' => true,
                        'strip_metadata' => true,
                    ],
                    'thumbnail' => [
                        'width' => 200,
                        'height' => 200,
                        'method' => 'crop',
                        'quality' => 75,
                        'optimize' => true,
                    ],
                    'security' => [
                        'scan_images' => true,
                        'strict_mode' => true,
                        'quarantine_suspicious' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with full processing',
                'data' => $result,
                'processing' => [
                    'compression' => 'Applied',
                    'resize' => '1024x768 fit',
                    'thumbnail' => '200x200 crop',
                    'security_scan' => 'Passed',
                    'format' => 'Converted to JPG',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// ==============================================
// ROUTE DEFINITIONS (add to routes/web.php or routes/api.php)
// ==============================================

/*
// Basic uploads
Route::post('/upload/image', [MinioStorageController::class, 'uploadImage']);
Route::post('/upload/document', [MinioStorageController::class, 'uploadDocument']);
Route::post('/upload/video', [MinioStorageController::class, 'uploadVideo']);

// Advanced processing
Route::post('/upload/image/compress', [MinioStorageController::class, 'uploadImageWithCompression']);
Route::post('/upload/image/scan', [MinioStorageController::class, 'uploadImageWithScan']);
Route::post('/upload/image/crop-square', [MinioStorageController::class, 'uploadImageWithCropSquare']);
Route::post('/upload/image/thumbnail', [MinioStorageController::class, 'uploadImageWithThumbnail']);
Route::post('/upload/document/scan', [MinioStorageController::class, 'uploadDocumentWithScan']);
Route::post('/upload/video/compress', [MinioStorageController::class, 'uploadVideoWithCompression']);

// URL and file operations
Route::get('/file/url', [MinioStorageController::class, 'getFileUrl']);
Route::get('/file/info', [MinioStorageController::class, 'getFileInfo']);
Route::delete('/file/delete', [MinioStorageController::class, 'deleteFile']);

// Batch operations
Route::post('/upload/batch', [MinioStorageController::class, 'batchUpload']);
Route::post('/upload/all-options', [MinioStorageController::class, 'uploadWithAllOptions']);

// Batch file operations
Route::delete('/file/batch-delete', [MinioStorageController::class, 'batchDelete']);
Route::post('/file/batch-urls', [MinioStorageController::class, 'batchGetUrls']);
Route::post('/file/batch-info', [MinioStorageController::class, 'batchGetFileInfo']);
Route::post('/file/batch-operations', [MinioStorageController::class, 'batchFileOperations']);
*/

// ==============================================
// FRONTEND EXAMPLES (HTML/JavaScript)
// ==============================================

/*
<!-- Basic image upload form -->
<form id="imageUploadForm" enctype="multipart/form-data">
    <input type="file" name="file" accept="image/*" required>
    <button type="submit">Upload Image</button>
</form>

<script>
document.getElementById('imageUploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('/upload/image', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Upload successful:', result.data);
            // Handle success
        } else {
            console.error('Upload failed:', result.message);
            // Handle error
        }
    } catch (error) {
        console.error('Error:', error);
    }
});

// Batch delete example
async function batchDeleteFiles(filePaths) {
    try {
        const response = await fetch('/file/batch-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                paths: filePaths
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Batch delete successful:', result.summary);
            result.results.forEach(item => {
                console.log(`${item.path}: ${item.success ? 'Deleted' : 'Failed - ' + item.message}`);
            });
        } else {
            console.error('Batch delete failed:', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Batch get URLs example
async function batchGetUrls(filePaths, urlType = 'public', expiration = null) {
    try {
        const response = await fetch('/file/batch-urls', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                paths: filePaths,
                url_type: urlType,
                expiration: expiration
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Batch URL generation successful:', result.summary);
            result.results.forEach(item => {
                if (item.success) {
                    console.log(`${item.path}: ${item.url}`);
                } else {
                    console.log(`${item.path}: Failed - ${item.message}`);
                }
            });
        } else {
            console.error('Batch URL generation failed:', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Batch get file info example
async function batchGetFileInfo(filePaths, includeMetadata = true, includeUrls = true) {
    try {
        const response = await fetch('/file/batch-info', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                paths: filePaths,
                include_metadata: includeMetadata,
                include_urls: includeUrls,
                expiration: 3600 // 1 hour for signed URLs
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Batch file info retrieval successful:', result.summary);
            result.results.forEach(item => {
                if (item.success) {
                    console.log(`${item.path}:`, item);
                } else {
                    console.log(`${item.path}: Failed - ${item.message}`);
                }
            });
        } else {
            console.error('Batch file info retrieval failed:', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Example usage:
// batchDeleteFiles(['/img/upload/file1.jpg', '/img/upload/file2.jpg']);
// batchGetUrls(['/img/upload/file1.jpg', '/img/upload/file2.jpg'], 'signed', 3600);
// batchGetFileInfo(['/img/upload/file1.jpg', '/img/upload/file2.jpg']);
</script>
*/

// ==============================================
// FFMPEG INSTALLATION NOTES
// ==============================================

/*
For video processing features, you need FFmpeg installed:

Ubuntu/Debian:
    sudo apt-get update
    sudo apt-get install ffmpeg

CentOS/RHEL:
    sudo yum install ffmpeg
    # or for newer versions:
    sudo dnf install ffmpeg

macOS:
    brew install ffmpeg

Windows:
    Download from https://ffmpeg.org/download.html
    Add to PATH environment variable

Configuration in .env:
    FFMPEG_BINARIES=/usr/bin/ffmpeg
    FFPROBE_BINARIES=/usr/bin/ffprobe
    FFMPEG_TIMEOUT=3600
    FFMPEG_THREADS=12

Test FFmpeg installation:
    ffmpeg -version
*/

// ==============================================
// BATCH OPERATIONS DOCUMENTATION
// ==============================================

/*
BATCH DELETE FILES:
POST /file/batch-delete
{
    "paths": [
        "/img/upload/file1.jpg",
        "/img/upload/file2.jpg",
        "/doc/upload/document.pdf"
    ]
}

Response:
{
    "success": true,
    "message": "Batch delete completed. Success: 2, Failed: 1",
    "summary": {
        "total": 3,
        "success": 2,
        "failed": 1
    },
    "results": [
        {
            "path": "/img/upload/file1.jpg",
            "success": true,
            "message": "File deleted successfully"
        },
        {
            "path": "/img/upload/file2.jpg",
            "success": true,
            "message": "File deleted successfully"
        },
        {
            "path": "/doc/upload/document.pdf",
            "success": false,
            "message": "File not found"
        }
    ]
}

BATCH GET URLs:
POST /file/batch-urls
{
    "paths": [
        "/img/upload/file1.jpg",
        "/img/upload/file2.jpg"
    ],
    "url_type": "signed",
    "expiration": 3600
}

Response:
{
    "success": true,
    "message": "Batch URL generation completed. Success: 2, Failed: 0",
    "summary": {
        "total": 2,
        "success": 2,
        "failed": 0,
        "url_type": "signed",
        "expiration": "3600 seconds"
    },
    "results": [
        {
            "path": "/img/upload/file1.jpg",
            "success": true,
            "url": "https://minio.example.com/bucket/img/upload/file1.jpg?X-Amz-Algorithm=...",
            "url_type": "signed",
            "expiration": "3600 seconds"
        },
        {
            "path": "/img/upload/file2.jpg",
            "success": true,
            "url": "https://minio.example.com/bucket/img/upload/file2.jpg?X-Amz-Algorithm=...",
            "url_type": "signed",
            "expiration": "3600 seconds"
        }
    ]
}

BATCH GET FILE INFO:
POST /file/batch-info
{
    "paths": [
        "/img/upload/file1.jpg",
        "/img/upload/file2.jpg"
    ],
    "include_metadata": true,
    "include_urls": true,
    "expiration": 3600
}

Response:
{
    "success": true,
    "message": "Batch file info retrieval completed. Success: 2, Failed: 0",
    "summary": {
        "total": 2,
        "success": 2,
        "failed": 0,
        "include_metadata": true,
        "include_urls": true,
        "expiration": "3600 seconds"
    },
    "results": [
        {
            "path": "/img/upload/file1.jpg",
            "success": true,
            "message": "File information retrieved successfully",
            "metadata": {
                "path": "/img/upload/file1.jpg",
                "size": 245760,
                "mime_type": "image/jpeg",
                "last_modified": 1640995200,
                "width": 1920,
                "height": 1080
            },
            "urls": {
                "public": "https://minio.example.com/bucket/img/upload/file1.jpg",
                "signed": "https://minio.example.com/bucket/img/upload/file1.jpg?X-Amz-Algorithm=...",
                "signed_expiration": "3600 seconds"
            }
        }
    ]
}

BATCH FILE OPERATIONS:
POST /file/batch-operations
{
    "paths": [
        "/img/upload/file1.jpg",
        "/img/upload/file2.jpg"
    ],
    "operations": ["exists", "metadata", "urls", "delete_check"]
}

Response:
{
    "success": true,
    "message": "Batch file operations completed",
    "operations": ["exists", "metadata", "urls", "delete_check"],
    "summary": {
        "total": 2,
        "exists": 2,
        "not_exists": 0,
        "has_metadata": 2,
        "has_urls": 2,
        "deletable": 2
    },
    "results": [
        {
            "path": "/img/upload/file1.jpg",
            "exists": true,
            "metadata": { ... },
            "urls": {
                "public": "https://minio.example.com/bucket/img/upload/file1.jpg",
                "signed_1h": "https://minio.example.com/bucket/img/upload/file1.jpg?X-Amz-Algorithm=...",
                "signed_24h": "https://minio.example.com/bucket/img/upload/file1.jpg?X-Amz-Algorithm=..."
            },
            "deletable": true,
            "errors": []
        }
    ]
}

USAGE TIPS:
• Use batch operations for better performance when handling multiple files
• Batch operations return detailed success/failure info for each file
• Include error handling for partial failures in batch operations
• Consider using background jobs for large batch operations
• Cache URLs when possible to reduce API calls
• Set appropriate timeouts for batch operations
• Use appropriate chunk sizes for very large batches (recommended: 50-100 files per batch)
*/
