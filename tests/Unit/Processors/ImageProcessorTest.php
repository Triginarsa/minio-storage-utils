<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\Processors;

use Triginarsa\MinioStorageUtils\Tests\TestCase;
use Triginarsa\MinioStorageUtils\Processors\ImageProcessor;
use Psr\Log\NullLogger;

class ImageProcessorTest extends TestCase
{
    private ImageProcessor $processor;
    private string $testImageContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ImageProcessor(new NullLogger());
        $this->testImageContent = $this->generateTestImageContent();
    }

    public function testBasicImageProcessing()
    {
        $options = [
            'quality' => 85,
            'max_width' => 800,
            'max_height' => 600,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testImageCompression()
    {
        $originalSize = strlen($this->testImageContent);
        
        $options = [
            'quality' => 60,
            'format' => 'jpg',
        ];

        $result = $this->processor->compressImage($this->testImageContent, $options);
        $compressedSize = strlen($result);
        
        $this->assertNotEmpty($result);
        $this->assertLessThan($originalSize, $compressedSize);
    }

    public function testTargetSizeCompression()
    {
        $targetSize = 50000; // 50KB
        
        $options = [
            'target_size' => $targetSize,
            'format' => 'jpg',
            'max_quality' => 90,
            'min_quality' => 50,
        ];

        $result = $this->processor->compressImage($this->testImageContent, $options);
        $finalSize = strlen($result);
        
        $this->assertNotEmpty($result);
        $this->assertLessThanOrEqual($targetSize * 1.1, $finalSize); // Allow 10% tolerance
    }

    public function testQualityPresetCompression()
    {
        $presets = ['low', 'medium', 'high', 'very_high', 'max'];
        
        foreach ($presets as $preset) {
            $options = [
                'quality_preset' => $preset,
                'format' => 'jpg',
            ];

            $result = $this->processor->compressImage($this->testImageContent, $options);
            
            $this->assertNotEmpty($result);
            $this->assertIsString($result);
        }
    }

    public function testWebOptimization()
    {
        $options = [
            'max_width' => 1920,
            'max_height' => 1080,
            'quality' => 85,
            'format' => 'jpg',
            'progressive' => true,
        ];

        $result = $this->processor->optimizeForWeb($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testSmartCompression()
    {
        $options = [
            'optimize' => true,
            'smart_compression' => true,
            'quality' => 80,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testFormatConversion()
    {
        $formats = ['jpg', 'png', 'webp'];
        
        foreach ($formats as $format) {
            $options = [
                'format' => $format,
                'quality' => 85,
            ];

            $result = $this->processor->process($this->testImageContent, $options);
            
            $this->assertNotEmpty($result);
            $this->assertIsString($result);
        }
    }

    public function testProgressiveJpegOption()
    {
        $options = [
            'format' => 'jpg',
            'quality' => 85,
            'progressive' => true,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testOptimizationSettings()
    {
        $options = [
            'optimize' => true,
            'quality' => 80,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testThumbnailCreation()
    {
        $options = [
            'width' => 150,
            'height' => 150,
            'method' => 'fit',
            'quality' => 75,
            'format' => 'jpg',
        ];

        $result = $this->processor->createThumbnail($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testThumbnailMethods()
    {
        $methods = ['fit', 'crop', 'resize'];
        
        foreach ($methods as $method) {
            $options = [
                'width' => 200,
                'height' => 200,
                'method' => $method,
                'quality' => 75,
            ];

            $result = $this->processor->createThumbnail($this->testImageContent, $options);
            
            $this->assertNotEmpty($result);
            $this->assertIsString($result);
        }
    }

    public function testImageInfoRetrieval()
    {
        $info = $this->processor->getImageInfo($this->testImageContent);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('width', $info);
        $this->assertArrayHasKey('height', $info);
        $this->assertArrayHasKey('aspect_ratio', $info);
        $this->assertArrayHasKey('file_size', $info);
        $this->assertArrayHasKey('megapixels', $info);
        
        $this->assertIsInt($info['width']);
        $this->assertIsInt($info['height']);
        $this->assertIsFloat($info['aspect_ratio']);
        $this->assertIsInt($info['file_size']);
        $this->assertIsFloat($info['megapixels']);
    }

    public function testImageMimeTypeDetection()
    {
        $this->assertTrue($this->processor->isImage('image/jpeg'));
        $this->assertTrue($this->processor->isImage('image/png'));
        $this->assertTrue($this->processor->isImage('image/gif'));
        $this->assertTrue($this->processor->isImage('image/webp'));
        $this->assertFalse($this->processor->isImage('text/plain'));
        $this->assertFalse($this->processor->isImage('application/pdf'));
    }

    public function testAutoOrientationOption()
    {
        $options = [
            'auto_orient' => true,
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testMetadataStripping()
    {
        $options = [
            'strip_metadata' => true,
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testImageResizing()
    {
        $options = [
            'resize' => [
                'width' => 400,
                'height' => 300,
            ],
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testResizeWithFitMethod()
    {
        $options = [
            'resize' => [
                'width' => 400,
                'height' => 300,
                'method' => 'fit',
            ],
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testResizeWithCropMethod()
    {
        $options = [
            'resize' => [
                'width' => 400,
                'height' => 300,
                'method' => 'crop',
            ],
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testResizeWithStretchMethod()
    {
        $options = [
            'resize' => [
                'width' => 400,
                'height' => 300,
                'method' => 'stretch',
            ],
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testResizeWithAllMethods()
    {
        $methods = ['fit', 'crop', 'fill', 'stretch', 'proportional', 'scale'];
        
        foreach ($methods as $method) {
            $options = [
                'resize' => [
                    'width' => 400,
                    'height' => 300,
                    'method' => $method,
                ],
                'quality' => 85,
            ];

            $result = $this->processor->process($this->testImageContent, $options);
            
            $this->assertNotEmpty($result, "Failed for method: {$method}");
            $this->assertIsString($result, "Failed for method: {$method}");
        }
    }

    public function testMaxDimensionsConstraint()
    {
        $options = [
            'max_width' => 1000,
            'max_height' => 800,
            'quality' => 85,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testCompressionRatioCalculation()
    {
        $originalSize = strlen($this->testImageContent);
        
        $options = [
            'quality' => 50,
            'format' => 'jpg',
        ];

        $result = $this->processor->compressImage($this->testImageContent, $options);
        $compressedSize = strlen($result);
        
        $compressionRatio = (1 - ($compressedSize / $originalSize)) * 100;
        
        $this->assertGreaterThan(0, $compressionRatio);
        $this->assertLessThan(100, $compressionRatio);
    }

    public function testMultipleQualityLevels()
    {
        $qualities = [30, 50, 70, 85, 95];
        $previousSize = 0;
        
        foreach ($qualities as $quality) {
            $options = [
                'quality' => $quality,
                'format' => 'jpg',
            ];

            $result = $this->processor->compressImage($this->testImageContent, $options);
            $currentSize = strlen($result);
            
            $this->assertNotEmpty($result);
            
            // Higher quality should generally result in larger file size
            if ($previousSize > 0) {
                $this->assertGreaterThanOrEqual($previousSize * 0.8, $currentSize); // Allow some variance
            }
            
            $previousSize = $currentSize;
        }
    }

    public function testInvalidQualityHandling()
    {
        // Test quality bounds
        $options = [
            'quality' => 150, // Should be clamped to 100
            'format' => 'jpg',
        ];

        $result = $this->processor->compressImage($this->testImageContent, $options);
        $this->assertNotEmpty($result);

        $options = [
            'quality' => -10, // Should be clamped to 1
            'format' => 'jpg',
        ];

        $result = $this->processor->compressImage($this->testImageContent, $options);
        $this->assertNotEmpty($result);
    }

    public function testFormatSpecificSettings()
    {
        // Test JPEG with progressive
        $options = [
            'format' => 'jpg',
            'quality' => 85,
            'progressive' => true,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        $this->assertNotEmpty($result);

        // Test WebP
        $options = [
            'format' => 'webp',
            'quality' => 80,
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        $this->assertNotEmpty($result);
    }

    private function generateTestImageContent(): string
    {
        // Create a simple test image
        $image = imagecreate(200, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $red = imagecolorallocate($image, 255, 0, 0);
        
        imagefill($image, 0, 0, $white);
        imagestring($image, 5, 50, 50, 'TEST IMAGE', $black);
        imagerectangle($image, 10, 10, 190, 190, $red);
        
        ob_start();
        imagejpeg($image, null, 90);
        $content = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        return $content;
    }

    public function testWatermarkWithMetadata()
    {
        // Create a simple watermark image
        $watermarkPath = sys_get_temp_dir() . '/test_watermark.png';
        $watermark = imagecreate(100, 30);
        $white = imagecolorallocate($watermark, 255, 255, 255);
        $black = imagecolorallocate($watermark, 0, 0, 0);
        imagefill($watermark, 0, 0, $white);
        imagestring($watermark, 3, 10, 10, 'TEST', $black);
        imagepng($watermark, $watermarkPath);
        imagedestroy($watermark);

        $options = [
            'quality' => 85,
            'watermark' => [
                'path' => $watermarkPath,
                'position' => 'bottom-right',
                'opacity' => 70,
                'size_ratio' => 0.15,
            ]
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        // Should return array with content and metadata when watermark is applied
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        // Check watermark metadata
        $metadata = $result['metadata'];
        $this->assertArrayHasKey('watermark', $metadata);
        
        $watermarkMeta = $metadata['watermark'];
        $this->assertArrayHasKey('watermark_applied', $watermarkMeta);
        $this->assertArrayHasKey('watermark_path', $watermarkMeta);
        $this->assertArrayHasKey('watermark_filename', $watermarkMeta);
        $this->assertArrayHasKey('position', $watermarkMeta);
        $this->assertArrayHasKey('opacity', $watermarkMeta);
        
        $this->assertTrue($watermarkMeta['watermark_applied']);
        $this->assertEquals($watermarkPath, $watermarkMeta['watermark_path']);
        $this->assertEquals('test_watermark.png', $watermarkMeta['watermark_filename']);
        $this->assertEquals('bottom-right', $watermarkMeta['position']);
        $this->assertEquals(70, $watermarkMeta['opacity']);
        
        // Content should still be a valid image string
        $this->assertNotEmpty($result['content']);
        $this->assertIsString($result['content']);
        
        // Clean up
        unlink($watermarkPath);
    }

    public function testThumbnailWithWatermarkMetadata()
    {
        // Create a simple watermark image
        $watermarkPath = sys_get_temp_dir() . '/test_watermark_thumb.png';
        $watermark = imagecreate(50, 20);
        $white = imagecolorallocate($watermark, 255, 255, 255);
        $black = imagecolorallocate($watermark, 0, 0, 0);
        imagefill($watermark, 0, 0, $white);
        imagestring($watermark, 2, 5, 5, 'THUMB', $black);
        imagepng($watermark, $watermarkPath);
        imagedestroy($watermark);

        $options = [
            'width' => 200,
            'height' => 200,
            'method' => 'crop',
            'watermark' => [
                'path' => $watermarkPath,
                'position' => 'center',
                'opacity' => 50,
            ]
        ];

        $result = $this->processor->createThumbnail($this->testImageContent, $options);
        
        // Should return array with content and metadata when watermark is applied
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        // Check thumbnail metadata
        $metadata = $result['metadata'];
        $this->assertArrayHasKey('watermark', $metadata);
        
        $watermarkMeta = $metadata['watermark'];
        $this->assertTrue($watermarkMeta['watermark_applied']);
        $this->assertEquals($watermarkPath, $watermarkMeta['watermark_path']);
        $this->assertEquals('center', $watermarkMeta['position']);
        $this->assertEquals(50, $watermarkMeta['opacity']);
        
        // Clean up
        unlink($watermarkPath);
    }

    public function testWatermarkPathResolution()
    {
        // Create a watermark in a public-like path structure
        $publicDir = sys_get_temp_dir() . '/public';
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        
        $watermarkPath = $publicDir . '/logo.png';
        $watermark = imagecreate(80, 25);
        $white = imagecolorallocate($watermark, 255, 255, 255);
        $blue = imagecolorallocate($watermark, 0, 100, 200);
        imagefill($watermark, 0, 0, $white);
        imagestring($watermark, 3, 5, 5, 'LOGO', $blue);
        imagepng($watermark, $watermarkPath);
        imagedestroy($watermark);

        $options = [
            'quality' => 90,
            'watermark' => [
                'path' => $watermarkPath, // Full path
                'position' => 'top-left',
                'opacity' => 80,
            ]
        ];

        $result = $this->processor->process($this->testImageContent, $options);
        
        $this->assertIsArray($result);
        $watermarkMeta = $result['metadata']['watermark'];
        
        // Should resolve to the actual file path
        $this->assertEquals($watermarkPath, $watermarkMeta['watermark_path']);
        $this->assertEquals('logo.png', $watermarkMeta['watermark_filename']);
        $this->assertEquals('top-left', $watermarkMeta['position']);
        
        // Clean up
        unlink($watermarkPath);
        rmdir($publicDir);
    }
} 