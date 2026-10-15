<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\Network;

final readonly class DockerNetworkConnect implements DockerCommand
{
    private function __construct(private Network $network, private string $container)
    {
    }

    public static function from(Network $network, string $container): DockerNetworkConnect
    {
        return new DockerNetworkConnect(network: $network, container: $container);
    }

    public function toArguments(): array
    {
        return ['docker', 'network', 'connect', $this->network->name, $this->container];
    }
}
