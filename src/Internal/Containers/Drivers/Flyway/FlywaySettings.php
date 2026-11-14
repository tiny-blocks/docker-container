<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\Flyway;

use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final readonly class FlywaySettings
{
    private const array DEFAULT_SETTINGS = ['JAVA_ARGS' => '-XX:TieredStopAtLevel=1 -XX:+UseSerialGC'];

    private function __construct(private FlywaySource $source, private array $settings = [])
    {
    }

    public static function create(): FlywaySettings
    {
        return new FlywaySettings(source: FlywaySource::none());
    }

    public function with(string $key, string $value): FlywaySettings
    {
        return clone($this, ['settings' => [...$this->settings, $key => $value]]);
    }

    public function applyTo(ContainerDefinition $definition): ContainerDefinition
    {
        $settings = [...self::DEFAULT_SETTINGS, ...$this->source->toSettings(), ...$this->settings];

        return array_reduce(
            array_keys($settings),
            static fn(ContainerDefinition $definition, string $key): ContainerDefinition => $definition
                ->withEnvironmentVariable(key: $key, value: $settings[$key]),
            $definition
        );
    }

    public function withSource(MySQLDockerContainer $source): FlywaySettings
    {
        return clone($this, ['source' => FlywaySource::from(container: $source)]);
    }
}
