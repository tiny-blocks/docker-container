<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Client;

use PHPUnit\Framework\TestCase;
use Test\Unit\Mocks\CommandMock;
use Test\Unit\Mocks\CommandWithTimeoutMock;
use TinyBlocks\DockerContainer\Internal\Client\DockerClient;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

final class DockerClientTest extends TestCase
{
    private DockerClient $client;

    protected function setUp(): void
    {
        $this->client = new DockerClient();
    }

    public function testExecuteCommandSuccessfully(): void
    {
        /** @Given a command that will succeed */
        $command = new CommandMock(command: 'echo Hello');

        /** @When the command is executed */
        $actual = $this->client->execute(command: $command);

        /** @Then the output should contain the expected result */
        self::assertTrue($actual->isSuccessful());
        self::assertStringContainsString('Hello', $actual->getOutput());
    }

    public function testExecuteCommandWithValidTimeout(): void
    {
        /** @Given a command with a valid timeout */
        $command = new CommandWithTimeoutMock(command: 'echo Hello', timeoutInWholeSeconds: 10);

        /** @When the command is executed */
        $actual = $this->client->execute(command: $command);

        /** @Then the execution should succeed */
        self::assertTrue($actual->isSuccessful());
    }

    public function testExceptionFromProcessWhenTimeoutIsInvalid(): void
    {
        /** @Given a command with an invalid negative timeout */
        $command = new CommandWithTimeoutMock(command: 'echo Hello', timeoutInWholeSeconds: -10);

        /** @Then a DockerCommandExecutionFailed exception should be thrown via fromProcess */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Failed to execute command .* Reason: .*timeout/i');

        /** @When the command is executed */
        $this->client->execute(command: $command);
    }

    public function testExecuteCommandReturnsErrorOutput(): void
    {
        /** @Given a command that will fail */
        $command = new CommandMock(command: 'cat /nonexistent/file/path');

        /** @When the command is executed */
        $actual = $this->client->execute(command: $command);

        /** @Then the execution should indicate failure */
        self::assertFalse($actual->isSuccessful());
        self::assertNotEmpty($actual->getOutput());
    }
}
