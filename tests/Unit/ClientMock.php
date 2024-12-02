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
    private array $runResponses = [];

    private array $listResponses = [];

    private array $inspectResponses = [];

    private bool $runIsSuccessful;

    private bool $listIsSuccessful;

    private bool $inspectIsSuccessful;

    public function withDockerRunResponse(string $data, bool $isSuccessful = true): void
    {
        $this->runResponses[] = $data;
        $this->runIsSuccessful = $isSuccessful;
    }

    public function withDockerListResponse(string $data): void
    {
        $this->listResponses[] = $data;
        $this->listIsSuccessful = !empty($data);
    }

    public function withDockerInspectResponse(array $data): void
    {
        $this->inspectResponses[] = $data;
        $this->inspectIsSuccessful = !empty($data);
    }

    public function execute(Command $command): ExecutionCompleted
    {
        [$output, $isSuccessful] = match (get_class($command)) {
            DockerRun::class     => [array_shift($this->runResponses), $this->runIsSuccessful],
            DockerList::class    => [array_shift($this->listResponses), $this->listIsSuccessful],
            DockerInspect::class => [json_encode([array_shift($this->inspectResponses)]), $this->inspectIsSuccessful],
            default              => ['', false]
        };

        return new readonly class($output, $isSuccessful) implements ExecutionCompleted {
            public function __construct(private string $output, private bool $isSuccessful)
            {
            }

            public function getOutput(): string
            {
                return $this->output;
            }

            public function isSuccessful(): bool
            {
                return $this->isSuccessful;
            }
        };
    }
}
