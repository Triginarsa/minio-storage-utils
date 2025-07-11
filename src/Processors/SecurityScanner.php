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
            '/\\x[0-9A-Fa-f]{2}/i',
            '/\\\\x[0-9A-Fa-f]{2}/i',
            '/\\u[0-9A-Fa-f]{4}/i',
            '/\\\\u[0-9A-Fa-f]{4}/i',
            '/\\[0-7]{3}/i',
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

        $this->performDeepScan($content, $filename);

        $this->scanForWebShells($content, $filename);

        $this->scanImageSpecificThreats($content, $filename);

        $this->logger->info('Security scan completed successfully', ['filename' => $filename]);
        return true;
    }

    /**
     * Perform deep scanning for sophisticated and obfuscated threats
     */
    private function performDeepScan(string $content, string $filename): void
    {
        $hexContent = bin2hex($content);
        
        $phpHexPatterns = [
            '3c3f706870',           // <?php in hex
            '3c3f20',               // <? (space) in hex
            '3c3f3d',               // <?= in hex
            '3c25',                 // <% in hex
        ];
        
        foreach ($phpHexPatterns as $pattern) {
            if (stripos($hexContent, $pattern) !== false) {
                throw new SecurityException(
                    "Hex-encoded PHP content detected in file: {$filename}",
                    ['filename' => $filename, 'threat' => 'hex_encoded_php', 'pattern' => $pattern]
                );
            }
        }
        
        // Check for suspicious base64 patterns that might contain PHP
        if (preg_match('/[A-Za-z0-9+\/]{50,}={0,2}/', $content, $matches)) {
            foreach ($matches as $match) {
                $decoded = @base64_decode($match);
                if ($decoded && $this->containsPhpCode($decoded)) {
                    throw new SecurityException(
                        "Base64-encoded PHP content detected in file: {$filename}",
                        ['filename' => $filename, 'threat' => 'base64_encoded_php']
                    );
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
            '/\x3c\x3f/',
            '/\x3c\x25/',
            '/\x70\x68\x70/',
            '/\x65\x76\x61\x6c/',
            '/\x65\x78\x65\x63/',
            '/\x73\x79\x73\x74\x65\x6d/',
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
            'BMP' => "\x00\x00",  // BMP doesn't have a specific end marker, but check for trailing data
        ];

        foreach ($endMarkers as $type => $marker) {
            $markerPos = strrpos($content, $marker);
            if ($markerPos !== false) {
                $afterMarker = substr($content, $markerPos + strlen($marker));
                if (strlen($afterMarker) > 2) { // More than 2 bytes after end marker
                    
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
                    ];
                    
                    foreach ($scriptPatterns as $pattern) {
                        if (preg_match($pattern, $afterMarker)) {
                            throw new SecurityException(
                                "Script-like content detected after {$type} end marker: {$filename}",
                                ['filename' => $filename, 'threat' => 'script_after_image_end', 'image_type' => $type, 'pattern' => $pattern]
                            );
                        }
                    }
                    
                    // Check for any executable content indicators
                    $executablePatterns = [
                        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]{10,}/', // Large amounts of binary data
                        '/MZ/',              // PE header
                        '/\x7fELF/',         // ELF header  
                        '/PK\x03\x04/',      // ZIP header
                        '/%PDF/',            // PDF header
                        '/\x89PNG/',         // PNG header (nested image)
                        '/\xFF\xD8\xFF/',    // JPEG header (nested image)
                        '/GIF8[79]a/',       // GIF header (nested image)
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