<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Represents the port mappings of a Docker container.
 */
interface Ports
{
    /**
     * Returns all container-internal exposed ports.
     *
     * @return array<int, int> The list of exposed port numbers.
     */
    public function exposedPorts(): array;

    /**
     * Returns all host-mapped ports. These are the ports accessible from the host machine.
     *
     * @return array<int, int> The list of host-mapped port numbers.
     */
    public function hostPorts(): array;

    /**
     * Returns the first container-internal exposed port, or null if no ports are exposed.
     *
     * @return int|null The first exposed port number, or null if none.
     */
    public function firstExposedPort(): ?int;

    /**
     * Returns the first host-mapped port, or null if no ports are mapped.
     *
     * @return int|null The first host-mapped port number, or null if none.
     */
    public function firstHostPort(): ?int;
}
