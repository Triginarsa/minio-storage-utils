<?php

// Complete Laravel Image Processing Example
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageController extends Controller
{
    /**
     * Simple image upload with basic processing
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('image'),
                'uploads/images/',
                [
                    'naming' => 'slug',
                    'image' => [
                        'quality' => 80,
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
     * Upload with compress and thumbnail creation
     */
    public function uploadWithThumbnail(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            // Upload original with compression
            $mainResult = MinioStorage::upload(
                $request->file('image'),
                'uploads/main/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 85,           // Compress to 85%
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 1920,
                            'height' => 1080,
                            'method' => 'fit'        // Maintain aspect ratio
                        ]
                    ]
                ]
            );

            // Create thumbnail
            $thumbnailResult = MinioStorage::upload(
                $request->file('image'),
                'uploads/thumbnails/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 75,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 300,
                            'height' => 200,
                            'method' => 'crop'       // Crop to exact size
                        ]
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Image and thumbnail created successfully',
                'main_image' => $mainResult,
                'thumbnail' => $thumbnailResult
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload with multiple sizes (original, large, medium, small)
     */
    public function uploadMultipleSizes(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $results = [];

            // Original size (compressed)
            $results['original'] = MinioStorage::upload(
                $request->file('image'),
                'uploads/original/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 90,
                        'convert' => 'jpg'
                    ]
                ]
            );

            // Large size
            $results['large'] = MinioStorage::upload(
                $request->file('image'),
                'uploads/large/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 85,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 1200,
                            'height' => 800,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );

            // Medium size
            $results['medium'] = MinioStorage::upload(
                $request->file('image'),
                'uploads/medium/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 80,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 800,
                            'height' => 600,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );

            // Small size (thumbnail)
            $results['small'] = MinioStorage::upload(
                $request->file('image'),
                'uploads/small/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 75,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 200,
                            'height' => 150,
                            'method' => 'crop'
                        ]
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Multiple sizes created successfully',
                'images' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get image URL
     */
    public function getUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');
            $url = MinioStorage::getUrl($path); // Uses default (public URLs)

            return response()->json([
                'success' => true,
                'url' => $url,
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get temporary URL (expires in 1 hour)
     */
    public function getTempUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'expires' => 'integer|min:1|max:604800', // Max 7 days
        ]);

        try {
            $path = $request->input('path');
            $expires = $request->input('expires', 3600); // Default 1 hour

            $url = MinioStorage::getTempUrl($path, $expires);

            return response()->json([
                'success' => true,
                'temp_url' => $url,
                'expires_in' => $expires . ' seconds',
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete image
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');
            $deleted = MinioStorage::delete($path);

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
                'deleted' => $deleted,
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple images
     */
    public function deleteMultiple(Request $request)
    {
        $request->validate([
            'paths' => 'required|array',
            'paths.*' => 'required|string',
        ]);

        try {
            $paths = $request->input('paths');
            $results = [];

            foreach ($paths as $path) {
                $deleted = MinioStorage::delete($path);
                $results[] = [
                    'path' => $path,
                    'deleted' => $deleted
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Multiple images processed',
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
     * Check if file exists
     */
    public function exists(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');
            $exists = MinioStorage::exists($path);

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file info
     */
    public function getInfo(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $path = $request->input('path');
            $info = MinioStorage::getInfo($path);

            return response()->json([
                'success' => true,
                'info' => $info,
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete workflow: Upload -> Process -> Get URLs -> Clean up
     */
    public function completeWorkflow(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'create_thumbnail' => 'boolean',
            'cleanup_after' => 'integer|min:0|max:3600', // Cleanup after X seconds
        ]);

        try {
            $createThumbnail = $request->input('create_thumbnail', true);
            $cleanupAfter = $request->input('cleanup_after', 0);

            // Step 1: Upload main image
            $mainResult = MinioStorage::upload(
                $request->file('image'),
                'uploads/workflow/',
                [
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 85,
                        'convert' => 'jpg',
                        'resize' => [
                            'width' => 1024,
                            'height' => 768,
                            'method' => 'fit'
                        ]
                    ]
                ]
            );

            $workflow = [
                'main_image' => $mainResult,
                'main_url' => MinioStorage::getUrl($mainResult['main']['path']), // Uses default (public URLs)
                'temp_url' => MinioStorage::getTempUrl($mainResult['main']['path'], 3600),
            ];

            // Step 2: Create thumbnail if requested
            if ($createThumbnail) {
                $thumbnailResult = MinioStorage::upload(
                    $request->file('image'),
                    'uploads/workflow/thumbs/',
                    [
                        'naming' => 'hash',
                        'image' => [
                            'quality' => 75,
                            'convert' => 'jpg',
                            'resize' => [
                                'width' => 200,
                                'height' => 150,
                                'method' => 'crop'
                            ]
                        ]
                    ]
                );

                $workflow['thumbnail'] = $thumbnailResult;
                $workflow['thumbnail_url'] = MinioStorage::getUrl($thumbnailResult['main']['path']); // Uses default (public URLs)
            }

            // Step 3: Schedule cleanup if requested
            if ($cleanupAfter > 0) {
                // You can implement a job/queue for this
                $workflow['cleanup_scheduled'] = "Files will be cleaned up after {$cleanupAfter} seconds";
            }

            return response()->json([
                'success' => true,
                'message' => 'Complete workflow executed successfully',
                'workflow' => $workflow
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// Route examples for routes/web.php or routes/api.php:
/*
Route::prefix('images')->group(function () {
    Route::post('/upload', [ImageController::class, 'upload']);
    Route::post('/upload-thumbnail', [ImageController::class, 'uploadWithThumbnail']);
    Route::post('/upload-multiple-sizes', [ImageController::class, 'uploadMultipleSizes']);
    Route::post('/get-url', [ImageController::class, 'getUrl']);
    Route::post('/get-temp-url', [ImageController::class, 'getTempUrl']);
    Route::delete('/delete', [ImageController::class, 'delete']);
    Route::delete('/delete-multiple', [ImageController::class, 'deleteMultiple']);
    Route::get('/exists', [ImageController::class, 'exists']);
    Route::get('/info', [ImageController::class, 'getInfo']);
    Route::post('/complete-workflow', [ImageController::class, 'completeWorkflow']);
});
*/

// HTML Form Examples:
/*
<!-- Simple Upload Form -->
<form action="/images/upload" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Upload Image</button>
</form>

<!-- Upload with Thumbnail Form -->
<form action="/images/upload-thumbnail" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Upload with Thumbnail</button>
</form>

<!-- Multiple Sizes Form -->
<form action="/images/upload-multiple-sizes" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Create Multiple Sizes</button>
</form>

<!-- Get URL Form -->
<form action="/images/get-url" method="POST">
    @csrf
    <input type="text" name="path" placeholder="Image path" required>
    <button type="submit">Get URL</button>
</form>

<!-- Get Temporary URL Form -->
<form action="/images/get-temp-url" method="POST">
    @csrf
    <input type="text" name="path" placeholder="Image path" required>
    <input type="number" name="expires" placeholder="Expires in seconds" value="3600">
    <button type="submit">Get Temporary URL</button>
</form>

<!-- Delete Form -->
<form action="/images/delete" method="POST">
    @csrf
    @method('DELETE')
    <input type="text" name="path" placeholder="Image path" required>
    <button type="submit">Delete Image</button>
</form>

<!-- Complete Workflow Form -->
<form action="/images/complete-workflow" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <label>
        <input type="checkbox" name="create_thumbnail" value="1" checked>
        Create Thumbnail
    </label>
    <input type="number" name="cleanup_after" placeholder="Cleanup after (seconds)" value="0">
    <button type="submit">Run Complete Workflow</button>
</form>
*/

// JavaScript Examples for AJAX:
/*
// Upload image with progress
function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);

    fetch('/images/upload', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Upload successful:', data.data);
        } else {
            console.error('Upload failed:', data.message);
        }
    });
}

// Get image URL
function getImageUrl(path) {
    fetch('/images/get-url', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ path: path })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Image URL:', data.url);
        }
    });
}

// Delete image
function deleteImage(path) {
    fetch('/images/delete', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ path: path })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Image deleted:', data.deleted);
        }
    });
}
*/ 