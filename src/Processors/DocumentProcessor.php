<?php

namespace Triginarsa\MinioStorageUtils\Processors;

use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Psr\Log\LoggerInterface;

class DocumentProcessor
{
    private LoggerInterface $logger;
    private array $documentTypes;
    private array $dangerousPatterns;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/rtf',
        ];

        $this->dangerousPatterns = [
            // Macro patterns
            '/Sub\s+\w+\s*\(/i',
            '/Function\s+\w+\s*\(/i',
            '/Private\s+Sub/i',
            '/Public\s+Sub/i',
            '/Auto_Open/i',
            '/Auto_Close/i',
            '/Workbook_Open/i',
            '/Document_Open/i',
            
            // VBA/Macro keywords
            '/CreateObject\s*\(/i',
            '/GetObject\s*\(/i',
            '/Shell\s*\(/i',
            '/WScript\./i',
            '/Scripting\./i',
            
            // Suspicious PDF content
            '/\/JavaScript\s*\(/i',
            '/\/JS\s*\(/i',
            '/\/OpenAction/i',
            '/\/Launch/i',
            '/\/EmbeddedFile/i',
            
            // Generic suspicious patterns
            '/cmd\.exe/i',
            '/powershell/i',
            '/mshta/i',
            '/regsvr32/i',
            '/rundll32/i',
        ];
    }

    public function isDocument(string $mimeType): bool
    {
        return in_array($mimeType, $this->documentTypes);
    }

    public function scan(string $content, string $filename, string $mimeType): bool
    {
        $this->logger->info('Document security scan started', [
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => strlen($content)
        ]);

        // Check file size
        $maxSize = $this->getConfigValue('minio-storage.security.max_file_size', 10485760);
        if (strlen($content) > $maxSize) {
            $this->logger->warning('Document too large for scanning', [
                'filename' => $filename,
                'size' => strlen($content),
                'max_size' => $maxSize
            ]);
            return true; // Skip scanning for large files
        }

        // Scan for dangerous patterns
        foreach ($this->dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logger->warning('Suspicious content detected in document', [
                    'filename' => $filename,
                    'pattern' => $pattern,
                    'mime_type' => $mimeType
                ]);
                
                throw new SecurityException(
                    "Potentially dangerous content detected in document: {$filename}",
                    [
                        'filename' => $filename,
                        'pattern' => $pattern,
                        'mime_type' => $mimeType
                    ]
                );
            }
        }

        // Additional checks for specific document types
        $this->performSpecificChecks($content, $filename, $mimeType);

        $this->logger->info('Document security scan completed successfully', [
            'filename' => $filename,
            'mime_type' => $mimeType
        ]);

        return true;
    }

    private function performSpecificChecks(string $content, string $filename, string $mimeType): void
    {
        switch ($mimeType) {
            case 'application/pdf':
                $this->scanPdf($content, $filename);
                break;
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                $this->scanOfficeDocument($content, $filename);
                break;
        }
    }

    private function scanPdf(string $content, string $filename): void
    {
        // Check for embedded files
        if (preg_match('/\/EmbeddedFiles/i', $content)) {
            throw new SecurityException(
                "PDF contains embedded files: {$filename}",
                ['filename' => $filename, 'threat' => 'embedded_files']
            );
        }

        // Check for forms with JavaScript
        if (preg_match('/\/AcroForm.*\/JavaScript/is', $content)) {
            throw new SecurityException(
                "PDF contains forms with JavaScript: {$filename}",
                ['filename' => $filename, 'threat' => 'javascript_forms']
            );
        }
    }

    private function scanOfficeDocument(string $content, string $filename): void
    {
        // Check for VBA project
        if (preg_match('/vbaProject\.bin/i', $content)) {
            throw new SecurityException(
                "Office document contains VBA macros: {$filename}",
                ['filename' => $filename, 'threat' => 'vba_macros']
            );
        }

        // Check for external links
        if (preg_match('/http[s]?:\/\/[^\s<>"{}|\\^`\[\]]+/i', $content)) {
            $this->logger->warning('Office document contains external links', [
                'filename' => $filename,
                'threat' => 'external_links'
            ]);
        }
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

    /**
     * Safely get configuration value with fallback
     */
    private function getConfigValue(string $key, $default = null)
    {
        // Check if Laravel config function is available and working
        if (function_exists('config')) {
            try {
                return config($key, $default);
            } catch (\Exception $e) {
                // Laravel config not properly initialized, use default
                return $default;
            }
        }
        
        return $default;
    }
} 