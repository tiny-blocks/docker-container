<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerReaper;

final readonly class ContainerReaper
{
    public function __construct(private DockerClient $client)
    {
    }

    public function watch(Name $containerName): void
    {
        if (!HostEnvironment::isInsideDocker()) {
            return;
        }

        $template = 'tiny-blocks-reaper-%s';
        $reaperName = Name::from(value: sprintf($template, $containerName->value));

        if (trim($this->client->execute(command: DockerList::from(name: $reaperName))->getOutput()) !== '') {
            return;
        }

        $this->client->execute(
            command: DockerReaper::from(
                reaperName: $reaperName,
                containerName: $containerName,
                testRunnerHostname: HostEnvironment::containerHostname()
            )
        );
    }
}
