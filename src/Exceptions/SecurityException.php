<?php

namespace Triginarsa\MinioStorageUtils\Exceptions;

class SecurityException extends BaseStorageException
{
    public function __construct(string $message, array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous, $context);
    }
} 