<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use InvalidArgumentException;

final class ContainerIdTooShort extends InvalidArgumentException
{
    public function __construct(string $containerId, int $minimumLength)
    {
        $template = 'Container ID <%s> is too short. Minimum length is <%d> characters.';

        parent::__construct(message: sprintf($template, $containerId, $minimumLength));
    }
}
