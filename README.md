# Docker container

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Creating a container](#creating-a-container)
    * [Running a container](#running-a-container)
    * [Running a container if it doesn't exist](#running-a-container-if-it-doesnt-exist)
    * [Setting network](#setting-network)
    * [Setting port mappings](#setting-port-mappings)
    * [Setting volumes mappings](#setting-volumes-mappings)
    * [Setting environment variables](#setting-environment-variables)
    * [Disabling auto-remove](#disabling-auto-remove)
    * [Copying files to a container](#copying-files-to-a-container)
    * [Waiting for a condition](#waiting-for-a-condition)
* [Usage examples](#usage-examples)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div> 

## Overview

The `DockerContainer` library provides an interface and implementations to manage Docker containers programmatically.
It simplifies the creation, execution, and interaction with containers, such as adding network configurations, mapping
ports, setting environment variables, and executing commands inside containers.
Designed specifically to support **unit tests** and **integration tests**, the library enables developers to simulate
and manage containerized environments with minimal effort, ensuring a seamless testing workflow.

<div id='installation'></div>

## Installation

```bash
composer require tiny-blocks/docker-container
```

<div id='how-to-use'></div>

## How to use

### Creating a container

Creates a container from a specified image and optionally a name.
The `from` method can be used to initialize a new container instance with an image and an optional name for
identification.

```php
$container = GenericDockerContainer::from(image: 'php:8.3-fpm', name: 'my-container');
```

### Running a container

The `run` method starts a container.
Optionally, it allows you to execute commands within the container after it has started and define a condition to wait
for using a `ContainerWaitAfterStarted` instance.

**Example with no commands or conditions:**

```php
$container->run();
```

**Example with commands only:**

```php
$container->run(commands: ['ls', '-la']);
```

**Example with commands and a wait condition:**

```php
$container->run(commands: ['ls', '-la'], waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 5));
```

### Running a container if it doesn't exist

The `runIfNotExists` method starts a container only if it doesn't already exist.
Optionally, it allows you to execute commands within the container after it has started and define a condition to wait
for using a `ContainerWaitAfterStarted` instance.

```php
$container->runIfNotExists();
```

**Example with commands only:**

```php
$container->runIfNotExists(commands: ['ls', '-la']);
```

**Example with commands and a wait condition:**

```php
$container->runIfNotExists(commands: ['ls', '-la'], waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 5));
```

### Setting network

The `withNetwork` method connects the container to a specified Docker network by name, allowing you to define the
network configuration the container will use.

```php
$container->withNetwork(name: 'my-network');
```

### Setting port mappings

Maps ports between the host and the container.
The `withPortMapping` method maps a port from the host to a port inside the container.

```php
$container->withPortMapping(portOnHost: 9000, portOnContainer: 9000);
```

### Setting volumes mappings

Maps a volume from the host to the container.
The `withVolumeMapping` method allows you to link a directory from the host to the container.

```php
$container->withVolumeMapping(pathOnHost: '/path/on/host', pathOnContainer: '/path/in/container');
```

### Setting environment variables

Sets environment variables inside the container.
The `withEnvironmentVariable` method allows you to configure environment variables within the container.

```php
$container->withEnvironmentVariable(key: 'XPTO', value: '123');
```

### Disabling auto-remove

Prevents the container from being automatically removed when stopped.
By default, Docker removes containers after they stop.
The `withoutAutoRemove` method disables this feature, keeping the container around even after it finishes its
execution.

```php
$container->withoutAutoRemove();
```

### Copying files to a container

Copies files or directories from the host machine to the container.
The `copyToContainer` method allows you to transfer files from the host system into the container’s file system.

```php
$container->copyToContainer(pathOnHost: '/path/to/files', pathOnContainer: '/path/in/container');
```

### Waiting for a condition

The `withWaitBeforeRun` method allows the container to pause its execution until a specified condition is met before
starting.

```php
$container->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: MySQLReady::from(container: $container)));
```

<div id='usage-examples'></div>

## Usage examples

- When running the containers from the library on a host (your local machine), you need to map the volume
  `/var/run/docker.sock:/var/run/docker.sock`.
  This ensures that the container has access to the Docker daemon on the host machine, allowing Docker commands to be
  executed within the container.


- In some cases, it may be necessary to add the `docker-cli` dependency to your PHP image.
  This enables the container to interact with Docker from within the container environment.

### MySQL and Generic Containers

Before configuring and starting the MySQL container, a PHP container is set up to execute the tests and manage the
integration process.

This container runs within a Docker network and uses a volume for the database migrations.
The following commands are used to prepare the environment:

1. **Create the Docker network**:
   ```bash
   docker network create tiny-blocks
   ```

2. **Create the volume for migrations**:
   ```bash
   docker volume create test-adm-migrations
   ```

3. **Run the PHP container**:
   ```bash
   docker run -u root --rm -it --network=tiny-blocks --name test-lib \
       -v ${PWD}:/app \
       -v ${PWD}/tests/Integration/Database/Migrations:/test-adm-migrations \
       -v /var/run/docker.sock:/var/run/docker.sock \
       -w /app gustavofreze/php:8.3 bash -c "composer tests"
   ```

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
    ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql')
    ->withoutAutoRemove()
    ->runIfNotExists();
```

With the MySQL container started, it is possible to retrieve data, such as the address and JDBC connection URL:

```php
$environmentVariables = $mySQLContainer->getEnvironmentVariables();
$jdbcUrl = $mySQLContainer->getJdbcUrl();
$database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
$username = $environmentVariables->getValueBy(key: 'MYSQL_USER');
$password = $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD');
```

The Flyway container is configured and only starts and executes migrations after the MySQL container is **ready**:

```php
$flywayContainer = GenericDockerContainer::from(image: 'flyway/flyway:11.0.0')
    ->withNetwork(name: 'tiny-blocks')
    ->copyToContainer(pathOnHost: '/test-adm-migrations', pathOnContainer: '/flyway/sql')
    ->withVolumeMapping(pathOnHost: '/test-adm-migrations', pathOnContainer: '/flyway/sql')
    ->withWaitBeforeRun(
        wait: ContainerWaitForDependency::untilReady(
            condition: MySQLReady::from(
                container: $mySQLContainer
            )
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

<div id='license'></div>

## License

Docker container is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
