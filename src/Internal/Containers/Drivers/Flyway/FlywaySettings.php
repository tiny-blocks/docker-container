<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\Flyway;

use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final readonly class FlywaySettings
{
    private const string HISTORY_TABLE = 'schema_history';
    private const string ROOT_USERNAME = 'root';

    private function __construct(private ?MySQLDockerContainer $source, private array $settings)
    {
    }

    public static function create(): FlywaySettings
    {
        return new FlywaySettings(source: null, settings: []);
    }

    public function with(string $key, string $value): FlywaySettings
    {
        return new FlywaySettings(source: $this->source, settings: [...$this->settings, $key => $value]);
    }

    public function applyTo(GenericDockerContainer $container): GenericDockerContainer
    {
        $settings = [...$this->sourceSettings(), ...$this->settings];

        return array_reduce(
            array_keys($settings),
            static fn(GenericDockerContainer $container, string $key): GenericDockerContainer => $container
                ->withEnvironmentVariable(key: $key, value: $settings[$key]),
            $container
        );
    }

    public function withSource(MySQLDockerContainer $source): FlywaySettings
    {
        return new FlywaySettings(source: $source, settings: $this->settings);
    }

    private function sourceSettings(): array
    {
        if (is_null($this->source)) {
            return [];
        }

        $sourceStarted = $this->source->runIfNotExists();
        $environmentVariables = $sourceStarted->getEnvironmentVariables();

        return [
            'FLYWAY_URL'      => $sourceStarted->getJdbcUrl(),
            'FLYWAY_USER'     => self::ROOT_USERNAME,
            'FLYWAY_TABLE'    => self::HISTORY_TABLE,
            'FLYWAY_SCHEMAS'  => $environmentVariables->getValueBy(key: 'MYSQL_DATABASE'),
            'FLYWAY_PASSWORD' => $environmentVariables->getValueBy(key: 'MYSQL_ROOT_PASSWORD')
        ];
    }
}
