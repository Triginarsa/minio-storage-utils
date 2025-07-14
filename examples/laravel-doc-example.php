<?php

/**
 * Laravel Document Management Example
 * 
 * This example demonstrates how to handle document uploads, URL generation, 
 * and deletion for PDF, Excel, and PowerPoint files using MinIO Storage Services.
 */

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    /**
     * Upload a document (PDF, Excel, PowerPoint)
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'document' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx',
                'max:20480' // 20MB max
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('document');
            
            // Upload with security scanning
            $result = MinioStorage::upload(
                $file,
                '/documents/uploads/',
                [
                    'scan' => true,
                    'security' => [
                        'strict_mode' => true,
                        'scan_documents' => true,
                        'quarantine_suspicious' => true,
                    ]
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'path' => $result['main']['path'],
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $result['main']['size'],
                    'mime_type' => $result['main']['mime_type'],
                    'upload_time' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a presigned URL for document download
     */
    public function getDocumentUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'expiration' => 'nullable|integer|min:60|max:604800' // 1 minute to 7 days
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

            // Check if file exists
            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            // Get metadata
            $metadata = MinioStorage::getMetadata($path);
            
            // Generate presigned URL
            $url = MinioStorage::getUrl($path, $expiration, true);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $url,
                    'expires_in' => $expiration,
                    'expires_at' => now()->addSeconds($expiration)->toISOString(),
                    'document_info' => [
                        'path' => $path,
                        'size' => $metadata['size'],
                        'mime_type' => $metadata['mime_type'],
                        'last_modified' => date('c', $metadata['last_modified'])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a document
     */
    public function deleteDocument(Request $request): JsonResponse
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

            // Check if file exists before deletion
            if (!MinioStorage::fileExists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            // Get metadata before deletion for logging
            $metadata = MinioStorage::getMetadata($path);
            
            // Delete the file
            $deleted = MinioStorage::delete($path);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document deleted successfully',
                    'data' => [
                        'deleted_path' => $path,
                        'deleted_at' => now()->toISOString(),
                        'file_info' => [
                            'size' => $metadata['size'],
                            'mime_type' => $metadata['mime_type']
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document'
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
     * Get document information without downloading
     */
    public function getDocumentInfo(Request $request): JsonResponse
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
                    'message' => 'Document not found'
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
                    'file_type' => $this->getFileTypeFromMime($metadata['mime_type']),
                    'last_modified' => date('c', $metadata['last_modified']),
                    'visibility' => $metadata['visibility']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch upload multiple documents
     */
    public function batchUploadDocuments(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'documents' => 'required|array|max:5', // Max 5 files at once
            'documents.*' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx',
                'max:20480'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $results = [];
        $errors = [];

        foreach ($request->file('documents') as $index => $file) {
            try {
                $result = MinioStorage::upload(
                    $file,
                    '/documents/batch-uploads/',
                    [
                        'scan' => true,
                        'security' => [
                            'strict_mode' => true,
                            'scan_documents' => true,
                        ]
                    ]
                );

                $results[] = [
                    'index' => $index,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $result['main']['path'],
                    'size' => $result['main']['size'],
                    'mime_type' => $result['main']['mime_type'],
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
                'summary' => [
                    'total_files' => count($request->file('documents')),
                    'successful' => count($results),
                    'failed' => count($errors)
                ]
            ]
        ]);
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
     * Helper method to get file type from MIME type
     */
    private function getFileTypeFromMime(string $mimeType): string
    {
        $types = [
            'application/pdf' => 'PDF Document',
            'application/msword' => 'Word Document (Legacy)',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
            'application/vnd.ms-excel' => 'Excel Spreadsheet (Legacy)',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel Spreadsheet',
            'application/vnd.ms-powerpoint' => 'PowerPoint Presentation (Legacy)',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint Presentation',
        ];

        return $types[$mimeType] ?? 'Unknown Document Type';
    }
}

/**
 * ROUTES EXAMPLE (add to routes/web.php or routes/api.php):
 * 
 * Route::prefix('documents')->group(function () {
 *     Route::post('upload', [DocumentController::class, 'uploadDocument']);
 *     Route::post('url', [DocumentController::class, 'getDocumentUrl']);
 *     Route::delete('delete', [DocumentController::class, 'deleteDocument']);
 *     Route::get('info', [DocumentController::class, 'getDocumentInfo']);
 *     Route::post('batch-upload', [DocumentController::class, 'batchUploadDocuments']);
 * });
 */

/**
 * FRONTEND USAGE EXAMPLES:
 * 
 * 1. Upload a document:
 * POST /documents/upload
 * Content-Type: multipart/form-data
 * Body: document (file)
 * 
 * 2. Get download URL:
 * POST /documents/url
 * Content-Type: application/json
 * Body: {"path": "/documents/uploads/filename.pdf", "expiration": 3600}
 * 
 * 3. Delete document:
 * DELETE /documents/delete
 * Content-Type: application/json
 * Body: {"path": "/documents/uploads/filename.pdf"}
 * 
 * 4. Get document info:
 * GET /documents/info?path=/documents/uploads/filename.pdf
 * 
 * 5. Batch upload:
 * POST /documents/batch-upload
 * Content-Type: multipart/form-data
 * Body: documents[] (multiple files)
 */

/**
 * EXAMPLE JAVASCRIPT FRONTEND CODE:
 * 
 * // Upload document
 * const uploadDocument = async (file) => {
 *     const formData = new FormData();
 *     formData.append('document', file);
 *     
 *     const response = await fetch('/documents/upload', {
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
 * // Get download URL
 * const getDownloadUrl = async (path, expiration = 3600) => {
 *     const response = await fetch('/documents/url', {
 *         method: 'POST',
 *         headers: {
 *             'Content-Type': 'application/json',
 *             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
 *         },
 *         body: JSON.stringify({ path, expiration })
 *     });
 *     
 *     return response.json();
 * };
 * 
 * // Delete document
 * const deleteDocument = async (path) => {
 *     const response = await fetch('/documents/delete', {
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
