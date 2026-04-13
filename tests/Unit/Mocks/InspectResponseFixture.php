<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

final readonly class InspectResponseFixture
{
    public static function containerId(): string
    {
        return '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8';
    }

    public static function shortContainerId(): string
    {
        return '6acae5967be0';
    }

    public static function build(
        string $id = '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8',
        string $hostname = 'alpine',
        string $ipAddress = '172.22.0.2',
        array $environment = [],
        string $networkName = 'bridge',
        array $exposedPorts = []
    ): array {
        return [
            'Id'              => $id,
            'Name'            => sprintf('/%s', $hostname),
            'Config'          => [
                'Hostname'     => $hostname,
                'ExposedPorts' => $exposedPorts,
                'Env'          => $environment
            ],
            'NetworkSettings' => [
                'Networks' => [
                    $networkName => [
                        'IPAddress' => $ipAddress
                    ]
                ]
            ]
        ];
    }
}
