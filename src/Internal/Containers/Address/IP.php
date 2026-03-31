<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Address;

final readonly class IP
{
    private const string LOCAL_IP = '127.0.0.1';

    private function __construct(public string $value)
    {
    }

    public static function from(string $value): IP
    {
        return new IP(value: empty($value) ? self::LOCAL_IP : $value);
    }
}
