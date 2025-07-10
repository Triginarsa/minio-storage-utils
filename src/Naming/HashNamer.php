<?php

namespace Triginarsa\MinioStorageUtils\Naming;

class HashNamer implements NamerInterface
{
    public function generateName(string $originalName, string $content, string $extension): string
    {
        $hash = hash('sha256', $content);
        return $hash . '.' . ltrim($extension, '.');
    }
} 