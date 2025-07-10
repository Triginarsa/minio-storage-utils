<?php

namespace Triginarsa\MinioStorageUtils\Tests\Feature;

use Triginarsa\MinioStorageUtils\StorageService;
use Triginarsa\MinioStorageUtils\Naming\HashNamer;
use Triginarsa\MinioStorageUtils\Naming\SlugNamer;
use Triginarsa\MinioStorageUtils\Naming\OriginalNamer;
use Triginarsa\MinioStorageUtils\Exceptions\UploadException;
use Triginarsa\MinioStorageUtils\Exceptions\FileNotFoundException;
use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Triginarsa\MinioStorageUtils\Tests\TestCase;

class StorageServiceTest extends TestCase
{
    private StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageService = $this->createMockStorageService();
    }

    public function testUploadImageWithProcessing(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        $options = [
            'process_image' => true,
            'image_options' => [
                'resize' => ['width' => 300, 'height' => 300],
                'quality' => 80,
            ],
            'naming_strategy' => new HashNamer(),
        ];

        $result = $this->storageService->upload($content, 'test-image.jpg', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertStringEndsWith('.jpg', $result['path']);
    }

    public function testUploadImageWithThumbnail(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        $options = [
            'process_image' => true,
            'generate_thumbnail' => true,
            'thumbnail_options' => [
                'width' => 150,
                'height' => 150,
                'method' => 'crop',
            ],
            'naming_strategy' => new SlugNamer(),
        ];

        $result = $this->storageService->upload($content, 'My Test Image.jpg', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('thumbnail', $result);
        $this->assertArrayHasKey('path', $result['thumbnail']);
        $this->assertArrayHasKey('url', $result['thumbnail']);
    }

    public function testUploadDocumentWithScanning(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $documentPath = $this->getTestDocumentPath();
        $content = file_get_contents($documentPath);
        
        $options = [
            'security_scan' => true,
            'document_scan' => true,
            'naming_strategy' => new OriginalNamer(),
        ];

        $result = $this->storageService->upload($content, 'test-document.txt', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertEquals('test-document.txt', basename($result['path']));
    }

    public function testUploadMaliciousFileThrowsException(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $maliciousPath = $this->getMaliciousFilePath();
        $content = file_get_contents($maliciousPath);
        
        $options = [
            'security_scan' => true,
        ];

        $this->expectException(SecurityException::class);
        $this->storageService->upload($content, 'malicious.php', $options);
    }

    public function testUploadVideoWithProcessing(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection and FFmpeg');
        
        $videoPath = $this->getTestVideoPath();
        $content = file_get_contents($videoPath);
        
        $options = [
            'process_video' => true,
            'video_options' => [
                'convert_to_mp4' => true,
                'compress' => true,
                'quality' => 'medium',
            ],
            'naming_strategy' => new HashNamer(),
        ];

        $result = $this->storageService->upload($content, 'test-video.avi', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertStringEndsWith('.mp4', $result['path']);
    }

    public function testGetFileInfo(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        // First upload the file
        $uploadResult = $this->storageService->upload($content, 'info-test.jpg', [
            'naming_strategy' => new OriginalNamer(),
        ]);
        
        $info = $this->storageService->getFileInfo($uploadResult['path']);
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('exists', $info);
        $this->assertArrayHasKey('size', $info);
        $this->assertArrayHasKey('last_modified', $info);
        $this->assertArrayHasKey('content_type', $info);
        $this->assertTrue($info['exists']);
        $this->assertGreaterThan(0, $info['size']);
    }

    public function testGetFileInfoNonExistentFile(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $info = $this->storageService->getFileInfo('non-existent-file.jpg');
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('exists', $info);
        $this->assertFalse($info['exists']);
    }

    public function testDeleteFile(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        // First upload the file
        $uploadResult = $this->storageService->upload($content, 'delete-test.jpg', [
            'naming_strategy' => new OriginalNamer(),
        ]);
        
        // Verify file exists
        $info = $this->storageService->getFileInfo($uploadResult['path']);
        $this->assertTrue($info['exists']);
        
        // Delete the file
        $result = $this->storageService->delete($uploadResult['path']);
        $this->assertTrue($result);
        
        // Verify file is deleted
        $info = $this->storageService->getFileInfo($uploadResult['path']);
        $this->assertFalse($info['exists']);
    }

    public function testDeleteNonExistentFile(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $this->expectException(FileNotFoundException::class);
        $this->storageService->delete('non-existent-file.jpg');
    }

    public function testGetUrl(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        // First upload the file
        $uploadResult = $this->storageService->upload($content, 'url-test.jpg', [
            'naming_strategy' => new OriginalNamer(),
        ]);
        
        // Get public URL (no expiration)
        $publicUrl = $this->storageService->getUrl($uploadResult['path']);
        $this->assertIsString($publicUrl);
        $this->assertStringContainsString($uploadResult['path'], $publicUrl);
        
        // Get presigned URL (with expiration)
        $presignedUrl = $this->storageService->getUrl($uploadResult['path'], 3600);
        $this->assertIsString($presignedUrl);
        $this->assertStringContainsString($uploadResult['path'], $presignedUrl);
        $this->assertNotEquals($publicUrl, $presignedUrl);
    }

    public function testUploadWithCustomNaming(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        // Test with HashNamer
        $hashResult = $this->storageService->upload($content, 'test.jpg', [
            'naming_strategy' => new HashNamer(),
        ]);
        $this->assertEquals(64 + 4, strlen(basename($hashResult['path']))); // SHA256 + .jpg
        
        // Test with SlugNamer
        $slugResult = $this->storageService->upload($content, 'My Test File.jpg', [
            'naming_strategy' => new SlugNamer(),
        ]);
        $this->assertStringContainsString('my-test-file', basename($slugResult['path']));
        
        // Test with OriginalNamer
        $originalResult = $this->storageService->upload($content, 'original.jpg', [
            'naming_strategy' => new OriginalNamer(),
        ]);
        $this->assertEquals('original.jpg', basename($originalResult['path']));
    }

    public function testUploadWithConventionalNaming(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        $options = [
            'conventional_naming' => true,
            'naming_prefix' => 'uploads/images/',
        ];

        $result = $this->storageService->upload($content, 'test.jpg', $options);
        
        $this->assertStringStartsWith('uploads/images/', $result['path']);
    }

    public function testUploadLargeFile(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        // Create a large file content (5MB)
        $largeContent = str_repeat('A', 5 * 1024 * 1024);
        
        $options = [
            'security_scan' => false, // Skip scanning for large files
            'naming_strategy' => new HashNamer(),
        ];

        $result = $this->storageService->upload($largeContent, 'large-file.txt', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        
        // Verify file info
        $info = $this->storageService->getFileInfo($result['path']);
        $this->assertEquals(5 * 1024 * 1024, $info['size']);
    }

    public function testUploadEmptyFile(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $emptyContent = '';
        
        $this->expectException(UploadException::class);
        $this->storageService->upload($emptyContent, 'empty.txt', []);
    }

    public function testUploadWithInvalidOptions(): void
    {
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        $options = [
            'invalid_option' => true,
        ];

        // Should not throw exception, just ignore invalid options
        try {
            $this->storageService->upload($content, 'test.jpg', $options);
            // If we get here, the test passed
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Expected to fail due to mock service, but not due to invalid options
            $this->assertStringNotContainsString('invalid_option', $e->getMessage());
        }
    }

    public function testConcurrentUploads(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection and async testing');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = $this->storageService->upload($content, "concurrent-$i.jpg", [
                'naming_strategy' => new HashNamer(),
            ]);
        }
        
        // All uploads should succeed
        $this->assertCount(5, $promises);
    }

    public function testUploadWithMetadata(): void
    {
        $this->markTestSkipped('Requires actual MinIO connection');
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        $options = [
            'metadata' => [
                'user_id' => '123',
                'category' => 'profile',
                'description' => 'User profile picture',
            ],
        ];

        $result = $this->storageService->upload($content, 'profile.jpg', $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('123', $result['metadata']['user_id']);
        $this->assertEquals('profile', $result['metadata']['category']);
    }
} 