<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkCreate;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkPrune;
use TinyBlocks\DockerContainer\Internal\Commands\DockerPull;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final class DockerCommandsTest extends TestCase
{
    public function testDockerPullGeneratesCorrectCommand(): void
    {
        /** @Given a Docker pull command for a specific image */
        $command = DockerPull::from(image: 'mysql:8.4');

        /** @When the command line is generated */
        $commandLine = $command->toCommandLine();

        /** @Then the command should pull the specified image */
        self::assertSame(expected: 'docker pull mysql:8.4', actual: $commandLine);
    }

    public function testDockerRemoveGeneratesCorrectCommand(): void
    {
        /** @Given a Docker remove command for a specific container */
        $containerId = ContainerId::from(value: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');
        $command = DockerRemove::from(id: $containerId);

        /** @When the command line is generated */
        $commandLine = $command->toCommandLine();

        /** @Then the command should force-remove the container with its volumes */
        self::assertSame(expected: 'docker rm --force --volumes 6acae5967be0', actual: $commandLine);
    }

    public function testDockerNetworkCreateGeneratesCommandWithLabel(): void
    {
        /** @Given a Docker network create command */
        $command = DockerNetworkCreate::from(network: 'my-network');

        /** @When the command line is generated */
        $commandLine = $command->toCommandLine();

        /** @Then the command should create the network with the managed label */
        self::assertSame(
            expected: 'docker network create --label tiny-blocks.docker-container=true my-network 2>/dev/null || true',
            actual: $commandLine
        );
    }

    public function testDockerNetworkPruneGeneratesCommandFilteredByLabel(): void
    {
        /** @Given a Docker network prune command */
        $command = DockerNetworkPrune::create();

        /** @When the command line is generated */
        $commandLine = $command->toCommandLine();

        /** @Then the command should prune only networks with the managed label */
        self::assertSame(
            expected: 'docker network prune --force --filter label=tiny-blocks.docker-container=true',
            actual: $commandLine
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

        /** @When the command line is generated */
        $commandLine = $command->toCommandLine();

        /** @Then the command should include the managed label */
        self::assertStringContainsString(
            needle: sprintf('--label %s', DockerRun::MANAGED_LABEL),
            haystack: $commandLine
        );
    }
}
