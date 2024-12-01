<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Defines the network configuration of a running Docker container.
 */
interface Address
{
    /**
     * Returns the IP address of the running container.
     *
     * The IP address is available for containers running in the following network modes:
     *  - `BRIDGE`: IP address is assigned and accessible within the bridge network.
     *  - `IPVLAN`: IP address is assigned and accessible within the ipvlan network.
     *  - `OVERLAY`: IP address is assigned and accessible within an overlay network.
     *  - `MACVLAN`: IP address is assigned and accessible within a macvlan network.
     *
     * For containers running in the `HOST` network mode:
     *  - The IP address is `127.0.0.1` (localhost) on the host machine.
     *
     * @return string The container's IP address.
     */
    public function getIp(): string;

    /**
     * Returns the network ports configuration for the running container.
     *
     * @return Ports The container's network ports.
     */
    public function getPorts(): Ports;

    /**
     * Returns the hostname of the running container.
     *
     * @return string The container's hostname.
     */
    public function getHostname(): string;
}
