<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Address;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;
use TinyBlocks\DockerContainer\Internal\Containers\HostEnvironment;
use TinyBlocks\Mapper\KeyPreservation;

final readonly class Ports implements ContainerPorts
{
    private function __construct(private Collection $exposedPorts, private Collection $hostMappedPorts)
    {
    }

    public static function from(Collection $exposedPorts, Collection $hostMappedPorts): Ports
    {
        return new Ports(
            exposedPorts: $exposedPorts->filter(),
            hostMappedPorts: $hostMappedPorts->filter()
        );
    }

    public function hostPorts(): array
    {
        return $this->hostMappedPorts->toArray(keyPreservation: KeyPreservation::DISCARD);
    }

    public function exposedPorts(): array
    {
        return $this->exposedPorts->toArray(keyPreservation: KeyPreservation::DISCARD);
    }

    public function firstHostPort(): ?int
    {
        $port = $this->hostMappedPorts->first();

        return empty($port) ? null : (int)$port;
    }

    public function firstExposedPort(): ?int
    {
        $port = $this->exposedPorts->first();

        return empty($port) ? null : (int)$port;
    }

    public function getPortForConnection(): ?int
    {
        return HostEnvironment::isInsideDocker()
            ? $this->firstExposedPort()
            : $this->firstHostPort();
    }
}
