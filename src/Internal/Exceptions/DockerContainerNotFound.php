<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use RuntimeException;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final class DockerContainerNotFound extends RuntimeException
{
    public function __construct(Name $name)
    {
        $template = 'Docker container with name <%s> was not found.';

        parent::__construct(message: sprintf($template, $name->value));
    }
}
