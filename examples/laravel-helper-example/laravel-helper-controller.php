<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// If you keep the helpers in this package's examples folder during testing:
// require_once base_path('vendor/triginarsa/minio-storage-utils/examples/helper-minio.php');
// If you copy helpers into your app (recommended), e.g. app/Support/helper-minio.php:
// require_once base_path('app/Support/helper-minio.php');

class MinioHelperController extends Controller
{
    // Route sample:
    // Route::post('/minio/upload-simple', [MinioHelperController::class, 'uploadSimple']);
    public function uploadSimple(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $result = minio_upload_image_simple($request->file('image'));
        return response()->json($result);
    }

    // Route::post('/minio/upload-resize', [MinioHelperController::class, 'uploadResize']);
    public function uploadResize(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'width' => 'nullable|integer|min:1',
            'height' => 'nullable|integer|min:1',
            'quality' => 'nullable|integer|min:1|max:100',
            'convert' => 'nullable|string|in:jpg,jpeg,png,webp,avif,gif',
        ]);

        $width = (int) $request->input('width', 1024);
        $height = (int) $request->input('height', 768);
        $quality = (int) $request->input('quality', 85);
        $convert = $request->input('convert', 'jpg');

        $result = minio_upload_image_resize(
            $request->file('image'),
            '/img/',
            $width,
            $height,
            $convert,
            $quality
        );

        return response()->json($result);
    }

    // Route::post('/minio/upload-watermark', [MinioHelperController::class, 'uploadWatermark']);
    public function uploadWatermark(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'watermark_path' => 'nullable|string',
            'position' => 'nullable|string|in:top-left,top,top-right,left,center,right,bottom-left,bottom,bottom-right',
            'opacity' => 'nullable|integer|min:0|max:100',
        ]);

        $watermarkPath = $request->input('watermark_path');
        $position = $request->input('position', 'bottom-right');
        $opacity = (int) $request->input('opacity', 70);

        $result = minio_upload_image_watermark(
            $request->file('image'),
            '/img/',
            $watermarkPath,
            $position,
            $opacity
        );

        return response()->json($result);
    }

    // Route::post('/minio/upload-thumbnail', [MinioHelperController::class, 'uploadWithThumbnail']);
    public function uploadWithThumbnail(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
            'thumb_width' => 'nullable|integer|min:1',
            'thumb_height' => 'nullable|integer|min:1',
            'method' => 'nullable|string|in:fit,crop,proportional',
            'quality' => 'nullable|integer|min:1|max:100',
            'suffix' => 'nullable|string',
        ]);

        $thumbWidth = (int) $request->input('thumb_width', 200);
        $thumbHeight = (int) $request->input('thumb_height', 200);
        $method = $request->input('method', 'fit');
        $quality = (int) $request->input('quality', 75);
        $suffix = $request->input('suffix', '-thumb');

        $result = minio_upload_image_thumbnail(
            $request->file('image'),
            '/img/',
            $thumbWidth,
            $thumbHeight,
            $method,
            $quality,
            $suffix
        );

        return response()->json($result);
    }

    // Route::get('/minio/public-url', [MinioHelperController::class, 'publicUrl']);
    public function publicUrl(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'bucket' => 'nullable|string',
            'check_exists' => 'nullable|boolean',
            'fallback' => 'nullable|string',
        ]);

        $path = $request->string('path');
        $bucket = $request->input('bucket');
        $checkExists = filter_var($request->input('check_exists', true), FILTER_VALIDATE_BOOLEAN);
        $fallback = $request->input('fallback', 'img/default.png');

        $url = minio_get_public_url(
            $path,
            fn ($e, $p) => $fallback,
            $checkExists,
            $bucket
        );

        return response()->json(['url' => $url]);
    }

    // Route::delete('/minio/delete', [MinioHelperController::class, 'delete']);
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $deleted = minio_delete_image($request->string('path'));
        return response()->json([
            'success' => $deleted,
        ], $deleted ? 200 : 404);
    }
}


