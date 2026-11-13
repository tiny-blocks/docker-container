<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\DockerCommand;

final readonly class ContainerInspector
{
    public function __construct(private DockerClient $client)
    {
    }

    public function inspect(DockerCommand $command): ?ContainerInspection
    {
        $inspectPayload = json_decode($this->client->execute(command: $command)->getOutput(), true);

        if (!is_array($inspectPayload) || empty($inspectPayload[0]) || !is_array($inspectPayload[0])) {
            return null;
        }

        return ContainerInspection::from(inspectResult: $inspectPayload[0]);
    }
}
