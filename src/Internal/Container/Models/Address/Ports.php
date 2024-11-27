<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models\Address;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\Internal\Operations\Transform\PreserveKeys;
use TinyBlocks\DockerContainer\Contracts\Ports as ContainerPorts;

final readonly class Ports implements ContainerPorts
{
    private function __construct(private array $exposedPorts)
    {
    }

    public static function createFrom(iterable $elements): Ports
    {
        $exposedPorts = Collection::createFrom($elements)
            ->filter()
            ->map(transformations: fn(array $data): int => (int)$data[0]['HostPort'])
            ->toArray(preserveKeys: PreserveKeys::DISCARD);

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
