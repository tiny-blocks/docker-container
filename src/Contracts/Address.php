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
}
