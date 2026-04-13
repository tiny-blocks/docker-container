<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

final readonly class DockerNetworkCreate implements Command
{
    private function __construct(private string $network)
    {
    }

    public static function from(string $network): DockerNetworkCreate
    {
        return new DockerNetworkCreate(network: $network);
    }

    public function toCommandLine(): string
    {
        return sprintf('docker network create %s 2>/dev/null || true', $this->network);
    }
}
