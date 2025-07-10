<?php

namespace Triginarsa\MinioStorageUtils\Exceptions;

class UploadException extends BaseStorageException
{
    public function __construct(string $message, array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous, $context);
    }
} 