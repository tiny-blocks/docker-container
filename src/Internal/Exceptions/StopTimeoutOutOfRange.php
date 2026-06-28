<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use InvalidArgumentException;

final class StopTimeoutOutOfRange extends InvalidArgumentException
{
    public function __construct(int $timeoutInWholeSeconds)
    {
        $template = 'Graceful stop timeout must be zero or greater, got <%d> seconds.';

        parent::__construct(message: sprintf($template, $timeoutInWholeSeconds));
    }
}
