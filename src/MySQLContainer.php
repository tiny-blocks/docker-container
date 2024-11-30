<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLStarted;

class MySQLContainer extends GenericContainer implements DockerContainer
{
    public function run(array $commandsOnRun = []): MySQLContainerStarted
    {
        $containerStarted = parent::run(commandsOnRun: $commandsOnRun);

        // Espera o MySQL estar pronto antes de executar os comandos
        $containerStarted->executeAfterStarted(commands: ['mysqladmin ping -uroot -proot --wait=30']);

        // Comando SQL para conceder permissões para o IP 172.% diretamente
        $grantPermissionsCommand = "
        GRANT ALL PRIVILEGES ON *.* TO 'root'@'172.%' WITH GRANT OPTION;
        FLUSH PRIVILEGES;
    ";

        $mysqlCommand = sprintf(
            "mysql -uroot -proot -e \"%s\"",
            $grantPermissionsCommand
        );

        // Executa o comando SQL
        $containerStarted->executeAfterStarted(commands: [$mysqlCommand]);

        // Valida se o IP foi adicionado corretamente
        $checkPermissionsCommand = "mysql -uroot -proot -e \"SELECT host, user FROM mysql.user WHERE user = 'root';\"";
        $result = $containerStarted->executeAfterStarted(commands: [$checkPermissionsCommand]);

        // Log para depuração
        echo "\nMySQL User Permissions:\n" . $result->getOutput() . "\n";

        return MySQLStarted::from(containerStarted: $containerStarted);
    }

    public function runIfNotExists(array $commandsOnRun = []): MySQLContainerStarted
    {
        $containerStarted = parent::runIfNotExists(commandsOnRun: $commandsOnRun);

        return MySQLStarted::from(containerStarted: $containerStarted);
    }

    public function withTimezone(string $timezone): static
    {
        $this->withEnvironmentVariable(key: 'TZ', value: $timezone);

        return $this;
    }

    public function withUsername(string $user): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_USER', value: $user);

        return $this;
    }

    public function withPassword(string $password): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_PASSWORD', value: $password);

        return $this;
    }

    public function withDatabase(string $database): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_DATABASE', value: $database);

        return $this;
    }

    public function withRootPassword(string $rootPassword): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_ROOT_PASSWORD', value: $rootPassword);

        return $this;
    }
}
