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

        // Should not throw exception since pattern was removed
        $result = $this->scanner->scan($content, $filename);
        $this->assertTrue($result);
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
} 