<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Represents the port mappings exposed by a Docker container.
 */
interface Ports
{
    /**
     * Returns all exposed ports mapped to the host.
     *
     * @return array<int, int> The list of exposed port numbers.
     */
    public function exposedPorts(): array;

    /**
     * Returns the first exposed port, or null if no ports are exposed.
     *
     * @return int|null The first exposed port number, or null if none.
     */
    public function firstExposedPort(): ?int;
}
