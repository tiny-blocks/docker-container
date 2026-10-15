<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Client;

use PHPUnit\Framework\TestCase;
use Test\Unit\CommandMock;
use Test\Unit\CommandWithTimeoutMock;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Client\CommandLineDockerClient;
use TinyBlocks\Time\Duration;

final class CommandLineDockerClientTest extends TestCase
{
    private CommandLineDockerClient $client;

    protected function setUp(): void
    {
        $this->client = new CommandLineDockerClient();
    }

    public function testExecuteWhenCommandSucceedsThenOutputIsReturned(): void
    {
        /** @Given a command that will succeed */
        $command = new CommandMock(command: 'echo Hello');

        /** @When the command is executed */
        $execution = $this->client->execute(command: $command);

        /** @Then the output should contain the expected result */
        self::assertTrue($execution->isSuccessful());
        self::assertStringContainsString('Hello', $execution->getOutput());
    }

    public function testExecuteWhenCommandFailsThenErrorOutputIsReturned(): void
    {
        /** @Given a command that will fail */
        $command = new CommandMock(command: 'cat /nonexistent/file/path');

        /** @When the command is executed */
        $execution = $this->client->execute(command: $command);

        /** @Then the execution should indicate failure and carry the error output */
        self::assertFalse($execution->isSuccessful());
        self::assertStringContainsString('No such file or directory', $execution->getOutput());
    }

    public function testExecuteWithTimeoutWhenCommandEndsWithinTimeoutThenItSucceeds(): void
    {
        /** @Given a command bounded by a timeout it does not reach */
        $command = new CommandWithTimeoutMock(command: 'sleep 1', timeout: Duration::fromSeconds(seconds: 5));

        /** @When the command is executed within its timeout */
        $execution = $this->client->executeWithTimeout(command: $command);

        /** @Then the execution should succeed */
        self::assertTrue($execution->isSuccessful());
    }

    public function testExecuteWithTimeoutWhenCommandOutlastsTimeoutThenDockerCommandExecutionFailed(): void
    {
        /** @Given a command that outlasts its timeout */
        $command = new CommandWithTimeoutMock(command: 'sleep 5', timeout: Duration::fromSeconds(seconds: 1));

        /** @Then a failure naming the command and the exceeded timeout should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/<'sleep' '5'>.*Reason: .*exceeded the timeout of 1 seconds/");

        /** @When the command is executed within its timeout */
        $this->client->executeWithTimeout(command: $command);
    }
}
