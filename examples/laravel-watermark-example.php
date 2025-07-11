<?php

// Laravel Controller for Watermark Examples
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WatermarkController extends Controller
{
    /**
     * Basic watermark upload with auto-resize
     */
    public function uploadWithBasicWatermark(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
            'watermark' => 'required|image|max:2048', // 2MB max for watermark
        ]);

        try {
            // Store watermark temporarily
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/watermarked/',
                [
                    'image' => [
                        'quality' => 85,
                        'max_width' => 1920,
                        'max_height' => 1080,
                    ],
                    'watermark' => [
                        'path' => $watermarkPath,
                        'auto_resize' => true,
                        'resize_method' => 'proportional',
                        'size_ratio' => 0.15, // 15% of image size
                        'position' => 'bottom-right',
                        'opacity' => 70,
                        'margin' => 20,
                    ],
                    'thumbnail' => [
                        'width' => 300,
                        'height' => 300,
                        'method' => 'crop',
                        'quality' => 80,
                    ]
                ]
            );
            
            // Clean up temporary watermark
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with watermark successfully',
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
     * Upload with different watermark resize methods
     */
    public function uploadWithResizeMethods(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'watermark' => 'required|image|max:2048',
            'resize_method' => 'required|in:proportional,percentage,fixed',
        ]);

        try {
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            $resizeMethod = $request->input('resize_method');
            
            $watermarkConfig = [
                'path' => $watermarkPath,
                'resize_method' => $resizeMethod,
            ];

            // Configure based on resize method
            switch ($resizeMethod) {
                case 'proportional':
                    $watermarkConfig = array_merge($watermarkConfig, [
                        'size_ratio' => 0.2,
                        'min_size' => 50,
                        'max_size' => 300,
                    ]);
                    break;
                case 'percentage':
                    $watermarkConfig = array_merge($watermarkConfig, [
                        'size_ratio' => 0.1,
                        'min_size' => 40,
                        'max_size' => 200,
                    ]);
                    break;
                case 'fixed':
                    $watermarkConfig = array_merge($watermarkConfig, [
                        'width' => 150,
                        'height' => 150,
                    ]);
                    break;
            }

            $result = MinioStorage::upload(
                $request->file('image'),
                "uploads/watermarked/{$resizeMethod}/",
                [
                    'watermark' => $watermarkConfig,
                    'thumbnail' => [
                        'width' => 200,
                        'height' => 200,
                        'method' => 'fit',
                    ]
                ]
            );
            
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => "Image uploaded with {$resizeMethod} watermark resize",
                'data' => $result,
                'resize_method' => $resizeMethod
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload with different watermark positions
     */
    public function uploadWithPositions(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'watermark' => 'required|image|max:2048',
            'position' => 'required|in:top-left,top-right,bottom-left,bottom-right,center',
        ]);

        try {
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            $position = $request->input('position');
            
            $result = MinioStorage::upload(
                $request->file('image'),
                "uploads/watermarked/position-{$position}/",
                [
                    'watermark' => [
                        'path' => $watermarkPath,
                        'position' => $position,
                        'size_ratio' => 0.15,
                        'opacity' => 60,
                        'margin' => 15,
                    ],
                    'thumbnail' => [
                        'width' => 200,
                        'height' => 200,
                        'method' => 'crop',
                    ]
                ]
            );
            
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => "Image uploaded with watermark at {$position} position",
                'data' => $result,
                'position' => $position
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload high-quality images with watermarks
     */
    public function uploadHighQualityWithWatermark(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:20480', // 20MB for high-res
            'watermark' => 'required|image|max:2048',
        ]);

        try {
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/watermarked/high-quality/',
                [
                    'image' => [
                        'quality' => 95,
                        'max_width' => 3840,
                        'max_height' => 2160,
                        'optimize' => true,
                    ],
                    'watermark' => [
                        'path' => $watermarkPath,
                        'resize_method' => 'proportional',
                        'size_ratio' => 0.1, // Smaller ratio for high-res images
                        'min_size' => 100,
                        'max_size' => 500,
                        'position' => 'bottom-right',
                        'opacity' => 50,
                        'margin' => 50,
                    ],
                    'thumbnail' => [
                        'width' => 400,
                        'height' => 400,
                        'method' => 'fit',
                        'quality' => 85,
                    ]
                ]
            );
            
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => 'High-quality image uploaded with watermark',
                'data' => $result,
                'compression_info' => [
                    'original_size' => $request->file('image')->getSize(),
                    'processed_size' => $result['main']['size'] ?? null,
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
     * Batch upload with watermarks
     */
    public function batchUploadWithWatermarks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|max:10240',
            'watermark' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            $results = [];
            $errors = [];

            foreach ($request->file('images') as $index => $image) {
                try {
                    $result = MinioStorage::upload(
                        $image,
                        "uploads/watermarked/batch/image-{$index}/",
                        [
                            'image' => [
                                'quality' => 85,
                                'max_width' => 1920,
                                'max_height' => 1080,
                            ],
                            'watermark' => [
                                'path' => $watermarkPath,
                                'auto_resize' => true,
                                'resize_method' => 'proportional',
                                'size_ratio' => 0.12,
                                'position' => 'bottom-right',
                                'opacity' => 65,
                            ],
                            'thumbnail' => [
                                'width' => 250,
                                'height' => 250,
                                'method' => 'crop',
                            ]
                        ]
                    );
                    
                    $results[$index] = $result;
                    
                } catch (\Exception $e) {
                    $errors[$index] = $e->getMessage();
                }
            }
            
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => 'Batch upload completed',
                'data' => $results,
                'errors' => $errors,
                'total_images' => count($request->file('images')),
                'successful_uploads' => count($results),
                'failed_uploads' => count($errors)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload with watermark and compression
     */
    public function uploadWithWatermarkAndCompression(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'watermark' => 'required|image|max:2048',
            'target_size' => 'nullable|integer|min:50000|max:2000000', // 50KB to 2MB
        ]);

        try {
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            $targetSize = $request->input('target_size', 500000); // Default 500KB
            
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/watermarked/compressed/',
                [
                    'compress' => true,
                    'compression_options' => [
                        'quality' => 75,
                        'target_size' => $targetSize,
                    ],
                    'watermark' => [
                        'path' => $watermarkPath,
                        'resize_method' => 'proportional',
                        'size_ratio' => 0.15,
                        'position' => 'bottom-right',
                        'opacity' => 70,
                    ],
                    'thumbnail' => [
                        'width' => 200,
                        'height' => 200,
                        'method' => 'crop',
                        'quality' => 70,
                    ]
                ]
            );
            
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with watermark and compression',
                'data' => $result,
                'compression_info' => [
                    'original_size' => $request->file('image')->getSize(),
                    'target_size' => $targetSize,
                    'actual_size' => $result['main']['size'] ?? null,
                    'target_achieved' => ($result['main']['size'] ?? 0) <= $targetSize,
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

    /**
     * Upload with custom watermark options
     */
    public function uploadWithCustomWatermarkOptions(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'watermark' => 'required|image|max:2048',
            'opacity' => 'nullable|integer|min:10|max:100',
            'size_ratio' => 'nullable|numeric|min:0.05|max:0.5',
            'margin' => 'nullable|integer|min:0|max:100',
        ]);

        try {
            $watermarkPath = $this->storeWatermarkTemporarily($request->file('watermark'));
            
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/watermarked/custom/',
                [
                    'image' => [
                        'quality' => 85,
                        'max_width' => 1920,
                        'max_height' => 1080,
                    ],
                    'watermark' => [
                        'path' => $watermarkPath,
                        'auto_resize' => true,
                        'resize_method' => 'proportional',
                        'size_ratio' => $request->input('size_ratio', 0.15),
                        'position' => 'bottom-right',
                        'opacity' => $request->input('opacity', 70),
                        'margin' => $request->input('margin', 20),
                    ],
                    'thumbnail' => [
                        'width' => 300,
                        'height' => 300,
                        'method' => 'crop',
                        'quality' => 80,
                    ]
                ]
            );
            
            $this->cleanupTemporaryFile($watermarkPath);
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded with custom watermark options',
                'data' => $result,
                'watermark_options' => [
                    'opacity' => $request->input('opacity', 70),
                    'size_ratio' => $request->input('size_ratio', 0.15),
                    'margin' => $request->input('margin', 20),
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
     * Store watermark file temporarily
     */
    private function storeWatermarkTemporarily($watermarkFile): string
    {
        $fileName = 'watermark_' . time() . '_' . uniqid() . '.' . $watermarkFile->getClientOriginalExtension();
        $path = storage_path('app/temp/' . $fileName);
        
        // Ensure temp directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $watermarkFile->move(dirname($path), $fileName);
        
        return $path;
    }

    /**
     * Clean up temporary file
     */
    private function cleanupTemporaryFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Calculate compression ratio
     */
    private function calculateCompressionRatio(int $originalSize, int $compressedSize): string
    {
        if ($originalSize === 0) {
            return '0%';
        }
        
        $ratio = (($originalSize - $compressedSize) / $originalSize) * 100;
        return round($ratio, 2) . '%';
    }
}

// Routes for the watermark controller (add to routes/web.php or routes/api.php)
/*
Route::post('/watermark/basic', [WatermarkController::class, 'uploadWithBasicWatermark']);
Route::post('/watermark/resize-methods', [WatermarkController::class, 'uploadWithResizeMethods']);
Route::post('/watermark/positions', [WatermarkController::class, 'uploadWithPositions']);
Route::post('/watermark/high-quality', [WatermarkController::class, 'uploadHighQualityWithWatermark']);
Route::post('/watermark/batch', [WatermarkController::class, 'batchUploadWithWatermarks']);
Route::post('/watermark/compressed', [WatermarkController::class, 'uploadWithWatermarkAndCompression']);
Route::post('/watermark/custom', [WatermarkController::class, 'uploadWithCustomWatermarkOptions']);
*/

// Blade view example for watermark upload form (resources/views/watermark-upload.blade.php)
/*
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Upload Image with Watermark</div>
                <div class="card-body">
                    <form id="watermarkForm" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Main Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="watermark" class="form-label">Watermark Image</label>
                            <input type="file" class="form-control" id="watermark" name="watermark" accept="image/*" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="position" class="form-label">Watermark Position</label>
                            <select class="form-control" id="position" name="position">
                                <option value="bottom-right">Bottom Right</option>
                                <option value="bottom-left">Bottom Left</option>
                                <option value="top-right">Top Right</option>
                                <option value="top-left">Top Left</option>
                                <option value="center">Center</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="opacity" class="form-label">Opacity (10-100)</label>
                            <input type="range" class="form-range" id="opacity" name="opacity" min="10" max="100" value="70">
                            <span id="opacityValue">70</span>%
                        </div>
                        
                        <div class="mb-3">
                            <label for="size_ratio" class="form-label">Size Ratio (0.05-0.5)</label>
                            <input type="range" class="form-range" id="size_ratio" name="size_ratio" min="0.05" max="0.5" step="0.01" value="0.15">
                            <span id="sizeRatioValue">0.15</span>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Upload with Watermark</button>
                    </form>
                    
                    <div id="result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const opacitySlider = document.getElementById('opacity');
    const opacityValue = document.getElementById('opacityValue');
    const sizeRatioSlider = document.getElementById('size_ratio');
    const sizeRatioValue = document.getElementById('sizeRatioValue');
    
    opacitySlider.addEventListener('input', function() {
        opacityValue.textContent = this.value;
    });
    
    sizeRatioSlider.addEventListener('input', function() {
        sizeRatioValue.textContent = this.value;
    });
    
    document.getElementById('watermarkForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const resultDiv = document.getElementById('result');
        
        fetch('/watermark/custom', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h5>Upload Successful!</h5>
                        <p><strong>Main Image:</strong> <a href="${data.data.main.url}" target="_blank">${data.data.main.url}</a></p>
                        <p><strong>Thumbnail:</strong> <a href="${data.data.thumbnail.url}" target="_blank">${data.data.thumbnail.url}</a></p>
                        <p><strong>File Size:</strong> ${data.data.main.size} bytes</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Upload Failed!</h5>
                        <p>${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h5>Error!</h5>
                    <p>An error occurred during upload.</p>
                </div>
            `;
        });
    });
});
</script>
@endsection
*/ 