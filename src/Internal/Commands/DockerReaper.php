<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

final readonly class DockerReaper implements Command
{
    private function __construct(
        private string $reaperName,
        private string $containerName,
        private string $testRunnerHostname
    ) {
    }

    public static function from(string $reaperName, string $containerName, string $testRunnerHostname): DockerReaper
    {
        return new DockerReaper(
            reaperName: $reaperName,
            containerName: $containerName,
            testRunnerHostname: $testRunnerHostname
        );
    }

    private function buildScript(): string
    {
        $template = implode(' ', [
            'while docker inspect %s >/dev/null 2>&1; do sleep 2; done;',
            'docker rm -fv %s 2>/dev/null;',
            'docker network prune -f --filter label=%s 2>/dev/null'
        ]);

        return sprintf($template, $this->testRunnerHostname, $this->containerName, DockerRun::MANAGED_LABEL);
    }

    public function toArguments(): array
    {
        return [
            'docker',
            'run',
            '--rm',
            '-d',
            '--name',
            $this->reaperName,
            '--label',
            DockerRun::MANAGED_LABEL,
            '-v',
            '/var/run/docker.sock:/var/run/docker.sock',
            'docker:cli',
            'sh',
            '-c',
            $this->buildScript()
        ];
    }
}
