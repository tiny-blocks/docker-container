<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

use TinyBlocks\DockerContainer\NetworkDrivers;

/**
 * Defines the network configuration of a running Docker container.
 */
interface Address
{
    /**
     * Returns the IP address of the running container.
     *
     * The IP address is available for containers running in the following network modes:
     *  - {@see NetworkDrivers::BRIDGE}: IP address is assigned and accessible within the bridge network.
     *  - {@see NetworkDrivers::IPVLAN}: IP address is assigned and accessible within the ipvlan network.
     *  - {@see NetworkDrivers::OVERLAY}: IP address is assigned and accessible within an overlay network.
     *  - {@see NetworkDrivers::MACVLAN}: IP address is assigned and accessible within a macvlan network.
     *
     * For containers running in the {@see NetworkDrivers::HOST} network mode:
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
     * Returns the network driver used by the container.
     *
     * @return NetworkDrivers The network driver in use by the container.
     */
    public function getDriver(): NetworkDrivers;
}
