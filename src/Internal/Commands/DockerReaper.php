<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Containers\Name;
use TinyBlocks\Time\Duration;

final readonly class DockerReaper implements DockerCommandWithTimeout
{
    private function __construct(
        private Duration $timeout,
        private Name $reaperName,
        private Name $containerName,
        private string $testRunnerHostname
    ) {
    }

    public static function from(
        Duration $timeout,
        Name $reaperName,
        Name $containerName,
        string $testRunnerHostname
    ): DockerReaper {
        return new DockerReaper(
            timeout: $timeout,
            reaperName: $reaperName,
            containerName: $containerName,
            testRunnerHostname: $testRunnerHostname
        );
    }

    public function timeout(): Duration
    {
        return $this->timeout;
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
