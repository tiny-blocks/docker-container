<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models\Address;

use TinyBlocks\DockerContainer\Contracts\Address as ContainerAddress;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;

final readonly class Address implements ContainerAddress
{
    private function __construct(private IP $ip, private ContainerPorts $ports, private Hostname $hostname)
    {
    }

    public static function create(): Address
    {
        return new Address(ip: IP::local(), ports: Ports::createFromEmpty(), hostname: Hostname::localhost());
    }

    public static function from(array $data): Address
    {
        $ip = IP::from(value: $data['ip']);
        $ports = Ports::createFrom(elements: $data['ports']);
        $hostname = Hostname::from(value: $data['hostname']);

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
