<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

/**
 * Represents a Docker CLI command that supports a configurable timeout.
 */
interface CommandWithTimeout extends Command
{
    /**
     * Returns the maximum time in seconds allowed for the command to complete.
     *
     * @return int The timeout in whole seconds.
     */
    public function getTimeoutInWholeSeconds(): int;
}
