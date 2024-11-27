<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

/**
 * Defines a basic Docker command.
 */
interface Command
{
    /**
     * Converts the command to a Docker command line string.
     *
     * This method should return a properly formatted string that can be executed in the Docker CLI.
     *
     * @return string The Docker command.
     */
    public function toCommandLine(): string;
}
