<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

/**
 * Defines a Docker command option.
 */
interface CommandOption
{
    /**
     * Converts the option to one or more command line arguments in a single string.
     *
     * @return string The option(s) in Docker command line argument format.
     */
    public function toArguments(): string;
}
