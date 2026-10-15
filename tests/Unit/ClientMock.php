<?php

declare(strict_types=1);

namespace Test\Unit;

use Throwable;
use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\ExecutionCompleted;

final class ClientMock implements DockerClient
{
    private const array UNANSWERED = [
        'docker ps'             => ['', false],
        'docker exec'           => ['', true],
        'docker stop'           => ['', true],
        'docker start'          => ['', true],
        'docker create'         => ['', true],
        'docker inspect'        => ['[null]', false],
        'docker start --attach' => ['', true],
        ''                      => ['', true]
    ];

    private array $responses = [
        'docker start --attach' => [],
        'docker inspect'        => [],
        'docker create'         => [],
        'docker start'          => [],
        'docker stop'           => [],
        'docker exec'           => [],
        'docker ps'             => [],
        ''                      => []
    ];

    private array $executedArguments = [];

    private array $requestedTimeouts = [];

    public function execute(DockerCommand $command): ExecutionCompleted
    {
        $arguments = $command->toArguments();
        $commandLine = implode(' ', $arguments);
        $this->executedArguments[] = $arguments;

        $prefix = (string)array_find(
            array_keys($this->responses),
            static fn(string $prefix): bool => str_starts_with($commandLine, $prefix)
        );
        $response = (array_shift($this->responses[$prefix]) ?? self::UNANSWERED[$prefix]);

        if ($response instanceof Throwable) {
            throw $response;
        }

        [$output, $isSuccessful] = $response;

        return new ExecutionCompletedMock(output: (string)$output, successful: $isSuccessful);
    }

    public function executeWithTimeout(DockerCommandWithTimeout $command): ExecutionCompleted
    {
        $this->requestedTimeouts[] = $command->timeout()->toSeconds();

        return $this->execute(command: $command);
    }

    public function getExecutedArguments(): array
    {
        return $this->executedArguments;
    }

    public function getRequestedTimeouts(): array
    {
        return $this->requestedTimeouts;
    }

    public function withDockerListResponse(string $output): void
    {
        $this->responses['docker ps'][] = [$output, !empty($output)];
    }

    public function withDockerStopResponse(string $output, bool $isSuccessful = true): void
    {
        $this->responses['docker stop'][] = [$output, $isSuccessful];
    }

    public function getExecutedCommandLines(): array
    {
        return array_map(static fn(array $arguments): string => implode(' ', $arguments), $this->executedArguments);
    }

    public function withDockerStartResponse(string $output, bool $isSuccessful = true): void
    {
        $this->responses['docker start'][] = [$output, $isSuccessful];
    }

    public function withDockerCreateResponse(string $output, bool $isSuccessful = true): void
    {
        $this->responses['docker create'][] = [$output, $isSuccessful];
    }

    public function withDockerExecuteResponse(string $output, bool $isSuccessful = true): void
    {
        $this->responses['docker exec'][] = [$output, $isSuccessful];
    }

    public function withDockerInspectResponse(array $inspectResult): void
    {
        $this->responses['docker inspect'][] = [json_encode([$inspectResult]), !empty($inspectResult)];
    }

    public function withDockerExecuteException(Throwable $exception): void
    {
        $this->responses['docker exec'][] = $exception;
    }

    public function withDockerAttachedStartResponse(string $output, bool $isSuccessful = true): void
    {
        $this->responses['docker start --attach'][] = [$output, $isSuccessful];
    }

    public function withDockerAttachedStartException(Throwable $exception): void
    {
        $this->responses['docker start --attach'][] = $exception;
    }
}
