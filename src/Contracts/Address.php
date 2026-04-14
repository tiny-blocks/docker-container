<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Represents the network address of a running Docker container.
 */
interface Address
{
    /**
     * Returns the IP address assigned to the container.
     *
     * @return string The container's IP address.
     */
    public function getIp(): string;

    /**
     * Returns the port mappings exposed by the container.
     *
     * @return Ports The container's exposed ports.
     */
    public function getPorts(): Ports;

    /**
     * Returns the hostname of the container.
     *
     * @return string The container's hostname.
     */
    public function getHostname(): string;

    /**
     * Returns the appropriate host address for connecting to the container.
     *
     * When running inside Docker (e.g., from another container), returns the container's hostname,
     * which is resolvable within the Docker network. When running on the host (e.g., in CI or local
     * development outside Docker), returns 127.0.0.1, since the container is accessible via port mapping.
     *
     * @return string The host address to use for connection.
     */
    public function getHostForConnection(): string;
}
