<?php

// In your Laravel controller
use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Illuminate\Http\Request;

class VideoUploadController extends Controller
{
    public function uploadVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:102400', // 100MB max
        ]);

        try {
            // Simple video upload with conversion to MP4
            $result = MinioStorage::upload(
                $request->file('video'),
                null, // Auto-generate path
                [
                    'scan' => false, // Usually disabled for videos
                    'naming' => 'hash',
                    'video' => [
                        'format' => 'mp4',
                        'compression' => 'medium',
                        'quality' => 'medium', // Will resize to 720p if larger
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
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function compressVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:204800', // 200MB max
        ]);

        try {
            // High compression video upload
            $result = MinioStorage::upload(
                $request->file('video'),
                'compressed-videos/' . time() . '.mp4',
                [
                    'video' => [
                        'format' => 'mp4',
                        'compression' => 'slow', // Better compression
                        'video_bitrate' => 1000, // Lower bitrate for smaller files
                        'audio_bitrate' => 96,
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
                'message' => 'Video compressed and uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadWithWatermark(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:102400',
        ]);

        try {
            // Video upload with watermark
            $result = MinioStorage::upload(
                $request->file('video'),
                'watermarked-videos/' . time() . '.mp4',
                [
                    'video' => [
                        'format' => 'mp4',
                        'compression' => 'medium',
                        'watermark' => [
                            'path' => public_path('watermark.png'),
                            'position' => 'bottom-right'
                        ],
                        'quality' => 'high' // Maintain quality for watermarked videos
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
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

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
} 