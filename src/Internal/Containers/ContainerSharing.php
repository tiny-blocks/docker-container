<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use Closure;
use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;

final readonly class ContainerSharing
{
    public function __construct(private DockerClient $client)
    {
    }

    public function share(Name $name, Closure $start): Shared
    {
        $listing = trim($this->client->execute(command: DockerList::from(name: $name))->getOutput());
        $lookup = new ContainerLookup(client: $this->client);

        $shared = $listing === ''
            ? Shared::created(containerStarted: $start())
            : Shared::reused(containerStarted: $lookup->byId(id: ContainerId::from(value: $listing), name: $name));

        new ContainerReaper(client: $this->client)->watch(containerName: $name);

        return $shared;
    }
}
