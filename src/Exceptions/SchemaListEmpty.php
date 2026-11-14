<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Exceptions;

use InvalidArgumentException;

/**
 * Raised when Flyway is asked to clean and migrate an empty list of schemas.
 */
final class SchemaListEmpty extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(message: 'Schema list cannot be empty.');
    }
}
