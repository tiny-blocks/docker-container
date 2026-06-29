<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

final class MySQLCommands
{
    private const string GRANT_ALL = "GRANT ALL PRIVILEGES ON *.* TO '%s'@'%s' WITH GRANT OPTION;";
    private const string USER_ROOT = 'root';
    private const string CREATE_USER = "CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s';";
    private const string EXECUTE_SQL = 'mysql -u%s -p%s -e "%s"';
    private const string CREATE_DATABASE = 'CREATE DATABASE IF NOT EXISTS %s;';
    private const string FLUSH_PRIVILEGES = 'FLUSH PRIVILEGES;';

    private function __construct()
    {
    }

    public static function setupDatabase(string $database, array $grantedHosts, string $rootPassword): string
    {
        $statements = [];

        if ($database !== '') {
            $statements[] = sprintf(self::CREATE_DATABASE, $database);
        }

        foreach ($grantedHosts as $host) {
            $createUser = sprintf(self::CREATE_USER, self::USER_ROOT, $host, $rootPassword);
            $grantAll = sprintf(self::GRANT_ALL, self::USER_ROOT, $host);
            $template = '%s %s';
            $statements[] = sprintf($template, $createUser, $grantAll);
        }

        if (!empty($statements)) {
            $statements[] = self::FLUSH_PRIVILEGES;
        }

        return sprintf(self::EXECUTE_SQL, self::USER_ROOT, $rootPassword, implode(' ', $statements));
    }
}
