<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use Throwable;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\CommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerExecute;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStop;

final class ClientMock implements Client
{
    private array $runResponses = [];

    private array $listResponses = [];

    private array $inspectResponses = [];

    private array $executeResponses = [];

    private array $stopResponses = [];

    private array $executedCommandLines = [];

    private bool $runIsSuccessful = true;

    public function withDockerRunResponse(string $output, bool $isSuccessful = true): void
    {
        $this->runResponses[] = $output;
        $this->runIsSuccessful = $isSuccessful;
    }

    public function withDockerListResponse(string $output): void
    {
        $this->listResponses[] = $output;
    }

    public function withDockerInspectResponse(array $inspectResult): void
    {
        $this->inspectResponses[] = $inspectResult;
    }

    public function withDockerExecuteResponse(string $output, bool $isSuccessful = true): void
    {
        $this->executeResponses[] = [$output, $isSuccessful];
    }

    public function withDockerExecuteException(Throwable $exception): void
    {
        $this->executeResponses[] = $exception;
    }

    public function withDockerStopResponse(string $output, bool $isSuccessful = true): void
    {
        $this->stopResponses[] = [$output, $isSuccessful];
    }

    public function getExecutedCommandLines(): array
    {
        return $this->executedCommandLines;
    }

    public function execute(Command $command): ExecutionCompleted
    {
        $this->executedCommandLines[] = $command->toCommandLine();

        if ($command instanceof CommandWithTimeout) {
            $command->getTimeoutInWholeSeconds();
        }

        if ($command instanceof DockerExecute) {
            $response = array_shift($this->executeResponses);

            if ($response instanceof Throwable) {
                throw $response;
            }

            [$output, $isSuccessful] = $response ?? ['', true];

            return new ExecutionCompletedMock(output: (string)$output, successful: $isSuccessful);
        }

        [$output, $isSuccessful] = match (true) {
            $command instanceof DockerRun     => [
                array_shift($this->runResponses) ?? '',
                $this->runIsSuccessful
            ],
            $command instanceof DockerList    => [
                ($listOutput = array_shift($this->listResponses) ?? ''),
                !empty($listOutput)
            ],
            $command instanceof DockerInspect => [
                json_encode([($inspectData = array_shift($this->inspectResponses))]),
                !empty($inspectData)
            ],
            $command instanceof DockerCopy    => ['', true],
            $command instanceof DockerStop    => array_shift($this->stopResponses) ?? ['', true],
            default                           => ['', false]
        };

        return new ExecutionCompletedMock(output: (string)$output, successful: $isSuccessful);
    }
}
