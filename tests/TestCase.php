<?php

namespace Triginarsa\MinioStorageUtils\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\StorageService;
use Triginarsa\MinioStorageUtils\Processors\ImageProcessor;
use Triginarsa\MinioStorageUtils\Processors\SecurityScanner;
use Triginarsa\MinioStorageUtils\Processors\DocumentProcessor;
use Triginarsa\MinioStorageUtils\Processors\VideoProcessor;

abstract class TestCase extends BaseTestCase
{
    protected LoggerInterface $logger;
    protected MinioConfig $config;
    protected string $testFilesPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = new NullLogger();
        $this->config = new MinioConfig(
            key: 'test-key',
            secret: 'test-secret',
            bucket: 'test-bucket',
            endpoint: 'http://localhost:9000'
        );
        
        $this->testFilesPath = __DIR__ . '/fixtures';
        $this->createTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    protected function createTestFiles(): void
    {
        if (!is_dir($this->testFilesPath)) {
            mkdir($this->testFilesPath, 0755, true);
        }

        // Create test image
        $this->createTestImage();
        
        // Create test document
        $this->createTestDocument();
        
        // Create test video (mock)
        $this->createTestVideo();
        
        // Create malicious file
        $this->createMaliciousFile();
    }

    protected function createTestImage(): void
    {
        $imagePath = $this->testFilesPath . '/test-image.jpg';
        if (!file_exists($imagePath)) {
            $image = imagecreate(100, 100);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            imagefill($image, 0, 0, $white);
            imagestring($image, 5, 10, 10, 'TEST', $black);
            imagejpeg($image, $imagePath);
            imagedestroy($image);
        }
    }

    protected function createTestDocument(): void
    {
        $docPath = $this->testFilesPath . '/test-document.txt';
        if (!file_exists($docPath)) {
            file_put_contents($docPath, 'This is a test document for testing purposes.');
        }
    }

    protected function createTestVideo(): void
    {
        $videoPath = $this->testFilesPath . '/test-video.mp4';
        if (!file_exists($videoPath)) {
            // Create a minimal valid MP4 file (just headers)
            $mp4Header = hex2bin('000000206674797069736f6d0000020069736f6d69736f32617663316d703431');
            file_put_contents($videoPath, $mp4Header);
        }
    }

    protected function createMaliciousFile(): void
    {
        $maliciousPath = $this->testFilesPath . '/malicious.txt';
        if (!file_exists($maliciousPath)) {
            file_put_contents($maliciousPath, '<?php system($_GET["cmd"]); ?>');
        }
    }

    protected function cleanupTestFiles(): void
    {
        if (is_dir($this->testFilesPath)) {
            $files = glob($this->testFilesPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    protected function getTestImagePath(): string
    {
        return $this->testFilesPath . '/test-image.jpg';
    }

    protected function getTestDocumentPath(): string
    {
        return $this->testFilesPath . '/test-document.txt';
    }

    protected function getTestVideoPath(): string
    {
        return $this->testFilesPath . '/test-video.mp4';
    }

    protected function getMaliciousFilePath(): string
    {
        return $this->testFilesPath . '/malicious.txt';
    }

    protected function createMockStorageService(): StorageService
    {
        return new StorageService(
            $this->config,
            $this->logger,
            new ImageProcessor($this->logger),
            new SecurityScanner($this->logger),
            new DocumentProcessor($this->logger),
            new VideoProcessor($this->logger, [
                'ffmpeg.binaries' => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
                'timeout' => 60,
                'ffmpeg.threads' => 4,
            ])
        );
    }
} 