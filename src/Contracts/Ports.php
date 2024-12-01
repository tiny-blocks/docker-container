<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Defines the port's configuration of a Docker container.
 */
interface Ports
{
    /**
     * Returns an array of all exposed ports of the container.
     *
     * @return array An associative array where keys are the container's exposed ports
     *               and values are the corresponding ports on the host machine.
     */
    public function exposedPorts(): array;

    /**
     * Returns the first exposed port of the container.
     *
     * @return int|null The first exposed port of the container, or null if no ports are exposed.
     */
    public function firstExposedPort(): ?int;
}
