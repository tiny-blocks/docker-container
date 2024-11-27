<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use Symfony\Component\Process\Process;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;

final readonly class Execution implements ExecutionCompleted
{
    private function __construct(private string $output, private bool $successful)
    {
    }

    public static function from(Process $process): Execution
    {
        return new Execution(output: $process->getOutput(), successful: $process->isSuccessful());
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
