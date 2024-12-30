<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models\Address;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;
use TinyBlocks\Mapper\KeyPreservation;

final readonly class Ports implements ContainerPorts
{
    private function __construct(private array $exposedPorts)
    {
    }

    public static function createFrom(array $elements): Ports
    {
        $exposedPorts = Collection::createFrom($elements['exposedPorts'])
            ->filter()
            ->toArray(keyPreservation: KeyPreservation::DISCARD);

        return new Ports(exposedPorts: $exposedPorts);
    }

    public static function createFromEmpty(): Ports
    {
        return new Ports(exposedPorts: []);
    }

    public function exposedPorts(): array
    {
        return $this->exposedPorts;
    }

    public function firstExposedPort(): ?int
    {
        return $this->exposedPorts()[0] ?? null;
    }
}
