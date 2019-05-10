<?php

namespace Squirrel\QueriesBundle\DependencyInjection\Compiler;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Logging\DebugStack;
use Squirrel\Queries\DBBuilder;
use Squirrel\Queries\DBBuilderInterface;
use Squirrel\Queries\DBInterface;
use Squirrel\Queries\Doctrine\DBErrorHandler;
use Squirrel\Queries\Doctrine\DBMySQLImplementation;
use Squirrel\Queries\Doctrine\DBPostgreSQLImplementation;
use Squirrel\Queries\Doctrine\DBSQLiteImplementation;
use Squirrel\QueriesBundle\DataCollector\SquirrelDataCollector;
use Squirrel\QueriesBundle\Twig\SquirrelQueriesExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Create DBInterface services with different layers
 */
class LayersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Whether Symfony profiler / toolbar is active
        $profilerActive = ( $container->has('profiler') ? true : false );

        // If no custom error handler has been defined, we use our default one
        $this->createErrorHandlerIfNotSet($container);

        // Find all layers (implementing DBRawInterface) which decorate all DBInterface services
        $taggedServices = $container->findTaggedServiceIds('squirrel.layer');

        // Order the layers
        $taggedServicesOrdered = $this->sortLayers($taggedServices);

        // Get all SQL connection definitions
        $services = $container->findTaggedServiceIds('squirrel.connection');

        // List of connections for profiler
        $connectionList = [];

        // Go through all tagged services
        foreach ($services as $id => $tags) {
            // Activate logging if the debug toolbar is active
            $this->setLoggerIfProfilerActive($container, $profilerActive, $id);

            // Go through all our tags in the service
            foreach ($tags as $tag) {
                $connectionList[$tag['connectionName']] = $this->createConnectionFromTag(
                    $container,
                    $id,
                    $tag,
                    $taggedServicesOrdered
                );
            }
        }

        // Create data collector & twig extension for profiler / web toolbar
        if ($profilerActive === true) {
            $this->createDataCollectorService($container, $connectionList);
        }
    }

    private function createConnectionFromTag(
        ContainerBuilder $container,
        string $id,
        array $tag,
        array $taggedServicesOrdered
    ) {
        // Create layered definition
        $layeredConnectionDefinition = $this->createLayeredConnection(
            $container,
            $this->getBaseImplementation($container, $id, $tag),
            $taggedServicesOrdered
        );

        // Set connection service name - relevant if there are multiple connections
        $container->setDefinition(
            'squirrel.connection.' . $tag['connectionName'],
            $layeredConnectionDefinition
        );

        // Set query builder service name
        $builderDefinition = new Definition(DBBuilder::class, [$layeredConnectionDefinition]);
        $container->setDefinition('squirrel.querybuilder.' . $tag['connectionName'], $builderDefinition);
        // Services associated with this connection
        $servicesList = ['squirrel.connection.' . $tag['connectionName']];

        // If this is the default connection we enable DBInterface type hints
        if (\boolval($tag['isDefault'] ?? false) === true) {
            // Only one default connection can exists, everything else is an error
            if ($container->hasDefinition(DBInterface::class)) {
                throw new \LogicException(
                    'You have multiple squirrel default connections - ' .
                    'make sure you only defined one connection as "default"'
                );
            }

            $container->setDefinition(DBInterface::class, $layeredConnectionDefinition);
            $container->setDefinition(DBBuilderInterface::class, $builderDefinition);
            $servicesList[] = DBInterface::class;
        }

        // Keep list of connections if we need them for profiler
        return [
            'connection' => new Reference('squirrel.connection.' . $tag['connectionName']),
            'services' => $servicesList,
        ];
    }

    private function createErrorHandlerIfNotSet(ContainerBuilder $container)
    {
        // If no custom error handler has been defined, we use our default one
        if (!$container->hasDefinition('squirrel.error_handler')) {
            $dbErrorHandler = new Definition(DBErrorHandler::class);
            $dbErrorHandler->setPublic(false);

            $container->setDefinition('squirrel.error_handler', $dbErrorHandler);
        }
    }

    // Sort DBRawInterface layers according to priority and return them
    private function sortLayers(array $taggedServices): array
    {
        // Ordered services according to priority
        $taggedServicesOrdered = [];

        // Create ordered services array
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                // Find out the priority of this tagged service
                $attributes['priority'] = \intval($attributes['priority'] ?? 0);

                // If priority is equal or above zero, add one so error handler can
                // be set to zero and there is no overlap with another layer
                if ($attributes['priority'] >= 0) {
                    $attributes['priority']++;
                }

                // Make sure priority has a fixed length for correct ordering
                $priority = \sprintf("%'.08d", $attributes['priority']);

                // Create priority sub-array if necessary
                if (!isset($taggedServicesOrdered[$priority])) {
                    $taggedServicesOrdered[$priority] = [];
                }

                // Add to list of ordered services
                $taggedServicesOrdered[$priority][] = [$id, $attributes];
            }
        }

        // Add error handler with priority zero
        $taggedServicesOrdered[\sprintf("%'.08d", 0)][] = ['squirrel.error_handler', ['priority' => 0]];

        // Order services according to priority and configuration order
        \ksort($taggedServicesOrdered, SORT_NUMERIC);

        return $taggedServicesOrdered;
    }

    private function setLoggerIfProfilerActive(ContainerBuilder $container, bool $profilerActive, string $serviceId)
    {
        if ($profilerActive === false) {
            return;
        }

        $dbalConfig = new Definition(Configuration::class);
        $dbalConfig->addMethodCall('setSQLLogger', [new Definition(DebugStack::class)]);

        $connection = $container->findDefinition($serviceId);
        $connection->setArgument('$config', $dbalConfig);
    }

    // Get base implementation interacting with Doctrine DBAL connection
    private function getBaseImplementation(ContainerBuilder $container, string $serviceId, array $tag)
    {
        // Connection with this name already exists - each connection name has to be unique
        if ($container->hasDefinition('squirrel.connection.' . $tag['connectionName'])) {
            throw new \LogicException(
                'You have multiple squirrel connections with same name - ' .
                'make sure to have unique connection names'
            );
        }

        // Custom implementation was defined as a service
        if ($container->hasDefinition('squirrel.implementation.' . $tag['connectionName'])) {
            return $container->findDefinition('squirrel.implementation.' . $tag['connectionName']);
        }

        // Connection type can be a class name with a unique implementation
        if (\class_exists($tag['connectionType'])) {
            return new Definition($tag['connectionType'], [new Reference($serviceId)]);
        }

        $connection = $container->findDefinition($serviceId);
        $dbalConfig = $connection->getArgument('$params');

        if (!isset($dbalConfig['driverOptions'])) {
            $dbalConfig['driverOptions'] = [];
        }

        // Turn off emulation of prepare / query & value separation
        $dbalConfig['driverOptions'][\PDO::ATTR_EMULATE_PREPARES] = false;

        // Default connection type with existing implementation
        switch ($tag['connectionType']) {
            case 'mysql':
                $implementationClass = DBMySQLImplementation::class;
                $dbalConfig['driverOptions'][\PDO::MYSQL_ATTR_FOUND_ROWS] = true;
                $dbalConfig['driverOptions'][\PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
                break;
            case 'pgsql':
                $implementationClass = DBPostgreSQLImplementation::class;
                break;
            case 'sqlite':
                $implementationClass = DBSQLiteImplementation::class;
                break;
            default:
                throw new \InvalidArgumentException(
                    'Only MySQL, Postgres and SQLite are currently supported by squirrel, ' .
                    'yet you have specified none of those as the connection type.'
                );
        }

        $connection->setArgument('$params', $dbalConfig);

        return new Definition($implementationClass, [$connection]);
    }

    private function createLayeredConnection(
        ContainerBuilder $container,
        Definition $implementationLayer,
        array $otherLayers
    ) {
        // The very lowest layer is the base implementation
        $topmostLayerDefinition = clone $implementationLayer;

        // Add the event listeners to our event manager
        foreach ($otherLayers as $priority => $entriesForThisPriority) {
            foreach ($entriesForThisPriority as $callData) {
                // Break up service and attributes
                [$id] = $callData;

                // Get definition for the current service
                $definition = clone $container->findDefinition(\strval($id));

                // Add lower layer to this service
                $definition->addMethodCall('setLowerLayer', [$topmostLayerDefinition]);

                // Mark this new service as the layer on top now
                $topmostLayerDefinition = $definition;
            }
        }

        return $topmostLayerDefinition;
    }

    private function createDataCollectorService(ContainerBuilder $container, array $connectionList)
    {
        $dataCollector = new Definition(SquirrelDataCollector::class, [$connectionList]);
        $dataCollector->addTag('data_collector', [
            'template' => '@SquirrelQueries/Collector/squirrel.html.twig',
            'id'       => 'squirrel',
            'priority' => 250,
        ]);
        $container->setDefinition(SquirrelDataCollector::class, $dataCollector);

        $twigExtension = new Definition(SquirrelQueriesExtension::class);
        $twigExtension->addTag('twig.extension');
        $container->setDefinition(SquirrelQueriesExtension::class, $twigExtension);
    }
}
