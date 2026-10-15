<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Address;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\DockerContainer\Internal\Containers\HostEnvironment;
use TinyBlocks\DockerContainer\Ports as ContainerPorts;

final readonly class Ports implements ContainerPorts
{
    private function __construct(private array $exposedPorts, private array $hostMappedPorts)
    {
    }

    public static function from(array $exposedPorts, array $portBindings): Ports
    {
        $isAssigned = static fn(int $port): bool => $port !== 0;

        return new Ports(
            exposedPorts: Collection::createFrom(elements: array_keys($exposedPorts))
                ->map(transformations: static fn(string $port): int => (int)explode('/', $port)[0])
                ->filter(predicates: $isAssigned)
                ->toArray(keyPreservation: KeyPreservation::DISCARD),
            hostMappedPorts: Collection::createFrom(elements: $portBindings)
                ->filter()
                ->flatten()
                ->map(transformations: static fn(array $binding): int => (int)($binding['HostPort'] ?? 0))
                ->filter(predicates: $isAssigned)
                ->toArray(keyPreservation: KeyPreservation::DISCARD)
        );
    }

    public function hostPorts(): array
    {
        return $this->hostMappedPorts;
    }

    public function exposedPorts(): array
    {
        return $this->exposedPorts;
    }

    public function firstHostPort(): ?int
    {
        return ($this->hostMappedPorts[0] ?? null);
    }

    public function firstExposedPort(): ?int
    {
        return ($this->exposedPorts[0] ?? null);
    }

    public function getPortForConnection(): ?int
    {
        return HostEnvironment::isInsideDocker()
            ? $this->firstExposedPort()
            : $this->firstHostPort();
    }
}
