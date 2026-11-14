<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Run;

final readonly class DockerNetworkPrune implements DockerCommand
{
    private function __construct(private Run $run)
    {
    }

    public static function from(Run $run): DockerNetworkPrune
    {
        return new DockerNetworkPrune(run: $run);
    }

    public function toArguments(): array
    {
        return ['docker', 'network', 'prune', '--force', '--filter', $this->run->filter()];
    }
}
