<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\Processors;

use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;
use Triginarsa\MinioStorageUtils\Tests\TestCase;

class VideoProcessorTest extends TestCase
{
    private VideoProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout' => 60,
            'ffmpeg.threads' => 4,
        ]);
    }

    public function testIsVideo(): void
    {
        $videoTypes = [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/3gp',
        ];

        foreach ($videoTypes as $mimeType) {
            $this->assertTrue($this->processor->isVideo($mimeType));
        }

        $nonVideoTypes = [
            'image/jpeg',
            'application/pdf',
            'audio/mp3',
            'text/plain',
        ];

        foreach ($nonVideoTypes as $mimeType) {
            $this->assertFalse($this->processor->isVideo($mimeType));
        }
    }

    public function testProcessVideoWithCompress(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'compress' => true,
            'quality' => 'medium',
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }

    public function testProcessVideoWithConvertToMp4(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'convert_to_mp4' => true,
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }

    public function testProcessVideoWithResize(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'resize' => ['width' => 640, 'height' => 480],
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }

    public function testProcessVideoWithWatermark(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        // Create a simple watermark image
        $watermarkPath = $this->testFilesPath . '/video-watermark.png';
        $watermark = imagecreate(100, 30);
        $white = imagecolorallocate($watermark, 255, 255, 255);
        $black = imagecolorallocate($watermark, 0, 0, 0);
        imagefill($watermark, 0, 0, $white);
        imagestring($watermark, 3, 10, 10, 'WATERMARK', $black);
        imagepng($watermark, $watermarkPath);
        imagedestroy($watermark);
        
        $options = [
            'watermark' => [
                'path' => $watermarkPath,
                'position' => 'top-right',
                'opacity' => 0.7,
            ],
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
        
        // Clean up
        unlink($watermarkPath);
    }

    public function testProcessVideoWithClip(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'clip' => [
                'start' => 0,
                'duration' => 10,
            ],
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }

    public function testProcessVideoWithMultipleOptions(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'convert_to_mp4' => true,
            'compress' => true,
            'quality' => 'high',
            'resize' => ['width' => 720, 'height' => 480],
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }

    public function testGenerateThumbnail(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $thumbnailContent = $this->processor->generateThumbnail($content, 5); // 5 seconds
        
        $this->assertNotEmpty($thumbnailContent);
        $this->assertNotEquals($content, $thumbnailContent);
    }

    public function testGetVideoInfo(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $info = $this->processor->getVideoInfo($content);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('duration', $info);
        $this->assertArrayHasKey('width', $info);
        $this->assertArrayHasKey('height', $info);
        $this->assertArrayHasKey('codec', $info);
        $this->assertArrayHasKey('bitrate', $info);
        $this->assertArrayHasKey('fps', $info);
        $this->assertArrayHasKey('file_size', $info);
    }

    public function testProcessInvalidVideo(): void
    {
        $invalidVideoPath = $this->testFilesPath . '/invalid.mp4';
        file_put_contents($invalidVideoPath, 'This is not a video');
        
        $this->expectException(\Exception::class);
        $this->processor->process($invalidVideoPath, $invalidVideoPath . '.out', []);
        
        unlink($invalidVideoPath);
    }

    public function testProcessEmptyContent(): void
    {
        $emptyVideoPath = $this->testFilesPath . '/empty.mp4';
        file_put_contents($emptyVideoPath, '');
        
        $this->expectException(\Exception::class);
        $this->processor->process($emptyVideoPath, $emptyVideoPath . '.out', []);
        
        unlink($emptyVideoPath);
    }

    public function testProcessVideoWithInvalidWatermark(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $outputPath = $this->testFilesPath . '/test_watermark_output.mp4';
        
        $options = [
            'watermark' => [
                'path' => '/nonexistent/watermark.png',
                'position' => 'center',
            ],
        ];

        $this->expectException(\Exception::class);
        $this->processor->process($videoPath, $outputPath, $options);
        
        if (file_exists($outputPath)) unlink($outputPath);
    }

    public function testProcessVideoWithInvalidDimensions(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $outputPath = $this->testFilesPath . '/test_dimensions_output.mp4';
        
        $options = [
            'resize' => ['width' => 0, 'height' => 0],
        ];

        $this->expectException(\Exception::class);
        $this->processor->process($videoPath, $outputPath, $options);
        
        if (file_exists($outputPath)) unlink($outputPath);
    }

    public function testProcessVideoWithInvalidClipDuration(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $outputPath = $this->testFilesPath . '/test_clip_output.mp4';
        
        $options = [
            'clip' => [
                'start' => -5,
                'duration' => 0,
            ],
        ];

        $this->expectException(\Exception::class);
        $this->processor->process($videoPath, $outputPath, $options);
        
        if (file_exists($outputPath)) unlink($outputPath);
    }

    public function testGenerateThumbnailWithInvalidTime(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $outputPath = $this->testFilesPath . '/test_thumb.jpg';
        
        $this->expectException(\Exception::class);
        $this->processor->createThumbnail($videoPath, $outputPath, ['time' => -5]);
        
        if (file_exists($outputPath)) unlink($outputPath);
    }

    public function testGetVideoInfoFromInvalidContent(): void
    {
        $invalidVideoPath = $this->testFilesPath . '/invalid_info.mp4';
        file_put_contents($invalidVideoPath, 'This is not a video');
        
        $this->expectException(\Exception::class);
        $this->processor->getVideoInfo($invalidVideoPath);
        
        unlink($invalidVideoPath);
    }

    public function testFFmpegNotInstalled(): void
    {
        $processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);

        $this->assertFalse($processor->isFFmpegAvailable());
        
        $this->expectException(\Triginarsa\MinioStorageUtils\Exceptions\UploadException::class);
        $this->expectExceptionMessage('FFmpeg is required for video processing');
        
        $processor->process('/tmp/dummy.mp4', '/tmp/output.mp4', []);
    }

    public function testFFmpegAvailabilityCheck(): void
    {
        // Test with valid processor (may or may not have FFmpeg)
        $available = $this->processor->isFFmpegAvailable();
        $this->assertIsBool($available);
        
        // Test with invalid FFmpeg path
        $invalidProcessor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);
        
        $this->assertFalse($invalidProcessor->isFFmpegAvailable());
    }

    public function testVideoProcessingWithoutFFmpeg(): void
    {
        $processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);

        // Video type detection should still work
        $this->assertTrue($processor->isVideo('video/mp4'));
        $this->assertFalse($processor->isVideo('image/jpeg'));
        
        // But processing should fail with informative error
        $this->expectException(\Triginarsa\MinioStorageUtils\Exceptions\UploadException::class);
        $this->expectExceptionMessage('FFmpeg is required for video processing but is not installed');
        
        $processor->process('/tmp/dummy.mp4', '/tmp/output.mp4', []);
    }

    public function testThumbnailCreationWithoutFFmpeg(): void
    {
        $processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);

        $this->expectException(\Triginarsa\MinioStorageUtils\Exceptions\UploadException::class);
        $this->expectExceptionMessage('FFmpeg is required for video thumbnail creation');
        
        $processor->createThumbnail('/tmp/dummy.mp4', '/tmp/thumb.jpg', []);
    }

    public function testVideoInfoWithoutFFmpeg(): void
    {
        $processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);

        $this->expectException(\Triginarsa\MinioStorageUtils\Exceptions\UploadException::class);
        $this->expectExceptionMessage('FFmpeg is required for video information retrieval');
        
        $processor->getVideoInfo('/tmp/dummy.mp4');
    }

    public function testConvertToMp4WithoutFFmpeg(): void
    {
        $processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);

        $this->expectException(\Triginarsa\MinioStorageUtils\Exceptions\UploadException::class);
        $this->expectExceptionMessage('FFmpeg is required for video conversion to MP4');
        
        $processor->convertToMp4('/tmp/dummy.avi', '/tmp/output.mp4');
    }

    public function testCompressVideoWithoutFFmpeg(): void
    {
        $processor = new VideoProcessor($this->logger, [
            'ffmpeg.binaries' => '/nonexistent/ffmpeg',
            'ffprobe.binaries' => '/nonexistent/ffprobe',
        ]);

        $this->expectException(\Triginarsa\MinioStorageUtils\Exceptions\UploadException::class);
        $this->expectExceptionMessage('FFmpeg is required for video compression');
        
        $processor->compressVideo('/tmp/dummy.mp4', '/tmp/output.mp4');
    }

    public function testProcessVideoWithCustomBitrate(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'bitrate' => '1000k',
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }

    public function testProcessVideoWithCustomFramerate(): void
    {
        $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'fps' => 30,
        ];

        $processedContent = $this->processor->process($content, $options);
        
        $this->assertNotEmpty($processedContent);
        $this->assertNotEquals($content, $processedContent);
    }
} 