# Docker container

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Creating a container](#creating-a-container)
    * [Running a container](#running-a-container)
    * [Running if not exists](#running-if-not-exists)
    * [Pulling an image](#pulling-an-image)
    * [Setting network](#setting-network)
    * [Setting port mappings](#setting-port-mappings)
    * [Setting volume mappings](#setting-volume-mappings)
    * [Setting environment variables](#setting-environment-variables)
    * [Disabling auto-remove](#disabling-auto-remove)
    * [Copying files to a container](#copying-files-to-a-container)
    * [Stopping a container](#stopping-a-container)
    * [Executing commands after startup](#executing-commands-after-startup)
    * [Wait strategies](#wait-strategies)
* [MySQL container](#mysql-container)
    * [Configuring MySQL options](#configuring-mysql-options)
    * [Setting readiness timeout](#setting-readiness-timeout)
    * [Retrieving connection data](#retrieving-connection-data)
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
$container->run(commands: ['ls', '-la'], waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 5));
```

### Running if not exists

Starts a container only if a container with the same name is not already running.

```php
$container->runIfNotExists();
```

### Pulling an image

Starts pulling the container image in the background. When `run()` or `runIfNotExists()` is called, it waits for
the pull to complete before starting the container. Calling this on multiple containers before running them enables
parallel image pulls.

```php
$alpine = GenericDockerContainer::from(image: 'alpine:latest')->pullImage();
$nginx = GenericDockerContainer::from(image: 'nginx:latest')->pullImage();

$alpineStarted = $alpine->run();
$nginxStarted = $nginx->run();
```

### Setting network

Sets the Docker network the container should join. The network is created automatically when the container is
started via `run()` or `runIfNotExists()`, if it does not already exist.

```php
$container->withNetwork(name: 'my-network');
```

### Setting port mappings

Maps ports between the host and the container. Multiple port mappings are supported.

```php
$container->withPortMapping(portOnHost: 9000, portOnContainer: 9000);
$container->withPortMapping(portOnHost: 8080, portOnContainer: 80);
```

### Setting volume mappings

Maps a volume from the host to the container.

```php
$container->withVolumeMapping(pathOnHost: '/path/on/host', pathOnContainer: '/path/in/container');
```

### Setting environment variables

Sets environment variables inside the container.

```php
$container->withEnvironmentVariable(key: 'APP_ENV', value: 'testing');
```

### Disabling auto-remove

Prevents the container from being automatically removed when stopped.

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
$container->withWaitBeforeRun(wait: ContainerWaitForTime::forSeconds(seconds: 3));
```

#### Waiting for a dependency

Blocks until a readiness condition is satisfied, with a configurable timeout. This is useful when one container
depends on another being fully ready.

```php
$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.1')
    ->withRootPassword(rootPassword: 'root')
    ->run();

$flywayContainer = GenericDockerContainer::from(image: 'flyway/flyway:11.1.0')
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
$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.1', name: 'my-database')
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
$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.1', name: 'my-database')
    ->withRootPassword(rootPassword: 'root')
    ->withReadinessTimeout(timeoutInSeconds: 60)
    ->run();
```

### Retrieving connection data

After the MySQL container starts, connection details are available through the `MySQLContainerStarted` instance.

```php
$address = $mySQLContainer->getAddress();
$ip = $address->getIp();
$port = $address->getPorts()->firstExposedPort();
$hostname = $address->getHostname();

$environmentVariables = $mySQLContainer->getEnvironmentVariables();
$database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
$username = $environmentVariables->getValueBy(key: 'MYSQL_USER');
$password = $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD');

$jdbcUrl = $mySQLContainer->getJdbcUrl();
```

## Usage examples

- When running the containers from the library on a host (your local machine), map the volume
  `/var/run/docker.sock:/var/run/docker.sock` so the container has access to the Docker daemon on the host machine.
- In some cases, it may be necessary to add the `docker-cli` dependency to your PHP image to interact with Docker
  from within the container.

### MySQL with Flyway migrations

The MySQL container is configured and started:

```php
$mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.1', name: 'test-database')
    ->withNetwork(name: 'tiny-blocks')
    ->withTimezone(timezone: 'America/Sao_Paulo')
    ->withUsername(user: 'xpto')
    ->withPassword(password: '123')
    ->withDatabase(database: 'test_adm')
    ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
    ->withRootPassword(rootPassword: 'root')
    ->withGrantedHosts()
    ->withReadinessTimeout(timeoutInSeconds: 60)
    ->withoutAutoRemove()
    ->runIfNotExists();
```

With the MySQL container started, retrieve the connection data:

```php
$environmentVariables = $mySQLContainer->getEnvironmentVariables();
$jdbcUrl = $mySQLContainer->getJdbcUrl();
$database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
$username = $environmentVariables->getValueBy(key: 'MYSQL_USER');
$password = $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD');
```

The Flyway container is configured and only starts after the MySQL container is **ready**:

```php
$flywayContainer = GenericDockerContainer::from(image: 'flyway/flyway:11.1.0')
    ->withNetwork(name: 'tiny-blocks')
    ->copyToContainer(pathOnHost: '/test-adm-migrations', pathOnContainer: '/flyway/sql')
    ->withVolumeMapping(pathOnHost: '/test-adm-migrations', pathOnContainer: '/flyway/sql')
    ->withWaitBeforeRun(
        wait: ContainerWaitForDependency::untilReady(
            condition: MySQLReady::from(container: $mySQLContainer),
            timeoutInSeconds: 30
        )
    )
    ->withEnvironmentVariable(key: 'FLYWAY_URL', value: $jdbcUrl)
    ->withEnvironmentVariable(key: 'FLYWAY_USER', value: $username)
    ->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: 'schema_history')
    ->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: $database)
    ->withEnvironmentVariable(key: 'FLYWAY_EDITION', value: 'community')
    ->withEnvironmentVariable(key: 'FLYWAY_PASSWORD', value: $password)
    ->withEnvironmentVariable(key: 'FLYWAY_LOCATIONS', value: 'filesystem:/flyway/sql')
    ->withEnvironmentVariable(key: 'FLYWAY_CLEAN_DISABLED', value: 'false')
    ->withEnvironmentVariable(key: 'FLYWAY_VALIDATE_MIGRATION_NAMING', value: 'true')
    ->run(
        commands: ['-connectRetries=15', 'clean', 'migrate'],
        waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 5)
    );
```

## License

Docker container is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
