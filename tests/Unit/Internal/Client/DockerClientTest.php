<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use PHPUnit\Framework\TestCase;
use Test\Unit\CommandMock;
use Test\Unit\CommandWithTimeoutMock;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

final class DockerClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new DockerClient();
    }

    public function testDockerCommandExecution(): void
    {
        /** @Given a command that will succeed */
        $command = new CommandMock(command: [' Hello, World! ']);

        /** @When the command is executed */
        $actual = $this->client->execute(command: $command);

        /** @Then the output should be the expected one */
        self::assertTrue($actual->isSuccessful());
        self::assertEquals("Hello, World!\n", $actual->getOutput());
    }

    public function testExceptionWhenDockerCommandExecutionFailed(): void
    {
        /** @Given a command that will fail due to invalid timeout */
        $command = new CommandWithTimeoutMock(command: ['Hello, World!'], timeoutInWholeSeconds: -10);

        /** @Then an exception indicating that the Docker command execution failed should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessage(
            'Failed to execute command <echo Hello, World!> in Docker container. Reason: The timeout value must be a valid positive integer or float number.'
        );

        /** @When the command is executed */
        $this->client->execute(command: $command);
    }
}
