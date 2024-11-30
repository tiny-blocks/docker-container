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

        // Aguarda o MySQL estar pronto antes de executar comandos
        $containerStarted->executeAfterStarted(commands: ['mysqladmin ping -uroot -proot --wait=30']);

        // Comandos para criar o usuário se não existir e conceder permissões
        $ipAddress = '172.%'; // Substitua pelo IP correto
        $createUserCommand = sprintf(
            "
        CREATE USER IF NOT EXISTS 'root'@'%s' IDENTIFIED BY 'root';
        GRANT ALL PRIVILEGES ON *.* TO 'root'@'%s' WITH GRANT OPTION;
        FLUSH PRIVILEGES;
        ",
            $ipAddress,
            $ipAddress
        );

        $mysqlCommand = sprintf(
            "mysql -uroot -proot -e \"%s\"",
            $createUserCommand
        );

        // Executa o comando SQL para criar o usuário e conceder permissões
        $containerStarted->executeAfterStarted(commands: [$mysqlCommand]);

        // Verifica se o usuário foi criado corretamente
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
