<?php

namespace Triginarsa\MinioStorageUtils\Tests\Feature;

use Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage;
use Triginarsa\MinioStorageUtils\Tests\TestCase;
use Illuminate\Http\UploadedFile;

class LaravelIntegrationTest extends TestCase
{
    public function testFacadeIsAccessible(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $this->assertTrue(class_exists('Triginarsa\MinioStorageUtils\Laravel\Facades\MinioStorage'));
    }

    public function testUploadViaFacade(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $imagePath = $this->getTestImagePath();
        $uploadedFile = new UploadedFile(
            $imagePath,
            'test-image.jpg',
            'image/jpeg',
            null,
            true
        );

        $result = MinioStorage::uploadFile($uploadedFile, [
            'process_image' => true,
            'image_options' => [
                'resize' => ['width' => 300, 'height' => 300],
                'quality' => 80,
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('url', $result);
    }

    public function testUploadFromRequest(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        // Mock Laravel request with file upload
        $request = $this->createMock(\Illuminate\Http\Request::class);
        $file = $this->createMock(UploadedFile::class);
        
        $file->method('getClientOriginalName')->willReturn('test.jpg');
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('get')->willReturn(file_get_contents($this->getTestImagePath()));
        
        $request->method('file')->willReturn($file);

        $result = MinioStorage::uploadFromRequest($request, 'image', [
            'process_image' => true,
            'generate_thumbnail' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('thumbnail', $result);
    }

    public function testConfigurationFromFilesystem(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        // Test that configuration is read from Laravel filesystem config
        $config = config('filesystems.disks.minio');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('key', $config);
        $this->assertArrayHasKey('secret', $config);
        $this->assertArrayHasKey('bucket', $config);
        $this->assertArrayHasKey('endpoint', $config);
    }

    public function testServiceProviderRegistration(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $app = app();
        
        $this->assertTrue($app->bound('minio-storage'));
        $this->assertTrue($app->bound('Triginarsa\MinioStorageUtils\StorageService'));
    }

    public function testConfigPublishing(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $configPath = config_path('minio-storage.php');
        
        // Test that config can be published
        $this->artisan('vendor:publish', [
            '--provider' => 'Triginarsa\MinioStorageUtils\Laravel\MinioStorageServiceProvider',
            '--tag' => 'config',
        ]);
        
        $this->assertFileExists($configPath);
    }

    public function testMiddlewareIntegration(): void
    {
        $this->markTestSkipped('Requires Laravel application context and middleware setup');
        
        // Test file upload with middleware validation
        $response = $this->post('/api/upload', [
            'file' => UploadedFile::fake()->image('test.jpg', 100, 100),
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'path',
            'url',
            'metadata',
        ]);
    }

    public function testValidationRules(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $rules = MinioStorage::getValidationRules();
        
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('file', $rules);
        $this->assertContains('required', $rules['file']);
        $this->assertContains('file', $rules['file']);
    }

    public function testEventListeners(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $eventFired = false;
        
        Event::listen('minio.file.uploaded', function ($event) use (&$eventFired) {
            $eventFired = true;
            $this->assertArrayHasKey('path', $event);
            $this->assertArrayHasKey('filename', $event);
        });
        
        MinioStorage::upload('test content', 'test.txt', []);
        
        $this->assertTrue($eventFired);
    }

    public function testQueuedProcessing(): void
    {
        $this->markTestSkipped('Requires Laravel application context and queue setup');
        
        Queue::fake();
        
        $imagePath = $this->getTestImagePath();
        $content = file_get_contents($imagePath);
        
        MinioStorage::upload($content, 'test.jpg', [
            'process_image' => true,
            'queue_processing' => true,
        ]);
        
        Queue::assertPushed(\Triginarsa\MinioStorageUtils\Jobs\ProcessImageJob::class);
    }

    public function testCacheIntegration(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        Cache::shouldReceive('remember')
            ->once()
            ->with('minio_file_info_test.jpg', 300, \Closure::class)
            ->andReturn([
                'exists' => true,
                'size' => 1024,
                'last_modified' => now(),
            ]);
        
        $info = MinioStorage::getFileInfo('test.jpg');
        
        $this->assertIsArray($info);
        $this->assertTrue($info['exists']);
    }

    public function testLoggingIntegration(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        Log::shouldReceive('info')
            ->once()
            ->with('File uploaded', [
                'path' => 'test.jpg',
                'size' => \Mockery::type('int'),
            ]);
        
        MinioStorage::upload('test content', 'test.jpg', []);
    }

    public function testArtisanCommands(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        // Test cleanup command
        $this->artisan('minio:cleanup')
            ->expectsOutput('Cleanup completed')
            ->assertExitCode(0);
        
        // Test status command
        $this->artisan('minio:status')
            ->expectsOutput('MinIO connection: OK')
            ->assertExitCode(0);
    }

    public function testDatabaseIntegration(): void
    {
        $this->markTestSkipped('Requires Laravel application context and database');
        
        // Test file metadata storage in database
        $result = MinioStorage::upload('test content', 'test.txt', [
            'store_metadata' => true,
        ]);
        
        $this->assertDatabaseHas('file_uploads', [
            'path' => $result['path'],
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
        ]);
    }

    public function testFormRequestIntegration(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $request = new \Triginarsa\MinioStorageUtils\Http\Requests\FileUploadRequest();
        
        $rules = $request->rules();
        
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('file', $rules);
    }

    public function testResourceIntegration(): void
    {
        $this->markTestSkipped('Requires Laravel application context');
        
        $fileData = [
            'path' => 'test.jpg',
            'url' => 'https://example.com/test.jpg',
            'metadata' => ['size' => 1024],
        ];
        
        $resource = new \Triginarsa\MinioStorageUtils\Http\Resources\FileResource($fileData);
        
        $array = $resource->toArray(request());
        
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('metadata', $array);
    }
} 