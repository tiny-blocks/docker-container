<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Name;

final readonly class DockerReaper implements DockerCommand
{
    private function __construct(
        private Name $reaperName,
        private Name $containerName,
        private string $testRunnerHostname
    ) {
    }

    public static function from(Name $reaperName, Name $containerName, string $testRunnerHostname): DockerReaper
    {
        return new DockerReaper(
            reaperName: $reaperName,
            containerName: $containerName,
            testRunnerHostname: $testRunnerHostname
        );
    }

    public function toArguments(): array
    {
        $template = implode(' ', [
            'while docker inspect %s >/dev/null 2>&1; do sleep 2; done;',
            'docker rm -fv %s 2>/dev/null;',
            'docker network prune -f --filter label=%s 2>/dev/null'
        ]);
        $script = sprintf(
            $template,
            $this->testRunnerHostname,
            $this->containerName->value,
            DockerCreate::MANAGED_LABEL
        );

        return [
            'docker',
            'run',
            '--rm',
            '-d',
            '--name',
            $this->reaperName->value,
            '--label',
            DockerCreate::MANAGED_LABEL,
            '-v',
            '/var/run/docker.sock:/var/run/docker.sock',
            'docker:cli',
            'sh',
            '-c',
            $script
        ];
    }
}
