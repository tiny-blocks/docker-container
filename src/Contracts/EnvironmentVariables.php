<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Represents the environment variables configured in a Docker container.
 */
interface EnvironmentVariables
{
    /**
     * Returns the value of an environment variable by its key.
     *
     * @param string $key The name of the environment variable.
     * @return string The value of the environment variable, or an empty string if not found.
     */
    public function getValueBy(string $key): string;
}
