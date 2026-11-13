<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
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

        $template = 'tiny-blocks-reaper-%s';
        $reaperName = Name::from(value: sprintf($template, $definition->name->value));

        if (trim($this->client->execute(command: DockerList::from(name: $reaperName))->getOutput()) !== '') {
            return;
        }

        $this->client->executeWithTimeout(
            command: DockerReaper::from(
                timeout: $definition->pullTimeout,
                reaperName: $reaperName,
                containerName: $definition->name,
                testRunnerHostname: HostEnvironment::containerHostname()
            )
        );
    }
}
