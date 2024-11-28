<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;

final class ClientMock implements Client
{
    private array $response = [];

    public function withResponse(array $response): void
    {
        $this->response = $response;
    }

    public function execute(Command $command): ExecutionCompleted
    {
        $output = json_encode([$this->response]);

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
