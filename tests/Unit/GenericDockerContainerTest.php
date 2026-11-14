<?php

declare(strict_types=1);

namespace Test\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;
use TinyBlocks\Time\Duration;

final class GenericDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        ClockMock::freeze();
        HostMock::outsideDocker();

        $this->client = new ClientMock();
    }

    protected function tearDown(): void
    {
        HostMock::reset();
        ClockMock::release();
    }

    public function testRunWhenNoNameIsGivenThenANameIsGenerated(): void
    {
        /** @Given a container without a name */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'auto-generated'
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should have an auto-generated name (non-empty) */
        self::assertNotEmpty($started->getName());
    }

    public function testFromWhenImageNameIsEmptyThenImageNameEmpty(): void
    {
        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image name cannot be empty.');

        /** @When creating a container with an empty image name */
        GenericDockerContainer::from(image: '');
    }

    public function testRemoveWhenCalledTwiceThenNoFailureIsRaised(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'already-removed');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'already-removed')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When remove is called twice */
        $started->remove();
        $started->remove();

        /** @Then both removals should have been issued without any failure */
        self::assertCount(
            2,
            array_filter(
                $this->client->getExecutedCommandLines(),
                static fn(string $commandLine): bool => str_starts_with($commandLine, 'docker rm')
            )
        );
    }

    public function testRunWhenBindingHasNoHostPortThenItIsSkipped(): void
    {
        /** @Given a container whose inspect payload includes a binding with no HostPort key */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-host-port-key');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns an inspect response with a partial binding */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-host-port-key',
                exposedPorts: ['80/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp' => [['HostIp' => '0.0.0.0']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then bindings lacking HostPort should be skipped without errors */
        self::assertEmpty($started->getAddress()->getPorts()->hostPorts());
    }

    public function testRunWhenNoPortIsExposedThenFirstPortsAreNull(): void
    {
        /** @Given a container with no exposed ports */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-ports');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'no-ports'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then firstExposedPort should return null */
        self::assertNull($started->getAddress()->getPorts()->firstExposedPort());
        self::assertEmpty($started->getAddress()->getPorts()->exposedPorts());

        /** @And firstHostPort should return null */
        self::assertNull($started->getAddress()->getPorts()->firstHostPort());
        self::assertEmpty($started->getAddress()->getPorts()->hostPorts());
    }

    public function testRunThenCreateCommandCarriesTheLabelOfThisRun(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'labeled');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'labeled'));

        /** @When the container is started */
        $container->run();

        /** @Then the container should carry the managed label and the label of this run */
        $template = '--label tiny-blocks.docker-container=true --label tiny-blocks.docker-container.run=%s-%d ';
        self::assertStringContainsString(
            sprintf($template, gethostname(), getmypid()),
            $this->client->getExecutedCommandLines()[0]
        );
    }

    public function testRunWhenCommandsAreGivenThenContainerIsStarted(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'cmd-test');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'cmd-test'));

        /** @When the container is started with commands */
        $started = $container->run(commands: ['echo', 'hello']);

        /** @Then the container should be running */
        self::assertSame('cmd-test', $started->getName());
    }

    public function testRunWhenAFileIsRegisteredThenContainerIsStarted(): void
    {
        /** @Given a container with files to copy */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'copy-test')
            ->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'copy-test'));

        /** @When the container is started (docker cp is automatically called) */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('copy-test', $started->getName());
    }

    public function testRunWhenHostPortIsMappedThenBothPortsAreExposed(): void
    {
        /** @Given a container with a host port mapping */
        $container = GenericDockerContainer::from(image: 'mysql:8.4', client: $this->client)
            ->withName(name: 'host-port')
            ->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns a response with host port bindings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'host-port',
                exposedPorts: ['3306/tcp' => (object)[]],
                hostPortBindings: [
                    '3306/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '33060']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the exposed port should be the container-internal port */
        self::assertSame(3306, $started->getAddress()->getPorts()->firstExposedPort());

        /** @And the host port should be the host-mapped port */
        self::assertSame(33060, $started->getAddress()->getPorts()->firstHostPort());
        self::assertSame([33060], $started->getAddress()->getPorts()->hostPorts());
    }

    public function testRunWhenHostPortIsZeroThenItIsDroppedFromResult(): void
    {
        /** @Given a container whose inspect payload reports a binding with HostPort equal to zero */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'zero-host-port');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns an inspect response with HostPort zero alongside a valid binding */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'zero-host-port',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp'  => [['HostIp' => '0.0.0.0', 'HostPort' => '0']],
                    '443/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '8443']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then only strictly positive host ports should be retained */
        self::assertSame([8443], $started->getAddress()->getPorts()->hostPorts());
    }

    public function testStopWhenContainerIsRunningThenStopIsSuccessful(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-test');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-test'));

        /** @And the Docker daemon stops the container */
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped */
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testStopWhenGracePeriodIsGivenThenStopIsSuccessful(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-timeout');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-timeout')
        );

        /** @And the Docker daemon stops the container */
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testRunWhenPortIsMappedThenCreateCommandPublishesIt(): void
    {
        /** @Given a container with a port mapping */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'port-cmd')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'port-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the executed docker run command should contain the port mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
    }

    public function testRunContainerThenInspectCommandTargetsContainerId(): void
    {
        /** @Given a container configured with an image and a name */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'inspect-cmd');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'inspect-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then a docker inspect command should have been executed for the container ID */
        $inspectCommand = $this->client->getExecutedCommandLines()[2];
        self::assertSame(sprintf('docker inspect %s', InspectResponseFixture::shortContainerId()), $inspectCommand);
    }

    public function testRunWhenNetworkIsSetThenNetworkIsCreatedAndJoined(): void
    {
        /** @Given a container with a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'net-cmd')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'net-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the first command should be the network creation, labeled with the managed label and this run */
        $template = 'docker network create --label %s --label tiny-blocks.docker-container.run=%s-%d my-network';
        self::assertSame(
            sprintf($template, 'tiny-blocks.docker-container=true', gethostname(), getmypid()),
            $this->client->getExecutedCommandLines()[0]
        );

        /** @And the docker run command should contain the network argument */
        $runCommand = $this->client->getExecutedCommandLines()[1];
        self::assertStringContainsString('--network=my-network', $runCommand);
    }

    public function testRunWhenAutoRemoveIsDisabledThenContainerIsStarted(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'persistent')
            ->withoutAutoRemove();

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'persistent'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('persistent', $started->getName());
    }

    public function testRunWhenDockerReturnsAnEmptyIdThenContainerIdEmpty(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'empty-id');

        /** @And the Docker daemon returns an empty container ID */
        $this->client->withDockerCreateResponse(output: '   ');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID cannot be empty.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenStartFailsThenDockerCommandExecutionFailed(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'start-fails');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it */
        $this->client->withDockerStartResponse(output: 'port is already allocated', isSuccessful: false);

        /** @Then the failure should name the start command and carry the reason reported by Docker */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/<'docker' 'start' '6acae5967be0'>.*Reason: port is already allocated/");

        /** @When the container is started */
        $container->run();
    }

    public function testGetValueByWhenKeyIsMissingThenReturnsAnEmptyString(): void
    {
        /** @Given a running container with known environment variables */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'env-test');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'env-test',
                environment: ['KNOWN=value']
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When querying for a missing environment variable */
        $missingValue = $started->getEnvironmentVariables()->getValueBy(key: 'MISSING');

        /** @Then it should return an empty string */
        self::assertSame('', $missingValue);
    }

    public function testRunWhenInsideDockerThenHostIsConnectedToTheNetwork(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container configured with a network, running inside a Docker environment */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'inside-docker')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the hostname */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'inside-docker'));

        /** @When the container is started */
        $container->run();

        /** @Then a docker network connect command should connect the host to the network, after the reaper starts */
        $connectCommand = $client->getExecutedCommandLines()[2];
        self::assertStringStartsWith('docker network connect my-network', $connectCommand);
    }

    public function testRunWhenTwoWaitsBeforeRunAreAddedThenBothAreApplied(): void
    {
        /** @Given a first condition that is immediately ready */
        $first = $this->createMock(ContainerReady::class);

        /** @And the condition holds on its only check */
        $first->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a second condition that is immediately ready */
        $second = $this->createMock(ContainerReady::class);

        /** @And the condition holds on its only check */
        $second->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container that waits for both conditions before it runs */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'two-waits-before')
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $first))
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $second));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'two-waits-before')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running after both conditions held */
        self::assertSame('two-waits-before', $started->getName());
    }

    public function testRunWhenVolumesAreMappedThenCreateCommandMountsThem(): void
    {
        /** @Given a container with two volume mappings */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'vol-cmd')
            ->withVolumeMapping(pathOnHost: '/host/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/host/logs', pathOnContainer: '/app/logs');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'vol-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the first volume mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--volume /host/data:/app/data', $runCommand);

        /** @And the docker run command should contain the second volume mapping argument */
        self::assertStringContainsString('--volume /host/logs:/app/logs', $runCommand);
    }

    public function testRunWhenWaitAfterStartedFailsThenItsFailureIsThrown(): void
    {
        /** @Given a wait strategy that fails once the container has started */
        $wait = $this->createStub(ContainerWaitAfterStarted::class);

        /** @And the wait strategy times out */
        $wait->method('waitAfter')->willThrowException(
            ContainerWaitTimeout::forReadiness(timeout: Duration::fromSeconds(seconds: 1))
        );

        /** @And a container that applies that wait strategy after it starts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'orphan-failure')
            ->withWaitAfterStarted(wait: $wait);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'orphan-failure')
        );

        /** @Then the failure of the wait strategy should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <1> seconds.');

        /** @When the container is started */
        $container->run();
    }

    public function testStopContainerWhenTimeoutIsZeroThenStopIsSuccessful(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-zero');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-zero'));

        /** @And the Docker daemon stops the container */
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a zero graceful timeout */
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 0));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testRunWhenAutoRemoveIsDisabledThenCreateCommandOmitsRm(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-rm-cmd')
            ->withoutAutoRemove();

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'no-rm-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should NOT contain --rm */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringNotContainsString('--rm', $runCommand);
    }

    public function testRunWhenInspectionIsEmptyThenDockerContainerNotFound(): void
    {
        /** @Given a container whose inspect returns empty data */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'ghost');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns a valid ID but empty inspect */
        $this->client->withDockerInspectResponse(inspectResult: []);

        /** @Then a DockerContainerNotFound exception should be thrown */
        $this->expectException(DockerContainerNotFound::class);
        $this->expectExceptionMessage('Docker container with name <ghost> was not found.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenCommandsAreGivenThenCreateCommandEndsWithThem(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'args-cmd');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'args-cmd'));

        /** @When the container is started with commands */
        $container->run(commands: ['-connectRetries=15', 'clean', 'migrate']);

        /** @Then the docker run command should end with the commands */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('-connectRetries=15 clean migrate', $runCommand);
    }

    public function testRunWhenCreationFailsThenDockerCommandExecutionFailed(): void
    {
        /** @Given a container that will fail to start */
        $container = GenericDockerContainer::from(image: 'invalid:image', client: $this->client)
            ->withName(name: 'fail-test');

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerCreateResponse(output: 'Cannot connect to the Docker daemon.', isSuccessful: false);

        /** @Then a DockerCommandExecutionFailed exception should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Cannot connect to the Docker daemon/');

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenExposedPortHasNoHostBindingThenHostPortIsNull(): void
    {
        /** @Given a container with an exposed port but no host binding */
        $container = GenericDockerContainer::from(image: 'redis:latest', client: $this->client)
            ->withName(name: 'no-host-bind');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns a response with exposed port but null host bindings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-host-bind',
                exposedPorts: ['6379/tcp' => (object)[]],
                hostPortBindings: ['6379/tcp' => null]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the exposed port should be available */
        self::assertSame(6379, $started->getAddress()->getPorts()->firstExposedPort());

        /** @And the host port should be null since there is no binding */
        self::assertNull($started->getAddress()->getPorts()->firstHostPort());
        self::assertEmpty($started->getAddress()->getPorts()->hostPorts());
    }

    public function testRunWhenFileCopyFailsThenDockerCommandExecutionFailed(): void
    {
        /** @Given a container with a file to copy */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'copy-fails')
            ->copyToContainer(pathOnHost: '/host/missing', pathOnContainer: '/app/config');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to copy the file */
        $this->client->withDockerCopyResponse(output: 'lstat /host/missing: no such file', isSuccessful: false);

        /** @Then the failure should name the copy command and carry the reason reported by Docker */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/<'docker' 'cp' .*Reason: lstat \/host\/missing: no such file/");

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenSeveralVolumesAreMappedThenContainerIsStarted(): void
    {
        /** @Given a container with multiple volume mappings */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'multi-vol')
            ->withVolumeMapping(pathOnHost: '/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'multi-vol'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('multi-vol', $started->getName());
    }

    public function testRunWhenWaitBeforeRunTimesOutThenContainerWaitTimeout(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createStub(ContainerReady::class);

        /** @And the condition never holds */
        $condition->method('isReady')->willReturn(false);

        /** @And a container with a wait-before-run that has a short timeout */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'timeout-wait')
            ->withWaitBeforeRun(
                wait: ContainerWaitForDependency::untilReady(condition: $condition)
                    ->withTimeout(timeout: Duration::fromSeconds(seconds: 1))
            );

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When the container is started */
        $container->run();
    }

    public function testStopOnShutdownWhenProcessExitsThenContainerIsRemoved(): void
    {
        /** @Given shutdown callbacks are captured instead of being registered */
        HostMock::captureShutdown();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'shutdown-run');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'shutdown-run'));

        /** @And the container is started and registered for removal on shutdown */
        $container->run()->stopOnShutdown();

        /** @When the registered shutdown callback runs */
        HostMock::runShutdown();

        /** @Then the container should have been removed */
        self::assertContains(
            sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId()),
            $client->getExecutedCommandLines()
        );
    }

    public function testStopWhenContainerIsRunningThenDockerStopTargetsItsId(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-cmd');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-cmd'));

        /** @And the Docker daemon stops the container */
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then a docker stop command should have been executed with the container ID */
        $stopCommand = $this->client->getExecutedCommandLines()[3];
        self::assertStringStartsWith('docker stop', $stopCommand);
        self::assertStringContainsString(InspectResponseFixture::shortContainerId(), $stopCommand);
    }

    public function testRunIfNotExistsWhenContainerExistsThenStartedWasReused(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reused-flag');

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'reused-flag'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the started container should report that it was reused */
        self::assertTrue($started->wasReused());
    }

    public function testRunWhenTmpfsMountsAreAddedThenCreateCommandMountsThem(): void
    {
        /** @Given a container with two tmpfs mounts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'tmpfs-cmd')
            ->withTmpfsMount(pathOnContainer: '/app/cache')
            ->withTmpfsMount(pathOnContainer: '/app/sessions');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'tmpfs-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker create command should mount both directories in memory */
        self::assertStringContainsString(
            '--tmpfs /app/cache --tmpfs /app/sessions',
            $this->client->getExecutedCommandLines()[0]
        );
    }

    public function testRunIfNotExistsWhenOutsideDockerThenSkipsReaperCreation(): void
    {
        /** @Given the code runs on the host, outside Docker */
        HostMock::outsideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container that already exists, running outside a Docker environment */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'reaper-outside');

        /** @And the Docker inspect returns the container details */
        $client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'reaper-outside'));

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then no reaper container should have been started */
        self::assertStringNotContainsString(
            'tiny-blocks-reaper',
            implode(PHP_EOL, $client->getExecutedCommandLines())
        );
    }

    public function testRunWhenAFileIsRegisteredThenDockerCopyCarriesBothPaths(): void
    {
        /** @Given a container with a copy instruction */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'cp-cmd')
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'cp-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the second executed command should be a docker cp with the correct arguments */
        $copyCommand = $this->client->getExecutedCommandLines()[1];
        self::assertStringStartsWith('docker cp', $copyCommand);
        self::assertStringContainsString('/host/sql', $copyCommand);
        self::assertStringContainsString('/app/sql', $copyCommand);
    }

    public function testRunWhenDockerReturnsATooShortIdThenContainerIdTooShort(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'short-id');

        /** @And the Docker daemon returns a too-short container ID */
        $this->client->withDockerCreateResponse(output: 'abc123');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID <abc123> is too short. Minimum length is <12> characters.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenOutsideDockerThenHostIsNotConnectedToTheNetwork(): void
    {
        /** @Given the code runs on the host, outside Docker */
        HostMock::outsideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container configured with a network, running outside a Docker environment */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'outside-docker')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'outside-docker')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('outside-docker', $started->getName());

        /** @And no network connect command should have been executed for the host */
        $commandLines = $client->getExecutedCommandLines();

        foreach ($commandLines as $commandLine) {
            self::assertStringNotContainsString(
                'docker network connect',
                $commandLine
            );
        }
    }

    public function testRunWhenSeveralFilesAreRegisteredThenContainerIsStarted(): void
    {
        /** @Given a container with multiple copy instructions */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'multi-copy')
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql')
            ->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'multi-copy'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (both docker cp calls were made) */
        self::assertSame('multi-copy', $started->getName());
    }

    public function testRunWhenFullyConfiguredThenEnvironmentAndPortsAreExposed(): void
    {
        /** @Given a fully configured container */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'web-server')
            ->withNetwork(name: 'my-network')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withVolumeMapping(pathOnHost: '/var/www', pathOnContainer: '/usr/share/nginx/html')
            ->withEnvironmentVariable(key: 'NGINX_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'NGINX_PORT', value: '80');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'web-server',
                environment: ['NGINX_HOST=localhost', 'NGINX_PORT=80'],
                networkName: 'my-network',
                exposedPorts: ['80/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should expose the configured environment variables */
        self::assertSame(
            'localhost',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'NGINX_HOST'
            )
        );
        self::assertSame('80', $started->getEnvironmentVariables()->getValueBy(key: 'NGINX_PORT'));

        /** @And the address should reflect the exposed port */
        self::assertSame(80, $started->getAddress()->getPorts()->firstExposedPort());
        self::assertSame([80], $started->getAddress()->getPorts()->exposedPorts());
    }

    public function testWithPullTimeoutThenLeavesTheOriginalDefinitionUntouched(): void
    {
        /** @Given a container with the default pull timeout */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'untouched-pull');

        /** @And a copy of it with a pull timeout of ten minutes, which is discarded */
        $container->withPullTimeout(timeout: Duration::fromSeconds(seconds: 600));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'untouched-pull')
        );

        /** @When the original container is started */
        $container->run();

        /** @Then the creation of the container should still be bounded by the default pull timeout */
        self::assertSame([300], $this->client->getRequestedTimeouts());
    }

    public function testExecuteAfterStartedWhenCommandFailsThenReturnsTheFailure(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'exec-fail');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-fail'));

        /** @And the command executed inside the container fails */
        $this->client->withDockerExecuteResponse(output: 'command not found', isSuccessful: false);

        /** @And the container is started */
        $started = $container->run();

        /** @When an invalid command is executed */
        $execution = $started->executeAfterStarted(commands: ['invalid-command']);

        /** @Then the result should indicate failure */
        self::assertFalse($execution->isSuccessful());
        self::assertSame('command not found', $execution->getOutput());
    }

    public function testRunIfNotExistsWhenContainerIsMissingThenANewOneIsStarted(): void
    {
        /** @Given a container that does not exist */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'new-container')
            ->withEnvironmentVariable(key: 'APP_ENV', value: 'test');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'new-container',
                environment: ['APP_ENV=test']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created */
        self::assertSame('new-container', $started->getName());
        self::assertSame('test', $started->getEnvironmentVariables()->getValueBy(key: 'APP_ENV'));
    }

    public function testRunIfNotExistsWhenContainerIsReusedThenNoReaperIsStarted(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a container that another run already started */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reused-elsewhere');

        /** @And the lookup returns the container details */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'reused-elsewhere')
        );

        /** @When the container is asked for */
        $container->runIfNotExists();

        /** @Then only the lookup should run, since this run created nothing to reap */
        self::assertSame(
            ['docker inspect --type container reused-elsewhere'],
            $this->client->getExecutedCommandLines()
        );
    }

    public function testRunWhenAutoRemoveIsNotDisabledThenCreateCommandCarriesRm(): void
    {
        /** @Given a container with default configuration */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'rm-cmd');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'rm-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain --rm */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--rm', $runCommand);
    }

    public function testRunWhenSeveralHostPortsAreBoundThenAllHostPortsAreListed(): void
    {
        /** @Given a container with multiple host port mappings */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'multi-host-port')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns a response with multiple host port bindings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-host-port',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp'  => [['HostIp' => '0.0.0.0', 'HostPort' => '8080']],
                    '443/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '8443']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then both exposed and host ports should be available */
        self::assertSame([80, 443], $started->getAddress()->getPorts()->exposedPorts());
        self::assertSame([8080, 8443], $started->getAddress()->getPorts()->hostPorts());
        self::assertSame(8080, $started->getAddress()->getPorts()->firstHostPort());
    }

    public function testRunWhenSeveralPortsAreMappedThenAllExposedPortsAreListed(): void
    {
        /** @Given a container with multiple port mappings */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'multi-port')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-port',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then both ports should be exposed */
        self::assertSame([80, 443], $started->getAddress()->getPorts()->exposedPorts());
        self::assertSame(80, $started->getAddress()->getPorts()->firstExposedPort());
    }

    public function testStopOnShutdownWhenCalledThenRegistersOneShutdownCallback(): void
    {
        /** @Given shutdown callbacks are captured instead of being registered */
        HostMock::captureShutdown();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'shutdown-test');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'shutdown-test'));

        /** @And the container is started */
        $started = $container->run();

        /** @When stopOnShutdown is called */
        $started->stopOnShutdown();

        /** @Then exactly one shutdown callback should have been registered */
        self::assertSame(1, HostMock::shutdownCallbackCount());
    }

    public function testStopWhenContainerIsSharedThenStopsTheUnderlyingContainer(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'shared-stop');

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'shared-stop'));

        /** @And the shared container is obtained */
        $started = $container->runIfNotExists();

        /** @When the shared container is stopped with a grace period of 30 seconds */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 30));

        /** @Then the underlying container should be stopped with that grace period */
        self::assertContains(
            sprintf('docker stop --time 30 %s', InspectResponseFixture::shortContainerId()),
            $this->client->getExecutedCommandLines()
        );
    }

    public function testRunWhenContainerStartsThenItsIdentityAndAddressAreExposed(): void
    {
        /** @Given a container configured with an image and a name */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'test-alpine');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'test-alpine',
                environment: ['PATH=/usr/local/bin']
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running with the expected properties */
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('test-alpine', $started->getName());
        self::assertSame('test-alpine', $started->getAddress()->getHostname());
        self::assertSame('172.22.0.2', $started->getAddress()->getIp());
    }

    public function testRunWhenSeveralPortsAreMappedThenCreateCommandPublishesAll(): void
    {
        /** @Given a container with multiple port mappings */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'multi-port-cmd')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-port-cmd'
            )
        );

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain both port mapping arguments */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
        self::assertStringContainsString('--publish 8443:443', $runCommand);
    }

    public function testExecuteAfterStartedWhenCommandSucceedsThenReturnsItsOutput(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'exec-test');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-test'));

        /** @And the command executed inside the container succeeds */
        $this->client->withDockerExecuteResponse(output: 'command output');

        /** @And the container is started */
        $started = $container->run();

        /** @When commands are executed inside the running container */
        $execution = $started->executeAfterStarted(commands: ['ls', '-la']);

        /** @Then the execution should be successful */
        self::assertTrue($execution->isSuccessful());
        self::assertSame('command output', $execution->getOutput());
    }

    public function testGetPortForConnectionWhenInsideDockerThenReturnsExposedPort(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container with a host port mapping */
        $container = GenericDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: 'conn-port')
            ->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries exposed and host-mapped ports */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'conn-port',
                exposedPorts: ['3306/tcp' => (object)[]],
                hostPortBindings: ['3306/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '33060']]]
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection port is resolved inside Docker */
        $port = $started->getAddress()->getPorts()->getPortForConnection();

        /** @Then it should be the container-internal exposed port */
        self::assertSame(3306, $port);
    }

    public function testRunContainerWhenExposedPortIsZeroThenItIsDroppedFromResult(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'zero-exposed');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns an inspect response with a zero exposed port alongside a valid one */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'zero-exposed',
                exposedPorts: ['0/tcp' => (object)[], '3306/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then only the strictly positive exposed port should be retained */
        self::assertSame([3306], $started->getAddress()->getPorts()->exposedPorts());
    }

    public function testRunIfNotExistsWhenContainerExistsThenItsDetailsAreReturned(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'existing');

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'existing',
                environment: ['EXISTING=true']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the existing container should be returned */
        self::assertSame('existing', $started->getName());
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('true', $started->getEnvironmentVariables()->getValueBy(key: 'EXISTING'));
    }

    public function testRunIfNotExistsThenLookupCommandTargetsTheExactContainerName(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'list-cmd');

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'list-cmd'));

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then the first command should look the container up by its exact name */
        self::assertSame('docker inspect --type container list-cmd', $this->client->getExecutedCommandLines()[0]);
    }

    public function testRunIfNotExistsWhenContainerIsMissingThenStartedWasNotReused(): void
    {
        /** @Given a container that does not exist */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'fresh-flag');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'fresh-flag'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the started container should report that it was not reused */
        self::assertFalse($started->wasReused());
    }

    public function testRunToCompletionWhenContainerRanAndFailedThenItIsNotRunAgain(): void
    {
        /** @Given a one-shot container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the container runs and exits with a non-zero status */
        $this->client->withDockerAttachedStartResponse(output: 'migration failed', isSuccessful: false);

        /** @And the inspection shows the container did run and exit */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(status: 'exited'));

        /** @Then the error the container wrote should be thrown, without a second attempt */
        $this->expectException(ContainerExitUnsuccessful::class);
        $this->expectExceptionMessageMatches('/Output: <migration failed>\.$/');

        /** @When the container runs to completion */
        $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));
    }

    public function testRunWhenBindingsMixValidAndNullThenValidHostPortsAreRetained(): void
    {
        /** @Given a container whose inspect payload mixes valid and null host bindings */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'mixed-bindings')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns an inspect response with a trailing null binding */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'mixed-bindings',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[], '5432/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp'   => [['HostIp' => '0.0.0.0', 'HostPort' => '8080']],
                    '443/tcp'  => [['HostIp' => '0.0.0.0', 'HostPort' => '8443']],
                    '5432/tcp' => null
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the host-mapped ports should retain all previously collected ports when a null binding follows */
        self::assertSame([8080, 8443], $started->getAddress()->getPorts()->hostPorts());
    }

    public function testRunWhenCreationFailsThenFailureRendersShellEscapedArguments(): void
    {
        /** @Given a container that will fail to start */
        $container = GenericDockerContainer::from(image: 'invalid:image', client: $this->client)
            ->withName(name: 'fail-render-test');

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerCreateResponse(output: 'boom', isSuccessful: false);

        /** @Then the failure message should render each argument shell-escaped */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/'docker' 'create'/");

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenEnvironmentVariableIsAddedThenCreateCommandCarriesIt(): void
    {
        /** @Given a container with an environment variable */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'env-cmd')
            ->withEnvironmentVariable(key: 'APP_ENV', value: 'production');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'env-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the environment variable argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--env APP_ENV=production', $runCommand);
    }

    public function testRunWhenInsideDockerThenReaperStartIsBoundedByThePullTimeout(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a container with a pull timeout of ten minutes */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reaper-pull')
            ->withPullTimeout(timeout: Duration::fromSeconds(seconds: 600));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'reaper-pull'));

        /** @When the container is started */
        $container->run();

        /** @Then starting the reaper and creating the container should both be bounded by the pull timeout */
        self::assertSame([600, 600], $this->client->getRequestedTimeouts());
    }

    public function testRunWhenNetworkDetailsAreEmptyThenAddressFallsBackToDefaults(): void
    {
        /** @Given a container with empty network info */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-net');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon returns a response with empty address data */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: '',
                ipAddress: ''
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the address should fall back to defaults */
        self::assertSame('127.0.0.1', $started->getAddress()->getIp());
        self::assertSame('localhost', $started->getAddress()->getHostname());
    }

    public function testRunWhenPullTimeoutIsGivenThenContainerCreationIsBoundedByIt(): void
    {
        /** @Given a container whose image may take up to ten minutes to pull */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'slow-pull')
            ->withPullTimeout(timeout: Duration::fromSeconds(seconds: 600));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'slow-pull'));

        /** @When the container is started */
        $container->run();

        /** @Then the creation of the container should be bounded by the given pull timeout */
        self::assertSame([600], $this->client->getRequestedTimeouts());
    }

    public function testRunWhenSeveralEnvironmentVariablesAreAddedThenAllAreExposed(): void
    {
        /** @Given a container with multiple environment variables */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'multi-env')
            ->withEnvironmentVariable(key: 'DB_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'DB_PORT', value: '5432')
            ->withEnvironmentVariable(key: 'DB_NAME', value: 'mydb');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-env',
                environment: ['DB_HOST=localhost', 'DB_PORT=5432', 'DB_NAME=mydb']
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then all environment variables should be accessible */
        self::assertSame(
            'localhost',
            $started->getEnvironmentVariables()->getValueBy(key: 'DB_HOST')
        );
        self::assertSame('5432', $started->getEnvironmentVariables()->getValueBy(key: 'DB_PORT'));
        self::assertSame('mydb', $started->getEnvironmentVariables()->getValueBy(key: 'DB_NAME'));
    }

    public function testRunWhenWaitAfterStartedFailsThenTheStartedContainerIsRemoved(): void
    {
        /** @Given a wait strategy that fails once the container has started */
        $wait = $this->createStub(ContainerWaitAfterStarted::class);

        /** @And the wait strategy times out */
        $wait->method('waitAfter')->willThrowException(
            ContainerWaitTimeout::forReadiness(timeout: Duration::fromSeconds(seconds: 1))
        );

        /** @And a container that applies that wait strategy after it starts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'orphan')
            ->withWaitAfterStarted(wait: $wait);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'orphan'));

        try {
            /** @When the container is started */
            $container->run();
        } catch (ContainerWaitTimeout) {
            /** @Then the started container should be removed, since no one holds it */
            self::assertSame(
                sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId()),
                array_last($this->client->getExecutedCommandLines())
            );
        }
    }

    public function testRunWhenWaitAfterStartedIsRegisteredThenItPausesAfterStarting(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'wait-after')
            ->withWaitAfterStarted(
                wait: ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 1))
            );

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-after'));

        /** @When the container is started with a wait-after condition */
        $started = $container->run();

        /** @Then the container should have waited one second after starting */
        self::assertSame('wait-after', $started->getName());
        self::assertSame(1.0, ClockMock::elapsedInSeconds());
    }

    public function testRunWhenStartCommandCannotRunThenTheLeftoverContainerIsRemoved(): void
    {
        /** @Given a container that joins no network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'start-timeout');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the start command exceeds the timeout of the client */
        $this->client->withDockerStartException(
            exception: DockerCommandExecutionFailed::from(reason: 'exceeded the timeout', command: 'docker start')
        );

        try {
            /** @When the container is started */
            $container->run();
        } catch (DockerCommandExecutionFailed) {
            /** @Then the leftover container should be removed */
            self::assertSame(
                sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId()),
                array_last($this->client->getExecutedCommandLines())
            );
        }
    }

    public function testGetPortForConnectionWhenOutsideDockerThenReturnsHostMappedPort(): void
    {
        /** @Given the code runs on the host, outside Docker */
        HostMock::outsideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container with a host port mapping */
        $container = GenericDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: 'conn-port')
            ->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries exposed and host-mapped ports */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'conn-port',
                exposedPorts: ['3306/tcp' => (object)[]],
                hostPortBindings: ['3306/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '33060']]]
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection port is resolved outside Docker */
        $port = $started->getAddress()->getPorts()->getPortForConnection();

        /** @Then it should be the host-mapped port */
        self::assertSame(33060, $port);
    }

    public function testRunIfNotExistsWhenContainerIsMissingThenWaitBeforeRunIsApplied(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);

        /** @And the condition holds on its only check */
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run that does not exist */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'wait-new')
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-new'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the wait-before-run should have been evaluated and the container created */
        self::assertSame('wait-new', $started->getName());
    }

    public function testRunWhenFilesAreCopiedThenTheyAreCopiedBeforeTheContainerStarts(): void
    {
        /** @Given a container with a copy instruction */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'cp-before-start')
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'cp-before-start')
        );

        /** @When the container is started */
        $container->run();

        /** @Then the container should be created, receive the files, be started, and only then be inspected */
        self::assertSame(
            ['docker create', 'docker cp', 'docker start', 'docker inspect'],
            array_map(
                static fn(array $arguments): string => implode(' ', array_slice($arguments, 0, 2)),
                $this->client->getExecutedArguments()
            )
        );
    }

    public function testRunWhenInsideDockerThenReaperStartsBeforeTheContainerIsCreated(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reaped');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'reaped'));

        /** @When the container is started */
        $container->run();

        /** @Then the reaper of this run should start first, named after the run */
        $template = 'docker run --rm -d --name tiny-blocks-reaper-%s-%d --label tiny-blocks.docker-container=true';
        self::assertStringStartsWith(
            sprintf($template, gethostname(), getmypid()),
            $this->client->getExecutedCommandLines()[0]
        );

        /** @And the container should be created right after it */
        self::assertStringStartsWith('docker create', $this->client->getExecutedCommandLines()[1]);
    }

    public function testRunWhenTwoWaitsAfterStartedAreAddedThenBothReceiveTheContainer(): void
    {
        /** @Given a first wait strategy applied after the container starts */
        $first = $this->createMock(ContainerWaitAfterStarted::class);

        /** @And the wait strategy is applied exactly once */
        $first->expects(self::once())->method('waitAfter');

        /** @And a second wait strategy applied after the container starts */
        $second = $this->createMock(ContainerWaitAfterStarted::class);

        /** @And the wait strategy is applied exactly once */
        $second->expects(self::once())->method('waitAfter');

        /** @And a container that applies both wait strategies after it starts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'two-waits-after')
            ->withWaitAfterStarted(wait: $first)
            ->withWaitAfterStarted(wait: $second);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'two-waits-after')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running after both wait strategies were applied */
        self::assertSame('two-waits-after', $started->getName());
    }

    public function testGetHostForConnectionWhenOutsideDockerThenReturnsLoopbackAddress(): void
    {
        /** @Given the code runs on the host, outside Docker */
        HostMock::outsideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container with a known hostname */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'conn-host');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the hostname */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'conn-host'));

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection host is resolved outside Docker */
        $host = $started->getAddress()->getHostForConnection();

        /** @Then it should be the loopback address */
        self::assertSame('127.0.0.1', $host);
    }

    public function testRunWhenCreateFailsThenContainersOfThisRunWithThatNameAreRemoved(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'half-created');

        /** @And the Docker daemon gives up on the creation after registering the container */
        $this->client->withDockerCreateResponse(output: 'context deadline exceeded', isSuccessful: false);

        /** @And two containers of this run carry that name */
        $this->client->withDockerListResponse(output: "aaaaaaaaaaaa\nbbbbbbbbbbbb\n");

        try {
            /** @When the container is started */
            $container->run();
        } catch (DockerCommandExecutionFailed) {
            /** @Then the containers of this run with that name should be listed and removed */
            $template = 'docker ps --all --quiet --filter name=^half-created$ --filter label=%s=%s-%d';
            self::assertSame(
                [
                    sprintf($template, 'tiny-blocks.docker-container.run', gethostname(), getmypid()),
                    'docker rm --force --volumes aaaaaaaaaaaa',
                    'docker rm --force --volumes bbbbbbbbbbbb'
                ],
                array_slice($this->client->getExecutedCommandLines(), 1)
            );
        }
    }

    public function testRunWhenNetworkVanishesBeforeStartThenTheContainerIsStartedAgain(): void
    {
        /** @Given a container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'network-retry')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container on the first attempt */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it because the network was pruned meanwhile */
        $this->client->withDockerStartResponse(output: 'network my-network not found', isSuccessful: false);

        /** @And the Docker daemon creates the container again on the second attempt */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'network-retry')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container of the second attempt should be the one handed over */
        self::assertSame('f1e2d3c4b5a6', $started->getId());
    }

    public function testRunWhenStartFailsOnBothAttemptsThenDockerCommandExecutionFailed(): void
    {
        /** @Given a container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'network-gone')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container on the first attempt */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it */
        $this->client->withDockerStartResponse(output: 'first failure', isSuccessful: false);

        /** @And the Docker daemon creates the container again on the second attempt */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the Docker daemon fails to start it again */
        $this->client->withDockerStartResponse(output: 'second failure', isSuccessful: false);

        /** @Then the failure of the second attempt should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/<'docker' 'start' 'f1e2d3c4b5a6'>.*Reason: second failure/");

        /** @When the container is started */
        $container->run();
    }

    public function testGetHostForConnectionWhenInsideDockerThenReturnsContainerHostname(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a Docker client */
        $client = new ClientMock();

        /** @And a container with a known hostname */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'conn-host');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the hostname */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'conn-host'));

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection host is resolved inside Docker */
        $host = $started->getAddress()->getHostForConnection();

        /** @Then it should be the container hostname */
        self::assertSame('conn-host', $host);
    }

    public function testRunIfNotExistsWhenExistingContainerIsStoppedThenItIsReusedAsItIs(): void
    {
        /** @Given a container that exists but has exited */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stopped');

        /** @And the lookup finds the container exited */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(status: 'exited', hostname: 'stopped')
        );

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then the container should be handed over at once, without waiting for it to run */
        self::assertTrue($started->wasReused());
        self::assertSame(0.0, ClockMock::elapsedInSeconds());
    }

    public function testRunContainerWhenContainerIdIsExactlyMinimumLengthThenIdIsAccepted(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'min-id');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: '123456789012');

        /** @And the Docker daemon returns a container ID exactly at the minimum length */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'min-id'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the full minimum-length ID should be accepted and preserved */
        self::assertSame('123456789012', $started->getId());
    }

    public function testRunWhenNetworkVanishesBeforeStartThenTheNetworkIsProvisionedAgain(): void
    {
        /** @Given a container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'network-again')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container on the first attempt */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it because the network was pruned meanwhile */
        $this->client->withDockerStartResponse(output: 'network my-network not found', isSuccessful: false);

        /** @And the Docker daemon creates the container again on the second attempt */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'network-again')
        );

        /** @When the container is started */
        $container->run();

        /** @Then the leftover should be removed and the network created again before the second attempt */
        self::assertSame(
            [
                'docker network create',
                'docker create',
                'docker start',
                'docker rm',
                'docker network create',
                'docker create',
                'docker start',
                'docker inspect'
            ],
            array_values(
                array_filter(
                    array_map(
                        static fn(string $commandLine): string => str_starts_with($commandLine, 'docker network')
                            ? implode(' ', array_slice(explode(' ', $commandLine), 0, 3))
                            : implode(' ', array_slice(explode(' ', $commandLine), 0, 2)),
                        $this->client->getExecutedCommandLines()
                    ),
                    static fn(string $commandLine): bool => $commandLine !== 'docker network connect'
                )
            )
        );
    }

    public function testRunWhenStartFailsThenTheLeftoverContainerIsRemovedWithoutRetrying(): void
    {
        /** @Given a container that joins no network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'start-cleanup');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it */
        $this->client->withDockerStartResponse(output: 'port is already allocated', isSuccessful: false);

        try {
            /** @When the container is started */
            $container->run();
        } catch (DockerCommandExecutionFailed) {
            /** @Then the leftover container should be removed and no second attempt should be made */
            self::assertSame(
                [
                    'docker create',
                    'docker start',
                    sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId())
                ],
                array_map(
                    static fn(string $commandLine): string => str_starts_with($commandLine, 'docker rm')
                        ? $commandLine
                        : implode(' ', array_slice(explode(' ', $commandLine), 0, 2)),
                    $this->client->getExecutedCommandLines()
                )
            );
        }
    }

    public function testRunIfNotExistsWhenContainerIsReusedThenWaitsAfterStartedAreSkipped(): void
    {
        /** @Given a wait strategy applied after a new container starts */
        $wait = $this->createMock(ContainerWaitAfterStarted::class);

        /** @And the wait strategy is never applied */
        $wait->expects(self::never())->method('waitAfter');

        /** @And a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reused-waits')
            ->withWaitAfterStarted(wait: $wait);

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'reused-waits')
        );

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then the existing container should be reused without applying the wait strategy */
        self::assertTrue($started->wasReused());
    }

    public function testStopContainerWhenCustomTimeoutThenProcessTimeoutExceedsGracePeriod(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-buffer');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-buffer')
        );

        /** @And the Docker daemon stops the container */
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom graceful timeout */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 45));

        /** @Then the process timeout requested exceeds the graceful period by the safety buffer */
        self::assertSame([300, 55], $this->client->getRequestedTimeouts());
    }

    public function testRunIfNotExistsWhenWaitAfterStartedFailsThenTheSharedContainerIsKept(): void
    {
        /** @Given a wait strategy that fails once the container has started */
        $wait = $this->createStub(ContainerWaitAfterStarted::class);

        /** @And the wait strategy times out */
        $wait->method('waitAfter')->willThrowException(
            ContainerWaitTimeout::forReadiness(timeout: Duration::fromSeconds(seconds: 1))
        );

        /** @And a shared container that applies that wait strategy after it starts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'shared-kept')
            ->withWaitAfterStarted(wait: $wait);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'shared-kept'));

        try {
            /** @When the container is asked for */
            $container->runIfNotExists();
        } catch (ContainerWaitTimeout) {
            /** @Then the shared container should not be removed, since other processes may already use it */
            self::assertNotContains(
                sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId()),
                $this->client->getExecutedCommandLines()
            );
        }
    }

    public function testRunWhenFileCopyFailsThenTheCreatedContainerIsRemovedAndNeverStarted(): void
    {
        /** @Given a container with a file to copy */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'copy-cleanup')
            ->copyToContainer(pathOnHost: '/host/missing', pathOnContainer: '/app/config');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to copy the file */
        $this->client->withDockerCopyResponse(output: 'lstat /host/missing: no such file', isSuccessful: false);

        try {
            /** @When the container is started */
            $container->run();
        } catch (DockerCommandExecutionFailed) {
            /** @Then the created container should be removed without ever being started */
            self::assertSame(
                ['docker create', 'docker cp', 'docker rm'],
                array_map(
                    static fn(array $arguments): string => implode(' ', array_slice($arguments, 0, 2)),
                    $this->client->getExecutedArguments()
                )
            );
        }
    }

    public function testRunToCompletionWhenFailedContainerIsNoLongerFoundThenItIsNotRunAgain(): void
    {
        /** @Given a one-shot container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the container exits with a non-zero status, and its inspection finds nothing */
        $this->client->withDockerAttachedStartResponse(output: 'gone', isSuccessful: false);

        /** @Then the error should be thrown, without a second attempt */
        $this->expectException(ContainerExitUnsuccessful::class);
        $this->expectExceptionMessageMatches('/Output: <gone>\.$/');

        /** @When the container runs to completion */
        $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));
    }

    public function testRunWhenWaitBeforeRunIsRegisteredThenConditionIsCheckedBeforeStarting(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);

        /** @And the condition holds on its only check */
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run condition */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'wait-test')
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-test'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (wait was called) */
        self::assertSame('wait-test', $started->getName());
    }

    public function testRemoveWhenContainerIsRunningThenContainerIsRemovedAndNetworksArePruned(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'force-remove');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'force-remove')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When remove is called */
        $started->remove();

        /** @Then a docker rm command should have been executed with the container ID */
        $commandLines = $this->client->getExecutedCommandLines();
        $removeCommand = $commandLines[3];

        self::assertStringContainsString('docker rm --force --volumes', $removeCommand);
        self::assertStringContainsString(
            InspectResponseFixture::shortContainerId(),
            $removeCommand
        );

        /** @And only the unused networks this run created should have been pruned */
        $template = 'docker network prune --force --filter label=tiny-blocks.docker-container.run=%s-%d';
        self::assertSame(sprintf($template, gethostname(), getmypid()), $commandLines[4]);
    }

    public function testRunIfNotExistsWhenAnotherProcessCreatesTheContainerFirstThenItIsReused(): void
    {
        /** @Given a container that does not exist when it is looked up */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'raced');

        /** @And another process creates a container with the same name first, so the creation conflicts */
        $this->client->withDockerCreateResponse(
            output: 'Conflict. The container name "/raced" is already in use by container "6acae5967be0".',
            isSuccessful: false
        );

        /** @And the container of the other process is found once the creation fails */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'raced', environment: ['WINNER=true'])
        );

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then the container of the other process should be reused */
        self::assertTrue($started->wasReused());
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('true', $started->getEnvironmentVariables()->getValueBy(key: 'WINNER'));
    }

    public function testRunIfNotExistsWhenExistingContainerIsNotRunningYetThenWaitsUntilItRuns(): void
    {
        /** @Given a container another process has created but not started yet */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'starting');

        /** @And the first lookup finds the container in the created state, without any host port */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(status: 'created', hostname: 'starting')
        );

        /** @And the container is still in the created state on the first readiness check */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(status: 'created', hostname: 'starting')
        );

        /** @And the container is running on the second readiness check */
        $this->client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'starting'));

        /** @And the lookup that follows carries the host port Docker assigned on start */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'starting',
                exposedPorts: ['80/tcp' => (object)[]],
                hostPortBindings: ['80/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '32768']]]
            )
        );

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then the reused container should carry the address it got once it was running */
        self::assertTrue($started->wasReused());
        self::assertSame(32768, $started->getAddress()->getPorts()->firstHostPort());
    }

    public function testRunToCompletionWhenContainerJoinsNoNetworkThenAFailureIsNeverInspected(): void
    {
        /** @Given a one-shot container that joins no network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client);

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the container exits with a non-zero status */
        $this->client->withDockerAttachedStartResponse(output: 'failed', isSuccessful: false);

        try {
            /** @When the container runs to completion */
            $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));
        } catch (ContainerExitUnsuccessful) {
            /** @Then the container should be removed without being inspected */
            self::assertSame(
                ['docker create', 'docker start', 'docker rm'],
                array_map(
                    static fn(array $arguments): string => implode(' ', array_slice($arguments, 0, 2)),
                    $this->client->getExecutedArguments()
                )
            );
        }
    }

    public function testRunToCompletionWhenNetworkVanishesBeforeStartThenTheContainerRunsAgain(): void
    {
        /** @Given a one-shot container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container on the first attempt */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it because the network was pruned meanwhile */
        $this->client->withDockerAttachedStartResponse(output: 'network my-network not found', isSuccessful: false);

        /** @And the inspection shows the container was never started */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(status: 'created'));

        /** @And the Docker daemon creates the container again on the second attempt */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the container runs and exits successfully on the second attempt */
        $this->client->withDockerAttachedStartResponse(output: 'completed');

        /** @When the container runs to completion */
        $execution = $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));

        /** @Then the outcome of the second attempt should be returned */
        self::assertSame('completed', $execution->getOutput());
    }

    public function testRunWhenPullTimeoutIsNotGivenThenContainerCreationIsBoundedByFiveMinutes(): void
    {
        /** @Given a container with the default pull timeout */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'default-pull');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'default-pull')
        );

        /** @When the container is started */
        $container->run();

        /** @Then the creation of the container should be bounded by 300 seconds */
        self::assertSame([300], $this->client->getRequestedTimeouts());
    }

    public function testStopContainerWhenCustomTimeoutThenCommandCarriesTimeoutAsStringArgument(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-args');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-args'));

        /** @And the Docker daemon stops the container */
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 45));

        /** @Then the stop command arguments should carry the timeout as a string */
        $stopArguments = $this->client->getExecutedArguments()[3];
        self::assertSame(
            ['docker', 'stop', '--time', '45', InspectResponseFixture::shortContainerId()],
            $stopArguments
        );
    }

    public function testRunIfNotExistsWhenInspectionCarriesNoStateThenTheContainerIsReusedAtOnce(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stateless');

        /** @And the lookup returns the container details without any state */
        $this->client->withDockerLookupResponse(
            inspectResult: array_diff_key(InspectResponseFixture::build(hostname: 'stateless'), ['State' => true])
        );

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then the container should be reused without waiting */
        self::assertTrue($started->wasReused());
        self::assertSame(0.0, ClockMock::elapsedInSeconds());
    }

    public function testRunWhenCreateFailsAndContainersCannotBeListedThenCreationFailureIsRaised(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'unlisted');

        /** @And the Docker daemon fails to create it */
        $this->client->withDockerCreateResponse(output: 'no space left on device', isSuccessful: false);

        /** @And the containers of this run cannot be listed */
        $this->client->withFailedDockerListResponse(output: 'denied');

        /** @Then the failure reported by Docker for the creation should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Reason: no space left on device/');

        /** @When the container is started */
        $container->run();
    }

    public function testRunWhenReaperStartsThenItRemovesOnlyWhatThisRunCreatedOnceTheRunnerExits(): void
    {
        /** @Given the code runs inside Docker */
        HostMock::insideDocker();

        /** @And a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reaper-script');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'reaper-script')
        );

        /** @When the container is started */
        $container->run();

        /** @Then the reaper should wait for the runner, then remove the containers and networks of this run only */
        $template = implode(' ', [
            'while docker inspect %1$s >/dev/null 2>&1; do sleep 2; done;',
            'docker ps --all --quiet --filter label=tiny-blocks.docker-container.run=%1$s-%2$d',
            '| xargs -r docker rm --force --volumes >/dev/null 2>&1;',
            'docker network prune --force --filter label=tiny-blocks.docker-container.run=%1$s-%2$d >/dev/null 2>&1'
        ]);
        self::assertSame(
            sprintf($template, gethostname(), getmypid()),
            array_last($this->client->getExecutedArguments()[0])
        );
    }

    public function testRunIfNotExistsWhenExistingContainerIsNeverStartedThenContainerWaitTimeout(): void
    {
        /** @Given a container another process created and never started */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stuck');

        /** @And every lookup finds the container still in the created state */
        for ($index = 0; $index < 200; $index++) {
            $this->client->withDockerLookupResponse(
                inspectResult: InspectResponseFixture::build(status: 'created', hostname: 'stuck')
            );
        }

        /** @Then a timeout exception should be thrown once the default thirty seconds elapse */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <30> seconds.');

        /** @When the container is asked for */
        $container->runIfNotExists();
    }

    public function testRunToCompletionWhenNetworkVanishesBeforeStartThenBothContainersAreRemoved(): void
    {
        /** @Given a one-shot container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container on the first attempt */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it because the network was pruned meanwhile */
        $this->client->withDockerAttachedStartResponse(output: 'network my-network not found', isSuccessful: false);

        /** @And the inspection shows the container was never started */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(status: 'created'));

        /** @And the Docker daemon creates the container again on the second attempt */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the container runs and exits successfully on the second attempt */
        $this->client->withDockerAttachedStartResponse(output: 'completed');

        /** @When the container runs to completion */
        $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));

        /** @Then the container of each attempt should have been removed */
        self::assertSame(
            [
                sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId()),
                'docker rm --force --volumes f1e2d3c4b5a6'
            ],
            array_values(
                array_filter(
                    $this->client->getExecutedCommandLines(),
                    static fn(string $commandLine): bool => str_starts_with($commandLine, 'docker rm')
                )
            )
        );
    }

    public function testRunToCompletionWhenNeverStartedOnBothAttemptsThenContainerExitUnsuccessful(): void
    {
        /** @Given a one-shot container that joins a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon creates the container on the first attempt */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it */
        $this->client->withDockerAttachedStartResponse(output: 'first failure', isSuccessful: false);

        /** @And the inspection shows the container was never started */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(status: 'created'));

        /** @And the Docker daemon creates the container again on the second attempt */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the Docker daemon fails to start it again */
        $this->client->withDockerAttachedStartResponse(output: 'second failure', isSuccessful: false);

        /** @And an inspection of the second container would also show it was never started */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(status: 'created'));

        /** @Then the failure of the second attempt should be thrown, without a third attempt */
        $this->expectException(ContainerExitUnsuccessful::class);
        $this->expectExceptionMessageMatches('/Output: <second failure>\.$/');

        /** @When the container runs to completion */
        $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));
    }

    public function testRunIfNotExistsWhenExistingContainerVanishesWhileWaitingThenANewOneIsStarted(): void
    {
        /** @Given a container another process created, then removed before starting it */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'vanished');

        /** @And the first lookup finds the container in the created state */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(status: 'created', hostname: 'vanished')
        );

        /** @And the Docker daemon creates a new container */
        $this->client->withDockerCreateResponse(output: 'f1e2d3c4b5a6f1e2d3c4b5a6');

        /** @And the inspect response carries the details of the new container */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'vanished'));

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then a new container should have been started rather than reused */
        self::assertFalse($started->wasReused());
        self::assertSame('f1e2d3c4b5a6', $started->getId());
    }

    public function testExecuteAfterStartedWhenCommandsAreGivenThenDockerExecTargetsTheContainerName(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'exec-cmd');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-cmd'));

        /** @And the command executed inside the container succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When executing commands inside the container */
        $started->executeAfterStarted(commands: ['ls', '-la', '/tmp']);

        /** @Then a docker exec command should have been executed with the container name and commands */
        $execCommand = $this->client->getExecutedCommandLines()[3];
        self::assertSame('docker exec exec-cmd ls -la /tmp', $execCommand);
    }

    public function testRunIfNotExistsWhenExistingContainerIsNotRunningYetThenPollsEveryQuarterSecond(): void
    {
        /** @Given a container another process has created but not started yet */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'polled');

        /** @And the first lookup finds the container in the created state */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(status: 'created', hostname: 'polled')
        );

        /** @And the container is still in the created state on the first readiness check */
        $this->client->withDockerLookupResponse(
            inspectResult: InspectResponseFixture::build(status: 'created', hostname: 'polled')
        );

        /** @And the container is running on the second readiness check */
        $this->client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'polled'));

        /** @And the lookup that follows finds it running */
        $this->client->withDockerLookupResponse(inspectResult: InspectResponseFixture::build(hostname: 'polled'));

        /** @When the container is asked for */
        $container->runIfNotExists();

        /** @Then a single poll interval should have been spent waiting */
        self::assertSame(0.25, ClockMock::elapsedInSeconds());
    }

    public function testRunToCompletionWhenWaitBeforeRunIsRegisteredThenWaitsBeforeCreatingTheContainer(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);

        /** @And the condition holds on its only check */
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run condition */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When the container runs to completion */
        $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));

        /** @Then the container should be created once the wait is satisfied */
        self::assertStringStartsWith('docker create', $this->client->getExecutedCommandLines()[0]);
    }

    public function testRunWhenCreateFailsAndNoContainerOfThisRunHasThatNameThenCreationFailureIsRaised(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'never-created');

        /** @And the Docker daemon fails to create it */
        $this->client->withDockerCreateResponse(output: 'no space left on device', isSuccessful: false);

        /** @And no container of this run carries that name */
        $this->client->withDockerListResponse(output: "  \n");

        /** @Then the failure reported by Docker should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Reason: no space left on device/');

        /** @When the container is started */
        $container->run();
    }

    public function testRunIfNotExistsWhenCreationFailsAndNoContainerExistsThenDockerCommandExecutionFailed(): void
    {
        /** @Given a container that does not exist */
        $container = GenericDockerContainer::from(image: 'invalid:image', client: $this->client)
            ->withName(name: 'never-created');

        /** @And the Docker daemon fails to create it for a reason other than a name conflict */
        $this->client->withDockerCreateResponse(output: 'pull access denied', isSuccessful: false);

        /** @Then the failure reported by Docker should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Reason: pull access denied/');

        /** @When the container is asked for */
        $container->runIfNotExists();
    }
}
