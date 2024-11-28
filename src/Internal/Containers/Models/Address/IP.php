<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models\Address;

final class IP
{
    private const string LOCAL_IP = '127.0.0.1';

    private function __construct(public string $value)
    {
    }

    public static function local(): IP
    {
        return new IP(value: self::LOCAL_IP);
    }

    public static function from(string $value): IP
    {
        $value = empty($value) ? self::LOCAL_IP : $value;

        return new IP(value: $value);
    }
}
