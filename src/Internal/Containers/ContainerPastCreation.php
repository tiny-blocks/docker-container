<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final readonly class ContainerPastCreation implements ContainerReady
{
    private function __construct(private Name $name, private ContainerLookup $lookup)
    {
    }

    public static function from(Name $name, ContainerLookup $lookup): ContainerPastCreation
    {
        return new ContainerPastCreation(name: $name, lookup: $lookup);
    }

    public function isReady(): bool
    {
        return $this->lookup->isPastCreation(name: $this->name);
    }
}
