<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Defines the environment variables configuration of a running Docker container.
 */
interface EnvironmentVariables
{
    /**
     * Retrieves the value of an environment variable by its key.
     *
     * @param string $key The key of the environment variable.
     * @return string The value of the environment variable.
     */
    public function getValueBy(string $key): string;
}
