<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Container\Models\Name;

final class DockerExecuteTest extends TestCase
{
    public function testDockerExecuteCommand(): void
    {
        /** @Given a DockerExecute command */
        $command = DockerExecute::from(
            name: Name::from(value: 'container-name'),
            commandOptions: ['ls', '-la']
        );

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @Then the command line should be as expected */
        self::assertSame('docker exec container-name ls -la', $actual);
    }
}
