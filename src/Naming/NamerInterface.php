<?php

namespace Triginarsa\MinioStorageUtils\Naming;

interface NamerInterface
{
    /**
     * Generate a filename based on the original filename and file content.
     *
     * @param string $originalName The original filename.
     * @param string $content The file content for hash-based naming.
     * @param string $extension The file extension.
     * @return string The generated filename.
     */
    public function generateName(string $originalName, string $content, string $extension): string;
} 