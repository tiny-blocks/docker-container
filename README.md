# Docker container

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Creating a container](#creating-a-container)
    * [Running a container](#running-a-container)
    * [Running if not exists](#running-if-not-exists)
    * [Pulling images in parallel](#pulling-images-in-parallel)
    * [Setting network](#setting-network)
    * [Setting port mappings](#setting-port-mappings)
    * [Setting volume mappings](#setting-volume-mappings)
    * [Setting environment variables](#setting-environment-variables)
    * [Disabling auto-remove](#disabling-auto-remove)
    * [Copying files to a container](#copying-files-to-a-container)
    * [Stopping a container](#stopping-a-container)
    * [Stopping on shutdown](#stopping-on-shutdown)
    * [Executing commands after startup](#executing-commands-after-startup)
    * [Wait strategies](#wait-strategies)
* [MySQL container](#mysql-container)
    * [Configuring MySQL options](#configuring-mysql-options)
    * [Setting readiness timeout](#setting-readiness-timeout)
    * [Retrieving connection data](#retrieving-connection-data)
        * [Environment-aware connection](#environment-aware-connection)
* [Flyway container](#flyway-container)
    * [Setting the database source](#setting-the-database-source)
    * [Configuring migrations](#configuring-migrations)
    * [Configuring Flyway options](#configuring-flyway-options)
    * [Running Flyway commands](#running-flyway-commands)
* [Usage examples](#usage-examples)
    * [MySQL with Flyway migrations](#mysql-with-flyway-migrations)
* [License](#license)
* [Contributing](#contributing)

## Overview

Manage Docker containers programmatically, simplifying the creation, running, and interaction with containers.

The library provides interfaces and implementations for adding network configurations, mapping ports, setting
environment variables, and executing commands inside containers. Designed to support **unit tests** and
**integration tests**, it enables developers to manage containerized environments with minimal effort.

## Installation

```bash
composer require tiny-blocks/docker-container
```

## How to use

### Creating a container

Creates a container from a specified image and an optional name.

```php
use TinyBlocks\DockerContainer\GenericDockerContainer;

$container = GenericDockerContainer::from(image: 'php:8.5-fpm', name: 'my-container');
```

### Running a container

Starts a container. Optionally accepts commands to run on startup and a wait strategy applied after the container
starts.

```php
$container->run();
```

With commands:

```php
$container->run(commands: ['ls', '-la']);
```

With commands and a wait strategy:

```php
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

$container->run(commands: ['ls', '-la'], waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 5));
```

### Running if not exists

Starts a container only if a container with the same name is not already running.

```php
$container->runIfNotExists();
```

### Pulling images in parallel

Calling `pullImage()` starts downloading the image in the background via a non-blocking process. When `run()` or
`runIfNotExists()` is called, it waits for the pull to complete before starting the container.

To pull multiple images in parallel, call `pullImage()` on all containers **before** calling `run()` on any of
them. This way the downloads happen concurrently:

```php
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\FlywayDockerContainer;

$mysql = MySQLDockerContainer::from(image: 'mysql:8.4', name: 'my-database')
    ->pullImage()
    ->withRootPassword(rootPassword: 'root');

$flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
    ->pullImage()
    ->withMigrations(pathOnHost: '/path/to/migrations');

# Both images are downloading in the background.
# MySQL pull completes here, container starts and becomes ready.
$mySQLStarted = $mysql->runIfNotExists();

# Flyway pull already finished while MySQL was starting.
$flyway->withSource(container: $mySQLStarted, username: 'root', password: 'root')
    ->cleanAndMigrate();
```

### Setting network

Sets the Docker network the container should join. The network is created automatically when the container is
started via `run()` or `runIfNotExists()`, if it does not already exist. Networks created by the library are
labeled with `tiny-blocks.docker-container=true` for safe cleanup.

```php
$container->withNetwork(name: 'my-network');
```

### Setting port mappings

Maps a port from the host to the container.

```php
$container->withPortMapping(portOnHost: 8080, portOnContainer: 80);
```

After the container starts, both ports are available through the `Address`:

```php
$ports = $started->getAddress()->getPorts();

$ports->firstExposedPort();  # 80   (container-internal)
$ports->firstHostPort();     # 8080 (host-accessible)
```

### Setting volume mappings

Mounts a directory from the host into the container.

```php
$container->withVolumeMapping(pathOnHost: '/host/data', pathOnContainer: '/container/data');
```

### Setting environment variables

Adds an environment variable to the container.

```php
$container->withEnvironmentVariable(key: 'APP_ENV', value: 'testing');
```

### Disabling auto-remove

By default, containers are removed when stopped. This disables that behavior.

```php
$container->withoutAutoRemove();
```

### Copying files to a container

Registers files or directories to be copied from the host into the container after it starts.

```php
$container->copyToContainer(pathOnHost: '/path/to/files', pathOnContainer: '/path/in/container');
```

### Stopping a container

Stops a running container. An optional timeout (in seconds) controls how long to wait before forcing the stop.
The default timeout is 300 seconds.

```php
$started = $container->run();
$result = $started->stop();
```

With a custom timeout:

```php
$result = $started->stop(timeoutInWholeSeconds: 60);
```

### Stopping on shutdown

Registers the container to be forcefully removed when the PHP process exits. On shutdown, the following cleanup
is performed automatically:

- The container is killed and removed (`docker rm --force --volumes`).
- Anonymous volumes created by the container (e.g., MySQL's `/var/lib/mysql`) are removed.
- Unused networks created by the library are pruned.

Only resources labeled with `tiny-blocks.docker-container=true` are affected. Containers, volumes, and networks
from other environments are never touched.

```php
$started = $container->run();
$started->stopOnShutdown();
```

### Executing commands after startup

Runs commands inside an already-started container.

```php
$started = $container->run();
$result = $started->executeAfterStarted(commands: ['php', '-v']);
```

The returned `ExecutionCompleted` provides the command output and success status:

```php
$result->getOutput();
$result->isSuccessful();
```

### Wait strategies

#### Waiting for a fixed time

Pauses execution for a specified number of seconds before or after starting a container.

```php
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

$container->withWaitBeforeRun(wait: ContainerWaitForTime::forSeconds(seconds: 3));
```

#### Waiting for a dependency

Blocks until a readiness condition is satisfied, with a configurable timeout. This is useful when one container
depends on another being fully ready.

```php
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQLReady;

$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withRootPassword(rootPassword: 'root')
    ->run();

$container = GenericDockerContainer::from(image: 'my-app:latest')
    ->withWaitBeforeRun(
        wait: ContainerWaitForDependency::untilReady(
            condition: MySQLReady::from(container: $mySQLStarted),
            timeoutInSeconds: 30
        )
    )
    ->run();
```

## MySQL container

`MySQLDockerContainer` provides a specialized container for MySQL databases. It extends the generic container with
MySQL-specific configuration and automatic readiness detection.

### Configuring MySQL options

| Method             | Parameter       | Description                                                     |
|--------------------|-----------------|-----------------------------------------------------------------|
| `withTimezone`     | `$timezone`     | Sets the container timezone (e.g., `America/Sao_Paulo`).        |
| `withUsername`     | `$user`         | Sets the MySQL user created on startup.                         |
| `withPassword`     | `$password`     | Sets the password for the MySQL user.                           |
| `withDatabase`     | `$database`     | Sets the default database created on startup.                   |
| `withRootPassword` | `$rootPassword` | Sets the root password for the MySQL instance.                  |
| `withGrantedHosts` | `$hosts`        | Sets hosts granted root privileges (default: `['%', '172.%']`). |

```php
use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.4', name: 'my-database')
    ->withTimezone(timezone: 'America/Sao_Paulo')
    ->withUsername(user: 'app_user')
    ->withPassword(password: 'secret')
    ->withDatabase(database: 'my_database')
    ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
    ->withRootPassword(rootPassword: 'root')
    ->withGrantedHosts()
    ->run();
```

### Setting readiness timeout

Configures how long the MySQL container waits for the database to become ready before throwing a
`ContainerWaitTimeout` exception. The default timeout is 30 seconds.

```php
use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.4', name: 'my-database')
    ->withRootPassword(rootPassword: 'root')
    ->withReadinessTimeout(timeoutInSeconds: 60)
    ->run();
```

### Retrieving connection data

After the MySQL container starts, connection details are available through the `MySQLContainerStarted` instance.

```php
$address = $mySQLContainer->getAddress();
$ip = $address->getIp();
$hostname = $address->getHostname();

$ports = $address->getPorts();
$containerPort = $ports->firstExposedPort();  # e.g. 3306 (container-internal)
$hostPort = $ports->firstHostPort();          # e.g. 49153 (host-accessible)

$environmentVariables = $mySQLContainer->getEnvironmentVariables();
$database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
$username = $environmentVariables->getValueBy(key: 'MYSQL_USER');
$password = $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD');

$jdbcUrl = $mySQLContainer->getJdbcUrl();
```

Use `firstExposedPort()` when connecting from another container in the same network.
Use `firstHostPort()` when connecting from the host machine (e.g., tests running outside Docker).

### Environment-aware connection

The `Address` and `Ports` contracts provide environment-aware methods that automatically resolve the correct host and
port for connecting to a container. These methods detect whether the caller is running inside Docker or on the host
machine:

```php
use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.4', name: 'my-database')
    ->withRootPassword(rootPassword: 'root')
    ->withDatabase(database: 'my_database')
    ->withPortMapping(portOnHost: 3306, portOnContainer: 3306);

$started = $mySQLContainer->runIfNotExists();
$address = $started->getAddress();

$host = $address->getHostForConnection();             # hostname inside Docker, 127.0.0.1 on host
$port = $address->getPorts()->getPortForConnection(); # container port inside Docker, host-mapped port on host
```

This is useful when the same test suite runs both locally (inside a Docker Compose stack) and in CI (on the host).
Instead of manually checking the environment and switching between `getHostname()`/`getIp()` or `firstExposedPort()`/
`firstHostPort()`, the environment-aware methods handle it transparently.

## Flyway container

`FlywayDockerContainer` provides a specialized container for running Flyway database migrations. It encapsulates
Flyway configuration, database source detection, and migration file management.

### Setting the database source

Configures the Flyway container to connect to a running MySQL container. Automatically detects the JDBC URL and
target schema from `MYSQL_DATABASE`, and sets the history table to `schema_history`.

```php
use TinyBlocks\DockerContainer\FlywayDockerContainer;

$flywayContainer = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
    ->withNetwork(name: 'my-network')
    ->withMigrations(pathOnHost: '/path/to/migrations')
    ->withSource(container: $mySQLStarted, username: 'root', password: 'root');
```

The schema and table can be overridden after calling `withSource()`:

```php
$flywayContainer
    ->withSource(container: $mySQLStarted, username: 'root', password: 'root')
    ->withSchema(schema: 'custom_schema')
    ->withTable(table: 'custom_history');
```

### Configuring migrations

Sets the host directory containing Flyway migration SQL files. The files are copied into the container at
`/flyway/migrations`.

```php
$flywayContainer->withMigrations(pathOnHost: '/path/to/migrations');
```

### Configuring Flyway options

| Method                        | Parameter   | Description                                                      |
|-------------------------------|-------------|------------------------------------------------------------------|
| `withTable`                   | `$table`    | Overrides the history table name (default: `schema_history`).    |
| `withSchema`                  | `$schema`   | Overrides the target schema (default: auto-detected from MySQL). |
| `withCleanDisabled`           | `$disabled` | Enables or disables Flyway's clean command.                      |
| `withConnectRetries`          | `$retries`  | Sets the number of database connection retries.                  |
| `withValidateMigrationNaming` | `$enabled`  | Enables or disables migration naming validation.                 |

### Running Flyway commands

| Method              | Flyway command  | Description                                  |
|---------------------|-----------------|----------------------------------------------|
| `migrate()`         | `migrate`       | Applies pending migrations.                  |
| `validate()`        | `validate`      | Validates applied migrations against local.  |
| `repair()`          | `repair`        | Repairs the schema history table.            |
| `cleanAndMigrate()` | `clean migrate` | Drops all objects and re-applies migrations. |

```php
$flywayContainer->migrate();
$flywayContainer->cleanAndMigrate();
```

## Usage examples

- When running the containers from the library on a host (your local machine), map the volume
  `/var/run/docker.sock:/var/run/docker.sock` so the container has access to the Docker daemon on the host machine.
- In some cases, it may be necessary to add the `docker-cli` dependency to your PHP image to interact with Docker
  from within the container.

### MySQL with Flyway migrations

Configure both containers and start image pulls in parallel before running either one:

```php
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\FlywayDockerContainer;

$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.4', name: 'test-database')
    ->pullImage()
    ->withNetwork(name: 'my-network')
    ->withTimezone(timezone: 'America/Sao_Paulo')
    ->withPassword(password: 'secret')
    ->withDatabase(database: 'test_adm')
    ->withRootPassword(rootPassword: 'root')
    ->withGrantedHosts();

$flywayContainer = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
    ->pullImage()
    ->withNetwork(name: 'my-network')
    ->withMigrations(pathOnHost: '/path/to/migrations')
    ->withCleanDisabled(disabled: false)
    ->withConnectRetries(retries: 5)
    ->withValidateMigrationNaming(enabled: true);

$mySQLStarted = $mySQLContainer->runIfNotExists();
$mySQLStarted->stopOnShutdown();

$flywayContainer
    ->withSource(container: $mySQLStarted, username: 'root', password: 'root')
    ->cleanAndMigrate();
```

## License

Docker container is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
