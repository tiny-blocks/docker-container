<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

final readonly class MySQLCommands
{
    public static function grantPrivilegesToRoot(string $host, string $rootPassword): string
    {
        $grantCommand = sprintf(
            <<<SQL
                CREATE USER IF NOT EXISTS 'root'@'%s' IDENTIFIED BY '%s';
                GRANT ALL PRIVILEGES ON *.* TO 'root'@'%s' WITH GRANT OPTION;
                FLUSH PRIVILEGES;
            SQL,
            $host,
            $rootPassword,
            $host
        );

        return sprintf('mysql -u%s -p%s -e "%s"', 'root', $rootPassword, $grantCommand);
    }
}
