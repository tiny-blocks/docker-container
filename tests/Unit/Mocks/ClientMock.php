<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use Throwable;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerExecute;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStop;

final class ClientMock implements Client
{
    /** @var array<int, string> */
    private array $runResponses = [];

    /** @var array<int, string> */
    private array $listResponses = [];

    /** @var array<int, array<string, mixed>> */
    private array $inspectResponses = [];

    /** @var array<int, array{string, bool}|Throwable> */
    private array $executeResponses = [];

    /** @var array<int, array{string, bool}> */
    private array $stopResponses = [];

    /** @var array<int, string> */
    private array $executedCommandLines = [];

    private bool $runIsSuccessful = true;

    public function withDockerRunResponse(string $data, bool $isSuccessful = true): void
    {
        $this->runResponses[] = $data;
        $this->runIsSuccessful = $isSuccessful;
    }

    public function withDockerListResponse(string $data): void
    {
        $this->listResponses[] = $data;
    }

    public function withDockerInspectResponse(array $data): void
    {
        $this->inspectResponses[] = $data;
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

        [$output, $isSuccessful] = match (true) {
            $command instanceof DockerRun     => [array_shift($this->runResponses) ?? '', $this->runIsSuccessful],
            $command instanceof DockerList    => $this->resolveListResponse(),
            $command instanceof DockerInspect => $this->resolveInspectResponse(),
            $command instanceof DockerCopy    => ['', true],
            $command instanceof DockerExecute => $this->resolveExecuteResponse(),
            $command instanceof DockerStop    => $this->resolveStopResponse(),
            default                           => ['', false]
        };

        return new ExecutionCompletedMock(output: (string)$output, successful: $isSuccessful);
    }

    private function resolveListResponse(): array
    {
        $data = array_shift($this->listResponses) ?? '';

        return [$data, !empty($data)];
    }

    private function resolveInspectResponse(): array
    {
        $data = array_shift($this->inspectResponses);

        return [json_encode([$data]), !empty($data)];
    }

    private function resolveExecuteResponse(): array
    {
        $response = array_shift($this->executeResponses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response ?? ['', true];
    }

    private function resolveStopResponse(): array
    {
        $response = array_shift($this->stopResponses);

        return $response ?? ['', true];
    }
}
