<?php

declare(strict_types=1);

namespace Test\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Test\Unit\Mocks\ClientMock;
use Test\Unit\Mocks\InspectResponseFixture;
use Test\Unit\Mocks\TestableGenericDockerContainer;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

final class GenericDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testRunContainerSuccessfully(): void
    {
        /** @Given a container configured with an image and a name */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'test-alpine',
            client: $this->client
        );

        /** @And the Docker daemon returns a valid container ID and inspect response */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
                hostname: 'test-alpine',
                env: ['PATH=/usr/local/bin']
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running with the expected properties */
        self::assertInstanceOf(ContainerStarted::class, $started);
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('test-alpine', $started->getName());
        self::assertSame('test-alpine', $started->getAddress()->getHostname());
        self::assertSame('172.22.0.2', $started->getAddress()->getIp());
    }

    public function testRunContainerWithFullConfiguration(): void
    {
        /** @Given a fully configured container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'nginx:latest',
            name: 'web-server',
            client: $this->client
        )
            ->withNetwork(name: 'my-network')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withVolumeMapping(pathOnHost: '/var/www', pathOnContainer: '/usr/share/nginx/html')
            ->withEnvironmentVariable(key: 'NGINX_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'NGINX_PORT', value: '80');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
                hostname: 'web-server',
                networkName: 'my-network',
                env: ['NGINX_HOST=localhost', 'NGINX_PORT=80'],
                exposedPorts: ['80/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should expose the configured environment variables */
        self::assertSame('localhost', $started->getEnvironmentVariables()->getValueBy(key: 'NGINX_HOST'));
        self::assertSame('80', $started->getEnvironmentVariables()->getValueBy(key: 'NGINX_PORT'));

        /** @And the address should reflect the exposed port */
        self::assertSame(80, $started->getAddress()->getPorts()->firstExposedPort());
        self::assertSame([80], $started->getAddress()->getPorts()->exposedPorts());
    }

    public function testRunContainerWithMultiplePortMappings(): void
    {
        /** @Given a container with multiple port mappings */
        $container = TestableGenericDockerContainer::createWith(
            image: 'nginx:latest',
            name: 'multi-port',
            client: $this->client
        )
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
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

    public function testRunContainerWithoutAutoRemove(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'persistent',
            client: $this->client
        )->withoutAutoRemove();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'persistent'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('persistent', $started->getName());
    }

    public function testRunContainerWithCopyToContainer(): void
    {
        /** @Given a container with files to copy */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'copy-test',
            client: $this->client
        )->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'copy-test'));

        /** @When the container is started (docker cp is automatically called) */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('copy-test', $started->getName());
    }

    public function testRunContainerWithCommands(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'cmd-test',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'cmd-test'));

        /** @When the container is started with commands */
        $started = $container->run(commands: ['echo', 'hello']);

        /** @Then the container should be running */
        self::assertSame('cmd-test', $started->getName());
    }

    public function testRunContainerWithWaitBeforeRun(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run condition */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'wait-test',
            client: $this->client
        )->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'wait-test'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (wait was called) */
        self::assertSame('wait-test', $started->getName());
    }

    public function testRunIfNotExistsCreatesNewContainer(): void
    {
        /** @Given a container that does not exist */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'new-container',
            client: $this->client
        )->withEnvironmentVariable(key: 'APP_ENV', value: 'test');

        /** @And the Docker list returns empty (container does not exist) */
        $this->client->withDockerListResponse(data: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
                hostname: 'new-container',
                env: ['APP_ENV=test']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created */
        self::assertSame('new-container', $started->getName());
        self::assertSame('test', $started->getEnvironmentVariables()->getValueBy(key: 'APP_ENV'));
    }

    public function testRunIfNotExistsReturnsExistingContainer(): void
    {
        /** @Given a container that already exists */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'existing',
            client: $this->client
        );

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(data: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
                hostname: 'existing',
                env: ['EXISTING=true']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the existing container should be returned */
        self::assertSame('existing', $started->getName());
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('true', $started->getEnvironmentVariables()->getValueBy(key: 'EXISTING'));
    }

    public function testStopContainer(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'stop-test',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'stop-test'));
        $this->client->withDockerStopResponse(output: '');

        $started = $container->run();

        /** @When the container is stopped */
        $result = $started->stop();

        /** @Then the stop should be successful */
        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteAfterStarted(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'exec-test',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'exec-test'));
        $this->client->withDockerExecuteResponse(output: 'command output');

        $started = $container->run();

        /** @When commands are executed inside the running container */
        $result = $started->executeAfterStarted(commands: ['ls', '-la']);

        /** @Then the execution should be successful */
        self::assertTrue($result->isSuccessful());
        self::assertSame('command output', $result->getOutput());
    }

    public function testExceptionWhenRunFails(): void
    {
        /** @Given a container that will fail to start */
        $container = TestableGenericDockerContainer::createWith(
            image: 'invalid:image',
            name: 'fail-test',
            client: $this->client
        );

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerRunResponse(data: 'Cannot connect to the Docker daemon.', isSuccessful: false);

        /** @Then a DockerCommandExecutionFailed exception should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Cannot connect to the Docker daemon/');

        /** @When the container is started */
        $container->run();
    }

    public function testExceptionWhenContainerInspectReturnsEmpty(): void
    {
        /** @Given a container whose inspect returns empty data */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'ghost',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: []);

        /** @Then a DockerContainerNotFound exception should be thrown */
        $this->expectException(DockerContainerNotFound::class);
        $this->expectExceptionMessage('Docker container with name <ghost> was not found.');

        /** @When the container is started */
        $container->run();
    }

    public function testAddressDefaultsWhenNetworkInfoIsEmpty(): void
    {
        /** @Given a container with empty network info */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'no-net',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
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

    public function testContainerWithNoExposedPortsReturnsNull(): void
    {
        /** @Given a container with no exposed ports */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'no-ports',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'no-ports'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then firstExposedPort should return null */
        self::assertNull($started->getAddress()->getPorts()->firstExposedPort());
        self::assertEmpty($started->getAddress()->getPorts()->exposedPorts());
    }

    public function testEnvironmentVariableReturnsEmptyStringForMissingKey(): void
    {
        /** @Given a running container with known environment variables */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'env-test',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
                hostname: 'env-test',
                env: ['KNOWN=value']
            )
        );

        $started = $container->run();

        /** @When querying for a missing environment variable */
        $actual = $started->getEnvironmentVariables()->getValueBy(key: 'MISSING');

        /** @Then it should return an empty string */
        self::assertSame('', $actual);
    }

    public function testRunContainerWithAutoGeneratedName(): void
    {
        /** @Given a container without a name */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: null,
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses (with any hostname from KSUID) */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'auto-generated'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should have an auto-generated name (non-empty) */
        self::assertNotEmpty($started->getName());
    }

    public function testRunContainerWithWaitAfterStarted(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'wait-after',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'wait-after'));

        /** @When the container is started with a wait-after condition */
        $start = microtime(as_float: true);
        $started = $container->run(waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 1));
        $elapsed = microtime(as_float: true) - $start;

        /** @Then the container should have waited after starting */
        self::assertSame('wait-after', $started->getName());
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testStopContainerWithCustomTimeout(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'stop-timeout',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'stop-timeout'));
        $this->client->withDockerStopResponse(output: '');

        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $result = $started->stop(timeoutInWholeSeconds: 10);

        /** @Then the stop should be successful */
        self::assertTrue($result->isSuccessful());
    }

    public function testExecuteAfterStartedReturnsFailure(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'exec-fail',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'exec-fail'));
        $this->client->withDockerExecuteResponse(output: 'command not found', isSuccessful: false);

        $started = $container->run();

        /** @When an invalid command is executed */
        $result = $started->executeAfterStarted(commands: ['invalid-command']);

        /** @Then the result should indicate failure */
        self::assertFalse($result->isSuccessful());
        self::assertSame('command not found', $result->getOutput());
    }

    public function testRunIfNotExistsWithWaitBeforeRun(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run that does not exist */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'wait-new',
            client: $this->client
        )->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker list returns empty */
        $this->client->withDockerListResponse(data: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'wait-new'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the wait-before-run should have been evaluated and the container created */
        self::assertSame('wait-new', $started->getName());
    }

    public function testExceptionWhenWaitBeforeRunTimesOut(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->method('isReady')->willReturn(false);

        /** @And a container with a wait-before-run that has a short timeout */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'timeout-wait',
            client: $this->client
        )->withWaitBeforeRun(
            wait: ContainerWaitForDependency::untilReady(
                condition: $condition,
                timeoutInSeconds: 1,
                pollIntervalInMicroseconds: 50_000
            )
        );

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When the container is started */
        $container->run();
    }

    public function testRunContainerWithMultipleVolumeMappings(): void
    {
        /** @Given a container with multiple volume mappings */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'multi-vol',
            client: $this->client
        )
            ->withVolumeMapping(pathOnHost: '/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'multi-vol'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('multi-vol', $started->getName());
    }

    public function testRunContainerWithMultipleEnvironmentVariables(): void
    {
        /** @Given a container with multiple environment variables */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'multi-env',
            client: $this->client
        )
            ->withEnvironmentVariable(key: 'DB_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'DB_PORT', value: '5432')
            ->withEnvironmentVariable(key: 'DB_NAME', value: 'mydb');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            data: InspectResponseFixture::build(
                hostname: 'multi-env',
                env: ['DB_HOST=localhost', 'DB_PORT=5432', 'DB_NAME=mydb']
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then all environment variables should be accessible */
        self::assertSame('localhost', $started->getEnvironmentVariables()->getValueBy(key: 'DB_HOST'));
        self::assertSame('5432', $started->getEnvironmentVariables()->getValueBy(key: 'DB_PORT'));
        self::assertSame('mydb', $started->getEnvironmentVariables()->getValueBy(key: 'DB_NAME'));
    }

    public function testRunContainerWithMultipleCopyInstructions(): void
    {
        /** @Given a container with multiple copy instructions */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'multi-copy',
            client: $this->client
        )
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql')
            ->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'multi-copy'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (both docker cp calls were made) */
        self::assertSame('multi-copy', $started->getName());
    }

    public function testExceptionWhenImageNameIsEmpty(): void
    {
        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image name cannot be empty.');

        /** @When creating a container with an empty image name */
        GenericDockerContainer::from(image: '');
    }

    public function testExceptionWhenDockerReturnsEmptyContainerId(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'empty-id',
            client: $this->client
        );

        /** @And the Docker daemon returns an empty container ID */
        $this->client->withDockerRunResponse(data: '   ');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID cannot be empty.');

        /** @When the container is started */
        $container->run();
    }

    public function testExceptionWhenDockerReturnsTooShortContainerId(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'short-id',
            client: $this->client
        );

        /** @And the Docker daemon returns a too-short container ID */
        $this->client->withDockerRunResponse(data: 'abc123');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID <abc123> is too short. Minimum length is <12> characters.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunCommandLineIncludesPortMapping(): void
    {
        /** @Given a container with a port mapping */
        $container = TestableGenericDockerContainer::createWith(
            image: 'nginx:latest',
            name: 'port-cmd',
            client: $this->client
        )->withPortMapping(portOnHost: 8080, portOnContainer: 80);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'port-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the executed docker run command should contain the port mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
    }

    public function testRunCommandLineIncludesMultiplePortMappings(): void
    {
        /** @Given a container with multiple port mappings */
        $container = TestableGenericDockerContainer::createWith(
            image: 'nginx:latest',
            name: 'multi-port-cmd',
            client: $this->client
        )
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'multi-port-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain both port mapping arguments */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
        self::assertStringContainsString('--publish 8443:443', $runCommand);
    }

    public function testRunCommandLineIncludesVolumeMapping(): void
    {
        /** @Given a container with a volume mapping */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'vol-cmd',
            client: $this->client
        )->withVolumeMapping(pathOnHost: '/host/data', pathOnContainer: '/app/data');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'vol-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the volume mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--volume /host/data:/app/data', $runCommand);
    }

    public function testRunCommandLineIncludesEnvironmentVariable(): void
    {
        /** @Given a container with an environment variable */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'env-cmd',
            client: $this->client
        )->withEnvironmentVariable(key: 'APP_ENV', value: 'production');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'env-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the environment variable argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString("--env APP_ENV='production'", $runCommand);
    }

    public function testRunCommandLineIncludesNetwork(): void
    {
        /** @Given a container with a network */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'net-cmd',
            client: $this->client
        )->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'net-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the network argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--network=my-network', $runCommand);
    }

    public function testRunCommandLineIncludesAutoRemoveByDefault(): void
    {
        /** @Given a container with default configuration */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'rm-cmd',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'rm-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain --rm */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--rm', $runCommand);
    }

    public function testRunCommandLineExcludesAutoRemoveWhenDisabled(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'no-rm-cmd',
            client: $this->client
        )->withoutAutoRemove();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'no-rm-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should NOT contain --rm */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringNotContainsString('--rm', $runCommand);
    }

    public function testRunCommandLineIncludesCommands(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'args-cmd',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'args-cmd'));

        /** @When the container is started with commands */
        $container->run(commands: ['-connectRetries=15', 'clean', 'migrate']);

        /** @Then the docker run command should end with the commands */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('-connectRetries=15 clean migrate', $runCommand);
    }

    public function testCopyToContainerExecutesDockerCpCommand(): void
    {
        /** @Given a container with a copy instruction */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'cp-cmd',
            client: $this->client
        )->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'cp-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the second executed command should be a docker cp with the correct arguments */
        $cpCommand = $this->client->getExecutedCommandLines()[2];
        self::assertStringStartsWith('docker cp', $cpCommand);
        self::assertStringContainsString('/host/sql', $cpCommand);
        self::assertStringContainsString('/app/sql', $cpCommand);
    }

    public function testStopExecutesDockerStopCommand(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'stop-cmd',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'stop-cmd'));
        $this->client->withDockerStopResponse(output: '');

        $started = $container->run();

        /** @When the container is stopped */
        $started->stop();

        /** @Then a docker stop command should have been executed with the container ID */
        $stopCommand = $this->client->getExecutedCommandLines()[2];
        self::assertStringStartsWith('docker stop', $stopCommand);
        self::assertStringContainsString(InspectResponseFixture::shortContainerId(), $stopCommand);
    }

    public function testExecuteAfterStartedRunsDockerExecCommand(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            image: 'alpine:latest',
            name: 'exec-cmd',
            client: $this->client
        );

        $this->client->withDockerRunResponse(data: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(data: InspectResponseFixture::build(hostname: 'exec-cmd'));
        $this->client->withDockerExecuteResponse(output: '', isSuccessful: true);

        $started = $container->run();

        /** @When executing commands inside the container */
        $started->executeAfterStarted(commands: ['ls', '-la', '/tmp']);

        /** @Then a docker exec command should have been executed with the container name and commands */
        $execCommand = $this->client->getExecutedCommandLines()[2];
        self::assertSame('docker exec exec-cmd ls -la /tmp', $execCommand);
    }
}
