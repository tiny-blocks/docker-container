<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

/**
 * Defines a Docker command with a timeout.
 */
interface CommandWithTimeout extends Command
{
    /**
     * Returns the timeout duration for executing the command.
     *
     * @return int The timeout duration in whole seconds.
     */
    public function getTimeoutInWholeSeconds(): int;
}
