<?php

namespace Squirrel\QueriesBundle\DependencyInjection;

use Squirrel\Connection\Config\Mysql;
use Squirrel\Connection\Config\Pgsql;
use Squirrel\Connection\Config\Sqlite;
use Squirrel\Connection\Config\Ssl;
use Squirrel\Connection\Config\SslVerification;
use Squirrel\Connection\ConnectionInterface;
use Squirrel\Connection\PDO\ConnectionPDO;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class SquirrelQueriesExtension extends Extension
{
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->getAlias());
    }

    public function getAlias(): string
    {
        return 'squirrel_queries';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration([], $container);
        $config = $this->processConfiguration($configuration, $configs);

        foreach ($config['connections'] as $name => $connection) {
            $configObj = match ($connection['type']) {
                'mariadb', 'mysql' => new Mysql(
                    host: $connection['host'],
                    user: $connection['user'],
                    password: $connection['password'],
                    port: $connection['port'] ?? 3306,
                    dbname: $connection['dbname'] ?? null,
                    charset: $connection['charset'] ?? 'utf8mb4',
                    ssl: $this->getSslConfig($connection['ssl'] ?? null),
                ),
                'pgsql' => new Pgsql(
                    host: $connection['host'],
                    user: $connection['user'],
                    password: $connection['password'],
                    port: $connection['port'] ?? 5432,
                    dbname: $connection['dbname'] ?? null,
                    charset: $connection['charset'] ?? 'UTF8',
                    ssl: $this->getSslConfig($connection['ssl'] ?? null),
                ),
                'sqlite' => new Sqlite(
                    path: $connection['path'],
                ),
                default => throw new \UnexpectedValueException('Invalid connection type: ' . $connection['type']),
            };

            $definition = new Definition(ConnectionPDO::class, [$configObj]);
            $definition->addTag('squirrel.connection', ['connectionName' => $name, 'connectionType' => $connection['type'], 'isDefault' => $name === 'default']);
            $container->setDefinition('squirrel_connection.' . $name, $definition);

            if ($name === 'default') {
                $container->setAlias(ConnectionInterface::class, 'squirrel_connection.' . $name);
            }
        }
    }

    private function getSslConfig(?array $sslConfig): ?Ssl
    {
        if (
            $sslConfig === null
            || \count($sslConfig) === 0
        ) {
            return null;
        }

        return new Ssl(
            rootCertificatePath: $sslConfig['rootCertificatePath'],
            privateKeyPath: $sslConfig['privateKeyPath'],
            certificatePath: $sslConfig['certificatePath'],
            verification: match ($sslConfig['verification']) {
                'Ca' => SslVerification::Ca,
                'None' => SslVerification::None,
                default => SslVerification::CaAndHostname,
            },
        );
    }
}
