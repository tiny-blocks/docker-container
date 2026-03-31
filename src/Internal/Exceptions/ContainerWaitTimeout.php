<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use RuntimeException;

final class ContainerWaitTimeout extends RuntimeException
{
    public function __construct(int $timeoutInSeconds)
    {
        $template = 'Container readiness check timed out after <%d> seconds.';

        parent::__construct(message: sprintf($template, $timeoutInSeconds));
    }
}
