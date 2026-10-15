<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

final class MySQLCommands
{
    private const string GRANT_ALL = "GRANT ALL PRIVILEGES ON *.* TO '%s'@'%s' WITH GRANT OPTION;";
    private const string USER_ROOT = 'root';
    private const string CREATE_USER = "CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s';";
    private const string ROOT_PASSWORD = 'MYSQL_PWD=%s';
    private const string CREATE_DATABASE = 'CREATE DATABASE IF NOT EXISTS %s;';
    private const string FLUSH_PRIVILEGES = 'FLUSH PRIVILEGES;';

    private function __construct()
    {
    }

    public static function setupDatabase(string $database, array $grantedHosts, string $rootPassword): array
    {
        $statements = [];

        if ($database !== '') {
            $statements[] = sprintf(self::CREATE_DATABASE, $database);
        }

        foreach ($grantedHosts as $host) {
            $statements[] = sprintf(self::CREATE_USER, self::USER_ROOT, $host, $rootPassword);
            $statements[] = sprintf(self::GRANT_ALL, self::USER_ROOT, $host);
        }

        $statements[] = self::FLUSH_PRIVILEGES;
        $template = '-u%s';

        return [
            'env',
            sprintf(self::ROOT_PASSWORD, $rootPassword),
            'mysql',
            sprintf($template, self::USER_ROOT),
            '-e',
            implode(' ', $statements)
        ];
    }
}
