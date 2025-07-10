<?php

// In your Laravel controller
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        try {
            // Simple upload - destination path is auto-generated
            $result = MinioStorage::upload($request->file('file'));
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWithProcessing(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            // Upload with image processing and thumbnail
            $result = MinioStorage::upload(
                $request->file('image'),
                null, // Auto-generate path
                [
                    'scan' => true,
                    'naming' => 'hash',
                    'image' => [
                        'resize' => ['width' => 1024],
                        'convert' => 'jpg',
                        'quality' => 85,
                    ],
                    'thumbnail' => [
                        'width' => 200,
                        'height' => 200,
                        'method' => 'fit'
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWithCompression(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            // Upload with advanced compression
            $result = MinioStorage::upload(
                $request->file('image'),
                'compressed-images/',
                [
                    'compress' => true,
                    'compression_options' => [
                        'quality' => 75,
                        'format' => 'jpg',
                        'progressive' => true,
                    ],
                    'thumbnail' => [
                        'width' => 300,
                        'height' => 300,
                        'method' => 'fit',
                        'optimize' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'compression_info' => [
                    'original_size' => $request->file('image')->getSize(),
                    'compressed_size' => $result['main']['size'] ?? null,
                    'compression_ratio' => $this->calculateCompressionRatio(
                        $request->file('image')->getSize(),
                        $result['main']['size'] ?? 0
                    )
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWithTargetSize(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'target_size' => 'nullable|integer|min:10000|max:5000000', // 10KB to 5MB
        ]);

        try {
            $targetSize = $request->input('target_size', 500000); // Default 500KB
            
            // Upload with target size compression
            $result = MinioStorage::upload(
                $request->file('image'),
                'size-optimized/',
                [
                    'compress' => true,
                    'compression_options' => [
                        'target_size' => $targetSize,
                        'format' => 'jpg',
                        'max_quality' => 90,
                        'min_quality' => 60,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'target_size' => $targetSize,
                'actual_size' => $result['main']['size'] ?? null,
                'target_achieved' => ($result['main']['size'] ?? 0) <= $targetSize
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWithQualityPreset(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'quality_preset' => 'required|in:low,medium,high,very_high,max',
        ]);

        try {
            // Upload with quality preset
            $result = MinioStorage::upload(
                $request->file('image'),
                'preset-quality/',
                [
                    'compress' => true,
                    'compression_options' => [
                        'quality_preset' => $request->input('quality_preset'),
                        'format' => 'jpg',
                        'progressive' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'quality_preset' => $request->input('quality_preset')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWebOptimized(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            // Upload with web optimization
            $result = MinioStorage::upload(
                $request->file('image'),
                'web-optimized/',
                [
                    'optimize_for_web' => true,
                    'web_options' => [
                        'max_width' => 1920,
                        'max_height' => 1080,
                        'quality' => 85,
                        'format' => 'jpg',
                        'progressive' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'optimization' => 'web-optimized'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWithSmartCompression(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            // Upload with smart compression
            $result = MinioStorage::upload(
                $request->file('image'),
                'smart-compressed/',
                [
                    'optimize' => true,
                    'smart_compression' => true,
                    'image' => [
                        'max_width' => 2048,
                        'max_height' => 2048,
                        'auto_orient' => true,
                        'strip_metadata' => true,
                    ],
                    'thumbnail' => [
                        'width' => 300,
                        'height' => 300,
                        'method' => 'fit',
                        'optimize' => true,
                    ]
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'compression_type' => 'smart'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadMultipleFormats(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $formats = ['jpg', 'webp', 'png'];
            $results = [];
            
            foreach ($formats as $format) {
                $result = MinioStorage::upload(
                    $request->file('image'),
                    "multi-format/{$format}/",
                    [
                        'compress' => true,
                        'compression_options' => [
                            'quality' => $format === 'png' ? 90 : 80,
                            'format' => $format,
                            'progressive' => $format === 'jpg',
                        ]
                    ]
                );
                
                $results[$format] = $result;
            }
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'formats_created' => $formats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        try {
            // Upload document with security scanning
            $result = MinioStorage::upload(
                $request->file('document'),
                'documents/' . time() . '-' . $request->file('document')->getClientOriginalName(),
                [
                    'scan' => true, // Will scan for macros and suspicious content
                    'naming' => 'original'
                ]
            );
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getFileInfo($path)
    {
        try {
            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $metadata = MinioStorage::getMetadata($path);
            $url = MinioStorage::getUrl($path, 3600);

            return response()->json([
                'success' => true,
                'data' => array_merge($metadata, ['url' => $url])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteFile($path)
    {
        try {
            $deleted = MinioStorage::delete($path);
            
            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'File deleted successfully' : 'Failed to delete file'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate compression ratio percentage
     */
    private function calculateCompressionRatio(int $originalSize, int $compressedSize): string
    {
        if ($originalSize === 0) {
            return '0%';
        }
        
        $ratio = (1 - ($compressedSize / $originalSize)) * 100;
        return round($ratio, 2) . '%';
    }

    /**
     * Batch upload with different compression settings
     */
    public function batchUploadWithCompression(Request $request)
    {
        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|max:10240',
        ]);

        try {
            $compressionSettings = [
                'low' => ['quality_preset' => 'low', 'format' => 'jpg'],
                'medium' => ['quality_preset' => 'medium', 'format' => 'jpg'],
                'high' => ['quality_preset' => 'high', 'format' => 'jpg'],
                'web' => ['quality' => 85, 'format' => 'webp'],
            ];

            $results = [];
            
            foreach ($request->file('images') as $index => $image) {
                $batchResults = [];
                
                foreach ($compressionSettings as $level => $options) {
                    $result = MinioStorage::upload(
                        $image,
                        "batch-{$level}/",
                        [
                            'compress' => true,
                            'compression_options' => $options,
                        ]
                    );
                    
                    $batchResults[$level] = $result;
                }
                
                $results["image_{$index}"] = $batchResults;
            }
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'compression_levels' => array_keys($compressionSettings)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// Example API Routes (add to routes/api.php)
/*
Route::post('/upload', [FileUploadController::class, 'upload']);
Route::post('/upload/processing', [FileUploadController::class, 'uploadWithProcessing']);
Route::post('/upload/compression', [FileUploadController::class, 'uploadWithCompression']);
Route::post('/upload/target-size', [FileUploadController::class, 'uploadWithTargetSize']);
Route::post('/upload/quality-preset', [FileUploadController::class, 'uploadWithQualityPreset']);
Route::post('/upload/web-optimized', [FileUploadController::class, 'uploadWebOptimized']);
Route::post('/upload/smart-compression', [FileUploadController::class, 'uploadWithSmartCompression']);
Route::post('/upload/multiple-formats', [FileUploadController::class, 'uploadMultipleFormats']);
Route::post('/upload/batch-compression', [FileUploadController::class, 'batchUploadWithCompression']);
Route::post('/upload/document', [FileUploadController::class, 'uploadDocument']);
Route::get('/file/{path}', [FileUploadController::class, 'getFileInfo'])->where('path', '.*');
Route::delete('/file/{path}', [FileUploadController::class, 'deleteFile'])->where('path', '.*');
*/ 