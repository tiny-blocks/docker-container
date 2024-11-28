<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models\Address;

final readonly class Hostname
{
    private const string LOCALHOST = 'localhost';

    private function __construct(public string $value)
    {
    }

    public static function from(string $value): Hostname
    {
        $value = empty($value) ? self::LOCALHOST : $value;

        return new Hostname(value: $value);
    }

    public static function localhost(): Hostname
    {
        return new Hostname(value: self::LOCALHOST);
    }
}
