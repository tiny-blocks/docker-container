<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final class DockerInspectTest extends TestCase
{
    public function testDockerInspectCommand(): void
    {
        /** @Given a DockerInspect command */
        $command = DockerInspect::fromId(id: ContainerId::from(value: 'abc123abc123'));

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @Then the command line should be as expected */
        self::assertSame('docker inspect abc123abc123', $actual);
    }
}
