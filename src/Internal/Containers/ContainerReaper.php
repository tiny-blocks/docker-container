<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerReaper;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;

final readonly class ContainerReaper
{
    public function __construct(private DockerClient $client)
    {
    }

    public function watch(ContainerDefinition $definition): void
    {
        if (!HostEnvironment::isInsideDocker()) {
            return;
        }

        $this->client->executeWithTimeout(
            command: DockerReaper::from(
                run: Run::current(),
                timeout: $definition->pullTimeout,
                testRunnerHostname: HostEnvironment::containerHostname()
            )
        );
    }
}
