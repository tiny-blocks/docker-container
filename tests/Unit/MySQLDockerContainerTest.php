<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Test\Unit\Mocks\ClientMock;
use Test\Unit\Mocks\InspectResponseFixture;
use Test\Unit\Mocks\TestableMySQLDockerContainer;
use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

final class MySQLDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testRunMySQLContainerSuccessfully(): void
    {
        /** @Given a MySQL container with full configuration */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'test-db',
            client: $this->client
        )
            ->withNetwork(name: 'my-net')
            ->withTimezone(timezone: 'America/Sao_Paulo')
            ->withUsername(user: 'app_user')
            ->withPassword(password: 'secret')
            ->withDatabase(database: 'test_adm')
            ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts()
            ->withoutAutoRemove()
            ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'test-db',
                environment: [
                    'TZ=America/Sao_Paulo',
                    'MYSQL_USER=app_user',
                    'MYSQL_PASSWORD=secret',
                    'MYSQL_DATABASE=test_adm',
                    'MYSQL_ROOT_PASSWORD=root'
                ],
                networkName: 'my-net',
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the database setup command succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then it should return a MySQLContainerStarted instance */
        self::assertSame(expected: 'test-db', actual: $started->getName());
        self::assertSame(expected: InspectResponseFixture::shortContainerId(), actual: $started->getId());

        /** @And the environment variables should be accessible */
        self::assertSame(
            expected: 'test_adm',
            actual: $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_DATABASE'
            )
        );
        self::assertSame(
            expected: 'app_user',
            actual: $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_USER'
            )
        );
        self::assertSame(
            expected: 'secret',
            actual: $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_PASSWORD'
            )
        );
        self::assertSame(
            expected: 'root',
            actual: $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_ROOT_PASSWORD'
            )
        );

        /** @And the port should be exposed */
        self::assertSame(expected: 3306, actual: $started->getAddress()->getPorts()->firstExposedPort());
    }

    public function testRunIfNotExistsReturnsMySQLContainerStarted(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'existing-db',
            client: $this->client
        )
            ->withDatabase(database: 'my_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the container already exists */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'existing-db',
                environment: ['MYSQL_DATABASE=my_db', 'MYSQL_ROOT_PASSWORD=root'],
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then it should return a MySQLContainerStarted wrapping the existing container */
        self::assertSame(expected: 'existing-db', actual: $started->getName());
    }

    public function testRunIfNotExistsCreatesNewMySQLContainer(): void
    {
        /** @Given a MySQL container that does not exist */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'new-db',
            client: $this->client
        )
            ->withDatabase(database: 'new_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker list returns empty (container does not exist) */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'new-db',
                environment: ['MYSQL_DATABASE=new_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check and CREATE DATABASE succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created */
        self::assertSame(expected: 'new-db', actual: $started->getName());
    }

    public function testRunMySQLContainerRetriesReadinessCheckBeforeSucceeding(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'retry-db',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 10);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'retry-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check fails twice before succeeding */
        $this->client->withDockerExecuteResponse(output: 'not ready', isSuccessful: false);
        $this->client->withDockerExecuteResponse(output: 'not ready', isSuccessful: false);
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the CREATE DATABASE command succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start after retries */
        self::assertSame(expected: 'retry-db', actual: $started->getName());
    }

    public function testRunMySQLContainerRetriesWhenReadinessCheckThrowsException(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'exception-db',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 10);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'exception-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check throws an exception first, then succeeds */
        $this->client->withDockerExecuteException(
            exception: new DockerCommandExecutionFailed(reason: 'container not running', command: 'docker exec')
        );
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the CREATE DATABASE command succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start after the exception was caught and retried */
        self::assertSame(expected: 'exception-db', actual: $started->getName());
    }

    public function testRunMySQLContainerWithSingleGrantedHost(): void
    {
        /** @Given a MySQL container with a single granted host */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'single-grant',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts(hosts: ['%']);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'single-grant',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And readiness and database setup succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should start successfully */
        self::assertSame(expected: 'single-grant', actual: $started->getName());
    }

    public function testRunMySQLContainerWithCopyToContainer(): void
    {
        /** @Given a MySQL container with files to copy */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'copy-db',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->copyToContainer(pathOnHost: '/host/init', pathOnContainer: '/docker-entrypoint-initdb.d');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'copy-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running with copy instructions executed */
        self::assertSame(expected: 'copy-db', actual: $started->getName());
    }

    public function testRunMySQLContainerWithWaitBeforeRun(): void
    {
        /** @Given a MySQL container with a wait-before-run condition */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And the container is configured */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'wait-db',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withWaitBeforeRun(
                wait: ContainerWaitForDependency::untilReady(condition: $condition)
            );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'wait-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the container is started */
        $started = $container->run();

        /** @Then the wait-before-run condition should have been evaluated */
        self::assertSame(expected: 'wait-db', actual: $started->getName());
    }

    public function testGetJdbcUrlWithDefaultOptions(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'test-db',
            database: 'test_adm',
            port: 3306
        );

        /** @When getting the JDBC URL with default options */
        $jdbcUrl = $started->getJdbcUrl();

        /** @Then the URL should include default JDBC options */
        self::assertSame(
            expected: 'jdbc:mysql://test-db:3306/test_adm?useSSL=false&useUnicode=yes&characterEncoding=UTF-8&allowPublicKeyRetrieval=true',
            actual: $jdbcUrl
        );
    }

    public function testGetJdbcUrlWithCustomOptions(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'test-db',
            database: 'test_adm',
            port: 3306
        );

        /** @When getting the JDBC URL with custom options */
        $jdbcUrl = $started->getJdbcUrl(options: ['connectTimeout' => '5000', 'useSSL' => 'true']);

        /** @Then the URL should include the custom options */
        self::assertSame(
            expected: 'jdbc:mysql://test-db:3306/test_adm?connectTimeout=5000&useSSL=true',
            actual: $jdbcUrl
        );
    }

    public function testGetJdbcUrlWithoutOptions(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'test-db',
            database: 'test_adm',
            port: 3306
        );

        /** @When getting the JDBC URL with empty options */
        $jdbcUrl = $started->getJdbcUrl(options: []);

        /** @Then the URL should not include any query string */
        self::assertSame(expected: 'jdbc:mysql://test-db:3306/test_adm', actual: $jdbcUrl);
    }

    public function testGetJdbcUrlDefaultsToPort3306WhenNoPortExposed(): void
    {
        /** @Given a running MySQL container with no exposed ports */
        $started = $this->createRunningMySQLContainer(
            hostname: 'test-db',
            database: 'test_adm',
            port: null
        );

        /** @When getting the JDBC URL */
        $jdbcUrl = $started->getJdbcUrl(options: []);

        /** @Then the URL should use the default MySQL port 3306 */
        self::assertSame(expected: 'jdbc:mysql://test-db:3306/test_adm', actual: $jdbcUrl);
    }

    public function testRunMySQLContainerWithoutDatabase(): void
    {
        /** @Given a MySQL container without a database configured */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'no-db',
            client: $this->client
        )->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon returns valid responses with no MYSQL_DATABASE */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the MySQL container is started (no CREATE DATABASE should be called) */
        $started = $container->run();

        /** @Then the container should start without errors */
        self::assertSame(expected: 'no-db', actual: $started->getName());
    }

    public function testRunMySQLContainerWithoutGrantedHosts(): void
    {
        /** @Given a MySQL container without granted hosts */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'no-grants',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-grants',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness and CREATE DATABASE calls succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started (no GRANT PRIVILEGES should be called) */
        $started = $container->run();

        /** @Then the container should start without errors */
        self::assertSame(expected: 'no-grants', actual: $started->getName());
    }

    public function testMySQLContainerDelegatesStopCorrectly(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'stop-db',
            database: 'test_adm',
            port: 3306
        );

        /** @And the Docker stop command succeeds */
        $this->client->withDockerStopResponse(output: '');

        /** @When the container is stopped */
        $stopped = $started->stop();

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testMySQLContainerDelegatesStopWithCustomTimeout(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'stop-timeout-db',
            database: 'test_adm',
            port: 3306
        );

        /** @And the Docker stop command succeeds */
        $this->client->withDockerStopResponse(output: '');

        /** @When the container is stopped with a custom timeout */
        $stopped = $started->stop(timeoutInWholeSeconds: 10);

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testMySQLContainerDelegatesExecuteAfterStarted(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'exec-db',
            database: 'test_adm',
            port: 3306
        );

        /** @And a command execution returns output */
        $this->client->withDockerExecuteResponse(output: 'SHOW DATABASES output');

        /** @When commands are executed inside the container */
        $execution = $started->executeAfterStarted(commands: ['mysql', '-e', 'SHOW DATABASES']);

        /** @Then the execution should return the output */
        self::assertTrue($execution->isSuccessful());
        self::assertSame(expected: 'SHOW DATABASES output', actual: $execution->getOutput());
    }

    public function testMySQLContainerDelegatesGetAddress(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'address-db',
            database: 'test_adm',
            port: 3306
        );

        /** @When getting the container address */
        $address = $started->getAddress();

        /** @Then the address should delegate correctly */
        self::assertSame(expected: 'address-db', actual: $address->getHostname());
        self::assertSame(expected: '172.22.0.2', actual: $address->getIp());
        self::assertSame(expected: 3306, actual: $address->getPorts()->firstExposedPort());
        self::assertSame(expected: [3306], actual: $address->getPorts()->exposedPorts());
    }

    public function testExceptionWhenMySQLNeverBecomesReady(): void
    {
        /** @Given a MySQL container with a very short readiness timeout */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'stuck-db',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 1);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'stuck-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check always fails */
        for ($index = 0; $index < 100; $index++) {
            $this->client->withDockerExecuteResponse(output: 'mysqld is not ready', isSuccessful: false);
        }

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <1> seconds.');

        /** @When attempting to start the MySQL container */
        $container->run();
    }

    public function testExceptionWhenMySQLReadinessCheckAlwaysThrowsExceptions(): void
    {
        /** @Given a MySQL container with a very short readiness timeout */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'crash-db',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 1);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'crash-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check always throws exceptions */
        for ($index = 0; $index < 100; $index++) {
            $this->client->withDockerExecuteException(
                exception: new DockerCommandExecutionFailed(reason: 'container crashed', command: 'docker exec')
            );
        }

        /** @Then a ContainerWaitTimeout exception should be thrown (not DockerCommandExecutionFailed) */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When attempting to start the MySQL container */
        $container->run();
    }

    public function testCustomReadinessTimeoutIsUsed(): void
    {
        /** @Given a MySQL container with a custom readiness timeout */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'timeout-db',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 60);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'timeout-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds on first attempt */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start successfully */
        self::assertSame(expected: 'timeout-db', actual: $started->getName());
    }

    public function testMySQLContainerWithEnvironmentVariableDirectly(): void
    {
        /** @Given a MySQL container with a custom environment variable */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'env-db',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withEnvironmentVariable(key: 'CUSTOM_KEY', value: 'custom_value');

        /** @And the Docker daemon returns valid responses including the custom env var */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'env-db',
                environment: ['MYSQL_ROOT_PASSWORD=root', 'CUSTOM_KEY=custom_value']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the custom environment variable should be accessible */
        self::assertSame(
            expected: 'custom_value',
            actual: $started->getEnvironmentVariables()->getValueBy(
                key: 'CUSTOM_KEY'
            )
        );
    }

    public function testRunMySQLContainerWithPullImage(): void
    {
        /** @Given a MySQL container with image pulling enabled */
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: 'pull-db',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->pullImage();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'pull-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the container is started (waiting for the image pull to complete first) */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame(expected: 'pull-db', actual: $started->getName());
    }

    public function testFromCreatesMySQLContainerInstance(): void
    {
        /** @Given a valid MySQL image name */
        $image = 'mysql:8.1';

        /** @When creating a MySQL container from the image */
        $container = MySQLDockerContainer::from(image: $image, name: 'from-mysql');

        /** @Then the container should be an instance of MySQLDockerContainer */
        self::assertInstanceOf(expected: MySQLDockerContainer::class, actual: $container);
    }

    public function testStopOnShutdownDelegatesToUnderlyingContainer(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'shutdown-db',
            database: 'test_adm',
            port: 3306
        );

        /** @When stopOnShutdown is called */
        $started->stopOnShutdown();

        /** @Then the container should still be accessible (the shutdown handler is deferred) */
        self::assertSame(expected: 'shutdown-db', actual: $started->getName());
    }

    public function testRemoveDelegatesToUnderlyingContainer(): void
    {
        /** @Given a running MySQL container */
        $started = $this->createRunningMySQLContainer(
            hostname: 'remove-db',
            database: 'test_adm',
            port: 3306
        );

        /** @When remove is called */
        $started->remove();

        /** @Then the docker rm command should have been executed */
        $commandLines = $this->client->getExecutedCommandLines();
        $removeCommand = $commandLines[4];

        self::assertStringContainsString(needle: 'docker rm --force --volumes', haystack: $removeCommand);
    }

    protected function createRunningMySQLContainer(
        string $hostname,
        string $database,
        ?int $port
    ): MySQLContainerStarted {
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.1',
            name: $hostname,
            client: $this->client
        )
            ->withDatabase(database: $database)
            ->withRootPassword(rootPassword: 'root');

        $exposedPorts = !is_null($port) ? [sprintf('%d/tcp', $port) => (object)[]] : [];

        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: $hostname,
                environment: [
                    sprintf('MYSQL_DATABASE=%s', $database),
                    'MYSQL_ROOT_PASSWORD=root'
                ],
                exposedPorts: $exposedPorts
            )
        );

        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        return $container->run();
    }
}
