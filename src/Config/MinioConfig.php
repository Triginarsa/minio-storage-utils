<?php

namespace Triginarsa\MinioStorageUtils\Config;

class MinioConfig
{
    private string $key;
    private string $secret;
    private string $region;
    private string $bucket;
    private string $endpoint;
    private string $version;
    private bool $usePathStyleEndpoint;

    public function __construct(
        string $key,
        string $secret,
        string $bucket,
        string $endpoint,
        string $region = 'us-east-1',
        string $version = 'latest',
        bool $usePathStyleEndpoint = true
    ) {
        $this->key = $key;
        $this->secret = $secret;
        $this->bucket = $bucket;
        $this->endpoint = $endpoint;
        $this->region = $region;
        $this->version = $version;
        $this->usePathStyleEndpoint = $usePathStyleEndpoint;
    }

    public function getClientConfig(): array
    {
        return [
            'credentials' => [
                'key'    => $this->key,
                'secret' => $this->secret,
            ],
            'region' => $this->region,
            'version' => $this->version,
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => $this->usePathStyleEndpoint,
        ];
    }
    
    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
} 