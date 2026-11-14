<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Containers\Run;
use TinyBlocks\Time\Duration;

final readonly class DockerReaper implements DockerCommandWithTimeout
{
    private function __construct(private Run $run, private Duration $timeout, private string $testRunnerHostname)
    {
    }

    public static function from(Run $run, Duration $timeout, string $testRunnerHostname): DockerReaper
    {
        return new DockerReaper(run: $run, timeout: $timeout, testRunnerHostname: $testRunnerHostname);
    }

    public function timeout(): Duration
    {
        return $this->timeout;
    }

    public function toArguments(): array
    {
        $template = implode(' ', [
            'while docker inspect %s >/dev/null 2>&1; do sleep 2; done;',
            'docker ps --all --quiet --filter %s | xargs -r docker rm --force --volumes >/dev/null 2>&1;',
            'docker network prune --force --filter %s >/dev/null 2>&1'
        ]);
        $script = sprintf($template, $this->testRunnerHostname, $this->run->filter(), $this->run->filter());
        $nameTemplate = 'tiny-blocks-reaper-%s';

        return [
            'docker',
            'run',
            '--rm',
            '-d',
            '--name',
            sprintf($nameTemplate, $this->run->identifier),
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
