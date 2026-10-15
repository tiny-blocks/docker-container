<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\Time\Duration;

/**
 * A Docker command that must finish within a timeout of its own, such as a container run until it exits.
 */
interface DockerCommandWithTimeout extends DockerCommand
{
    /**
     * Returns the longest time the command may run before it is interrupted.
     *
     * @return Duration The timeout.
     */
    public function timeout(): Duration;
}
