<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models\Address;

use TinyBlocks\DockerContainer\Contracts\Address as ContainerAddress;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;
use TinyBlocks\DockerContainer\NetworkDrivers;

final readonly class Address implements ContainerAddress
{
    private function __construct(private IP $ip, private ContainerPorts $ports, private NetworkDrivers $driver)
    {
    }

    public static function create(): Address
    {
        return new Address(
            ip: IP::local(),
            ports: Ports::createFromEmpty(),
            driver: NetworkDrivers::NONE
        );
    }

    public static function from(array $data): Address
    {
        $network = (array)$data['Networks'];
        $networkMode = (string)key($network);

        $ip = IP::from(data: $network[$networkMode]);
        $ports = Ports::createFrom(elements: $data['Ports']);
        $networkDriver = NetworkDrivers::from($networkMode);

        return new Address(ip: $ip, ports: $ports, driver: $networkDriver);
    }

    public function getIp(): string
    {
        return $this->ip->value;
    }

    public function getPorts(): ContainerPorts
    {
        return $this->ports;
    }

    public function getDriver(): NetworkDrivers
    {
        return $this->driver;
    }
}
