<?php

namespace Triginarsa\MinioStorageUtils\Naming;

class OriginalNamer implements NamerInterface
{
    public function generateName(string $originalName, string $content, string $extension): string
    {
        return $originalName;
    }
} 