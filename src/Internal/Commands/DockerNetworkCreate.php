<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\Network;

final readonly class DockerNetworkCreate implements DockerCommand
{
    private function __construct(private Network $network)
    {
    }

    public static function from(Network $network): DockerNetworkCreate
    {
        return new DockerNetworkCreate(network: $network);
    }

    public function toArguments(): array
    {
        return ['docker', 'network', 'create', '--label', DockerCreate::MANAGED_LABEL, $this->network->name];
    }
}
