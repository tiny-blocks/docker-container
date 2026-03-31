<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;

final readonly class ExecutionCompletedMock implements ExecutionCompleted
{
    public function __construct(private string $output, private bool $successful)
    {
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }
}
