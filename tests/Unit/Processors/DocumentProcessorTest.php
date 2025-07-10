<?php

namespace Triginarsa\MinioStorageUtils\Tests\Unit\Processors;

use Triginarsa\MinioStorageUtils\Processors\DocumentProcessor;
use Triginarsa\MinioStorageUtils\Exceptions\SecurityException;
use Triginarsa\MinioStorageUtils\Tests\TestCase;

class DocumentProcessorTest extends TestCase
{
    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new DocumentProcessor($this->logger);
    }

    public function testIsDocument(): void
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];

        foreach ($documentTypes as $mimeType) {
            $this->assertTrue($this->processor->isDocument($mimeType));
        }

        $nonDocumentTypes = [
            'image/jpeg',
            'video/mp4',
            'audio/mp3',
            'application/zip',
        ];

        foreach ($nonDocumentTypes as $mimeType) {
            $this->assertFalse($this->processor->isDocument($mimeType));
        }
    }

    public function testScanCleanDocument(): void
    {
        $content = 'This is a clean document with no malicious content.';
        $filename = 'clean-document.txt';
        $mimeType = 'text/plain';

        $result = $this->processor->scan($content, $filename, $mimeType);
        $this->assertTrue($result);
    }

    public function testScanDetectsVBAMacros(): void
    {
        $macroPatterns = [
            'Sub AutoOpen()',
            'Function MyFunction()',
            'Private Sub Workbook_Open()',
            'Public Sub Document_Open()',
            'Auto_Open',
            'Auto_Close',
        ];

        foreach ($macroPatterns as $pattern) {
            $this->expectException(SecurityException::class);
            try {
                $this->processor->scan($pattern, 'malicious.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testScanDetectsVBAObjects(): void
    {
        $vbaObjects = [
            'CreateObject("WScript.Shell")',
            'GetObject("winmgmts:")',
            'Shell("cmd.exe")',
            'WScript.CreateObject',
            'Scripting.FileSystemObject',
        ];

        foreach ($vbaObjects as $object) {
            $this->expectException(SecurityException::class);
            try {
                $this->processor->scan($object, 'malicious.xlsm', 'application/vnd.ms-excel');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testScanDetectsPDFJavaScript(): void
    {
        $jsPatterns = [
            '/JavaScript (',
            '/JS (',
            '/OpenAction',
            '/Launch',
            '/EmbeddedFile',
        ];

        foreach ($jsPatterns as $pattern) {
            $this->expectException(SecurityException::class);
            try {
                $this->processor->scan($pattern, 'malicious.pdf', 'application/pdf');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testScanDetectsSuspiciousExecutables(): void
    {
        $executables = [
            'cmd.exe',
            'powershell',
            'mshta',
            'regsvr32',
            'rundll32',
        ];

        foreach ($executables as $executable) {
            $this->expectException(SecurityException::class);
            try {
                $this->processor->scan($executable, 'malicious.doc', 'application/msword');
            } catch (SecurityException $e) {
                $this->assertStringContainsString('Potentially dangerous content detected', $e->getMessage());
                throw $e;
            }
        }
    }

    public function testScanDetectsEmbeddedFiles(): void
    {
        $content = 'PDF content with /EmbeddedFiles dictionary';
        $filename = 'malicious.pdf';
        $mimeType = 'application/pdf';

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Potentially dangerous content detected in document: malicious.pdf');
        
        $this->processor->scan($content, $filename, $mimeType);
    }

    public function testScanDetectsVBAProject(): void
    {
        $content = 'Office document with vbaProject.bin file';
        $filename = 'malicious.docx';
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Office document contains VBA macros');
        
        $this->processor->scan($content, $filename, $mimeType);
    }

    public function testScanDetectsPDFFormsWithJavaScript(): void
    {
        $content = 'PDF with /AcroForm and /JavaScript combination';
        $filename = 'malicious.pdf';
        $mimeType = 'application/pdf';

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('PDF contains forms with JavaScript');
        
        $this->processor->scan($content, $filename, $mimeType);
    }

    public function testScanLargeDocumentSkipped(): void
    {
        // Create content larger than max scan size (10MB default)
        $largeContent = str_repeat('A', 11 * 1024 * 1024);
        $filename = 'large-document.txt';
        $mimeType = 'text/plain';

        // Should return true (skip scanning) for large files
        $result = $this->processor->scan($largeContent, $filename, $mimeType);
        $this->assertTrue($result);
    }

    public function testAddCustomPattern(): void
    {
        $this->processor->addPattern('/custom_dangerous_pattern/i');
        
        $content = 'Document with custom_dangerous_pattern inside';
        $filename = 'test.txt';
        $mimeType = 'text/plain';

        $this->expectException(SecurityException::class);
        $this->processor->scan($content, $filename, $mimeType);
    }

    public function testRemovePattern(): void
    {
        $pattern = '/Auto_Open/i';
        $this->processor->removePattern($pattern);
        
        $content = 'Auto_Open macro function';
        $filename = 'test.doc';
        $mimeType = 'application/msword';

        // Should not throw exception since pattern was removed
        $result = $this->processor->scan($content, $filename, $mimeType);
        $this->assertTrue($result);
    }

    public function testScanWithContext(): void
    {
        $content = 'Sub AutoOpen()';
        $filename = 'malicious.docx';
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        try {
            $this->processor->scan($content, $filename, $mimeType);
            $this->fail('Expected SecurityException was not thrown');
        } catch (SecurityException $e) {
            $context = $e->getContext();
            $this->assertArrayHasKey('filename', $context);
            $this->assertArrayHasKey('pattern', $context);
            $this->assertArrayHasKey('mime_type', $context);
            $this->assertEquals('malicious.docx', $context['filename']);
            $this->assertEquals($mimeType, $context['mime_type']);
        }
    }

    public function testCaseInsensitiveDetection(): void
    {
        $content = 'SUB AUTOOPEN()';
        $filename = 'malicious.doc';
        $mimeType = 'application/msword';

        $this->expectException(SecurityException::class);
        $this->processor->scan($content, $filename, $mimeType);
    }

    public function testScanEmptyDocument(): void
    {
        $content = '';
        $filename = 'empty.txt';
        $mimeType = 'text/plain';

        $result = $this->processor->scan($content, $filename, $mimeType);
        $this->assertTrue($result);
    }

    public function testScanDetectsExternalLinks(): void
    {
        $content = 'Document with external link: https://malicious.com/payload';
        $filename = 'suspicious.docx';
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        // This should only log a warning, not throw an exception
        $result = $this->processor->scan($content, $filename, $mimeType);
        $this->assertTrue($result);
    }
} 