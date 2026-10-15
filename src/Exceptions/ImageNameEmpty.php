<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a container is created from an empty image name.
 */
final class ImageNameEmpty extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(message: 'Image name cannot be empty.');
    }
}
