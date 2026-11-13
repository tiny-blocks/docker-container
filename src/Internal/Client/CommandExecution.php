<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\ExecutionCompleted;

final readonly class CommandExecution
{
    private function __construct(private DockerCommand $command, private ExecutionCompleted $execution)
    {
    }

    public static function from(DockerCommand $command, ExecutionCompleted $execution): CommandExecution
    {
        return new CommandExecution(command: $command, execution: $execution);
    }

    public function successful(): ExecutionCompleted
    {
        if (!$this->execution->isSuccessful()) {
            throw DockerCommandExecutionFailed::fromCommand(command: $this->command, execution: $this->execution);
        }

        return $this->execution;
    }
}
