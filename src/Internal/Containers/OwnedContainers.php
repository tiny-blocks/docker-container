<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;

final readonly class OwnedContainers
{
    public function __construct(private DockerClient $client)
    {
    }

    public function remove(Name $name): void
    {
        $listing = $this->client->execute(command: DockerList::from(run: Run::current(), name: $name));

        if (!$listing->isSuccessful()) {
            return;
        }

        Collection::createFrom(elements: explode(PHP_EOL, trim($listing->getOutput())))
            ->filter()
            ->each(actions: fn(string $id) => $this->client->execute(
                command: DockerRemove::from(id: ContainerId::from(value: $id))
            ));
    }
}
