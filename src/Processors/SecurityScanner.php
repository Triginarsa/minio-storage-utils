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
            // PHP patterns
            '/<\?php/i',
            '/<\?\s/i',  // PHP short tag
            '/<\?=/i',
            '/<%/i',     // ASP style tags
            
            // Script patterns
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/data:application\/x-javascript/i',
            
            // PHP functions
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
            '/base64_decode\s*\(/i',
            '/gzinflate\s*\(/i',
            '/str_rot13\s*\(/i',
            '/preg_replace\s*\(/i',
            '/create_function\s*\(/i',
            
            // Image-specific threats
            '/<!--.*?-->/is',  // HTML comments in images
            '/\x00\x00\x00\x00IEND\xAE\x42\x60\x82.*?<\?php/is', // PHP after PNG end
            '/\xFF\xD9.*?<\?php/is', // PHP after JPEG end
            '/GIF8[79]a.*?<\?php/is', // PHP in GIF
            '/\x00\x00\x00\x00IEND\xAE\x42\x60\x82.*?<script/is', // Script after PNG end
            '/\xFF\xD9.*?<script/is', // Script after JPEG end
            
            // SVG threats
            '/<svg[^>]*>/i',
            '/xmlns[^>]*>/i',
            '/onload\s*=/i',
            '/onclick\s*=/i',
            '/onerror\s*=/i',
            '/onmouseover\s*=/i',
            '/xlink:href\s*=/i',
            
            // Polyglot indicators
            '/\x50\x4B\x03\x04.*?<\?php/is', // ZIP signature with PHP
            '/\x25\x50\x44\x46.*?<\?php/is', // PDF signature with PHP
            '/\x4D\x5A.*?<\?php/is', // PE/EXE signature with PHP
            
            // Suspicious hex patterns
            '/\\x[0-9A-Fa-f]{2}/i',
            '/\\\\x[0-9A-Fa-f]{2}/i',
            '/\\u[0-9A-Fa-f]{4}/i',
            '/\\\\u[0-9A-Fa-f]{4}/i',
            
            // Obfuscation patterns
            '/chr\s*\(/i',
            '/ord\s*\(/i',
            '/hex2bin\s*\(/i',
            '/pack\s*\(/i',
            '/unpack\s*\(/i',
            
            // Network patterns
            '/curl_exec\s*\(/i',
            '/fsockopen\s*\(/i',
            '/socket_create\s*\(/i',
            '/stream_socket_client\s*\(/i',
            '/wget\s/i',
            '/curl\s/i',
            
            // File system patterns
            '/rmdir\s*\(/i',
            '/unlink\s*\(/i',
            '/chmod\s*\(/i',
            '/chown\s*\(/i',
            '/mkdir\s*\(/i',
            '/glob\s*\(/i',
            '/scandir\s*\(/i',
            '/readdir\s*\(/i',
            '/opendir\s*\(/i',
            '/is_dir\s*\(/i',
            '/is_file\s*\(/i',
        ];
    }

    public function scan(string $content, string $filename): bool
    {
        $this->logger->info('Security scan started', ['filename' => $filename]);

        // Basic pattern scanning
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

        $this->scanImageSpecificThreats($content, $filename);

        $this->logger->info('Security scan completed successfully', ['filename' => $filename]);
        return true;
    }

    public function scanImageSpecificThreats(string $content, string $filename): void
    {
        // Check for polyglot files
        $this->checkPolyglotFile($content, $filename);
        
        // Check for EXIF data threats
        $this->scanExifData($content, $filename);
        
        // Check for suspicious image structure
        $this->scanImageStructure($content, $filename);
        
        // Check for hidden scripts after image end markers
        $this->scanImageEndMarkers($content, $filename);
    }

    private function checkPolyglotFile(string $content, string $filename): void
    {
        $signatures = [
            'ZIP' => ['signature' => "\x50\x4B\x03\x04", 'name' => 'ZIP'],
            'PDF' => ['signature' => "\x25\x50\x44\x46", 'name' => 'PDF'],
            'PE' => ['signature' => "\x4D\x5A", 'name' => 'PE/EXE'],
            'ELF' => ['signature' => "\x7F\x45\x4C\x46", 'name' => 'ELF'],
            'JAVA' => ['signature' => "\xCA\xFE\xBA\xBE", 'name' => 'Java Class'],
        ];

        foreach ($signatures as $type => $info) {
            if (strpos($content, $info['signature']) !== false) {
                $this->logger->warning('Polyglot file detected', [
                    'filename' => $filename,
                    'detected_type' => $info['name']
                ]);
                
                throw new SecurityException(
                    "Polyglot file detected: {$filename} contains {$info['name']} signature",
                    ['filename' => $filename, 'threat' => 'polyglot', 'detected_type' => $info['name']]
                );
            }
        }
    }

    private function scanExifData(string $content, string $filename): void
    {
        // Check for suspicious EXIF data
        if (function_exists('exif_read_data')) {
            $tempFile = tempnam(sys_get_temp_dir(), 'security_scan_');
            file_put_contents($tempFile, $content);
            
            try {
                $exifData = @exif_read_data($tempFile);
                if ($exifData) {
                    foreach ($exifData as $key => $value) {
                        if (is_string($value)) {
                            // Check for suspicious patterns in EXIF data
                            foreach ($this->dangerousPatterns as $pattern) {
                                if (preg_match($pattern, $value)) {
                                    unlink($tempFile);
                                    throw new SecurityException(
                                        "Malicious content detected in EXIF data: {$filename}",
                                        ['filename' => $filename, 'threat' => 'exif_malicious', 'exif_key' => $key]
                                    );
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // EXIF reading failed, continue with other checks
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }

    private function scanImageStructure(string $content, string $filename): void
    {
        // Check for suspicious image structure
        $length = strlen($content);
        
        // Check for oversized images (potential zip bombs)
        if ($length > 50 * 1024 * 1024) { // 50MB
            $this->logger->warning('Oversized image detected', [
                'filename' => $filename,
                'size' => $length
            ]);
        }
        
        // Check for suspicious null bytes patterns
        $nullByteCount = substr_count($content, "\x00");
        if ($nullByteCount > ($length * 0.1)) { // More than 10% null bytes
            $this->logger->warning('Suspicious null byte pattern in image', [
                'filename' => $filename,
                'null_byte_percentage' => round(($nullByteCount / $length) * 100, 2)
            ]);
        }
    }

    private function scanImageEndMarkers(string $content, string $filename): void
    {
        // Check for content after image end markers
        $endMarkers = [
            'PNG' => "\x00\x00\x00\x00IEND\xAE\x42\x60\x82",
            'JPEG' => "\xFF\xD9",
            'GIF' => "\x00\x3B",
        ];

        foreach ($endMarkers as $type => $marker) {
            $markerPos = strrpos($content, $marker);
            if ($markerPos !== false) {
                $afterMarker = substr($content, $markerPos + strlen($marker));
                if (strlen($afterMarker) > 10) { // More than 10 bytes after end marker
                    // Check if the extra content contains suspicious patterns
                    foreach ($this->dangerousPatterns as $pattern) {
                        if (preg_match($pattern, $afterMarker)) {
                            throw new SecurityException(
                                "Malicious content detected after {$type} end marker: {$filename}",
                                ['filename' => $filename, 'threat' => 'post_image_content', 'image_type' => $type]
                            );
                        }
                    }
                }
            }
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
} 