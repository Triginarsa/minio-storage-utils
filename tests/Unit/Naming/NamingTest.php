<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\Naming;

use Triginarsa\MinioStorageUtils\Naming\HashNamer;
use Triginarsa\MinioStorageUtils\Naming\SlugNamer;
use Triginarsa\MinioStorageUtils\Naming\OriginalNamer;
use Triginarsa\MinioStorageUtils\Tests\TestCase;

class NamingTest extends TestCase
{
    public function testHashNamer(): void
    {
        $namer = new HashNamer();
        $content = 'test content';
        $originalName = 'test-file.jpg';
        $extension = 'jpg';

        $generatedName = $namer->generateName($originalName, $content, $extension);
        
        $this->assertStringEndsWith('.jpg', $generatedName);
        $this->assertEquals(64 + 4, strlen($generatedName)); // SHA256 hash (64) + .jpg (4)
        
        // Test consistency
        $generatedName2 = $namer->generateName($originalName, $content, $extension);
        $this->assertEquals($generatedName, $generatedName2);
        
        // Test different content produces different hash
        $generatedName3 = $namer->generateName($originalName, 'different content', $extension);
        $this->assertNotEquals($generatedName, $generatedName3);
    }

    public function testSlugNamer(): void
    {
        $namer = new SlugNamer();
        $content = 'test content';
        $originalName = 'Test File With Spaces.jpg';
        $extension = 'jpg';

        $generatedName = $namer->generateName($originalName, $content, $extension);
        
        $this->assertStringEndsWith('.jpg', $generatedName);
        $this->assertStringContainsString('test-file-with-spaces', $generatedName);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+\-\d+\.jpg$/', $generatedName);
    }

    public function testSlugNamerWithSpecialCharacters(): void
    {
        $namer = new SlugNamer();
        $content = 'test content';
        $originalName = 'File@#$%^&*()Name!.png';
        $extension = 'png';

        $generatedName = $namer->generateName($originalName, $content, $extension);
        
        $this->assertStringEndsWith('.png', $generatedName);
        $this->assertStringContainsString('file', $generatedName);
        $this->assertStringContainsString('name', $generatedName);
        $this->assertDoesNotMatchRegularExpression('/[@#$%^&*()!]/', $generatedName);
    }

    public function testOriginalNamer(): void
    {
        $namer = new OriginalNamer();
        $content = 'test content';
        $originalName = 'original-file.pdf';
        $extension = 'pdf';

        $generatedName = $namer->generateName($originalName, $content, $extension);
        
        $this->assertEquals($originalName, $generatedName);
    }

    public function testHashNamerWithDifferentExtensions(): void
    {
        $namer = new HashNamer();
        $content = 'test content';
        $originalName = 'test-file';

        $jpgName = $namer->generateName($originalName, $content, 'jpg');
        $pngName = $namer->generateName($originalName, $content, 'png');
        
        $this->assertStringEndsWith('.jpg', $jpgName);
        $this->assertStringEndsWith('.png', $pngName);
        
        // Same content but different extensions should have same hash but different extensions
        $jpgHash = substr($jpgName, 0, -4);
        $pngHash = substr($pngName, 0, -4);
        $this->assertEquals($jpgHash, $pngHash);
    }

    public function testSlugNamerTimestampUniqueness(): void
    {
        $namer = new SlugNamer();
        $content = 'test content';
        $originalName = 'test-file.jpg';
        $extension = 'jpg';

        $name1 = $namer->generateName($originalName, $content, $extension);
        sleep(1); // Ensure different timestamp
        $name2 = $namer->generateName($originalName, $content, $extension);
        
        $this->assertNotEquals($name1, $name2);
    }
} 