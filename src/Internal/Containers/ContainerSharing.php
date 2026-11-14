<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;

final readonly class ContainerSharing
{
    public function __construct(
        private DockerClient $client,
        private ContainerStartup $startup,
        private ContainerDefinition $definition
    ) {
    }

    public function share(array $commands): Shared
    {
        $name = $this->definition->name;
        $lookup = new ContainerLookup(client: $this->client);
        $existing = $lookup->byName(name: $name);

        return is_null($existing)
            ? new SharedStart(name: $name, lookup: $lookup, startup: $this->startup)->startOrJoin(commands: $commands)
            : Shared::reused(containerStarted: $existing);
    }
}
