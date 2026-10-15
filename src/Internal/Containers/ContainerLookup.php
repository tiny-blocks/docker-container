<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;

final readonly class ContainerLookup
{
    public function __construct(private DockerClient $client)
    {
    }

    public function byId(ContainerId $id, Name $name): Started
    {
        $inspectPayload = json_decode($this->client->execute(command: DockerInspect::from(id: $id))->getOutput(), true);

        if (!is_array($inspectPayload) || empty($inspectPayload[0]) || !is_array($inspectPayload[0])) {
            throw new DockerContainerNotFound(name: $name);
        }

        $inspection = ContainerInspection::from(inspectResult: $inspectPayload[0]);

        return new Started(
            id: $id,
            name: $name,
            client: $this->client,
            address: $inspection->toAddress(),
            environmentVariables: $inspection->toEnvironmentVariables()
        );
    }
}
