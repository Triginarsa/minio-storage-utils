<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\Processors;

use Triginarsa\MinioStorageUtils\Processors\SecurityScanner;
use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Triginarsa\MinioStorageUtils\Tests\TestCase;

class SecurityScannerTest extends TestCase
{
    private SecurityScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new SecurityScanner($this->logger);
    }

    public function testScanCleanContent(): void
    {
        $content = 'This is a clean text file with no malicious content.';
        $filename = 'clean-file.txt';

        $result = $this->scanner->scan($content, $filename);
        $this->assertTrue($result);
    }

    public function testScanDetectsPHPCode(): void
    {
        $content = '<?php echo "Hello World"; ?>';
        $filename = 'malicious.php';

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $this->scanner->scan($content, $filename);
    }

    public function testScanDetectsPHPShortTag(): void
    {
        $content = '<? echo "Hello World"; ?>';
        $filename = 'malicious.php';

        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testScanDetectsPHPEchoTag(): void
    {
        $content = '<?= "Hello World" ?>';
        $filename = 'malicious.php';

        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testScanDetectsJavaScript(): void
    {
        $content = '<script>alert("XSS");</script>';
        $filename = 'malicious.html';

        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testScanDetectsEval(): void
    {
        $content = 'eval($_POST["code"]);';
        $filename = 'malicious.php';

        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testScanDetectsSystemCommands(): void
    {
        $systemCommands = [
            'exec("rm -rf /")',
            'system("whoami")',
            'shell_exec("ls -la")',
            'passthru("cat /etc/passwd")',
        ];

        foreach ($systemCommands as $command) {
            $this->expectException(SecurityException::class);
            try {
                $this->scanner->scan($command, 'malicious.php');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testScanDetectsFileOperations(): void
    {
        $fileOperations = [
            'file_get_contents("/etc/passwd")',
            'file_put_contents("shell.php", $code)',
            'fopen("/etc/passwd", "r")',
            'fwrite($handle, $data)',
        ];

        foreach ($fileOperations as $operation) {
            $this->expectException(SecurityException::class);
            try {
                $this->scanner->scan($operation, 'malicious.php');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testScanDetectsIncludes(): void
    {
        $includes = [
            'include("malicious.php")',
            'require("config.php")',
            'include_once("header.php")',
            'require_once("footer.php")',
        ];

        foreach ($includes as $include) {
            $this->expectException(SecurityException::class);
            try {
                $this->scanner->scan($include, 'malicious.php');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testAddCustomPattern(): void
    {
        $this->scanner->addPattern('/custom_dangerous_function\s*\(/i');
        
        $content = 'custom_dangerous_function("payload");';
        $filename = 'test.php';

        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testRemovePattern(): void
    {
        $pattern = '/eval\s*\(/i';
        $this->scanner->removePattern($pattern);
        
        $content = 'eval($_POST["code"]);';
        $filename = 'test.php';

        // Should still throw exception because eval is caught by other patterns
        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testScanWithContext(): void
    {
        $content = '<?php system($_GET["cmd"]); ?>';
        $filename = 'malicious.php';

        try {
            $this->scanner->scan($content, $filename);
            $this->fail('Expected SecurityException was not thrown');
        } catch (SecurityException $e) {
            $context = $e->getContext();
            $this->assertArrayHasKey('filename', $context);
            $this->assertArrayHasKey('pattern', $context);
            $this->assertEquals('malicious.php', $context['filename']);
        }
    }

    public function testCaseInsensitiveDetection(): void
    {
        $content = '<?PHP SYSTEM($_GET["cmd"]); ?>';
        $filename = 'malicious.php';

        $this->expectException(SecurityException::class);
        $this->scanner->scan($content, $filename);
    }

    public function testScanLargeContent(): void
    {
        $cleanContent = str_repeat('This is clean content. ', 10000);
        $filename = 'large-file.txt';

        $result = $this->scanner->scan($cleanContent, $filename);
        $this->assertTrue($result);
    }

    public function testScanEmptyContent(): void
    {
        $content = '';
        $filename = 'empty-file.txt';

        $result = $this->scanner->scan($content, $filename);
        $this->assertTrue($result);
    }

    public function testPolyglotDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Polyglot file detected');
        
        $polyglotContent = "\x50\x4B\x03\x04malicious content with ZIP signature";
        $this->scanner->scan($polyglotContent, 'polyglot.jpg');
    }

    public function testImageEndMarkerBypass(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected in file');
        
        $maliciousJpeg = "fake_jpeg_content\xFF\xD9<?php echo 'hidden script'; ?>";
        $this->scanner->scan($maliciousJpeg, 'malicious.jpg');
    }

    public function testSvgScriptDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $maliciousSvg = '<svg onload="alert(\'xss\')" xmlns="http://www.w3.org/2000/svg"></svg>';
        $this->scanner->scan($maliciousSvg, 'malicious.svg');
    }

    public function testObfuscatedCodeDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $obfuscatedContent = 'eval(base64_decode("malicious code"));';
        $this->scanner->scan($obfuscatedContent, 'obfuscated.txt');
    }

    public function testNetworkFunctionDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $networkContent = 'curl_exec($ch);';
        $this->scanner->scan($networkContent, 'network.txt');
    }

    public function testFileSystemFunctionDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $filesystemContent = 'unlink("/etc/passwd");';
        $this->scanner->scan($filesystemContent, 'filesystem.txt');
    }

    public function testImageSpecificThreats(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected in file');
        
        $maliciousPng = "fake_png_content\x00\x00\x00\x00IEND\xAE\x42\x60\x82<script>alert('xss')</script>";
        $this->scanner->scan($maliciousPng, 'malicious.png');
    }

    public function testHexObfuscationDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $hexObfuscated = 'chr(112).chr(104).chr(112)';
        $this->scanner->scan($hexObfuscated, 'hex.txt');
    }

    public function testAspTagDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $aspContent = '<% response.write("ASP code") %>';
        $this->scanner->scan($aspContent, 'asp.txt');
    }

    public function testDataUrlDetection(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected');
        
        $dataUrl = 'data:text/html,<script>alert("xss")</script>';
        $this->scanner->scan($dataUrl, 'data.txt');
    }

    public function testSafeContentPasses(): void
    {
        $safeContent = "This is safe text content without any malicious patterns.";
        $result = $this->scanner->scan($safeContent, 'safe.txt');
        $this->assertTrue($result);
    }

    public function testImageWithPhpAtEnd(): void
    {
        // Simulate a JPEG with PHP code appended at the end
        $fakeJpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00";
        $fakeJpegData = str_repeat("\xFF\xAA\xBB\xCC", 100); // Fake image data
        $jpegEnd = "\xFF\xD9"; // JPEG end marker
        $phpCode = "<?php system(\$_GET['cmd']); ?>";
        
        $maliciousJpeg = $fakeJpegHeader . $fakeJpegData . $jpegEnd . $phpCode;

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected in file');
        
        $this->scanner->scan($maliciousJpeg, 'malicious.jpg');
    }

    public function testImageWithHexEncodedPhp(): void
    {
        // Test hex-encoded PHP in image
        $fakeImageData = str_repeat("\xFF\xAA\xBB\xCC", 50);
        $hexEncodedPhp = hex2bin('3c3f706870'); // <?php in hex
        $maliciousContent = $fakeImageData . $hexEncodedPhp . " echo 'test'; ?>";

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected in file');
        
        $this->scanner->scan($maliciousContent, 'hex-malicious.jpg');
    }

    public function testImageWithBase64EncodedPhp(): void
    {
        // Test base64-encoded PHP in image - now detected with enhanced scanner
        $fakeImageData = str_repeat("\xFF\xAA\xBB\xCC", 50);
        $base64EncodedPhp = base64_encode("<?php system(\$_GET['cmd']); ?>");
        $maliciousContent = $fakeImageData . $base64EncodedPhp;

        // Enhanced scanner now detects base64-encoded threats
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Base64-encoded PHP content detected');
        
        $this->scanner->scan($maliciousContent, 'base64-malicious.jpg');
    }

    public function testImageWithPngEndMarkerBypass(): void
    {
        // Simulate a PNG with PHP code appended at the end
        $fakePngHeader = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";
        $fakePngData = str_repeat("\x00\xFF\xAA\xBB", 100);
        $pngEnd = "\x00\x00\x00\x00IEND\xAE\x42\x60\x82"; // PNG end marker
        $phpCode = "<?php eval(\$_POST['data']); ?>";
        
        $maliciousPng = $fakePngHeader . $fakePngData . $pngEnd . $phpCode;

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected in file');
        
        $this->scanner->scan($maliciousPng, 'malicious.png');
    }
} 