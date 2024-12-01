<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;

final class ClientMock implements Client
{
    private array $dockerRunResponses = [];

    private array $dockerListResponses = [];

    private array $dockerInspectResponses = [];

    public function withDockerRunResponse(string $data): void
    {
        $this->dockerRunResponses[] = $data;
    }

    public function withDockerListResponse(string $data): void
    {
        $this->dockerListResponses[] = $data;
    }

    public function withDockerInspectResponse(array $data): void
    {
        $this->dockerInspectResponses[] = $data;
    }

    public function execute(Command $command): ExecutionCompleted
    {
        $output = match (get_class($command)) {
            DockerRun::class     => array_shift($this->dockerRunResponses),
            DockerList::class    => array_shift($this->dockerListResponses),
            DockerInspect::class => json_encode([array_shift($this->dockerInspectResponses)]),
            default              => ''
        };

        return new readonly class($output) implements ExecutionCompleted {
            public function __construct(private string $output)
            {
            }

            public function getOutput(): string
            {
                return $this->output;
            }

            public function isSuccessful(): bool
            {
                return !empty($this->output);
            }
        };
    }
}
