<?php

namespace Tests\Unit\Processors;

use Tests\TestCase;
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
} 