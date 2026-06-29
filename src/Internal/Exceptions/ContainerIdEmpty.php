<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use InvalidArgumentException;

final class ContainerIdEmpty extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct(message: 'Container ID cannot be empty.');
    }
}
