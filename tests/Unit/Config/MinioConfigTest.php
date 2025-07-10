<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\Config;

use Triginarsa\MinioStorageUtils\Config\MinioConfig;
use Triginarsa\MinioStorageUtils\Tests\TestCase;

class MinioConfigTest extends TestCase
{
    public function testConfigurationWithDefaults(): void
    {
        $config = new MinioConfig(
            key: 'test-key',
            secret: 'test-secret',
            bucket: 'test-bucket',
            endpoint: 'http://localhost:9000'
        );

        $this->assertEquals('test-key', $config->getKey());
        $this->assertEquals('test-secret', $config->getSecret());
        $this->assertEquals('test-bucket', $config->getBucket());
        $this->assertEquals('http://localhost:9000', $config->getEndpoint());
        $this->assertEquals('us-east-1', $config->getRegion());
    }

    public function testConfigurationWithCustomValues(): void
    {
        $config = new MinioConfig(
            key: 'custom-key',
            secret: 'custom-secret',
            bucket: 'custom-bucket',
            endpoint: 'https://minio.example.com',
            region: 'eu-west-1',
            version: '2006-03-01',
            usePathStyleEndpoint: false
        );

        $this->assertEquals('custom-key', $config->getKey());
        $this->assertEquals('custom-secret', $config->getSecret());
        $this->assertEquals('custom-bucket', $config->getBucket());
        $this->assertEquals('https://minio.example.com', $config->getEndpoint());
        $this->assertEquals('eu-west-1', $config->getRegion());
    }

    public function testGetClientConfig(): void
    {
        $config = new MinioConfig(
            key: 'test-key',
            secret: 'test-secret',
            bucket: 'test-bucket',
            endpoint: 'http://localhost:9000',
            region: 'us-west-2',
            version: '2006-03-01',
            usePathStyleEndpoint: true
        );

        $clientConfig = $config->getClientConfig();

        $this->assertArrayHasKey('credentials', $clientConfig);
        $this->assertArrayHasKey('region', $clientConfig);
        $this->assertArrayHasKey('version', $clientConfig);
        $this->assertArrayHasKey('endpoint', $clientConfig);
        $this->assertArrayHasKey('use_path_style_endpoint', $clientConfig);

        $this->assertEquals('test-key', $clientConfig['credentials']['key']);
        $this->assertEquals('test-secret', $clientConfig['credentials']['secret']);
        $this->assertEquals('us-west-2', $clientConfig['region']);
        $this->assertEquals('2006-03-01', $clientConfig['version']);
        $this->assertEquals('http://localhost:9000', $clientConfig['endpoint']);
        $this->assertTrue($clientConfig['use_path_style_endpoint']);
    }
} 