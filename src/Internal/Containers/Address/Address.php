<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Address;

use TinyBlocks\DockerContainer\Contracts\Address as ContainerAddress;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;

final readonly class Address implements ContainerAddress
{
    private function __construct(private IP $ip, private ContainerPorts $ports, private Hostname $hostname)
    {
    }

    public static function from(IP $ip, Ports $ports, Hostname $hostname): Address
    {
        return new Address(ip: $ip, ports: $ports, hostname: $hostname);
    }

    public function getIp(): string
    {
        return $this->ip->value;
    }

    public function getPorts(): ContainerPorts
    {
        return $this->ports;
    }

    public function getHostname(): string
    {
        return $this->hostname->value;
    }
}
