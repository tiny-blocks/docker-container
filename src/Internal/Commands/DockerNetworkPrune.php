<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

final readonly class DockerNetworkPrune implements Command
{
    private function __construct()
    {
    }

    public static function create(): DockerNetworkPrune
    {
        return new DockerNetworkPrune();
    }

    public function toArguments(): array
    {
        $template = 'label=%s';
        $filter = sprintf($template, DockerRun::MANAGED_LABEL);

        return ['docker', 'network', 'prune', '--force', '--filter', $filter];
    }
}
