<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;

final readonly class DockerNetworkPrune implements DockerCommand
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
        $filter = sprintf($template, DockerCreate::MANAGED_LABEL);

        return ['docker', 'network', 'prune', '--force', '--filter', $filter];
    }
}
