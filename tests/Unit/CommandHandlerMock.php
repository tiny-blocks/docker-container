<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\CommandHandler;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;

final class CommandHandlerMock implements CommandHandler
{
    public function run(DockerRun $dockerRun): Container
    {
        // TODO: Implement run() method.
    }

    public function findBy(DockerList $dockerList): Container
    {
        // TODO: Implement findBy() method.
    }

    public function execute(Command $command): ExecutionCompleted
    {
        // TODO: Implement execute() method.
    }
}
