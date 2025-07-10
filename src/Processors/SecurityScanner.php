<?php

namespace Triginarsa\MinioStorageUtils\Processors;

use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Psr\Log\LoggerInterface;

class SecurityScanner
{
    private LoggerInterface $logger;
    private array $dangerousPatterns;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->dangerousPatterns = [
            '/<\?php/i',
            '/<\?\s/i',  // PHP short tag
            '/<\?=/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/file_put_contents\s*\(/i',
            '/fopen\s*\(/i',
            '/fwrite\s*\(/i',
            '/include\s*\(/i',
            '/require\s*\(/i',
        ];
    }

    public function scan(string $content, string $filename): bool
    {
        $this->logger->info('Security scan started', ['filename' => $filename]);

        foreach ($this->dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logger->warning('Security threat detected', [
                    'filename' => $filename,
                    'pattern' => $pattern,
                ]);
                
                throw new SecurityException(
                    "Potentially dangerous content detected in file: {$filename}",
                    ['filename' => $filename, 'pattern' => $pattern]
                );
            }
        }

        $this->logger->info('Security scan completed successfully', ['filename' => $filename]);
        return true;
    }

    public function addPattern(string $pattern): void
    {
        $this->dangerousPatterns[] = $pattern;
    }

    public function removePattern(string $pattern): void
    {
        $this->dangerousPatterns = array_filter(
            $this->dangerousPatterns,
            fn($p) => $p !== $pattern
        );
    }
} 