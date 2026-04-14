<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerReaper;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final readonly class ContainerReaper
{
    public function __construct(private Client $client)
    {
    }


    public function ensureRunningFor(string $containerName): void
    {
        if (!file_exists('/.dockerenv')) {
            return;
        }

        $reaperName = sprintf('tiny-blocks-reaper-%s', $containerName);
        $reaperList = DockerList::from(name: Name::from(value: $reaperName));
        $reaperExists = !empty(trim($this->client->execute(command: $reaperList)->getOutput()));

        if ($reaperExists) {
            return;
        }

        $this->client->execute(
            command: DockerReaper::from(
                reaperName: $reaperName,
                containerName: $containerName,
                testRunnerHostname: gethostname()
            )
        );
    }
}
