<?php

namespace Triginarsa\MinioStorageUtils\Exceptions;

class FileNotFoundException extends BaseStorageException
{
    public function __construct(string $path, array $context = [])
    {
        parent::__construct("File not found: {$path}", 404, null, array_merge(['path' => $path], $context));
    }
} 