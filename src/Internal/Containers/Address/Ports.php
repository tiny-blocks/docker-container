<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Address;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;
use TinyBlocks\Mapper\KeyPreservation;

final readonly class Ports implements ContainerPorts
{
    private function __construct(private Collection $ports)
    {
    }

    public static function from(Collection $ports): Ports
    {
        return new Ports(ports: $ports->filter());
    }

    public function exposedPorts(): array
    {
        return $this->ports->toArray(keyPreservation: KeyPreservation::DISCARD);
    }

    public function firstExposedPort(): ?int
    {
        $port = $this->ports->first();

        return empty($port) ? null : (int)$port;
    }
}
