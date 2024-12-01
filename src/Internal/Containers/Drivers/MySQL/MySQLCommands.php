<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

final readonly class MySQLCommands
{
    private const string USER_ROOT = 'root';

    public static function grantPrivilegesToRoot(string $host, string $rootPassword): string
    {
        $query = sprintf(
            <<<SQL
                CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s';
                GRANT ALL PRIVILEGES ON *.* TO '%s'@'%s' WITH GRANT OPTION;
                FLUSH PRIVILEGES;
            SQL,
            self::USER_ROOT,
            $host,
            $rootPassword,
            self::USER_ROOT,
            $host
        );

        return sprintf('mysql -u%s -p%s -e "%s"', self::USER_ROOT, $rootPassword, $query);
    }
}
