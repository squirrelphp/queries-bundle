<?php

namespace Squirrel\QueriesBundle\DependencyInjection;

use Squirrel\Connection\Config\Mysql;
use Squirrel\Connection\Config\Pgsql;
use Squirrel\Connection\Config\Sqlite;
use Squirrel\Connection\Config\Ssl;
use Squirrel\Connection\Config\SslVerification;
use Squirrel\Connection\PDO\ConnectionPDO;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
                'mariadb', 'mysql' => new Definition(
                    Mysql::class,
                    [
                        '$host' => $connection['host'] ?? throw new InvalidConfigurationException('No host provided for ' . $connection['type'] . ' squirrel database connection, provided configuration: ' . \print_r($connection, true)),
                        '$user' => $connection['user'] ?? throw new InvalidConfigurationException('No user provided for ' . $connection['type'] . ' squirrel database connection, provided configuration: ' . \print_r($connection, true)),
                        '$password' => $connection['password'] ?? throw new InvalidConfigurationException('No password provided for ' . $connection['type'] . ' squirrel database connection, provided configuration: ' . \print_r($connection, true)),
                        '$port' => $connection['port'] ?? Mysql::DEFAULT_PORT,
                        '$dbname' => $connection['dbname'] ?? null,
                        '$charset' => $connection['charset'] ?? Mysql::DEFAULT_CHARSET,
                        '$ssl' => $this->getSslConfig($connection['ssl'] ?? null),
                    ],
                ),
                'pgsql' => new Definition(
                    Pgsql::class,
                    [
                        '$host' => $connection['host'] ?? throw new InvalidConfigurationException('No host provided for ' . $connection['type'] . ' squirrel database connection, provided configuration: ' . \print_r($connection, true)),
                        '$user' => $connection['user'] ?? throw new InvalidConfigurationException('No user provided for ' . $connection['type'] . ' squirrel database connection, provided configuration: ' . \print_r($connection, true)),
                        '$password' => $connection['password'] ?? throw new InvalidConfigurationException('No password provided for ' . $connection['type'] . ' squirrel database connection, provided configuration: ' . \print_r($connection, true)),
                        '$port' => $connection['port'] ?? Pgsql::DEFAULT_PORT,
                        '$dbname' => $connection['dbname'] ?? null,
                        '$charset' => $connection['charset'] ?? Pgsql::DEFAULT_CHARSET,
                        '$ssl' => $this->getSslConfig($connection['ssl'] ?? null),
                    ],
                ),
                'sqlite' => new Definition(
                    Sqlite::class,
                    [
                        '$path' => $connection['path'],
                    ],
                ),
                default => throw new InvalidConfigurationException('Invalid connection type ("' . $connection['type'] . '") for squirrel database connection, provided configuration: ' . \print_r($connection, true)),
            };

            $definition = new Definition(ConnectionPDO::class, [$configObj]);
            $definition->addTag('squirrel.connection', ['connectionName' => $name, 'connectionType' => $connection['type'], 'isDefault' => $name === 'default']);
            $container->setDefinition('squirrel_connection.' . $name, $definition);
        }
    }

    private function getSslConfig(?array $sslConfig): ?Definition
    {
        if (
            $sslConfig === null
            || \count($sslConfig) === 0
        ) {
            return null;
        }

        return new Definition(
            Ssl::class,
            [
                '$rootCertificatePath' => $sslConfig['rootCertificatePath'] ?? throw new InvalidConfigurationException('No rootCertificatePath provided for SSL config of squirrel database connection, provided SSL configuration: ' . \print_r($sslConfig, true)),
                '$privateKeyPath' => $sslConfig['privateKeyPath'] ?? throw new InvalidConfigurationException('No privateKeyPath provided for SSL config of squirrel database connection, provided SSL configuration: ' . \print_r($sslConfig, true)),
                '$certificatePath' => $sslConfig['certificatePath'] ?? throw new InvalidConfigurationException('No certificatePath provided for SSL config of squirrel database connection, provided SSL configuration: ' . \print_r($sslConfig, true)),
                '$verification' => match ($sslConfig['verification']) {
                    'Ca' => SslVerification::Ca,
                    'None' => SslVerification::None,
                    default => SslVerification::CaAndHostname,
                },
            ],
        );
    }
}
