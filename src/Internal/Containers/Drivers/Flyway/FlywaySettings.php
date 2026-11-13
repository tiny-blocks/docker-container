<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\Flyway;

use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final readonly class FlywaySettings
{
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

    public function applyTo(GenericDockerContainer $container): GenericDockerContainer
    {
        $settings = [...$this->source->toSettings(), ...$this->settings];

        return array_reduce(
            array_keys($settings),
            static fn(GenericDockerContainer $container, string $key): GenericDockerContainer => $container
                ->withEnvironmentVariable(key: $key, value: $settings[$key]),
            $container
        );
    }

    public function withSource(MySQLDockerContainer $source): FlywaySettings
    {
        return clone($this, ['source' => FlywaySource::from(container: $source)]);
    }
}
