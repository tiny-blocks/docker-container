<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use InvalidArgumentException;

final class ImageNameEmpty extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(message: 'Image name cannot be empty.');
    }
}
