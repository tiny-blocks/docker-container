<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerLookup;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

final readonly class ContainerLookup
{
    public function __construct(private DockerClient $client)
    {
    }

    public function byId(ContainerId $id, Name $name): Started
    {
        $inspection = new ContainerInspector(client: $this->client)->inspect(command: DockerInspect::from(id: $id));

        if (is_null($inspection)) {
            throw new DockerContainerNotFound(name: $name);
        }

        return Started::from(id: $id, name: $name, client: $this->client, inspection: $inspection);
    }

    public function byName(Name $name): ?Started
    {
        $inspection = new ContainerInspector(client: $this->client)->inspect(command: DockerLookup::from(name: $name));

        if (is_null($inspection)) {
            return null;
        }

        if (!$inspection->wasNeverStarted()) {
            return Started::from(
                id: $inspection->toContainerId(),
                name: $name,
                client: $this->client,
                inspection: $inspection
            );
        }

        ContainerWaitForDependency::untilReady(condition: ContainerPastCreation::from(name: $name, lookup: $this))
            ->waitBefore();

        return $this->byName(name: $name);
    }

    public function isPastCreation(Name $name): bool
    {
        $inspection = new ContainerInspector(client: $this->client)->inspect(command: DockerLookup::from(name: $name));

        return is_null($inspection) || !$inspection->wasNeverStarted();
    }

    public function wasNeverStarted(ContainerId $id): bool
    {
        $inspection = new ContainerInspector(client: $this->client)->inspect(command: DockerInspect::from(id: $id));

        return !is_null($inspection) && $inspection->wasNeverStarted();
    }
}
