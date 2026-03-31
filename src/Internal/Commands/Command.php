<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

/**
 * Represents a Docker CLI command that can be converted to a command-line string.
 */
interface Command
{
    /**
     * Converts the command to its command-line string representation.
     *
     * @return string The full command-line string ready for execution.
     */
    public function toCommandLine(): string;
}
