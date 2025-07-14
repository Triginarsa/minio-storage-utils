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
            '/<\?\s/i', 
            '/<\?\t/i', 
            '/<\?\r/i', 
            '/<\?\n/i', 
            '/<\?=/i',  
            '/<%/i',     // ASP style tags
            
            // Enhanced PHP tag detection with various separators
            '/<\?[\s\x00-\x1F]*php/i',
            '/<\?[\s\x00-\x1F]+/i',
            '/<\?[\x00-\x20]*=/i',
            
            // PHP tags with null bytes and unusual spacing
            '/<\?\x00*php/i',    
            '/<\?\s*php/i',            
            '/<\?\r\n\s*php/i',       
            '/<\?[\s\x00]*php/i',
            
            // Obfuscated PHP tags
            '/\<\?\s*[pP][hH][pP]/i',
            '/\x3c\x3f\x70\x68\x70/i',
            '/\074\077\160\150\160/i',
            
            // PHP tags hidden in comments or unusual positions
            '/\/\*.*?<\?php.*?\*\//is',
            '/<!--.*?<\?php.*?-->/is',
            '/\/\/.*?<\?php/i',
            
            // Script patterns
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i',
            '/data:application\/x-javascript/i',
            
            // Enhanced PHP functions with variations
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
            
            // Additional dangerous functions
            '/assert\s*\(/i',
            '/call_user_func\s*\(/i',
            '/call_user_func_array\s*\(/i',
            '/file\s*\(/i',
            '/readfile\s*\(/i',
            '/show_source\s*\(/i',
            '/highlight_file\s*\(/i',
            
            // Enhanced image-specific threats
            '/<!--.*?-->/is',  // HTML comments in images
            '/\x00\x00\x00\x00IEND\xAE\x42\x60\x82.*?<\?php/is', // PHP after PNG end
            '/\xFF\xD9.*?<\?php/is', // PHP after JPEG end
            '/GIF8[79]a.*?<\?php/is', // PHP in GIF
            '/\x00\x00\x00\x00IEND\xAE\x42\x60\x82.*?<script/is', // Script after PNG end
            '/\xFF\xD9.*?<script/is', // Script after JPEG end
            
            // Additional image end marker bypasses
            '/\xFF\xD9.*?<\?/is', 
            '/\x00\x00\x00\x00IEND\xAE\x42\x60\x82.*?<\?/is', 
            '/GIF8[79]a.*?<\?/is',  
            '/\x89PNG\r\n\x1a\n.*?<\?php/is', 
            '/\xFF\xE0.*?JFIF.*?<\?php/is',
            
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
            
            // Enhanced obfuscation patterns
            '/\\\\x[0-9A-Fa-f]{2}/i',
            '/\\\\\\\\x[0-9A-Fa-f]{2}/i',
            '/\\\\u[0-9A-Fa-f]{4}/i',
            '/\\\\\\\\u[0-9A-Fa-f]{4}/i',
            '/\\\\[0-7]{3}/i',
            '/&#x[0-9A-Fa-f]+;/i',
            '/&#[0-9]+;/i',
            
            // Obfuscation functions
            '/chr\s*\(/i',
            '/ord\s*\(/i',
            '/hex2bin\s*\(/i',
            '/pack\s*\(/i',
            '/unpack\s*\(/i',
            '/bin2hex\s*\(/i',
            '/strrev\s*\(/i',
            '/strtr\s*\(/i',
            '/str_replace\s*\(/i',
            '/substr\s*\(/i',
            
            // Network patterns
            '/curl_exec\s*\(/i',
            '/fsockopen\s*\(/i',
            '/socket_create\s*\(/i',
            '/stream_socket_client\s*\(/i',
            '/wget\s/i',
            '/curl\s/i',
            '/gethostbyname\s*\(/i',
            '/dns_get_record\s*\(/i',
            
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
            '/dirname\s*\(/i',
            '/basename\s*\(/i',
            '/pathinfo\s*\(/i',
            '/realpath\s*\(/i',
            
            // Additional suspicious patterns for images
            '/php.*?code/i',
            '/eval.*?base64/i',
            '/gzinflate.*?base64/i',
            '/str_rot13.*?eval/i',
            '/\$_GET\[/i',
            '/\$_POST\[/i',
            '/\$_REQUEST\[/i',
            '/\$_COOKIE\[/i',
            '/\$_SERVER\[/i',
            '/\$_SESSION\[/i',
            '/\$_FILES\[/i',
            '/\$GLOBALS\[/i',
        ];
    }

    public function scan(string $content, string $filename): bool
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $contentLength = strlen($content);
        
        $this->logger->info('Security scan started', [
            'filename' => $filename, 
            'content_size' => $contentLength
        ]);
        
        // Skip intensive checks for very large files (>10MB) to prevent false positives and performance issues
        $isLargeFile = $contentLength > (10 * 1024 * 1024);
        if ($isLargeFile) {
            $this->logger->info('Large file detected, using optimized scanning', [
                'filename' => $filename,
                'size' => $contentLength
            ]);
        }

        // Basic pattern scanning
        foreach ($this->dangerousPatterns as $pattern) {
            try {
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
            } catch (SecurityException $e) {
                // Re-throw security exceptions
                throw $e;
            } catch (\Exception $e) {
                // Log regex errors but don't fail the entire scan
                $this->logger->error('Error in pattern matching', [
                    'filename' => $filename,
                    'pattern' => $pattern,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        // Deep scanning with error handling
        try {
            $this->performDeepScan($content, $filename);
        } catch (SecurityException $e) {
            throw $e; // Re-throw security exceptions
        } catch (\Exception $e) {
            $this->logger->error('Error in deep scan', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
        }

        // Web shell scanning with error handling
        try {
            $this->scanForWebShells($content, $filename);
        } catch (SecurityException $e) {
            throw $e; // Re-throw security exceptions
        } catch (\Exception $e) {
            $this->logger->error('Error in web shell scan', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
        }

        // Image-specific threat scanning with error handling (skip for very large files)
        if (!$isLargeFile) {
            try {
                $this->scanImageSpecificThreats($content, $filename);
            } catch (SecurityException $e) {
                throw $e; // Re-throw security exceptions
            } catch (\Exception $e) {
                $this->logger->error('Error in image-specific threat scan', [
                    'filename' => $filename,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // For large files, only do basic pattern scanning (already done above)
            $this->logger->debug('Skipped intensive image scanning for large file', ['filename' => $filename]);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $scanDuration = round(($endTime - $startTime) * 1000, 2); // ms
        $memoryUsed = $endMemory - $startMemory;
        
        $this->logger->info('Security scan completed successfully', [
            'filename' => $filename,
            'duration_ms' => $scanDuration,
            'memory_used_bytes' => $memoryUsed,
            'content_size' => $contentLength,
            'scan_rate_mb_per_sec' => $contentLength > 0 ? round(($contentLength / 1024 / 1024) / ($scanDuration / 1000), 2) : 0
        ]);
        
        // Log performance warning if scan is slow
        if ($scanDuration > 1000) { // More than 1 second
            $this->logger->warning('Security scan took longer than expected', [
                'filename' => $filename,
                'duration_ms' => $scanDuration,
                'content_size' => $contentLength
            ]);
        }
        
        return true;
    }

    /**
     * Perform deep scanning for sophisticated and obfuscated threats
     */
    private function performDeepScan(string $content, string $filename): void
    {
        // Skip hex pattern scanning for image files to avoid false positives
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'svg'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $imageExtensions)) {
            $hexContent = bin2hex($content);
            
            // Use longer, more specific patterns to reduce false positives
            $phpHexPatterns = [
                '3c3f70687020',         // <?php  (with space) in hex - 6 bytes
                '3c3f7068700a',         // <?php\n in hex - 6 bytes  
                '3c3f7068700d',         // <?php\r in hex - 6 bytes
                '3c3f706870283c3f706870',  // Repeated <?php pattern - very specific
                '3c3f3d24',             // <?=$ in hex - 4 bytes, more specific
                '3c2520',               // <%  (with space) in hex - 3 bytes
            ];
            
            foreach ($phpHexPatterns as $pattern) {
                if (stripos($hexContent, $pattern) !== false) {
                    throw new SecurityException(
                        "Hex-encoded PHP content detected in file: {$filename}",
                        ['filename' => $filename, 'threat' => 'hex_encoded_php', 'pattern' => $pattern]
                    );
                }
            }
        }
        
        // Check for suspicious base64 patterns that might contain PHP
        if (preg_match_all('/[A-Za-z0-9+\/]{20,}={0,2}/', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $decoded = @base64_decode($match, true); // Strict mode
                if ($decoded !== false && strlen($decoded) > 10 && $this->containsPhpCode($decoded)) {
                    throw new SecurityException(
                        "Base64-encoded PHP content detected in file: {$filename}",
                        ['filename' => $filename, 'threat' => 'base64_encoded_php', 'encoded_content' => substr($match, 0, 50)]
                    );
                }
            }
        }
        
        // Additional base64 pattern check for shorter sequences
        if (preg_match_all('/[A-Za-z0-9+\/]{16,49}={0,2}/', $content, $shortMatches)) {
            foreach ($shortMatches[0] as $match) {
                $decoded = @base64_decode($match, true);
                if ($decoded !== false && strlen($decoded) > 5) {
                    // Check for specific dangerous patterns in shorter sequences
                    $suspiciousShortPatterns = [
                        '/<\?php/i',
                        '/<\?\s/i',
                        '/<\?=/i',
                        '/eval\(/i',
                        '/system\(/i',
                        '/exec\(/i',
                        '/\$_GET/i',
                        '/\$_POST/i',
                    ];
                    
                    foreach ($suspiciousShortPatterns as $pattern) {
                        if (preg_match($pattern, $decoded)) {
                            throw new SecurityException(
                                "Base64-encoded suspicious content detected in file: {$filename}",
                                ['filename' => $filename, 'threat' => 'base64_encoded_threat', 'pattern' => $pattern, 'encoded_content' => $match]
                            );
                        }
                    }
                }
            }
        }
        
        // Check for URL-encoded PHP patterns
        $urlEncodedPatterns = [
            '%3C%3Fphp',            // <?php URL encoded
            '%3C%3F%20',            // <? (space) URL encoded
            '%3C%3F%3D',            // <?= URL encoded
            '%3C%25',               // <% URL encoded
        ];
        
        foreach ($urlEncodedPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                throw new SecurityException(
                    "URL-encoded PHP content detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'url_encoded_php', 'pattern' => $pattern]
                );
            }
        }
        
        $suspiciousPatterns = [
            '/\x3c\x3f/',              // <?
            '/\x3c\x25/',              // <%
            '/\x70\x68\x70/',          // php
            '/\x65\x76\x61\x6c/',      // eval
            '/\x65\x78\x65\x63/',      // exec
            '/\x73\x79\x73\x74\x65\x6d/', // system
            '/\x3c\x3f\x70\x68\x70/',  // <?php
            '/\x3c\x3f\x20/',          // <? (space)
            '/\x3c\x3f\x3d/',          // <?=
            '/\x3c\x3f\x0a/',          // <?\n
            '/\x3c\x3f\x0d/',          // <?\r
            '/\x3c\x3f\x09/',          // <?\t
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException(
                    "Suspicious byte sequence detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'suspicious_bytes', 'pattern' => $pattern]
                );
            }
        }
        
        // Check for concatenated strings that might form PHP code
        if (preg_match('/[\'"]\s*\.\s*[\'"].*?php/i', $content)) {
            throw new SecurityException(
                "Concatenated string pattern potentially containing PHP detected: {$filename}",
                ['filename' => $filename, 'threat' => 'concatenated_php']
            );
        }
        
        // Check for eval with various obfuscation techniques
        $evalPatterns = [
            '/\$[a-zA-Z_][a-zA-Z0-9_]*\s*\(\s*.*?eval/i',
            '/call_user_func.*?eval/i',
            '/array_map.*?eval/i',
            '/preg_replace.*?\/e/i',
        ];
        
        foreach ($evalPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException(
                    "Obfuscated eval pattern detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'obfuscated_eval', 'pattern' => $pattern]
                );
            }
        }
    }
    
    /**
     * Scan for common web shell patterns and signatures
     */
    private function scanForWebShells(string $content, string $filename): void
    {
        // Common web shell signatures and patterns
        $webShellPatterns = [
            // Popular web shell names/signatures
            '/c99shell/i',
            '/r57shell/i',
            '/wso[\s_]*shell/i',
            '/b374k/i',
            '/indoxploit/i',
            '/webshell/i',
            '/backdoor/i',
            '/php.*?shell/i',
            '/mini.*?shell/i',
            '/simple.*?shell/i',
            
            // Common web shell function patterns
            '/move_uploaded_file.*?\$_FILES/i',
            '/if.*?\$_GET.*?eval/i',
            '/if.*?\$_POST.*?eval/i',
            '/\$_REQUEST.*?eval/i',
            '/\$_COOKIE.*?eval/i',
            '/base64_decode.*?\$_/i',
            '/gzinflate.*?base64_decode/i',
            '/str_rot13.*?eval/i',
            '/assert.*?\$_/i',
            
            // File upload/management patterns
            '/\$_FILES\[.*?\]\[.*?tmp_name.*?\]/i',
            '/copy\s*\(\s*\$_FILES/i',
            '/move_uploaded_file\s*\(/i',
            '/file_put_contents.*?\$_/i',
            '/fwrite.*?\$_/i',
            '/fputs.*?\$_/i',
            
            // Command execution patterns
            '/system\s*\(\s*\$_/i',
            '/exec\s*\(\s*\$_/i',
            '/shell_exec\s*\(\s*\$_/i',
            '/passthru\s*\(\s*\$_/i',
            '/popen\s*\(\s*\$_/i',
            '/proc_open\s*\(\s*\$_/i',
            
            // Directory traversal and file manipulation
            '/\.\.\/.*?\.\.\/.*?\.\.\//i',
            '/\$_.*?\.\.\//',
            '/scandir\s*\(\s*\$_/i',
            '/opendir\s*\(\s*\$_/i',
            '/readdir\s*\(\s*\$_/i',
            '/glob\s*\(\s*\$_/i',
            
            // Process and system information gathering
            '/phpinfo\s*\(\s*\)/i',
            '/system\s*\(\s*["\']uname/i',
            '/system\s*\(\s*["\']whoami/i',
            '/system\s*\(\s*["\']id\s/i',
            '/system\s*\(\s*["\']pwd/i',
            '/system\s*\(\s*["\']ls\s/i',
            '/exec\s*\(\s*["\']ps\s/i',
            '/shell_exec\s*\(\s*["\']netstat/i',
            
            // Network operations
            '/fsockopen\s*\(/i',
            '/socket_create\s*\(/i',
            '/curl_exec\s*\(/i',
            '/file_get_contents\s*\(\s*["\']http/i',
            '/fopen\s*\(\s*["\']http/i',
            
            // Database operations in suspicious context
            '/mysql_query\s*\(\s*\$_/i',
            '/mysqli_query\s*\(\s*.*?\$_/i',
            '/pg_query\s*\(\s*.*?\$_/i',
            
            // Obfuscated eval patterns
            '/\$\w+\s*=\s*["\']eval["\'].*?\$\w+\s*\(/i',
            '/\$\w+\s*=\s*["\']assert["\'].*?\$\w+\s*\(/i',
            '/\$\w+\s*=\s*["\']system["\'].*?\$\w+\s*\(/i',
            '/\$\w+\s*=\s*["\']exec["\'].*?\$\w+\s*\(/i',
            
            // Create function patterns (deprecated but still used)
            '/create_function\s*\(/i',
            '/\$\w+\s*=\s*create_function/i',
            
            // Reflection-based execution
            '/ReflectionFunction\s*\(/i',
            '/ReflectionMethod\s*\(/i',
            '/call_user_func\s*\(\s*\$_/i',
            '/call_user_func_array\s*\(\s*\$_/i',
            
            // File inclusion patterns
            '/include\s*\(\s*\$_/i',
            '/require\s*\(\s*\$_/i',
            '/include_once\s*\(\s*\$_/i',
            '/require_once\s*\(\s*\$_/i',
            
            // Preg replace with eval modifier (old PHP)
            '/preg_replace\s*\(.*?\/.*?e.*?[\'"]/i',
        ];
        
        foreach ($webShellPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException(
                    "Web shell pattern detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'web_shell', 'pattern' => $pattern]
                );
            }
        }
        
        // Check for multiple suspicious indicators together (lower threshold for detection)
        $suspiciousCount = 0;
        $indicators = [
            '/\$_GET/',
            '/\$_POST/',
            '/\$_REQUEST/',
            '/eval\s*\(/i',
            '/base64_decode/',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec/',
            '/file_get_contents/',
            '/file_put_contents/',
            '/move_uploaded_file/',
        ];
        
        foreach ($indicators as $indicator) {
            if (preg_match($indicator, $content)) {
                $suspiciousCount++;
            }
        }
        
        // If we have 3 or more suspicious indicators, flag as potential web shell
        if ($suspiciousCount >= 3) {
            throw new SecurityException(
                "Multiple web shell indicators detected in file: {$filename}",
                ['filename' => $filename, 'threat' => 'multiple_webshell_indicators', 'indicator_count' => $suspiciousCount]
            );
        }
    }
    
    /**
     * Check if content contains PHP code patterns
     */
    private function containsPhpCode(string $content): bool
    {
        $phpIndicators = [
            '<?php',
            '<?=',
            '<? ',
            '<%',
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            'passthru(',
            '$_GET',
            '$_POST',
            '$_REQUEST',
            'base64_decode',
            'gzinflate',
            'str_rot13',
        ];
        
        foreach ($phpIndicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
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
        
        // Check for script injection anywhere in image content
        $this->scanScriptInjection($content, $filename);
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
        
        // Check for suspicious null bytes patterns - but only flag as suspicious, not as threat
        $nullByteCount = substr_count($content, "\x00");
        if ($nullByteCount > ($length * 0.3)) { // More than 30% null bytes (increased threshold)
            $this->logger->warning('High null byte count in image - potentially suspicious', [
                'filename' => $filename,
                'null_byte_percentage' => round(($nullByteCount / $length) * 100, 2)
            ]);
            // Note: This is logged but doesn't throw an exception
        }
    }

    private function scanImageEndMarkers(string $content, string $filename): void
    {
        // Check for content after image end markers
        $endMarkers = [
            'PNG' => "\x00\x00\x00\x00IEND\xAE\x42\x60\x82",
            'JPEG' => "\xFF\xD9",
            'GIF' => "\x00\x3B",
            // Note: BMP doesn't have a reliable end marker, so we skip it to avoid false positives
        ];

        foreach ($endMarkers as $type => $marker) {
            $markerPos = strrpos($content, $marker);
            if ($markerPos !== false) {
                $afterMarker = substr($content, $markerPos + strlen($marker));
                if (strlen($afterMarker) > 10) { // Increased threshold - more than 10 bytes after end marker is suspicious
                    
                    // Skip detection if it's just repetitive binary data (not actually malicious)
                    if ($this->isRepetitiveBinaryData($afterMarker)) {
                        continue; // Skip this marker, it's likely legitimate image data
                    }
                    
                    // Skip if the trailing data looks like legitimate image metadata
                    if ($this->isLegitimateImageMetadata($afterMarker, $type)) {
                        continue; // Skip this marker, it's likely legitimate metadata
                    }
                    
                    // First check with existing dangerous patterns
                    foreach ($this->dangerousPatterns as $pattern) {
                        if (preg_match($pattern, $afterMarker)) {
                            throw new SecurityException(
                                "Malicious content detected after {$type} end marker: {$filename}",
                                ['filename' => $filename, 'threat' => 'post_image_content', 'image_type' => $type]
                            );
                        }
                    }
                    
                    // Additional checks for hidden PHP code
                    if ($this->containsPhpCode($afterMarker)) {
                        throw new SecurityException(
                            "PHP code detected after {$type} end marker: {$filename}",
                            ['filename' => $filename, 'threat' => 'php_after_image_end', 'image_type' => $type]
                        );
                    }
                    
                    // Check for any script-like content patterns
                    $scriptPatterns = [
                        '/echo\s+/i',
                        '/print\s+/i',
                        '/var_dump\s*\(/i',
                        '/print_r\s*\(/i',
                        '/die\s*\(/i',
                        '/exit\s*\(/i',
                        '/return\s+/i',
                        '/function\s+\w+/i',
                        '/class\s+\w+/i',
                        '/if\s*\(/i',
                        '/for\s*\(/i',
                        '/while\s*\(/i',
                        '/switch\s*\(/i',
                        '/\$\w+\s*=/i',    // Variable assignment
                        '/\$\w+\[/i',      // Array access
                        
                        // Enhanced PHP detection patterns
                        '/<\?[\s\x00-\x1F]*php/i',
                        '/<\?[\s\x00-\x1F]*=/i',
                        '/<\?[\s\x00-\x1F]+/i',
                        '/phpinfo\s*\(/i',
                        '/eval\s*\(/i',
                        '/exec\s*\(/i',
                        '/system\s*\(/i',
                        '/shell_exec\s*\(/i',
                        '/passthru\s*\(/i',
                        '/base64_decode\s*\(/i',
                        '/gzinflate\s*\(/i',
                        '/str_rot13\s*\(/i',
                        '/assert\s*\(/i',
                        '/create_function\s*\(/i',
                        '/call_user_func\s*\(/i',
                        '/preg_replace\s*\(/i',
                        '/\$_GET\s*\[/i',
                        '/\$_POST\s*\[/i',
                        '/\$_REQUEST\s*\[/i',
                        '/\$_COOKIE\s*\[/i',
                        '/\$_SERVER\s*\[/i',
                        '/\$_SESSION\s*\[/i',
                        '/\$_FILES\s*\[/i',
                        '/\$GLOBALS\s*\[/i',
                        
                        // Script tag variations
                        '/<script[^>]*>/i',
                        '/<\/script>/i',
                        '/javascript:/i',
                        '/vbscript:/i',
                        '/on\w+\s*=/i',  // Event handlers like onclick, onload
                        
                        // Common PHP functions that shouldn't be in images
                        '/file_get_contents\s*\(/i',
                        '/file_put_contents\s*\(/i',
                        '/fopen\s*\(/i',
                        '/fwrite\s*\(/i',
                        '/include\s*\(/i',
                        '/require\s*\(/i',
                        '/move_uploaded_file\s*\(/i',
                        '/curl_exec\s*\(/i',
                        '/fsockopen\s*\(/i',
                        '/socket_create\s*\(/i',
                        '/unlink\s*\(/i',
                        '/rmdir\s*\(/i',
                        '/chmod\s*\(/i',
                        '/chown\s*\(/i',
                        '/mkdir\s*\(/i',
                    ];
                    
                    foreach ($scriptPatterns as $pattern) {
                        if (preg_match($pattern, $afterMarker)) {
                            throw new SecurityException(
                                "Script-like content detected after {$type} end marker: {$filename}",
                                ['filename' => $filename, 'threat' => 'script_after_image_end', 'image_type' => $type, 'pattern' => $pattern]
                            );
                        }
                    }
                    
                    // Check for specific executable/malicious content indicators (not just any binary data)
                    $executablePatterns = [
                        '/MZ[\x00-\xFF]{2}[\x00-\xFF]*PE\x00\x00/', // PE executable header sequence
                        '/\x7fELF[\x01\x02]/',         // ELF header with class
                        '/PK\x03\x04[\x00-\xFF]{2}[\x00-\xFF]*\.exe/i', // ZIP containing .exe
                        '/%PDF[\x00-\xFF]*\/JavaScript/i',            // PDF with JavaScript
                        '/\x89PNG[\x00-\xFF]*<script/i',         // PNG with embedded script
                        '/\xFF\xD8\xFF[\x00-\xFF]*<\?php/i',     // JPEG with embedded PHP
                        '/GIF8[79]a[\x00-\xFF]*<script/i',       // GIF with embedded script
                        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]{200,}.*<\?php/s', // Large binary with PHP (much higher threshold)
                    ];
                    
                    foreach ($executablePatterns as $pattern) {
                        if (preg_match($pattern, $afterMarker)) {
                            throw new SecurityException(
                                "Executable or binary content detected after {$type} end marker: {$filename}",
                                ['filename' => $filename, 'threat' => 'binary_after_image_end', 'image_type' => $type]
                            );
                        }
                    }
                    
                    // Log suspicious but not necessarily malicious trailing data
                    if (strlen($afterMarker) > 100) { // More than 100 bytes of trailing data
                        $this->logger->warning('Large amount of trailing data after image end marker', [
                            'filename' => $filename,
                            'image_type' => $type,
                            'trailing_bytes' => strlen($afterMarker),
                            'preview' => substr($afterMarker, 0, 50) // First 50 bytes for analysis
                        ]);
                    }
                }
            }
        }
    }

    private function scanScriptInjection(string $content, string $filename): void
    {
        // Scan for script injection patterns with high accuracy
        $injectionPatterns = [
            // PHP tags with various whitespace and separators
            '/\x3c\x3f\s*php\s+/i',     // <?php with space
            '/\x3c\x3f\s*php\s*\(/i',   // <?php with function call
            '/\x3c\x3f\s*=\s*/i',       // <?= with space
            '/\x3c\x3f\s+\w+/i',        // <? with code
            '/\x3c\x3f\s*\w+\s*\(/i',   // <? with function call
            
            // Common PHP patterns that shouldn't be in images
            '/phpinfo\s*\(\s*\)/i',
            '/eval\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/system\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/exec\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/shell_exec\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/passthru\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            
            // Variable access patterns
            '/\$_GET\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$_POST\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$_REQUEST\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$_COOKIE\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$_SERVER\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$_SESSION\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$_FILES\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            '/\$GLOBALS\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i',
            
            // File operations
            '/file_get_contents\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/file_put_contents\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/fopen\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/fwrite\s*\(\s*\$\w+\s*,/i',
            '/include\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/require\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/include_once\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/require_once\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            
            // Network operations
            '/curl_exec\s*\(\s*\$\w+\s*\)/i',
            '/fsockopen\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/socket_create\s*\(\s*AF_INET\s*,/i',
            '/stream_socket_client\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/gethostbyname\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            
            // Base64 and encoding operations
            '/base64_decode\s*\(\s*[\'"][A-Za-z0-9+\/=]{20,}[\'"]\s*\)/i',
            '/gzinflate\s*\(\s*base64_decode\s*\(/i',
            '/str_rot13\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/hex2bin\s*\(\s*[\'"][0-9a-fA-F]+[\'"]\s*\)/i',
            '/pack\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/unpack\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            
            // Script execution patterns
            '/create_function\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/call_user_func\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/call_user_func_array\s*\(\s*[\'"][^\'"]*[\'"]\s*,/i',
            '/assert\s*\(\s*[\'"][^\'"]*[\'"]\s*\)/i',
            '/preg_replace\s*\(\s*[\'"][^\'"]*\/e[^\'"]*[\'"]\s*,/i',
            
            // Web shell indicators
            '/if\s*\(\s*isset\s*\(\s*\$_GET\s*\[/i',
            '/if\s*\(\s*isset\s*\(\s*\$_POST\s*\[/i',
            '/if\s*\(\s*isset\s*\(\s*\$_REQUEST\s*\[/i',
            '/if\s*\(\s*!empty\s*\(\s*\$_GET\s*\[/i',
            '/if\s*\(\s*!empty\s*\(\s*\$_POST\s*\[/i',
            '/if\s*\(\s*!empty\s*\(\s*\$_REQUEST\s*\[/i',
            
            // Common webshell patterns
            '/c99shell/i',
            '/r57shell/i',
            '/wso[\s_]*shell/i',
            '/b374k/i',
            '/indoxploit/i',
            '/webshell/i',
            '/backdoor/i',
            '/mini.*?shell/i',
            '/simple.*?shell/i',
        ];
        
        foreach ($injectionPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException(
                    "Script injection detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'script_injection', 'pattern' => $pattern]
                );
            }
        }
        
        // Check for suspicious byte sequences that might indicate script injection
        $this->scanSuspiciousBytes($content, $filename);
    }
    
    private function scanSuspiciousBytes(string $content, string $filename): void
    {
        // Check for suspicious byte patterns that might indicate script injection
        $bytePatterns = [
            // PHP tags in various encodings
            '/\x3c\x3f\x70\x68\x70\x20/',  // <?php (space)
            '/\x3c\x3f\x70\x68\x70\x0a/',  // <?php (newline)
            '/\x3c\x3f\x70\x68\x70\x0d/',  // <?php (carriage return)
            '/\x3c\x3f\x70\x68\x70\x09/',  // <?php (tab)
            '/\x3c\x3f\x70\x68\x70\x28/',  // <?php(
            '/\x3c\x3f\x20\x70\x68\x70/',  // <? php
            '/\x3c\x3f\x3d\x20/',          // <?= (space)
            '/\x3c\x3f\x3d\x24/',          // <?=$
            '/\x3c\x3f\x3d\x22/',          // <?="
            '/\x3c\x3f\x3d\x27/',          // <?='
            
            // Common PHP functions in bytes
            '/\x65\x76\x61\x6c\x28/',      // eval(
            '/\x73\x79\x73\x74\x65\x6d\x28/', // system(
            '/\x65\x78\x65\x63\x28/',      // exec(
            '/\x70\x68\x70\x69\x6e\x66\x6f\x28/', // phpinfo(
            '/\x62\x61\x73\x65\x36\x34\x5f\x64\x65\x63\x6f\x64\x65\x28/', // base64_decode(
            '/\x67\x7a\x69\x6e\x66\x6c\x61\x74\x65\x28/', // gzinflate(
            '/\x73\x68\x65\x6c\x6c\x5f\x65\x78\x65\x63\x28/', // shell_exec(
            '/\x70\x61\x73\x73\x74\x68\x72\x75\x28/', // passthru(
            
            // Variable access in bytes
            '/\x24\x5f\x47\x45\x54\x5b/',  // $_GET[
            '/\x24\x5f\x50\x4f\x53\x54\x5b/', // $_POST[
            '/\x24\x5f\x52\x45\x51\x55\x45\x53\x54\x5b/', // $_REQUEST[
            '/\x24\x5f\x43\x4f\x4f\x4b\x49\x45\x5b/', // $_COOKIE[
            '/\x24\x5f\x53\x45\x52\x56\x45\x52\x5b/', // $_SERVER[
            '/\x24\x5f\x53\x45\x53\x53\x49\x4f\x4e\x5b/', // $_SESSION[
            '/\x24\x5f\x46\x49\x4c\x45\x53\x5b/', // $_FILES[
            '/\x24\x47\x4c\x4f\x42\x41\x4c\x53\x5b/', // $GLOBALS[
            
            // File operations in bytes
            '/\x66\x69\x6c\x65\x5f\x67\x65\x74\x5f\x63\x6f\x6e\x74\x65\x6e\x74\x73\x28/', // file_get_contents(
            '/\x66\x69\x6c\x65\x5f\x70\x75\x74\x5f\x63\x6f\x6e\x74\x65\x6e\x74\x73\x28/', // file_put_contents(
            '/\x66\x6f\x70\x65\x6e\x28/',  // fopen(
            '/\x66\x77\x72\x69\x74\x65\x28/', // fwrite(
            '/\x69\x6e\x63\x6c\x75\x64\x65\x28/', // include(
            '/\x72\x65\x71\x75\x69\x72\x65\x28/', // require(
            
            // Network operations in bytes
            '/\x63\x75\x72\x6c\x5f\x65\x78\x65\x63\x28/', // curl_exec(
            '/\x66\x73\x6f\x63\x6b\x6f\x70\x65\x6e\x28/', // fsockopen(
            '/\x73\x6f\x63\x6b\x65\x74\x5f\x63\x72\x65\x61\x74\x65\x28/', // socket_create(
            
            // Obfuscation patterns
            '/\x63\x68\x72\x28/',          // chr(
            '/\x6f\x72\x64\x28/',          // ord(
            '/\x68\x65\x78\x32\x62\x69\x6e\x28/', // hex2bin(
            '/\x70\x61\x63\x6b\x28/',      // pack(
            '/\x75\x6e\x70\x61\x63\x6b\x28/', // unpack(
            '/\x62\x69\x6e\x32\x68\x65\x78\x28/', // bin2hex(
            '/\x73\x74\x72\x72\x65\x76\x28/', // strrev(
            '/\x73\x74\x72\x74\x72\x28/',  // strtr(
            '/\x73\x74\x72\x5f\x72\x65\x70\x6c\x61\x63\x65\x28/', // str_replace(
            '/\x73\x75\x62\x73\x74\x72\x28/', // substr(
            
            // Dangerous functions
            '/\x63\x72\x65\x61\x74\x65\x5f\x66\x75\x6e\x63\x74\x69\x6f\x6e\x28/', // create_function(
            '/\x63\x61\x6c\x6c\x5f\x75\x73\x65\x72\x5f\x66\x75\x6e\x63\x28/', // call_user_func(
            '/\x63\x61\x6c\x6c\x5f\x75\x73\x65\x72\x5f\x66\x75\x6e\x63\x5f\x61\x72\x72\x61\x79\x28/', // call_user_func_array(
            '/\x61\x73\x73\x65\x72\x74\x28/', // assert(
            '/\x70\x72\x65\x67\x5f\x72\x65\x70\x6c\x61\x63\x65\x28/', // preg_replace(
        ];
        
        foreach ($bytePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException(
                    "Suspicious byte sequence detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'suspicious_byte_sequence', 'pattern' => $pattern]
                );
            }
        }
    }

    /**
     * Check if content is just repetitive binary data (likely legitimate)
     */
    private function isRepetitiveBinaryData(string $content): bool
    {
        $length = strlen($content);
        if ($length < 10) {
            return false; // Too short to be repetitive pattern
        }
        
        // Check for simple repetitive patterns (2-8 byte repeats)
        for ($patternSize = 2; $patternSize <= 8; $patternSize++) {
            if ($length % $patternSize === 0) {
                $pattern = substr($content, 0, $patternSize);
                $repeated = str_repeat($pattern, $length / $patternSize);
                
                if ($content === $repeated) {
                    return true; // It's just a repeating pattern
                }
            }
        }
        
        // Check for mostly similar bytes (>90% the same byte)
        $byteCounts = array_count_values(str_split($content));
        $maxCount = max($byteCounts);
        if ($maxCount / $length > 0.9) {
            return true; // Mostly the same byte repeated
        }
        
        return false;
    }

    /**
     * Check if trailing data looks like legitimate image metadata
     */
    private function isLegitimateImageMetadata(string $content, string $imageType): bool
    {
        $length = strlen($content);
        
        // For very small amounts of trailing data, it's likely just padding
        if ($length <= 20) {
            return true;
        }
        
        // Check for mostly null bytes (padding)
        $nullCount = substr_count($content, "\x00");
        if ($nullCount / $length > 0.8) {
            return true; // Mostly null bytes, likely padding
        }
        
        // Check for common metadata patterns that are legitimate
        $metadataPatterns = [
            // ICC color profiles
            '/^ADBE|^APPL|^MSFT|^scnr|^mntr|^prtr|^spac/',
            // EXIF data markers
            '/^Exif\x00\x00/',
            '/^MM\x00\x2A|^II\x2A\x00/', // TIFF headers in EXIF
            // XMP metadata
            '/^<?xpacket/',
            '/^<x:xmpmeta/',
            // Photoshop metadata
            '/^8BIM/',
            // Generic XML metadata (common in PNG)
            '/^<\?xml\s+version/',
            '/^<metadata/',
            // Comment chunks
            '/^tEXt|^zTXt|^iTXt/', // PNG text chunks
        ];
        
        foreach ($metadataPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true; // Looks like legitimate metadata
            }
        }
        
        // Check if content is mostly printable ASCII (likely text metadata)
        $printableCount = 0;
        for ($i = 0; $i < min($length, 100); $i++) { // Check first 100 bytes
            $byte = ord($content[$i]);
            if (($byte >= 32 && $byte <= 126) || $byte === 9 || $byte === 10 || $byte === 13) {
                $printableCount++;
            }
        }
        
        $checkedLength = min($length, 100);
        if ($printableCount / $checkedLength > 0.7) {
            return true; // Mostly printable text, likely metadata
        }
        
        return false;
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