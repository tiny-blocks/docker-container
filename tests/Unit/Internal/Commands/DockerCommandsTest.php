<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Commands;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkConnect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkCreate;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkPrune;
use TinyBlocks\DockerContainer\Internal\Commands\DockerPull;
use TinyBlocks\DockerContainer\Internal\Commands\DockerReaper;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStop;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final class DockerCommandsTest extends TestCase
{
    public function testDockerPullGeneratesCorrectCommand(): void
    {
        /** @Given a Docker pull command for a specific image */
        $command = DockerPull::from(image: 'mysql:8.4');

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should represent pulling the specified image */
        self::assertSame(['docker', 'pull', 'mysql:8.4'], $arguments);
    }

    public function testDockerRemoveGeneratesCorrectCommand(): void
    {
        /** @Given a Docker remove command for a specific container */
        $containerId = ContainerId::from(value: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');
        $command = DockerRemove::from(id: $containerId);

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should force-remove the container with its volumes */
        self::assertSame(['docker', 'rm', '--force', '--volumes', '6acae5967be0'], $arguments);
    }

    public function testDockerNetworkCreateGeneratesCommandWithLabel(): void
    {
        /** @Given a Docker network create command */
        $command = DockerNetworkCreate::from(network: 'my-network');

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should create the network with the managed label */
        self::assertSame(
            ['docker', 'network', 'create', '--label', 'tiny-blocks.docker-container=true', 'my-network'],
            $arguments
        );
    }

    public function testDockerNetworkPruneGeneratesCommandFilteredByLabel(): void
    {
        /** @Given a Docker network prune command */
        $command = DockerNetworkPrune::create();

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should prune only networks with the managed label */
        self::assertSame(
            ['docker', 'network', 'prune', '--force', '--filter', 'label=tiny-blocks.docker-container=true'],
            $arguments
        );
    }

    public function testDockerRunIncludesManagedLabel(): void
    {
        /** @Given a Docker run command built from a container definition */
        $definition = ContainerDefinition::create(
            image: 'alpine:latest',
            name: 'test-label'
        );
        $command = DockerRun::from(definition: $definition);

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should include the managed label flag and value */
        self::assertContains('--label', $arguments);
        self::assertContains(DockerRun::MANAGED_LABEL, $arguments);
    }

    public function testDockerInspectGeneratesCorrectCommand(): void
    {
        /** @Given a Docker inspect command for a specific container */
        $containerId = ContainerId::from(value: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');
        $command = DockerInspect::from(id: $containerId);

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should invoke docker inspect on the container id */
        self::assertSame(['docker', 'inspect', '6acae5967be0'], $arguments);
    }

    public function testDockerListGeneratesCorrectCommand(): void
    {
        /** @Given a Docker list command filtered by container name */
        $command = DockerList::from(name: Name::from(value: 'my-container'));

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should list containers filtered by the exact name */
        self::assertSame(
            ['docker', 'ps', '--all', '--quiet', '--filter', 'name=^my-container$'],
            $arguments
        );
    }

    public function testDockerNetworkConnectGeneratesCorrectCommand(): void
    {
        /** @Given a Docker network connect command */
        $command = DockerNetworkConnect::from(network: 'my-network', container: 'my-container');

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should connect the container to the network */
        self::assertSame(['docker', 'network', 'connect', 'my-network', 'my-container'], $arguments);
    }

    public function testDockerStopGeneratesCorrectCommandWithStringTimeout(): void
    {
        /** @Given a Docker stop command with an integer timeout */
        $containerId = ContainerId::from(value: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');
        $command = DockerStop::from(id: $containerId, timeoutInWholeSeconds: 45);

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the arguments should include the timeout as a string argument */
        self::assertSame(['docker', 'stop', '--time', '45', '6acae5967be0'], $arguments);
    }

    public function testDockerReaperGeneratesCommandWithDockerAndScript(): void
    {
        /** @Given a Docker reaper command */
        $command = DockerReaper::from(
            reaperName: 'tiny-blocks-reaper-db',
            containerName: 'db',
            testRunnerHostname: 'runner-host'
        );

        /** @When the argument list is generated */
        $arguments = $command->toArguments();

        /** @Then the command should start with docker run and set the reaper name */
        self::assertSame('docker', $arguments[0]);
        self::assertSame('run', $arguments[1]);
        self::assertContains('tiny-blocks-reaper-db', $arguments);

        /** @And the embedded shell script should poll, remove and prune by watching the runner hostname */
        $script = end($arguments);
        self::assertStringContainsString('while docker inspect runner-host', $script);
        self::assertStringContainsString('docker rm -fv db', $script);
        self::assertStringContainsString(sprintf('label=%s', DockerRun::MANAGED_LABEL), $script);
    }

    public function testContainerIdAcceptsExactMinimumLength(): void
    {
        /** @Given a container ID whose length is exactly the minimum */
        $containerId = ContainerId::from(value: '123456789012');

        /** @Then the full string should be preserved as the container value */
        self::assertSame('123456789012', $containerId->value);
    }

    public function testContainerIdRejectsElevenCharacterValue(): void
    {
        /** @Then an InvalidArgumentException should be thrown for a value one character below the minimum */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID <12345678901> is too short. Minimum length is <12> characters.');

        /** @When a container ID shorter than the minimum length is created */
        ContainerId::from(value: '12345678901');
    }
}
