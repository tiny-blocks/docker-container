<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkConnect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkCreate;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\Network;

final readonly class NetworkProvisioner
{
    public function __construct(private DockerClient $client)
    {
    }

    public function provision(Network $network): void
    {
        $this->client->execute(command: DockerNetworkCreate::from(network: $network));

        if (HostEnvironment::isInsideDocker()) {
            $command = DockerNetworkConnect::from(network: $network, container: HostEnvironment::containerHostname());
            $this->client->execute(command: $command);
        }
    }
}
