<?php

namespace Squirrel\QueriesBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final readonly class Configuration implements ConfigurationInterface
{
    public function __construct(
        private string $alias,
    ) {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->alias);

        $rootNode = $treeBuilder->getRootNode();

        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new \LogicException('Configuration for ' . $this->alias . ' was unexpectedly not an array');
        }

        // phpcs:disable
        $rootNode
            ->fixXmlConfig('connection', 'connections')
            ->children()
                ->arrayNode('connections')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type')
                                ->info('Database system used for this connection: mariadb, mysql, pgsql or sqlite')
                                ->values(['mariadb', 'mysql', 'pgsql', 'sqlite'])
                                ->isRequired()
                            ->end()
                            ->scalarNode('path')
                                ->info('Path to SQLite database file')
                                ->defaultValue(null)
                            ->end()
                            ->scalarNode('host')
                                ->info('Hostname or IP to reach the database server')
                            ->end()
                            ->scalarNode('port')
                                ->info('Port used to connect to the database server, or null to use the default port')
                                ->defaultValue(null)
                            ->end()
                            ->scalarNode('user')
                                ->info('Username to authenticate with database server')
                            ->end()
                            ->scalarNode('password')
                                ->info('Password to authenticate with database server')
                            ->end()
                            ->scalarNode('dbname')
                                ->info('Default database to open and use on the server')
                            ->end()
                            ->arrayNode('ssl')
                                ->children()
                                    ->scalarNode('rootCertificatePath')
                                        ->info('Path to root certificate the server should use')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('privateKeyPath')
                                        ->info('Path to private key used to authenticate the client')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('certificatePath')
                                        ->info('Path to the certificate used to authenticate the client')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->enumNode('verification')
                                        ->info('SSL verification of the server certificate, can be: CaAndHostname, Ca or None')
                                        ->values(['CaAndHostname', 'Ca', 'None'])
                                        ->defaultValue('CaAndHostname')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        // phpcs:enable

        return $treeBuilder;
    }
}
