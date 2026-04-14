<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

final readonly class DockerNetworkConnect implements Command
{
    private function __construct(private string $network, private string $container)
    {
    }

    public static function from(string $network, string $container): DockerNetworkConnect
    {
        return new DockerNetworkConnect(network: $network, container: $container);
    }

    public function toCommandLine(): string
    {
        return sprintf('docker network connect %s %s 2>/dev/null || true', $this->network, $this->container);
    }
}
