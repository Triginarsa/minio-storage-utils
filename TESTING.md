# Testing Guide

This document provides comprehensive information about testing the MinIO Storage Utils library.

## Test Structure

The test suite is organized into two main categories:

### Unit Tests (`tests/Unit/`)

- **Config Tests**: Test configuration classes and validation
- **Naming Tests**: Test file naming strategies (Hash, Slug, Original)
- **Processor Tests**: Test individual processors (Security, Image, Document, Video)
- **Exception Tests**: Test custom exception handling

### Feature Tests (`tests/Feature/`)

- **Storage Service Tests**: Integration tests for the main service
- **Laravel Integration Tests**: Tests for Laravel-specific features

## Running Tests

### Quick Start

```bash
# use composer
composer test

# Or use PHPUnit directly
./vendor/bin/phpunit
```

### Specific Test Categories

```bash
# Run only unit tests
./vendor/bin/phpunit tests/Unit

# Run only feature tests
./vendor/bin/phpunit tests/Feature

# Run specific test class
./vendor/bin/phpunit tests/Unit/Naming/NamingTest.php

# Run specific test method
./vendor/bin/phpunit --filter testHashNamer tests/Unit/Naming/NamingTest.php
```

### With Coverage

```bash
# Generate coverage report (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage

# View coverage in browser
open coverage/index.html
```

## Test Configuration

### Environment Variables

Set these in your `.env` file or `phpunit.xml`:

```xml
<php>
    <env name="MINIO_ACCESS_KEY" value="your-access-key"/>
    <env name="MINIO_SECRET_KEY" value="your-secret-key"/>
    <env name="MINIO_BUCKET" value="test-bucket"/>
    <env name="MINIO_ENDPOINT" value="http://localhost:9000"/>
    <env name="FFMPEG_BINARIES" value="/usr/bin/ffmpeg"/>
    <env name="FFPROBE_BINARIES" value="/usr/bin/ffprobe"/>
</php>
```

## Test Categories Explained

### 1. Configuration Tests

**Location**: `tests/Unit/Config/MinioConfigTest.php`

Tests the MinIO configuration class:

- ✅ Default values validation
- ✅ Custom configuration options
- ✅ Client configuration generation
- ✅ Parameter validation

### 2. Naming Strategy Tests

**Location**: `tests/Unit/Naming/NamingTest.php`

Tests file naming strategies:

- ✅ **HashNamer**: SHA256-based naming with consistency
- ✅ **SlugNamer**: URL-friendly names with timestamps
- ✅ **OriginalNamer**: Preserves original filenames
- ✅ Edge cases and special characters

### 3. Security Scanner Tests

**Location**: `tests/Unit/Processors/SecurityScannerTest.php`

Tests security scanning functionality:

- ✅ PHP code detection (tags, functions, includes)
- ✅ JavaScript/XSS detection
- ✅ System command detection
- ✅ File operation detection
- ✅ Custom pattern support
- ✅ Case-insensitive detection
- ✅ Large file handling

### 4. Document Processor Tests

**Location**: `tests/Unit/Processors/DocumentProcessorTest.php`

Tests document-specific security scanning:

- ✅ Document type detection
- ✅ VBA macro detection
- ✅ PDF JavaScript detection
- ✅ Embedded file detection
- ✅ Suspicious executable detection
- ✅ Custom pattern support

### 5. Image Processor Tests

**Location**: `tests/Unit/Processors/ImageProcessorTest.php`

Tests image processing and advanced compression capabilities:

#### Basic Image Processing
- ✅ Image type detection
- ✅ Resize functionality
- ✅ Watermark application
- ✅ Format conversion
- ✅ Auto-orientation
- ✅ Image metadata extraction
- ✅ Error handling for invalid images

#### Advanced Compression Features
- ✅ **Quality-based compression** (1-100 scale)
- ✅ **Target size compression** (compress to specific file size)
- ✅ **Quality preset compression** (low, medium, high, very_high, max)
- ✅ **Smart compression** (auto-adjust based on image characteristics)
- ✅ **Web optimization** (resize + compress for web delivery)
- ✅ **Progressive JPEG** encoding
- ✅ **Format conversion** (JPG, PNG, WebP, AVIF)
- ✅ **Compression ratio calculation**
- ✅ **Multiple quality levels** testing
- ✅ **Format-specific settings** (progressive JPEG, WebP quality)
- ✅ **Thumbnail optimization**
- ✅ **Enhanced metadata** (file size, megapixels)
- ✅ **Optimization settings** validation
- ✅ **Invalid quality handling** (bounds checking)

### 6. Video Processor Tests

**Location**: `tests/Unit/Processors/VideoProcessorTest.php`

Tests video processing capabilities:

- ✅ Video type detection
- ✅ Compression and quality control
- ✅ Format conversion to MP4
- ✅ Resize/resolution change
- ✅ Watermark application
- ✅ Video clipping
- ✅ Thumbnail generation from video
- ✅ Video metadata extraction
- ✅ FFmpeg dependency validation

> **Note**: Video tests are mostly skipped by default as they require FFmpeg binaries and actual video files.

### 7. Storage Service Integration Tests

**Location**: `tests/Feature/StorageServiceTest.php`

Tests the main service integration:

- ✅ File upload with various options
- ✅ Image processing during upload
- ✅ **Advanced compression integration**
  - Quality-based compression
  - Target size compression
  - Quality preset compression
  - Web optimization
  - Smart compression
  - Multiple format generation
- ✅ **Thumbnail generation with optimization**
- ✅ Security scanning integration
- ✅ File deletion
- ✅ File information retrieval
- ✅ URL generation (public/presigned)
- ✅ Custom naming strategies
- ✅ **Enhanced metadata handling** (compression info)
- ✅ **Compression ratio reporting**
- ✅ Error scenarios

### 8. Laravel Integration Tests

**Location**: `tests/Feature/LaravelIntegrationTest.php`

Tests Laravel-specific features:

- ✅ Service provider registration
- ✅ Facade accessibility
- ✅ **Configuration publishing** (including compression settings)
- ✅ File upload from requests
- ✅ **Compression parameter validation**
- ✅ **Environment-based compression defaults**
- ✅ Event system integration
- ✅ Queue integration
- ✅ Cache integration
- ✅ Artisan commands

## Testing Compression Features

### Compression Test Categories

#### 1. Unit Tests for Compression

**Location**: `tests/Unit/Processors/ImageProcessorTest.php`

Test individual compression methods:

```bash
# Test basic compression
./vendor/bin/phpunit --filter testImageCompression

# Test target size compression
./vendor/bin/phpunit --filter testTargetSizeCompression

# Test quality presets
./vendor/bin/phpunit --filter testQualityPresetCompression

# Test web optimization
./vendor/bin/phpunit --filter testWebOptimization

# Test smart compression
./vendor/bin/phpunit --filter testSmartCompression
```

#### 2. Integration Tests for Compression

**Location**: `tests/Feature/StorageServiceTest.php`

Test compression in real upload scenarios:

```bash
# Test compression integration
./vendor/bin/phpunit --filter testUploadWithCompression

# Test multiple format generation
./vendor/bin/phpunit --filter testMultipleFormatGeneration

# Test compression with thumbnails
./vendor/bin/phpunit --filter testCompressionWithThumbnails
```

#### 3. Laravel Compression Tests

**Location**: `tests/Feature/LaravelIntegrationTest.php`

Test Laravel-specific compression features:

```bash
# Test facade compression methods
./vendor/bin/phpunit --filter testFacadeCompression

# Test configuration-based compression
./vendor/bin/phpunit --filter testConfigurationBasedCompression

# Test environment variable compression
./vendor/bin/phpunit --filter testEnvironmentCompression
```

### Compression Test Data

#### Test Images for Compression

The test suite uses various image sizes and formats:

```php
// Small image (100x100) - for basic tests
$smallImage = $this->generateTestImageContent(100, 100);

// Medium image (800x600) - for web optimization tests
$mediumImage = $this->generateTestImageContent(800, 600);

// Large image (2048x1536) - for smart compression tests
$largeImage = $this->generateTestImageContent(2048, 1536);

// High-resolution image (4000x3000) - for target size tests
$highResImage = $this->generateTestImageContent(4000, 3000);
```

#### Compression Test Scenarios

```php
// Test quality levels
$qualityLevels = [30, 50, 70, 85, 95];

// Test quality presets
$qualityPresets = ['low', 'medium', 'high', 'very_high', 'max'];

// Test target sizes
$targetSizes = [100000, 500000, 1000000]; // 100KB, 500KB, 1MB

// Test formats
$formats = ['jpg', 'png', 'webp', 'avif'];
```

### Performance Testing

#### Compression Ratio Testing

```bash
# Test compression efficiency
./vendor/bin/phpunit --filter testCompressionRatioCalculation

# Test multiple quality levels
./vendor/bin/phpunit --filter testMultipleQualityLevels
```

#### Memory Usage Testing

```php
// Monitor memory usage during compression
public function testCompressionMemoryUsage(): void
{
    $initialMemory = memory_get_usage();
    
    $result = $this->processor->compressImage($this->largeImageContent, [
        'quality' => 75,
        'format' => 'jpg'
    ]);
    
    $finalMemory = memory_get_usage();
    $memoryUsed = $finalMemory - $initialMemory;
    
    // Assert memory usage is reasonable
    $this->assertLessThan(50 * 1024 * 1024, $memoryUsed); // Less than 50MB
}
```

### Testing Compression Configuration

#### Environment Variable Testing

```bash
# Test with different compression settings
MINIO_COMPRESSION_QUALITY=90 ./vendor/bin/phpunit --filter testEnvironmentCompression
MINIO_COMPRESSION_FORMAT=webp ./vendor/bin/phpunit --filter testEnvironmentCompression
MINIO_COMPRESSION_PRESET=high ./vendor/bin/phpunit --filter testEnvironmentCompression
```

#### Configuration File Testing

```php
// Test custom compression configuration
public function testCustomCompressionConfig(): void
{
    $config = [
        'compression' => [
            'quality' => 90,
            'format' => 'webp',
            'progressive' => true,
            'quality_preset' => 'very_high',
        ]
    ];
    
    $result = $this->storageService->upload($this->testImage, null, [
        'compress' => true,
        'compression_options' => $config['compression']
    ]);
    
    $this->assertArrayHasKey('main', $result);
    $this->assertStringContains('webp', $result['main']['path']);
}
```

## Test Dependencies

### Required for Full Testing

- **MinIO Server**: For integration tests
- **FFmpeg**: For video processing tests
- **Xdebug**: For coverage reports
- **Laravel**: For Laravel integration tests
- **GD Extension**: For image processing and compression tests
- **Large test images**: For compression performance testing

### Test Setup with Docker

```bash
# Start MinIO server
docker run -d \
  -p 9000:9000 \
  -p 9001:9001 \
  --name minio \
  -e "MINIO_ROOT_USER=testuser" \
  -e "MINIO_ROOT_PASSWORD=testpass123" \
  minio/minio server /data --console-address ":9001"

# Install FFmpeg (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install ffmpeg

# Install FFmpeg (macOS)
brew install ffmpeg
```

## Skipped Tests

Many feature tests are skipped by default because they require external dependencies:

### To Enable Skipped Tests:

1. **Set up MinIO server** (see Docker setup above)
2. **Install FFmpeg** for video processing
3. **Configure environment variables** in `phpunit.xml`
4. **Remove `markTestSkipped()` calls** from test methods
5. **Create actual test files** in the fixtures directory

### Example: Enabling Video Tests

```php
// In VideoProcessorTest.php, change:
$this->markTestSkipped('Requires FFmpeg binaries and actual video file');

// To:
// $this->markTestSkipped('Requires FFmpeg binaries and actual video file');
```

## Mock vs Real Testing

### Mock Testing (Default)

- Fast execution
- No external dependencies
- Tests logic and error handling
- Suitable for CI/CD pipelines

### Real Testing (Optional)

- Requires actual MinIO server
- Tests actual file operations
- Slower but more comprehensive
- Better for local development

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
  
    services:
      minio:
        image: minio/minio
        ports:
          - 9000:9000
        env:
          MINIO_ROOT_USER: testuser
          MINIO_ROOT_PASSWORD: testpass123
        options: --health-cmd "curl -f http://localhost:9000/minio/health/live"
  
    steps:
      - uses: actions/checkout@v2
    
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
          extensions: gd, imagick
        
      - name: Install FFmpeg
        run: sudo apt-get update && sudo apt-get install -y ffmpeg
      
      - name: Install dependencies
        run: composer install
      
      - name: Run tests
        run: ./run-tests.sh
        env:
          MINIO_ACCESS_KEY: testuser
          MINIO_SECRET_KEY: testpass123
          MINIO_ENDPOINT: http://localhost:9000
```

## Writing New Tests

### Test Structure

```php
<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\YourModule;

use Triginarsa\MinioStorageUtils\Tests\TestCase;

class YourModuleTest extends TestCase
{
    private YourModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new YourModule($this->logger);
    }

    public function testYourFeature(): void
    {
        // Arrange
        $input = 'test data';
      
        // Act
        $result = $this->module->yourMethod($input);
      
        // Assert
        $this->assertSomething($result);
    }
}
```

### Testing Guidelines

1. **Use descriptive test names** that explain what is being tested
2. **Follow AAA pattern** (Arrange, Act, Assert)
3. **Test both success and failure scenarios**
4. **Use appropriate assertions** for the expected outcomes
5. **Mock external dependencies** when possible
6. **Clean up resources** in tearDown() if needed

## Test Data

### Fixtures Directory

Test files are automatically created in `tests/fixtures/`:

- `test-image.jpg`: 100x100 JPEG image
- `test-document.txt`: Plain text document
- `test-video.mp4`: Minimal MP4 file (headers only)
- `malicious.txt`: File with PHP code for security testing

### Custom Test Data

```php
// Create custom test image
protected function createCustomTestImage(int $width, int $height): string
{
    $image = imagecreate($width, $height);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $white);
    imagestring($image, 5, 10, 10, 'TEST', $black);
  
    $path = $this->testFilesPath . "/custom-{$width}x{$height}.jpg";
    imagejpeg($image, $path);
    imagedestroy($image);
  
    return $path;
}
```

## Debugging Tests

### Debug Output

```bash
# Run with verbose output
./vendor/bin/phpunit --verbose

# Run with debug output
./vendor/bin/phpunit --debug

# Run single test with output
./vendor/bin/phpunit --filter testMethodName --verbose
```

### Using Xdebug

```php
// Add breakpoint in test
public function testSomething(): void
{
    xdebug_break(); // Debugger will stop here
    $result = $this->service->method();
    $this->assertSomething($result);
}
```

## Performance Testing

### Memory Usage

```bash
# Monitor memory usage during tests
./vendor/bin/phpunit --verbose | grep -i memory
```

### Execution Time

```bash
# Show execution time for each test
./vendor/bin/phpunit --testdox --verbose
```

## Testing Compression Edge Cases

### Edge Case Testing

#### Invalid Compression Parameters

```php
// Test invalid quality values
public function testInvalidQualityHandling(): void
{
    // Quality too high (should be clamped to 100)
    $result = $this->processor->compressImage($this->testImage, [
        'quality' => 150
    ]);
    $this->assertNotEmpty($result);
    
    // Quality too low (should be clamped to 1)
    $result = $this->processor->compressImage($this->testImage, [
        'quality' => -10
    ]);
    $this->assertNotEmpty($result);
}
```

#### Unreachable Target Size

```php
// Test when target size cannot be achieved
public function testUnreachableTargetSize(): void
{
    $smallImage = $this->generateTestImageContent(50, 50);
    
    $result = $this->processor->compressImage($smallImage, [
        'target_size' => 1000, // Very small target
        'min_quality' => 60
    ]);
    
    // Should still return a result at minimum quality
    $this->assertNotEmpty($result);
}
```

#### Large Image Compression

```php
// Test compression of very large images
public function testLargeImageCompression(): void
{
    $largeImage = $this->generateTestImageContent(8000, 6000);
    
    $result = $this->processor->compressImage($largeImage, [
        'quality' => 80,
        'format' => 'jpg'
    ]);
    
    $this->assertNotEmpty($result);
    $this->assertLessThan(strlen($largeImage), strlen($result));
}
```

### Performance Benchmarking

#### Compression Speed Testing

```bash
# Run compression performance tests
./vendor/bin/phpunit --filter testCompressionSpeed --verbose

# Test with different image sizes
./vendor/bin/phpunit --filter testCompressionPerformance --verbose
```

#### Memory Leak Testing

```php
// Test for memory leaks during batch compression
public function testBatchCompressionMemoryUsage(): void
{
    $initialMemory = memory_get_usage();
    
    // Process multiple images
    for ($i = 0; $i < 10; $i++) {
        $result = $this->processor->compressImage($this->testImage, [
            'quality' => 80
        ]);
        unset($result); // Force garbage collection
    }
    
    $finalMemory = memory_get_usage();
    $memoryIncrease = $finalMemory - $initialMemory;
    
    // Memory increase should be minimal
    $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease); // Less than 10MB
}
```

## Troubleshooting

### Common Issues

1. **Tests fail with "Class not found"**

   - Run `composer dump-autoload`
   - Check namespace declarations

2. **Image processing tests fail**

   - Ensure GD extension is installed
   - Check if Intervention Image is properly installed

3. **Compression tests fail with memory errors**

   - Increase PHP memory limit: `ini_set('memory_limit', '512M')`
   - Use smaller test images for unit tests
   - Enable garbage collection: `gc_collect_cycles()`

4. **WebP/AVIF format tests fail**

   - Ensure GD extension supports WebP/AVIF
   - Check `gd_info()` output for format support
   - Install required system libraries

5. **Target size compression tests inconsistent**

   - Results may vary slightly due to compression algorithms
   - Use tolerance in assertions: `$this->assertLessThanOrEqual($targetSize * 1.1, $actualSize)`

6. **Progressive JPEG tests fail**

   - Progressive JPEG support depends on GD compilation
   - May need to mock the progressive functionality

7. **Video tests always skip**

   - Install FFmpeg binaries
   - Update paths in test configuration

8. **MinIO connection fails**

   - Verify MinIO server is running
   - Check connection credentials
   - Ensure bucket exists

### Debug Commands

```bash
# Check PHP extensions
php -m | grep -E "(gd|imagick|xdebug)"

# Check GD extension capabilities
php -r "print_r(gd_info());"

# Check supported image formats
php -r "var_dump(imagetypes());"

# Check FFmpeg installation
ffmpeg -version

# Test MinIO connection
curl -I http://localhost:9000/minio/health/live
```

### Running Specific Compression Tests

```bash
# Run all compression-related tests
./vendor/bin/phpunit --filter Compression

# Run specific compression test methods
./vendor/bin/phpunit --filter testImageCompression
./vendor/bin/phpunit --filter testTargetSizeCompression
./vendor/bin/phpunit --filter testQualityPresetCompression
./vendor/bin/phpunit --filter testWebOptimization
./vendor/bin/phpunit --filter testSmartCompression

# Run compression tests with verbose output
./vendor/bin/phpunit --filter testCompressionRatioCalculation --verbose

# Run performance tests
./vendor/bin/phpunit --filter testMultipleQualityLevels --verbose

# Test specific image formats
./vendor/bin/phpunit --filter testFormatConversion --verbose
./vendor/bin/phpunit --filter testFormatSpecificSettings --verbose

# Test compression edge cases
./vendor/bin/phpunit --filter testInvalidQualityHandling --verbose
./vendor/bin/phpunit --filter testCompressionMemoryUsage --verbose
```

## Best Practices

1. **Keep tests isolated** - Each test should be independent
2. **Use meaningful assertions** - Assert specific expected values
3. **Test edge cases** - Empty inputs, invalid data, boundary conditions
4. **Mock external services** - Don't rely on external APIs in unit tests
5. **Use fixtures** - Create reusable test data
6. **Clean up after tests** - Remove temporary files and data
7. **Document complex tests** - Add comments explaining test scenarios
8. **Keep tests fast** - Avoid unnecessary delays and heavy operations

## Contributing

When adding new features:

1. Write tests first (TDD approach)
2. Ensure all existing tests pass
3. Add tests for new functionality
4. Update this documentation if needed
5. Run the full test suite before submitting

---

For more information about the library itself, see [README.md](README.md).
