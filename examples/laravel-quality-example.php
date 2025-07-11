<?php

// Laravel Quality Settings Example
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QualityExampleController extends Controller
{
    /**
     * Basic quality setting example
     */
    public function basicQuality(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/basic/',
                [
                    'image' => [
                        'quality' => 50,        // Set quality to 50%
                        'convert' => 'jpg'      // Convert to JPEG
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with quality 50',
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
     * Dynamic quality based on user input
     */
    public function dynamicQuality(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'quality' => 'required|integer|min:1|max:100',
        ]);

        try {
            $quality = $request->input('quality');
            
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/dynamic/',
                [
                    'naming' => 'slug',
                    'image' => [
                        'quality' => $quality,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 1024,
                            'height' => 768,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Image uploaded with quality {$quality}",
                'data' => $result,
                'quality_used' => $quality
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quality presets example
     */
    public function qualityPresets(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'preset' => 'required|string|in:low,medium,high,very_high,max',
        ]);

        try {
            $preset = $request->input('preset');
            
            $result = MinioStorage::upload(
                $request->file('image'),
                "uploads/presets/{$preset}/",
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality_preset' => $preset,  // Use quality preset
                        'convert' => 'jpg'
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Image uploaded with {$preset} quality preset",
                'data' => $result,
                'preset_used' => $preset
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Multiple quality versions (thumbnails with different qualities)
     */
    public function multipleQualityVersions(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $results = [];

            // High quality main image
            $mainResult = MinioStorage::upload(
                $request->file('image'),
                'uploads/multi/main/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 90,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 1920,
                            'height' => 1080,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );
            $results['main'] = $mainResult;

            // Medium quality thumbnail
            $thumbResult = MinioStorage::upload(
                $request->file('image'),
                'uploads/multi/thumb/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 60,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 400,
                            'height' => 300,
                            'method' => 'crop'
                        ]
                    ]
                ]
            );
            $results['thumbnail'] = $thumbResult;

            // Low quality preview
            $previewResult = MinioStorage::upload(
                $request->file('image'),
                'uploads/multi/preview/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 30,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 200,
                            'height' => 150,
                            'method' => 'crop'
                        ]
                    ]
                ]
            );
            $results['preview'] = $previewResult;

            return response()->json([
                'success' => true,
                'message' => 'Multiple quality versions created',
                'data' => $results,
                'sizes' => [
                    'main' => $mainResult['main']['size'] . ' bytes',
                    'thumbnail' => $thumbResult['main']['size'] . ' bytes',
                    'preview' => $previewResult['main']['size'] . ' bytes'
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
     * Quality based on file size
     */
    public function adaptiveQuality(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $file = $request->file('image');
            $fileSize = $file->getSize();

            // Adaptive quality based on file size
            if ($fileSize > 5000000) {        // > 5MB
                $quality = 60;
            } elseif ($fileSize > 2000000) {  // > 2MB
                $quality = 70;
            } elseif ($fileSize > 1000000) {  // > 1MB
                $quality = 80;
            } else {                          // < 1MB
                $quality = 90;
            }

            $result = MinioStorage::upload(
                $file,
                'uploads/adaptive/',
                [
                    'naming' => 'slug',
                    'image' => [
                        'quality' => $quality,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 1200,
                            'height' => 800,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Adaptive quality applied: {$quality}% (based on {$fileSize} bytes)",
                'data' => $result,
                'original_size' => $fileSize,
                'quality_used' => $quality,
                'final_size' => $result['main']['size']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quality comparison test
     */
    public function qualityComparison(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $qualities = [10, 25, 50, 75, 100];
            $results = [];

            foreach ($qualities as $quality) {
                $result = MinioStorage::upload(
                    $request->file('image'),
                    "uploads/comparison/q{$quality}/",
                    [
                        'naming' => 'original',
                        'image' => [
                            'quality' => $quality,
                            'convert' => 'jpg',
                            'resize' => [
                                'width' => 800,
                                'height' => 600,
                                'method' => 'fit'
                            ]
                        ]
                    ]
                );

                $results["quality_{$quality}"] = [
                    'quality' => $quality,
                    'size' => $result['main']['size'],
                    'size_kb' => round($result['main']['size'] / 1024, 2),
                    'url' => $result['main']['url'],
                    'path' => $result['main']['path']
                ];
            }

            // Calculate compression ratios
            $baseline = $results['quality_100']['size'];
            foreach ($results as $key => &$result) {
                $compressionRatio = round((1 - ($result['size'] / $baseline)) * 100, 2);
                $result['compression_ratio'] = $compressionRatio . '%';
            }

            return response()->json([
                'success' => true,
                'message' => 'Quality comparison completed',
                'results' => $results,
                'baseline_size' => $baseline . ' bytes',
                'note' => 'Check Laravel logs for detailed quality determination logs'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quality with format optimization
     */
    public function formatOptimizedQuality(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'format' => 'required|string|in:jpg,png,webp',
        ]);

        try {
            $format = $request->input('format');
            
            // Format-specific quality optimization
            $qualityMap = [
                'jpg' => 85,    // JPEG works well with 85
                'png' => 90,    // PNG needs higher quality
                'webp' => 80,   // WebP is efficient at 80
            ];

            $result = MinioStorage::upload(
                $request->file('image'),
                "uploads/format-optimized/{$format}/",
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => $qualityMap[$format],
                        'convert' => $format,
                        'resize' => [
                            'width' => 1024,
                            'height' => 768,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Image optimized for {$format} format",
                'data' => $result,
                'format' => $format,
                'optimized_quality' => $qualityMap[$format]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quality with logging for debugging
     */
    public function debugQuality(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'quality' => 'required|integer|min:1|max:100',
        ]);

        try {
            $quality = $request->input('quality');
            
            Log::info('Quality debug test started', [
                'requested_quality' => $quality,
                'file_size' => $request->file('image')->getSize(),
                'mime_type' => $request->file('image')->getMimeType()
            ]);

            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/debug/',
                [
                    'naming' => 'slug',
                    'image' => [
                        'quality' => $quality,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 600,
                            'height' => 400,
                            'method' => 'crop'
                        ]
                    ]
                ]
            );

            Log::info('Quality debug test completed', [
                'requested_quality' => $quality,
                'final_size' => $result['main']['size'],
                'compression_achieved' => round((1 - ($result['main']['size'] / $request->file('image')->getSize())) * 100, 2) . '%'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Quality debug test completed with quality {$quality}",
                'data' => $result,
                'debug_info' => [
                    'requested_quality' => $quality,
                    'original_size' => $request->file('image')->getSize(),
                    'final_size' => $result['main']['size'],
                    'log_message' => 'Check Laravel logs for detailed quality determination process'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Quality debug test failed', [
                'error' => $e->getMessage(),
                'requested_quality' => $request->input('quality')
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// Route examples for web.php or api.php:
/*
Route::post('/quality/basic', [QualityExampleController::class, 'basicQuality']);
Route::post('/quality/dynamic', [QualityExampleController::class, 'dynamicQuality']);
Route::post('/quality/presets', [QualityExampleController::class, 'qualityPresets']);
Route::post('/quality/multiple', [QualityExampleController::class, 'multipleQualityVersions']);
Route::post('/quality/adaptive', [QualityExampleController::class, 'adaptiveQuality']);
Route::post('/quality/comparison', [QualityExampleController::class, 'qualityComparison']);
Route::post('/quality/format-optimized', [QualityExampleController::class, 'formatOptimizedQuality']);
Route::post('/quality/debug', [QualityExampleController::class, 'debugQuality']);
*/

// HTML form examples:
/*
<!-- Basic Quality Form -->
<form action="/quality/basic" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Upload with Quality 50</button>
</form>

<!-- Dynamic Quality Form -->
<form action="/quality/dynamic" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <input type="range" name="quality" min="1" max="100" value="50">
    <span id="quality-value">50</span>
    <button type="submit">Upload with Custom Quality</button>
</form>

<!-- Quality Presets Form -->
<form action="/quality/presets" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <select name="preset" required>
        <option value="low">Low Quality</option>
        <option value="medium">Medium Quality</option>
        <option value="high">High Quality</option>
        <option value="very_high">Very High Quality</option>
        <option value="max">Maximum Quality</option>
    </select>
    <button type="submit">Upload with Preset</button>
</form>

<!-- Format Optimized Form -->
<form action="/quality/format-optimized" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <select name="format" required>
        <option value="jpg">JPEG</option>
        <option value="png">PNG</option>
        <option value="webp">WebP</option>
    </select>
    <button type="submit">Upload with Format Optimization</button>
</form>
*/ 