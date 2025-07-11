<?php

// Security Testing Example for Laravel
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityTestController extends Controller
{
    /**
     * Test basic security scanning
     */
    public function testBasicSecurity(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            // Test with security scanning enabled (default)
            $result = MinioStorage::upload(
                $request->file('file'),
                'security-tests/basic/',
                [
                    'scan' => true,  // Enable security scanning
                    'naming' => 'slug'
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'File passed security scan',
                'data' => $result
            ]);

        } catch (SecurityException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Security threat detected',
                'error' => $e->getMessage(),
                'threat_info' => $e->getContext()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test image-specific security scanning
     */
    public function testImageSecurity(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            // Test with enhanced image security scanning
            $result = MinioStorage::upload(
                $request->file('image'),
                'security-tests/images/',
                [
                    'scan' => true,
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 85,
                        'convert' => 'jpg',
                        'strip_metadata' => true,  // Remove potentially malicious EXIF data
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
                'message' => 'Image passed security scan',
                'data' => $result,
                'security_info' => [
                    'original_scanned' => true,
                    'processed_scanned' => true,
                    'metadata_stripped' => true
                ]
            ]);

        } catch (SecurityException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Security threat detected in image',
                'error' => $e->getMessage(),
                'threat_info' => $e->getContext()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test strict security mode
     */
    public function testStrictSecurity(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            // Test with strict security mode
            $result = MinioStorage::upload(
                $request->file('file'),
                'security-tests/strict/',
                [
                    'scan' => true,
                    'naming' => 'hash',
                    'strict_security' => true,  // Enable strict mode
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'File passed strict security scan',
                'data' => $result
            ]);

        } catch (SecurityException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Strict security check failed',
                'error' => $e->getMessage(),
                'threat_info' => $e->getContext()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test security bypass (for demonstration)
     */
    public function testSecurityBypass(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            // Test with security scanning disabled
            $result = MinioStorage::upload(
                $request->file('file'),
                'security-tests/bypass/',
                [
                    'scan' => false,  // Disable security scanning
                    'naming' => 'original'
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'File uploaded without security scan',
                'data' => $result,
                'warning' => 'Security scanning was disabled - file not checked for threats'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test polyglot file detection
     */
    public function testPolyglotDetection(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('file'),
                'security-tests/polyglot/',
                [
                    'scan' => true,
                    'naming' => 'slug'
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'File is not a polyglot threat',
                'data' => $result
            ]);

        } catch (SecurityException $e) {
            $context = $e->getContext();
            
            return response()->json([
                'success' => false,
                'message' => 'Polyglot file detected',
                'error' => $e->getMessage(),
                'threat_info' => $context,
                'detected_type' => $context['detected_type'] ?? 'unknown'
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test EXIF data scanning
     */
    public function testExifSecurity(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            $result = MinioStorage::upload(
                $request->file('image'),
                'security-tests/exif/',
                [
                    'scan' => true,
                    'naming' => 'hash',
                    'image' => [
                        'quality' => 85,
                        'convert' => 'jpg',
                        'strip_metadata' => false,  // Keep EXIF data for testing
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Image EXIF data is clean',
                'data' => $result
            ]);

        } catch (SecurityException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Malicious EXIF data detected',
                'error' => $e->getMessage(),
                'threat_info' => $e->getContext()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test security with detailed logging
     */
    public function testSecurityWithLogging(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            Log::info('Security test started', [
                'filename' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
                'mime_type' => $request->file('file')->getMimeType()
            ]);

            $result = MinioStorage::upload(
                $request->file('file'),
                'security-tests/logged/',
                [
                    'scan' => true,
                    'naming' => 'slug'
                ]
            );

            Log::info('Security test completed successfully', [
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File passed security scan with logging',
                'data' => $result,
                'note' => 'Check Laravel logs for detailed security scan information'
            ]);

        } catch (SecurityException $e) {
            Log::error('Security threat detected', [
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Security threat detected',
                'error' => $e->getMessage(),
                'threat_info' => $e->getContext(),
                'note' => 'Threat details logged to Laravel logs'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Security test failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get security scan report
     */
    public function getSecurityReport(Request $request)
    {
        try {
            $report = [
                'security_features' => [
                    'image_scanning' => config('minio-storage.security.scan_images', true),
                    'document_scanning' => config('minio-storage.security.scan_documents', true),
                    'strict_mode' => config('minio-storage.security.strict_mode', false),
                    'allow_svg' => config('minio-storage.security.allow_svg', false),
                    'max_file_size' => config('minio-storage.security.max_file_size', 10485760),
                ],
                'threat_detection' => [
                    'php_code_injection' => true,
                    'script_injection' => true,
                    'polyglot_files' => true,
                    'exif_malicious_data' => true,
                    'image_end_marker_bypass' => true,
                    'svg_script_injection' => true,
                    'obfuscated_code' => true,
                    'file_system_functions' => true,
                    'network_functions' => true,
                ],
                'scan_coverage' => [
                    'original_files' => true,
                    'processed_images' => true,
                    'thumbnail_generation' => true,
                    'format_conversion' => true,
                ],
                'recommendations' => [
                    'Enable image scanning' => config('minio-storage.security.scan_images', true) ? 'Enabled' : 'Disabled',
                    'Strip metadata' => 'Recommended for security',
                    'Use strict mode' => config('minio-storage.security.strict_mode', false) ? 'Enabled' : 'Consider enabling',
                    'Limit file sizes' => 'Configure max_file_size appropriately',
                    'Monitor logs' => 'Check Laravel logs for security events',
                ]
            ];

            return response()->json([
                'success' => true,
                'security_report' => $report
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

// Route examples for web.php or api.php:
/*
Route::post('/security/basic', [SecurityTestController::class, 'testBasicSecurity']);
Route::post('/security/image', [SecurityTestController::class, 'testImageSecurity']);
Route::post('/security/strict', [SecurityTestController::class, 'testStrictSecurity']);
Route::post('/security/bypass', [SecurityTestController::class, 'testSecurityBypass']);
Route::post('/security/polyglot', [SecurityTestController::class, 'testPolyglotDetection']);
Route::post('/security/exif', [SecurityTestController::class, 'testExifSecurity']);
Route::post('/security/logging', [SecurityTestController::class, 'testSecurityWithLogging']);
Route::get('/security/report', [SecurityTestController::class, 'getSecurityReport']);
*/

// HTML form examples:
/*
<!-- Basic Security Test Form -->
<form action="/security/basic" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <button type="submit">Test Security Scan</button>
</form>

<!-- Image Security Test Form -->
<form action="/security/image" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Test Image Security</button>
</form>

<!-- Strict Security Test Form -->
<form action="/security/strict" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <button type="submit">Test Strict Security</button>
</form>

<!-- Security Bypass Test Form -->
<form action="/security/bypass" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <button type="submit">Upload Without Security Scan</button>
</form>
*/ 